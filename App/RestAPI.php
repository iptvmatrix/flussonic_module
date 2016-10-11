<?php namespace App;

/**
 * RestAPI
 *
 * Shorthands for making requests for REST APIs
 *
 * @author Iptvmatrix.net dev team
 * @version 1.0
 */
class RestAPI
{
    protected $curl;
    protected $host;
    protected $action;
    protected $fields = [];
    protected $headers;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->curl = new Curl();
        $this->curl->returnTransfer(true);
    }

    /**
     * curl
     *
     * @param mixed $curl
     * @return this
     */
    public function curl($curl)
    {
        $this->curl = $curl;
        return $this;
    }

    /**
     * host
     *
     * @param mixed $host
     * @return this
     */
    public function host($host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * action
     *
     * @param mixed $url
     * @return this
     */
    public function action($url)
    {
        $this->action = $url;
        return $this;
    }

    /**
     * fields
     *
     * @param mixed $fields
     * @return this
     */
    public function fields($fields)
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * authBasic
     *
     * @param string $username
     * @param string $password
     * @return this
     */
    public function authBasic($username, $password)
    {
        $this->curl->authCreds($username, $password);

        return $this;
    }

    /**
     * headers
     *
     * @param mixed $headers
     * @return this
     */
    public function headers($headers)
    {
        $this->curl->headers($headers);
        return $this;
    }

    /**
     * clearFields
     *
     * @return this
     */
    protected function clearFields()
    {
        $this->curl->clearPostFields();
        $this->fields = [];

        return $this;
    }

    /**
     * fieldsToQuery
     *
     * http_build_query()
     *
     * @return string
     */
    protected function fieldsToQuery()
    {
        return $this->fields ? "?".http_build_query($this->fields) : '';
    }

    /**
     * execBinaryPost
     *
     * curl --request POST --data-binary ""
     *
     * @param mixed $postfield
     * @return mixed
     */
    public function execBinaryPost($postfield)
    {
        $this->curl->location($this->host . $this->action)
            ->post()
            ->dataType(Curl::BINARY_DATA)
            ->postFields($postfield);

        $retval = $this->curl->exec();
        $this->clearFields();

        return $retval;

    }

    /**
     * execPost
     *
     * @return mixed
     */
    public function execPost()
    {
        $this->curl->location($this->host . $this->action)
            ->post()
            ->dataType(Curl::HTTP_QUERY)
            ->postFields($this->fields);

        $retval = $this->curl->exec();
        $this->clearFields();

        return $retval;
    }

    /**
     * execPut
     *
     * @return mixed
     */
    public function execPut()
    {
        $this->curl->location($this->host . $this->action)
            ->put()
            ->dataType(Curl::HTTP_QUERY)
            ->postFields($this->fields);

        $retval = $this->curl->exec();
        $this->clearFields();

        return $retval;

    }

    /**
     * execGet
     *
     * @return mixed
     */
    public function execGet()
    {
        $this->curl->location($this->host . $this->action . $this->fieldsToQuery())
            ->get();

        $retval = $this->curl->exec();
        $this->clearFields();

        return $retval;
    }

    /**
     * execDelete
     *
     * @return mixed
     */
    public function execDelete()
    {
        $this->curl->location($this->host . $this->action . $this->fieldsToQuery())
            ->delete();

        $retval = $this->curl->exec();
        $this->clearFields();

        return $retval;
    }

    /**
     * getCode
     *
     * Return HTTP code (200, 404, 403, etc.)
     *
     * @return int
     */
    public function getCode()
    {
        return $this->curl->getCode();
    }
}
