<?php namespace App;
use WebSocket as WebSocket;


/**
 * FlussonicApi
 *
 * @uses RestAPI
 * @package
 * @author Iptvmatrix.net dev team
 * @version 1.0
 */
class FlussonicApi extends RestAPI
{
    protected $config = [];

    public function __construct($config)
    {
        parent::__construct();
        $config = (object) $config;
        $this->config = $config;
        $this->host($config->flussonic_host)
            ->authBasic($config->flussonic_user, $config->flussonic_pwd);
    }

    /**
     * apiGet
     *
     * return json decoded answer from GET request
     *
     * @param string $action
     * @return array
     */
    public function apiGet($action)
    {
        return json_decode($this->action($action)->execGet(), true);
    }

    /**
     * getServerInfo
     *
     * @return array
     */
    public function getServerInfo()
    {
        return $this->apiGet('/flussonic/api/server');
    }


    /**
     * getFeedInfo
     *
     * @param string $feed_name
     * @return array
     */
    public function getFeedInfo($feed_name)
    {
        $retVal = $this->apiGet("/flussonic/api/media_info/$feed_name");

        if (!$retVal) {
            return Result::failure("Error code: ".$this->getCode());
        }

        return $retVal;
    }

    /**
     * getStreams
     *
     * Returns all streams data from flussonic WebSocket API
     *
     * @return array
     */
    public function getStreams()
    {
        $user = $this->config->flussonic_user;
        $pwd  = $this->config->flussonic_pwd;
        $host = str_replace("http://", '', $this->config->flussonic_host);

        $url = "ws://$user:$pwd@$host/flussonic/api/events";

        $c = new WebSocket\Client($url);
        $c->send("streams");

        $answer = json_decode($c->receive(), true);
        $this->streams = $answer['streams'];

        return $this->streams;
    }

    /**
     * getSessions
     *
     * @return array
     */
    public function getSessions()
    {
        $this->sessions = $this->apiGet('/flussonic/api/sessions')['sessions'];
        return $this->sessions;
    }

    /**
     * getFeeds
     *
     * returns flussonic streams, filtered by application name
     *
     * @param string $app_name
     * @return array
     */
    public function getFeeds($app_name)
    {
        $retArr = [];

        if (!$this->streams) {
            $this->getStreams();
        }

        foreach ($this->streams as $stream)
        {
            if (explode("/", $stream['name'])[0] == $app_name) {
                $retArr[] = $stream;
            }
        }

        return $retArr;
    }

    /**
     * getClients
     *
     * returns flussonic sessions, filtered by application name
     *
     * @param string $app_name
     * @return array
     */
    public function getClients($app_name)
    {
        $retArr = [];

        if (!$this->sessions) {
            $this->getSessions();
        }

        foreach ($this->sessions as $session)
        {
            if (explode("/", $session['name'])[0] == $app_name) {
                $retArr[] = $session;
            }
        }

        return $retArr;
    }

    /**
     * createFeed
     *
     * @param string $name
     * @param string $url
     * @param bool $persistent
     * @return mixed
     */
    public function createFeed($name, $url, $persistent = true)
    {
        $streamtype = $persistent ? "stream" : "ondemand";
        $postfield = "$streamtype $name $url";
        $this->action("/flussonic/api/config/stream_create");

        return $this->execBinaryPost($postfield);
    }

    /**
     * removeAllStreams
     *
     * Delete all streams for Matrix Application
     *
     * @param string $app_name
     * @return this
     */
    public function removeAllStreams($app_name)
    {
        foreach($this->getFeeds($app_name) as $feed)
        {
            $this->deleteStream($feed['name']);
        }

        return $this;
    }

    /**
     * deleteStream
     *
     * @param string $name
     * @return this
     */
    public function deleteStream($name)
    {
        $this->action("/flussonic/api/config/stream_delete");
        return $this->execBinaryPost($name);
    }

    /**
     * restartStream
     *
     * @param string $name
     * @return mixed
     */
    public function restartStream($name)
    {
        $this->action("/flussonic/api/stream_restart/$name");
        return $this->execPost();
    }

    /**
     * removeDevices
     *
     * Drop devices via matrix tokens
     *
     * @param tokens $tokens
     * @return mixed
     */
    public function removeDevices($tokens)
    {
        $toRemove = '';
        foreach ($this->sessions as $flussonic_session) {
            if (empty($flussonic_session['token'])) {
                continue;
            }
            pr($flussonic_session['token']);
            if (in_array($flussonic_session['token'], $tokens)) {
                $toRemove .= $toRemove ? "\n" : "";
                $toRemove .= $flussonic_session['id'];
            }
        }

        $this->action("/flussonic/api/close_sessions");
        return $this->execBinaryPost($toRemove);
    }

    /**
     * hashToToken
     *
     * Matrix hash billing_id.portal_id.user_id.platform_id.device_id to Token
     *
     * @param string $hash
     * @param string $app_name
     * @return string
     */
    public function hashToToken($hash, $app_name)
    {
        $hash_pieces = explode('.', $hash);

        $hash_billing_id  = $hash_pieces[0];
        $hash_portal_id   = $hash_pieces[1];
        $hash_user_id     = $hash_pieces[2];
        $hash_device_id   = $hash_pieces[4];
        $hash_platform_id = $hash_pieces[3];

        foreach ($this->getClients($app_name) as $session)
        {
            if (empty($session['token'])) {
                continue;
            }

            $token_pieces = explode('.', $session['token']);
            $token_billing_id  = $token_pieces[0];
            $token_portal_id   = $token_pieces[1];
            $token_user_id     = $token_pieces[2];
            $token_device_id   = $token_pieces[3];
            $token_platform_id = $token_pieces[6];

            if ($hash_billing_id  == $token_billing_id &&
                $hash_portal_id   == $token_portal_id  &&
                $hash_user_id     == $token_user_id    &&
                $hash_device_id   == $token_device_id  &&
                $hash_platform_id == $token_platform_id
            ) {
                return $session['token'];
            }
        }

        return '';
    }
}
