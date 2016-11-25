<?php namespace App;

class FlussonicSource
{
    private $stream;

    public $sources = [];
    public $outputs = [
        'hds_off' => 0,
        'hls_off' => 0,
        'm4s_off' => 0,
        'dash_off' => 0,
        'mpgets_off' => 0,
        'rtmp_off' => 0,
        'rtsp_off' => 0
    ];

    public $time_limit = 0;
    public $cluster_key = '';
    public $path = '';
    public $position = 0;
    public $app_name = '';

    public static function createFromArray(array $stream)
    {
        $self = new static();
        $self->stream = $stream;

        $self->sources = $stream['urls'];
        $self->position = $stream['position'];

        $self->outputs['hds_off']    = (int) $self->getFromArray('hds_off');
        $self->outputs['hls_off']    = (int) $self->getFromArray('hls_off');
        $self->outputs['m4s_off']    = (int) $self->getFromArray('m4s_off');
        $self->outputs['dash_off']   = (int) $self->getFromArray('dash_off');
        $self->outputs['mpgets_off'] = (int) $self->getFromArray('mpegts_off');
        $self->outputs['rtmp_off']   = (int) $self->getFromArray('rtmp_off');
        $self->outputs['rtsp_off']   = (int) $self->getFromArray('rtsp_off');

        if (isset($stream['meta']['comment'])) {
            $self->app_name = $stream['meta']['comment'];
        }

        if (isset($stream['cache']['time_limit'])) {
            $self->time_limit = $stream['cache']['time_limit'];
        }

        if (isset($stream['cache']['path'])) {
            $self->path = $stream['cache']['path'];
        }

        $self->cluster_key = $stream['cluster_key'];

        $self->stream = [];
        return $self;
    }

    public function getFromArray($key, $default = 0)
    {
        return isset($this->stream[$key]) ? $this->stream[$key] : $default;
    }

    public function isOutputsCorrect()
    {
        return Core::$correct_outputs == $this->outputs;
    }

    public function isSourcesCorrect($sources)
    {
        if (!is_array($sources)) {
            $sources = [$sources];
        }

        return $this->sources === $sources;
    }

    public function isDvrSettingsCorrect($storage, $time_limit)
    {
        return $this->path === $storage && $this->time_limit === $time_limit;
    }

    public function isAppNameCoorect($app_name)
    {
        return $this->app_name === $app_name;
    }

    public function isClusterKeyCorrect($cluster_key)
    {
        return $this->cluster_key === $cluster_key;
    }
}
