<?php namespace App;

/**
 * Core
 *
 * @author Iptvmatrix.net dev team
 * @version 1.0
 */
class Core
{
    const TYPE_ORIGIN = 1;
    const TYPE_EDGE = 2;
    const LAST_DEVICES_REPORT = 'last_devices_report';

    public $fa;
    public $ma;
    public $config;
    public $app_name;
    public $app_type;

    protected $matrix_cfg = [];

    /**
     * __construct
     *
     * @param MatrixApi $ma
     * @param FlussonicApi $fa
     * @param array $config
     * @return $this
     */
    public function __construct(MatrixApi $ma, FlussonicApi $fa, $config)
    {
        $this->fa = $fa;
        $this->ma = $ma;
        $this->config = (object) $config;

        $this->log = new Log();
    }

    /**
     * setApp
     *
     * @param string $app_name
     * @param string $type
     * @return this
     */
    public function setApp($app_name, $type)
    {
        $this->app_name = $app_name;
        $this->app_type = $type;
        $this->app_file = __DIR__ . "/tmp/mtrx.$app_name.t$type.json";

        return $this;
    }

    /**
     * generateStreamName
     *
     * returns name for stream from feed id
     *
     * @param int $feed_id
     * @return string
     */
    public function generateStreamName($feed_id)
    {
        switch ($this->app_type)
        {
            case self::TYPE_ORIGIN:
                return $this->app_name ."/_definst_/$feed_id";
                break;

            case self::TYPE_EDGE:
                return $this->app_name ."/$feed_id";
                break;
        }

        return "";
    }

    /**
     * isFirstRun
     *
     * First run means we need to query Matrix API for configuration
     *
     * @return bool
     */
    public function isFirstRun()
    {
        if (!file_exists($this->app_file))
        {
            return true;
        }

        if (!$this->matrix_cfg = json_decode(file_get_contents($this->app_file), true))
        {
            return true;
        }

        if ($this->matrix_cfg[MatrixApi::LAST_RUN] + $this->config->first_run_ttl < time())
        {
            return true;
        }

        if (!$this->matrix_cfg[MatrixApi::HEARTBEAT_URL])
        {
            return true;
        }

        return false;
    }

    /**
     * createStateFile
     *
     * Get conig from Matrix API via "First run" request
     *
     * @return this
     */
    public function createStateFile()
    {
        if (!$this->ma->getFirstRun($this->app_name, $this->app_type)) {
            $this->log->error("Cant first run from matrix for app: {$this->app_name}");
            die();
        }

        $m_cfg[MatrixApi::LAST_RUN] = time();
        $m_cfg[MatrixApi::HEARTBEAT_URL] = $this->ma->getAnswerArg(MatrixApi::HEARTBEAT_URL);
        $m_cfg[MatrixApi::HEARTBEAT_DELAY] = $this->ma->getAnswerArg(MatrixApi::HEARTBEAT_DELAY);
        $m_cfg[MatrixApi::DEVICES_REPORT_DELAY] = $this->ma->getAnswerArg(MatrixApi::DEVICES_REPORT_DELAY);
        $m_cfg[MatrixApi::DEVICE_MIN_LIFETIME] = $this->ma->getAnswerArg(MatrixApi::DEVICE_MIN_LIFETIME);

        $this->matrix_cfg = $m_cfg;

        file_put_contents($this->app_file, json_encode($this->matrix_cfg));

        return $this;
    }

    /**
     * updateStateFileLastRun
     *
     * @return this
     */
    public function updateStateFileLastRun()
    {
        if (!$this->matrix_cfg) {
            $this->matrix_cfg = json_decode(file_get_contents($this->app_file), true);
        }
        $this->matrix_cfg[MatrixApi::LAST_RUN] = time();

        return $this;
    }

    /**
     * getHeartBeatUrl
     *
     * @return string
     */
    public function getHeartBeatUrl()
    {
        if (!$this->matrix_cfg) {
            $this->matrix_cfg = json_decode(file_get_contents($this->app_file), true);
        }

        return $this->matrix_cfg[MatrixApi::HEARTBEAT_URL];
    }

    /**
     * setConfigArg
     *
     * @param string $arg
     * @param string $value
     * @return void
     */
    public function setConfigArg($arg, $value)
    {
        if (!$this->matrix_cfg) {
            $this->matrix_cfg = json_decode(file_get_contents($this->app_file), true);
        }

        $this->matrix_cfg[$arg] = $value;

        return $this;
    }

    /**
     * getConfigArg
     *
     * @param string $arg
     * @return void
     */
    public function getConfigArg($arg)
    {
        if (!$this->matrix_cfg) {
            $this->matrix_cfg = json_decode(file_get_contents($this->app_file), true);
        }

        return isset($this->matrix_cfg[$arg]) ? $this->matrix_cfg[$arg] : '';
    }

    /**
     * isDeviceReportNeeded
     *
     * if its time to send device report (depends on DEVICE_REPORT_DELAY option)
     *
     * @return bool
     */
    public function isDeviceReportNeeded()
    {
        $report_delay = $this->getConfigArg(MatrixApi::DEVICES_REPORT_DELAY) / 1000;
        $last_report  = $this->getConfigArg(self::LAST_DEVICES_REPORT);

        return time() > ($last_report + $report_delay);
    }

    /**
     * isRunNeeded
     *
     * if its time to send report (depends on HEARTBEAT_DELAY option)
     *
     * @return bool
     */
    public function isRunNeeded()
    {
        $report_delay = $this->getConfigArg(MatrixApi::HEARTBEAT_DELAY) / 1000;
        $last_report  = $this->getConfigArg(MatrixApi::LAST_RUN);

        return time() > ($last_report + $report_delay);
    }

    /**
     * saveMatrixCfg
     *
     * @return int
     */
    public function saveMatrixCfg()
    {
        if (!$this->matrix_cfg) {
            return true;
        }

        return file_put_contents($this->app_file, json_encode($this->matrix_cfg));
    }
}
