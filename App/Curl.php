<?php namespace App;

/**
 * Curl
 *
 * Object Oriented wrap over php curl_* functions
 *
 * @author Iptvmatrix.net dev team
 * @version 1.0
 */
class Curl
{
    const JSON = 'json';
    const HTTP_QUERY = 'query';
    const DEFAULT_ARRAY = 'array';
    const BINARY_DATA = '';

    protected $curl_connection;

    protected $post;
    protected $requestFields = [];
    protected $requestDataType;

    public function __construct()
    {
        if (!$this->curl_connection = curl_init()) {
            throw new Exception("Cant initiate curl!");
        }
    }

    public function location($location)
    {
        curl_setopt($this->curl_connection, CURLOPT_URL, $location);
        return $this;
    }

    public function get()
    {
        curl_setopt($this->curl_connection, CURLOPT_CUSTOMREQUEST, "GET");
        $this->post = false;

        return $this;
    }

    public function post()
    {
        curl_setopt($this->curl_connection, CURLOPT_CUSTOMREQUEST, "POST");
        $this->post = true;

        return $this;
    }

    public function put()
    {
        curl_setopt($this->curl_connection, CURLOPT_CUSTOMREQUEST, "PUT");
        $this->post = false;

        return $this;
    }

    public function delete()
    {
        curl_setopt($this->curl_connection, CURLOPT_CUSTOMREQUEST, "DELETE");
        $this->post = false;

        return $this;
    }

    public function dataType($type)
    {
        $this->requestDataType = $type;
        return $this;
    }

    public function postFields($postfields)
    {
        $this->requestFields = $postfields;
        return $this;
    }

    public function clearPostFields()
    {
        curl_setopt($this->curl_connection, CURLOPT_POSTFIELDS, null);
        $this->requestFields = [];

        return $this;
    }

    public function returnTransfer($val)
    {
        curl_setopt($this->curl_connection, CURLOPT_RETURNTRANSFER, (bool) $val);
        return $this;
    }

    public function SSLVerifyPeer($val)
    {
        curl_setopt($this->curl_connection, CURLOPT_SSL_VERIFYPEER, (bool) $val);
        return $this;
    }

    public function SSLVerifyHost($val)
    {
        curl_setopt($this->curl_connection, CURLOPT_SSL_VERIFYHOST, (bool) $val);
        return $this;
    }

    public function authBasic()
    {
        curl_setopt($this->curl_connection, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        return $this;
    }

    public function authCreds($username, $password)
    {
        curl_setopt($this->curl_connection, CURLOPT_USERPWD, "$username:$password");
        return $this;
    }

    public function headers($headers)
    {
        curl_setopt($this->curl_connection, CURLOPT_HTTPHEADER, $headers);
        return $this;
    }

    public function exec()
    {
        switch ($this->requestDataType)
        {
            case(self::JSON):
                $request = json_encode($this->requestFields);
                break;

            case(self::HTTP_QUERY):
                $request = http_build_query($this->requestFields);
                break;

            default:
                $request = $this->requestFields;
                break;
        }

        curl_setopt($this->curl_connection, CURLOPT_POSTFIELDS, $request);

        $retVal = curl_exec($this->curl_connection);
        $this->answer_code = curl_getinfo($this->curl_connection, CURLINFO_HTTP_CODE);

        return $retVal;
    }

    public function getCode()
    {
        return $this->answer_code;
    }


    public function __destruct() {
        curl_close($this->curl_connection);
    }

}
