<?php
namespace App;

class SystemStatus {

    const TRANSMITTED = 'tx';
    const RECEIVED    = 'rx';

    protected $ifaces = [];
    protected $stats = [];

    protected $network_in = null;
    protected $network_out = null;

    protected $mk_time_start = 0;
    protected $mk_time_end = 0;


    public function __construct()
    {
        $this->obtainIFaces();
        return $this;
    }

    public function obtainIFaces()
    {
        $this->ifaces = scandir('/sys/class/net/');

        foreach ($this->ifaces as $i => $iface) {
            if (in_array($iface, ['lo', '.', '..'])) {
                unset($this->ifaces[$i]);
            }
        }

        return $this;
    }

    public function initMeasurement()
    {
        $this->mk_time_start = microtime(true);
        $this->mk_time_end   = 0;

        $this->getIFacesBytes(self::TRANSMITTED)
            ->getIFacesBytes(self::RECEIVED);

        return $this;
    }

    public function stopMeasurement()
    {
        if ($this->mk_time_end) {
            return $this;
        }

        $this->mk_time_end = microtime(true);

        $this->getIFacesBytes(self::TRANSMITTED)
            ->getIFacesBytes(self::RECEIVED);

        return $this;
    }

    public function getIFacesBytes($type)
    {
        $time_point = $this->mk_time_end ? 'end' : 'start';

        foreach ($this->ifaces as $iface) {
            $this->stats[$iface][$time_point][$type] = file_get_contents("/sys/class/net/$iface/statistics/{$type}_bytes");
        }

        return $this;
    }

    public function getNetworkUsage()
    {
        if (!is_null($this->network_in) && !is_null($this->network_out)) {
            return [
                'in'  => ceil($this->network_in  / 1000),
                'out' => ceil($this->network_out / 1000)
            ];
        }

        $this->network_in  = 0;
        $this->network_out = 0;

        $delta_time = round(microtime(true) - $this->mk_time_start, 6);

        if ($delta_time < 1) {
            $need_microsecs_to_reach_second = (1 - $delta_time) * 1000000;
            usleep($need_microsecs_to_reach_second);
        }

        $this->stopMeasurement();

        foreach ($this->stats as $iface => $bytes)
        {
            $this->network_in  += $bytes['end'][self::RECEIVED]    - $bytes['start'][self::RECEIVED];
            $this->network_out += $bytes['end'][self::TRANSMITTED] - $bytes['start'][self::TRANSMITTED];
        }

        return [
            'in'  => ceil($this->network_in  / 1000),
            'out' => ceil($this->network_out / 1000)
        ];
    }

    function getMemoryUsage()
    {
	    $free = shell_exec('free');
	    $free = (string)trim($free);
	    $free_arr = explode("\n", $free);
	    $mem = explode(" ", $free_arr[1]);
	    $mem = array_filter($mem);
	    $mem = array_merge($mem);
        $memory_usage = 100 - ($mem[3]+$mem[5]+$mem[6])/$mem[1]*100;

	    return ceil($memory_usage);
    }

    function getCpuUsage()
    {
        $load = sys_getloadavg();
        $core_nums=trim(shell_exec("grep -P '^physical id' /proc/cpuinfo|wc -l"));
        $load=ceil($load[0]/$core_nums * 100);

	    return $load;
    }
}
