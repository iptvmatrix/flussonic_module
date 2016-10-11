<?php namespace App;

class Log
{
    public function error($msg)
    {
        pr($msg);
        return;
    }
}
