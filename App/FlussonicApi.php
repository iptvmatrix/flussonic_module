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
    public $streams_objs = [];
    public $sources_objs = [];


    public function __construct($config)
    {
        parent::__construct();
        $config = (object) $config;
        $this->config = $config;
        $this->host($config->flussonic_host)
            ->authBasic($config->flussonic_user, $config->flussonic_pwd);
    }

    /**
     * isFlussonicAlive
     *
     * @return bool
     */
    public function isFlussonicAlive()
    {
        $this->apiGet("/admin");
        return $this->getCode() !== 500;
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
     * getMemUsage
     *
     * @return int
     */
    public function getMemUsage()
    {
        $c = $this->getWSConnection();
        $c->send("pulse_subscribe:total_memusage");

        $answer = json_decode($c->receive(), true);
        $c->closeConnection();
        unset($c);

        return end(end($answer['total_memusage']['minute'])['data'])[1];
    }

    /**
     * getCpuUsage
     *
     * @return int
     */
    public function getCpuUsage()
    {
        $c = $this->getWSConnection();
        $c->send("pulse_subscribe:cpu_usage");

        $answer = json_decode($c->receive(), true);
        $c->closeConnection();
        unset($c);

        return end(end($answer['cpu_usage']['minute'])['data'])[1];
    }

    /**
     * getNetworkStatus
     *
     * @return array
     */
    public function getNetworkStatus()
    {
        $retArr = ['in' => 0, 'out' => 0];

        $c = $this->getWSConnection();
        $c->send("pulse_subscribe:overview");

        $answer = json_decode($c->receive(), true);

        foreach ($answer['overview']['minute'] as $report)
        {
            if ($report['label'] == 'total_output') {
                $retArr['out'] = ceil(end($report['data'])[1] / 1000);
            }
            else if ($report['label'] == 'total_input') {
                $retArr['in'] = ceil(end($report['data'])[1] / 1000);
            }
        }

        $c->closeConnection();
        unset($c);

        return $retArr;
    }

    protected function getWSConnection()
    {
        $user = $this->config->flussonic_user;
        $pwd  = $this->config->flussonic_pwd;
        $host = str_replace("http://", '', $this->config->flussonic_host);

        $url = "ws://$user:$pwd@$host/flussonic/api/events";

        return new WebSocket\Client($url);
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
        $c = $this->getWSConnection();
        $c->send("streams");
        $answer = json_decode($c->receive(), true);

        $this->streams = $answer['streams'];


        $c->send("read_config");
        $answer_read = json_decode($c->receive(), true);

        foreach ($this->streams as $index => $stream){
            $this->streams[$index]['transcoding_options'] = '';

            if (!empty($answer_read['data']['streams'][$stream['name']]['transcoder'])){
                $transcoding_options = $answer_read['data']['streams'][$stream['name']]['transcoder'];

                $this->streams[$index]['transcoding_options'] = $this->serializeTranscoder($transcoding_options);
            }

        }

        foreach ($this->streams as $stream) {
            $streamObj = FlussonicStream::createFromArray($stream);
            $this->streams_objs[$streamObj->name] = $streamObj;
        }

        return $this->streams;
    }

    public function getSources()
    {
  /*
        $c = $this->getWSConnection();
        $c->send("read_config");

        $answer = json_decode($c->receive(), true);
        $this->sources = isset($answer['data']['sources']) ? $answer['data']['sources'] : [];

        foreach ($this->sources as $source) {
            $obj = FlussonicSource::createFromArray($source);
            $this->sources_objs[$obj->position] = $obj;
        }

        unset($c);
   */
        return $this->getDvrEdges([]);

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
    public function getFeeds($app_name, $only_names = false)
    {
        $retArr = [];

        if (!$this->streams) {
            $this->getStreams();
        }

        foreach ($this->streams as $stream)
        {
            if (explode("/", $stream['name'])[0] == $app_name) {
                $retArr[] = $only_names ? $stream['name'] : $stream;
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
     * getDvrEdgeReplicatedStreams
     *
     * @param String $app_name
     * @return array
     */
    public function getDvrEdgeReplicatedStreams($app_name)
    {
        if ($edges = $this->getDvrEdges([]))
        {
            foreach($edges as $edge) {
                if ($edge['meta']['comment'] == $app_name) {
                    return $edge['only'];
                }
            }
        }

        return [];
    }

    /**
     * getDvrEdgeSessions
     *
     * @param array $origin_streams
     * @return array
     */
    public function getDvrEdgeSessions(array $origin_streams = [])
    {
        $retArr = [];

        if (!$this->sessions) {
            $this->getSessions();
        }

        foreach ($this->sessions as $session)
        {
            if (in_array(explode('/', $session['name'])[0], $origin_streams)) {
                $retArr[] = $session;
            }
        }

        return $retArr;

    }

    /**
     * getDvrEdgeStreams
     *
     * @param array $origin_streams
     * @return array
     */
    public function getDvrEdgeStreams(array $origin_streams = [])
    {
        $retArr = [];

        if (!$this->streams) {
            $this->getStreams();
        }

        foreach ($this->streams as $stream)
        {
            if (in_array($stream['name'], $origin_streams)) {
                $retArr[] = $stream;
            }
        }

        return $retArr;
    }

    public function getStreamObj($name)
    {
        return isset($this->streams_objs[$name]) ? $this->streams_objs[$name] : [];
    }

    public function getSourceObj($position)
    {
        return isset($this->sources_objs[$position]) ? $this->sources_objs[$position] : [];
    }

    /**
     * createFeed
     *
     * @param string $name
     * @param string $url
     * @param bool $persistent
     * @return mixed
     */
    public function createFeed($name, $url, $persistent = true, $transcoding = '')
    {
        if ($currentStream = $this->getStreamObj($name)) {
            if (!$currentStream->isOutputsCorrect())
            {
                $this->disableStreamFormats($name, ['dash', 'hds', 'mpegts', 'rtsp']);
            }
            if ($currentStream->isSourcesCorrect($url) && $currentStream->isTranscodingCorrect($transcoding)) {
                return true;
            }
        }

        $streamtype = $persistent ? "stream" : "ondemand";

        $postfield = "$streamtype $name ";
        if ($transcoding){
            $postfield .= "{url $url; transcoder $transcoding}";
        } else {
            $postfield .= $url;
        }

        $this->action("/flussonic/api/config/stream_create");

        $result = $this->execBinaryPost($postfield);

        if (!$currentStream) {
            $this->disableStreamFormats($name, ['dash', 'hds', 'mpegts', 'rtsp']);
        }

        return $this;
    }

    public function createPushEndpoint($stream_name, $pwd, $transcoding = '')
    {
        $payload = [];
        $currentStream = $this->getStreamObj($stream_name);

        if (!$currentStream) {
            $payload = [
                'streams' => [
                    $stream_name => [
                        'name' => $stream_name,
                        'urls' => [],
                        'publish_enabled' => true,
                        'password' => $pwd
                    ]
                ]
            ];

            if ($transcoding) {
                $payload['streams'][$stream_name]['transcoder'] = $this->deserializeTranscoder($transcoding);
            }

            $this->disableStreamFormats($stream_name, ['dash', 'hds', 'mpegts', 'rtsp']);
        }
        else {
            if (!$currentStream->isOutputsCorrect())
            {
                $this->disableStreamFormats($stream_name, ['dash', 'hds', 'mpegts', 'rtsp']);
            }

            if (!$currentStream->isPushCorrect($pwd)) {
                $payload['streams'][$stream_name]['publish_enabled'] = true;
                $payload['streams'][$stream_name]['password'] = $pwd;
            }

            if (!$currentStream->isSourcesCorrect([])) {
                $payload['streams'][$stream_name]['urls'] = [];
            }

            if (!$currentStream->isTranscodingCorrect($transcoding)){
                $payload['streams'][$stream_name]['transcoder'] = $this->deserializeTranscoder($transcoding);
            }
        }

        if ($payload) {
            $this->action('/flussonic/api/modify_config');

            return $this->execBinaryPost(json_encode($payload));
        }

        return true;
    }

    public function disableStreamFormats($stream_name, array $formats, $isSource = false)
    {
        $section = $isSource ? 'sources' : 'streams';

        $payload = [$section => [$stream_name => []]];

        foreach ($formats as $format) {
            $payload[$section][$stream_name][$format."_off"] = true;
        }

        $this->action("/flussonic/api/modify_config");

        return $this->execBinaryPost(json_encode($payload));
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

    public function deleteSource($source_id)
    {
        $source_id = (int) $source_id;
        $this->action("/flussonic/api/modify_config");
        $data = '{"sources":{"'. $source_id . '": null}}';

        return $this->execBinaryPost($data);
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
    public function hashToToken($hash, $app_name, $type)
    {
        $hash_pieces = explode('.', $hash);

        $hash_billing_id  = $hash_pieces[0];
        $hash_portal_id   = $hash_pieces[1];
        $hash_user_id     = $hash_pieces[2];
        $hash_device_id   = $hash_pieces[4];
        $hash_platform_id = $hash_pieces[3];

        switch ($type) {
            case Core::TYPE_DVR_EDGE:
                $only    = $this->getDvrEdgeReplicatedStreams($app_name);
                $clients = $this->getDvrEdgeSessions($only);
                break;

            default:
                $clients = $this->getClients($app_name);
                break;
        }

        foreach ($clients as $session)
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

    public function setServerClusterKey($key)
    {
        $this->action("/flussonic/api/read_config");
        $current_key = json_decode($this->execGet(), true)["cluster_key"];

        if ($current_key === $key) {
            return true;
        }

        $this->action("/flussonic/api/modify_config");

        return $this->execBinaryPost(json_encode(["cluster_key" => $key]));
    }

    public function updateDvrChannel($name, array $sources, $storage, $depth)
    {
        if (!$currentStream = $this->getStreamObj($name))
        {
            $urls = [];
            foreach ($sources as $src) {
                $urls[] = ["url" => $src];
            }

            $cfg_arr = json_encode([
                "streams" =>
                [
                    $name => [
                        "name" => $name,
                        "urls" => $urls,
                        "dash_off" => true,
                        "hds_off" => true,
                        "mpegts_off" => true,
                        "rtsp_off" => true,
                        "dvr"  => [
                            "root" => $storage,
                            "dvr_limit" => $depth
                        ]
                    ]
                ]
            ]);

            $this->action("/flussonic/api/modify_config");
            $result = $this->execBinaryPost($cfg_arr);

            return $result;
        }

        $cfg_arr = [];

        if (!$currentStream->isOutputsCorrect())
        {
            $this->disableStreamFormats($name, ['dash', 'hds', 'mpegts', 'rtsp']);
        }

        if (!$currentStream->isSourcesCorrect($sources)) {
            $urls = [];
            foreach ($sources as $src) {
                $urls[] = ["url" => $src];
            }

            $cfg_arr['urls'] = $urls;
        }

        if (!$currentStream->isDvrSettingsCorrect($storage, $depth))
        {
            $cfg_arr["dvr"] = ["root" => $storage, "dvr_limit" => $depth];
        }

        if ($cfg_arr) {

            $cfg_arr["name"] = $name;

            $payload = json_encode([
                "streams" => [$name => $cfg_arr]
            ]);

            $this->action("/flussonic/api/modify_config");

            return $this->execBinaryPost($payload);
        }

        return true;
    }

    public function getDvrEdges($app_name)
    {
        $json_cfg = $this->action("/flussonic/api/read_config")
            ->execGet();
        $flussonic_cfg = json_decode($json_cfg, true);

        if (!isset($flussonic_cfg['sources'])) {
            return [];
        }

        foreach ($flussonic_cfg['sources'] as $source) {
            $obj = FlussonicSource::createFromArray($source);
            $this->sources_objs[$obj->position] = $obj;
        }
    }

    public function updateDvrEdge($index, $app_name, $data)
    {
        $time_limit = $data['cache'] * 60 * 60 * 24;
        if (!$currentSource = $this->getSourceObj($index))
        {
            $cfg_arr = json_encode([
                'sources' => [
                    $index => [
                        "position" => (int) $index,
                        "urls" => $data['hosts'],
                        "only" => $data['only'],
                        "cluster_key" => $data['cluster_key'],
                        "meta" => ["comment" => $app_name],
                        "dash_off" => true,
                        "hds_off" => true,
                        "mpegts_off" => true,
                        "rtsp_off" => true,
                        "cache" => [
                            "path" => $data['folder'],
                            "time_limit" => $time_limit //Days to sec
                        ],
                    ]
                ]
            ]);

            $this->action("/flussonic/api/modify_config");

            return $this->execBinaryPost($cfg_arr);
        }

        $cfg_arr = [];

        if (!$currentSource->isOutputsCorrect())
        {
            $this->disableStreamFormats($index, ['dash', 'hds', 'mpegts', 'rtsp'], $isSource = true);
        }

        if (!$currentSource->isSourcesCorrect($data['hosts']))
        {
            $cfg_arr["urls"] = $data["hosts"];
        }

        if (!$currentSource->isDvrSettingsCorrect($data['folder'], $time_limit)){
            $cfg_arr["cache"] = ["path" => $data['folder'], "time_limit" => $time_limit];
        }

        if (!$currentSource->isClusterKeyCorrect($data['cluster_key'])) {
            $cfg_arr["cluster_key"] = $data['cluster_key'];
        }

        if (!$currentSource->isAppNameCoorect($app_name)) {
            $cfg_arr["meta"]["comment"] = $app_name;
        }

        if ($cfg_arr) {
            $payload = json_encode(["sources" => [$index => $cfg_arr]]);
            $this->action("/flussonic/api/modify_config");

            return $this->execBinaryPost($payload);
        }

        return true;

    }

    public function setGlobalAuth($url)
    {
        $this->action("/flussonic/api/read_config");
        $current_auth_url = json_decode($this->execGet(), true)["auth"]["url"];

        if ($current_auth_url === $url) {
            return true;
        }

        $this->action("/flussonic/api/modify_config");

        return $this->execBinaryPost(json_encode(["auth" => ["url" => $url]]));
    }

    public function readConfig()
    {
        $json_cfg = $this->action("/flussonic/api/read_config")
            ->execGet();

        return json_decode($json_cfg, true);
    }

    public function getAllSourcesFromConfig(array $cfg)
    {
        return isset($cfg['sources']) ? $cfg['sources'] : [];
    }

    public function getAllStreamsFromCOnfig(array $cfg)
    {
        return isset($cfg['streams']) ? $cfg['streams'] : [];
    }

    protected function serializeTranscoder($transcoder)
    {
        if (!$transcoder) {
            return "";
        }

        $s = "";

        foreach ($transcoder as $j => $tr)
        {
            $k = $tr[0];
            $v = $tr[1];

            if ($k == "audio_bitrate") {
                $s .= " ab=" . $this->bitrate($v);
            } else if ($k == "video_bitrate") {
                $s .= " vb=" . $this->bitrate($v);
            } else if (!$k && !$v) {
                $s .= " ";
            } else if (count($tr) == 1) {
                $s .= " " . $k;
            } else {
                $s .= " " . $k . "=" . $v;
            }
        }

        return trim($s);
    }

    protected function bitrate ($v)
    {
        if ($v == "copy") {
            return $v;
        } else if ($v > 0) {
            return round($v / 1000) . "k";
        } else {
            return $v;
        }
    }

    protected function deserializeTranscoder($transcoder_s)
    {
        if ($transcoder_s == '') {
            return null;
        }

        $s = preg_split('/\s+/', $transcoder_s);
        $parts = array_map(function($part) {
            return explode("=", $part);
        }, $s);
        return $parts;
    }
}
