<?php namespace App;

/**
 * MatrixApi
 *
 * @uses RestAPI
 * @package
 * @author Iptvmatrix.net dev team
 * @version 1.0
 */
class MatrixApi extends RestAPI
{
    const LAST_RUN = 'last_run';
    const HEARTBEAT_URL = 'heartbeat_url';
    const HEARTBEAT_DELAY = 'heartbeat_delay';
    const DEVICES_REPORT_DELAY = 'devices_report_delay';
    const DEVICE_MIN_LIFETIME = 'device_min_lifetime';

    protected $config = [];
    protected $heartbeat_url = '';
    protected $device_min_lifetime = 0;
    protected $answer = [];

    protected $feeds_updates = [];
    protected $feeds_remove = [];
    protected $feeds_reset = [];
    protected $kill_devices = [];

    /**
     * __construct
     *
     * @param array $config
     * @return this
     */
    public function __construct($config)
    {
        parent::__construct();
        $this->config = (object) $config;
    }

    /**
     * getHeartbeatUrl
     *
     * @return string
     */
    public function getHeartbeatUrl()
    {
        return $this->heartbeat_url;
    }

    /**
     * clearAnswers
     *
     * Reset Matrix API's answer to default (empty array)
     *
     * @return this
     */
    public function clearAnswers()
    {
        $this->answer = [];
        $this->feeds_updates = [];
        $this->feeds_remove = [];
        $this->feeds_reset = [];

        return $this;
    }

    /**
     * getServerID
     *
     * @return array
     */
    public function getServerID()
    {
        $c = $this->config;

        return isset($c->flussonic_id) ? ['wowza_id' => $c->flussonic_id] : [];
    }

    /**
     * getFirstRun
     *
     * First run request to Matrix API
     *
     * @param string $appname
     * @param int $type
     * @return array
     */
    public function getFirstRun($appname, $type)
    {
        $fields = [
            "data" => json_encode([
                "hls" => 0,
                "rtmp" => 0,
                "in" => 0,
                "out" => 0,
                "app" => $appname, "type"  => $type,
                "ver" => "1.90",   "first" => 1,
            ] + $this->getServerID())
        ];

        $c = $this->config;
        $this->host($c->matrix_url)
            ->fields($fields);

        $retVal = json_decode($this->execPost(), true);

        $this->answer = $retVal;

        if (!is_null($retVal))
        {
            $this->heartbeat_url = $retVal['heartbeat_url'];

            if (!empty($retVal['feeds_update'])) {
                $this->feeds_updates = $retVal['feeds_update'];
            }
        }

        return $retVal;
    }

    /**
     * sendReport
     *
     * Report request to Matrix API, cache answer.
     *
     * @param string $app_name
     * @param int $type
     * @param string $to
     * @param ApplicationReport $report
     * @return this
     */
    public function sendReport($app_name, $type, $to, ApplicationReport $report)
    {
        $fields = [
            "data" => json_encode(
                $report->getNetworkStatus() +
                ["app" => $app_name, "type"  => $type,
                 "ver" => "1.90",   "first" => 0,
                 "time" => time(),
                 "report" => $report->getFeedsStatus(),
                 "devices" => $report->getDevicesStatus(),
                ] + $this->getServerID())
        ];

        $this->host($to)
            ->fields($fields);

        $answer = json_decode($this->execPost(), true);

        $this->answer = $answer;

        if (isset($answer['feeds_update'])) {
            $this->feeds_updates = $answer['feeds_update'];
        }

        if (isset($answer['feeds_remove'])) {
            $this->feeds_remove = $answer['feeds_remove'];
        }

        if (isset($answer['feeds_reset'])) {
            $this->feeds_reset = $answer['feeds_reset'];
        }

        if (isset($answer['kill_devices'])) {
            $this->kill_devices = $answer['kill_devices'];
        }

        return $this;
    }

    /**
     * getFeedsUpdates
     *
     * @return array
     */
    public function getFeedsUpdates()
    {
        return $this->feeds_updates;
    }

    /**
     * getFeedsRemove
     *
     * @return array
     */
    public function getFeedsRemove()
    {
        return $this->feeds_remove;
    }

    /**
     * getFeedsReset
     *
     * @return array
     */
    public function getFeedsReset()
    {
        return $this->feeds_reset;
    }

    /**
     * getKillDevices
     *
     * @return array
     */
    public function getKillDevices()
    {
        return $this->kill_devices;
    }

    /**
     * getAnswerArg
     *
     * @param string $arg
     * @return string|bool
     */
    public function getAnswerArg($arg)
    {
        return isset($this->answer[$arg]) ? $this->answer[$arg] : false;
    }
}