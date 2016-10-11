<?php

namespace App;

/**
 * Result
 *
 * @package
 * @author Iptvmatrix.net dev team
 * @version 1.0
 */
class Result
{

    const FAILURE = 0;
    const SUCCESS = 1;
    const STATUS = 'status';
    const BODY = 'body';
    const MESSAGE = 'message';

    public static function res($status, $body, $message)
    {
        return array(self::STATUS => $status, self::BODY => $body, self::MESSAGE => $message);
    }

    public static function success($body = null, $message = null)
    {
        return array(self::STATUS => self::SUCCESS, self::BODY => $body, self::MESSAGE => $message);
    }

    public static function failure($message, $body = '')
    {
        return array(self::STATUS => self::FAILURE, self::BODY => $body, self::MESSAGE => $message);
    }

    public static function successDb($database, $body = null, $message = null)
    {
        $database->commit();
        return self::success($body, $message);
    }

    public static function failureDb($database, $message, $body = '')
    {
        $database->rollback();
        return self::failure($message, $body);
    }

    public static function isSuccess($result)
    {
        return $result[self::STATUS] == self::SUCCESS;
    }

    public static function isFailure($result)
    {
        return $result[self::STATUS] != self::SUCCESS;
    }

    public static function getBody($result)
    {
        return $result[self::BODY];
    }

    public static function getMessage($result)
    {
        return $result[self::MESSAGE];
    }

    public static function isResult($candidate)
    {
        return isset($candidate[self::STATUS]) &&
               (isset($candidate[self::BODY]) ||
                isset($candidate[self::MESSAGE]));
    }

}
