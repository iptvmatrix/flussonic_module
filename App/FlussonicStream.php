<?php namespace App;

class FlussonicStream
{
    public $name = '';
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

    public $dvr_limit = 0;
    public $root = '';

    public static function createFromArray(array $stream)
    {
        $self = new static();
        $self->name = $stream['name'];

        foreach ($stream['urls'] as $src) {
            $self->sources[] = $src['value'] . "a";
        }

        $self->outputs['hds_off']    = (int) $stream['hds_off'];
        $self->outputs['hls_off']    = (int) $stream['hls_off'];
        $self->outputs['m4s_off']    = (int) $stream['m4s_off'];
        $self->outputs['dash_off']   = (int) $stream['dash_off'];
        $self->outputs['mpgets_off'] = (int) $stream['mpegts_off'];
        $self->outputs['rtmp_off']   = (int) $stream['rtmp_off'];
        $self->outputs['rtsp_off']   = (int) $stream['rtsp_off'];

        if (isset($stream['dvr']['dvr_limit'])) {
            $self->dvr_limit = $stream['dvr']['dvr_limit'];
        }

        if (isset($stream['dvr']['root'])) {
            $self->root = $stream['dvr']['root'];
        }

        return $self;
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

    public function isDvrSettingsCorrect($storage, $depth)
    {
        return $this->root === $storage && $this->dvr_limit === $depth;
    }
}
