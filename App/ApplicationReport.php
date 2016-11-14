<?php namespace App;

/**
 * ApplicationReport
 *
 * @author Iptvmatrix.net dev team
 * @version 1.0
 */
class ApplicationReport
{
    public $app_name;
    public $app_type;
    public $server;
    public $feeds_report = [];
    public $devices_report = [];

    public $streams;
    public $sessions;

    public $device_min_lifetime = 0;

    public $connected = [];
    public $toDrop = [];

    protected $network_status = [
        'rtmp' => 0, 'hls' => 0, 'in' => 0, 'out' => 0, 'cpu' => 0, 'mem' => 0
    ];

    //path to file, contains feeds statuses state
    protected $feeds_statuses_file;
    protected $last_feeds_statuses;

    public function __construct($app_name, $app_type)
    {
        $this->app_name = $app_name;
        $this->app_type = $app_type;
        $this->feeds_statuses_file = __DIR__."/tmp/$app_name.$app_type.feeds.json";
    }

    /**
     * setDevicesMinLifetime
     *
     * @param int $threshold
     * @return tjis
     */
    public function setDevicesMinLifetime($threshold)
    {
        $this->device_min_lifetime = $threshold;
        return $this;
    }

    /**
     * setFeedsStatus
     *
     * @param array $flussonic_sessions
     * @return this
     */
    public function setFeedsStatus(array $flussonic_sessions)
    {
        $this->streams = $flussonic_sessions;
        return $this;
    }

    /**
     * setConnectedClients
     *
     * @param array $flussonic_sessions
     * @return void
     */
    public function setConnectedClients(array $flussonic_sessions)
    {
        $this->sessions = $flussonic_sessions;
        return $this;
    }

    /**
     * setFeedMediaInfo
     *
     * @param String $feed_name
     * @param array $data
     * @return this
     */
    public function setFeedMediaInfo($feed_name, $data)
    {
        $this->feeds_media_info[$feed_name] = $data;
        return $this;
    }

    /**
     * setSystemStatus
     *
     * @param array $network
     * @param int $cpu
     * @param int $mem
     * @return $this
     */
    public function setSystemStatus(array $network, $cpu, $mem)
    {
        $this->network_status['in'] = $network['in'];
        $this->network_status['out'] = $network['out'];
        $this->network_status['cpu'] = $cpu;
        $this->network_status['mem'] = $mem;

        return $this;
    }

    /**
     * buildReport
     *
     * Build report for Matrix API, report availieble
     * in properties network_status, toDrop, devices_report
     * NOTE! Do not return report array!
     *
     * @param bool $withDevices
     * @return this
     */
    public function buildReport($withDevices = true)
    {
        foreach ($this->streams as $feed)
        {
            $name = explode('/', $feed['name']);
            $feed_id = $name[count($name) - 1];

            $status      = $this->extractFeedReport($feed);
            $status['t'] = $this->getFeedStatusChangeTs($feed_id, $status);

            $bitrate = $this->extractBitrate($feed);
            $client_count = isset($feed['client_count']) ? $feed['client_count'] : 0;

            $this->feeds_report[$feed_id] = $status;
        }

        $this->devices_report = $withDevices ? [] : null;

        foreach ($this->sessions as $client)
        {
            switch ($client['type']) {
                case 'hls_dvr':
                    $type = 'hls';
                    break;

                default:
                    $type = $client['type'];
            }

            if (isset($this->network_status[$type])) {
                $this->network_status[$type]++;
            }

            if (!empty($client['token'])) {
                $pieces = explode('.', $client['token']);

                $billing_id  = $pieces[0];
                $portal_id   = $pieces[1];
                $user_id     = $pieces[2];
                $device_id   = $pieces[3];
                $platform_id = $pieces[6];

                $hash = "$billing_id.$portal_id.$user_id.$device_id.$platform_id";

                if (!isset($this->connected[$hash])) {
                    $this->connected[$hash] = [
                        "token" => $client['token'],
                        "duration" => $client['duration']
                    ];

                    if ($withDevices && ($client['duration'] > $this->device_min_lifetime)) {
                        $this->devices_report[$billing_id][$portal_id][$user_id][] = "$platform_id.$device_id";
                    }
                }
                else {
                    $existed_conn = $this->connected[$hash];

                    $this->toDrop[] = $existed_conn['duration'] > $client['duration'] ?
                        $existed_conn['token'] : $client['token'];
                }
            }
        }

        return $this;
    }

    /**
     * getDuplicatedConnections
     *
     * @return this
     */
    public function getDuplicatedConnections()
    {
        return $this->toDrop;
    }

    /**
     * updateFeedsStatusesFile
     *
     * Rewrite feeds statuses file
     *
     * @return this
     */
    public function updateFeedsStatusesFile()
    {
        $data = json_encode($this->last_feeds_statuses);
        return file_put_contents($this->feeds_statuses_file, $data);
    }

    /**
     * loadFeedsLastStatuses
     *
     * @return this
     */
    protected function loadFeedsLastStatuses()
    {
        $file = file_get_contents($this->feeds_statuses_file);

        if ($file === false) {
            $file = json_encode([]);
            file_put_contents($this->feeds_statuses_file, $file);
        }

        $this->last_feeds_statuses = json_decode($file, true);

        return $this;
    }

    /**
     * getLastFeedStatus
     *
     * Get feeds statuses from state file
     *
     * @param mixed $feed_id
     * @return void
     */
    protected function getLastFeedStatus($feed_id)
    {
        $status = ['s' => 0, 'v' => 0, 'a' => '0', 't' => 0];

        if (!$this->last_feeds_statuses) {
            $this->loadFeedsLastStatuses();
        }

        if (isset($this->last_feeds_statuses[$feed_id])) {
            return $this->last_feeds_statuses[$feed_id];
        }

        return $status;
    }

    /**
     * getFeedStatusChangeTs
     *
     * Returns timestamp for last update feed's time.
     * We need to send to Matrix API timestamp, when feed change its status
     *
     * @param int $feed_id
     * @param array $status
     * @return int
     */
    protected function getFeedStatusChangeTs($feed_id, $status)
    {
        $old_status = $this->getLastFeedStatus($feed_id);

        if (!$old_status['t'])
        {
            $status['t'] = time();
            $this->last_feeds_statuses[$feed_id] = $status;

            return $status['t'];
        }

        $old_status_str = $old_status['s']. $old_status['v']. $old_status['a'];
        $new_status_str = $status['s']    . $status['v']    . $status['a'];

        if ($old_status_str != $new_status_str)
        {
            $status['t'] = time();
            $this->last_feeds_statuses[$feed_id] = $status;

            return $status['t'];
        }

        return $old_status['t'];
    }

    /**
     * extractFeedReport
     *
     * Extract feed status in Matrix API format, from flussonic stream
     *
     * @param array $feed
     * @return array
     */
    protected function extractFeedReport(array $feed)
    {
        $feed_status = ['s' => 0, 'v' => 0, 'a' => 0];

        if (empty($feed['alive'])) {
            return $feed_status;
        }

        $feed_status['s'] = 1;

        foreach($feed['media_info']['streams'] as $track)
        {
            if ($track['content'] == 'video') {
                $feed_status['v'] = 1;
            }
            elseif ($track['content'] == 'audio') {
                $feed_status['a'] = 1;
            }
        }

        return $feed_status;
    }

    /**
     * extractBitrate
     *
     * @param array $feed
     * @return int
     */
    protected function extractBitrate(array $feed)
    {
        if (!empty($feed['alive'])) {
            return $feed['bitrate'];
        }

        return 0;
    }

    /**
     * getNetworkStatus
     *
     * @return array
     */
    public function getNetworkStatus()
    {
        return $this->network_status;
    }

    /**
     * getFeedsStatus
     *
     * @return array
     */
    public function getFeedsStatus()
    {
        return $this->feeds_report;
    }

    /**
     * getDevicesStatus
     *
     * @return array
     */
    public function getDevicesStatus()
    {
        return $this->devices_report;
    }
}
