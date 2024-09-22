<?php
namespace WPUmbrella\Helpers;


abstract class DataTemporary
{
    protected static $values = [];

    public static function setDataByKey($key, $value)
    {
        self::$values[$key] = $value;
    }

    public static function getDataByKey($key)
    {
        if (isset(self::$values[$key])) {
            return self::$values[$key];
        }

        return null;
    }
}
