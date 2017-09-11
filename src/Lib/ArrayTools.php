<?php

namespace Yard\Lib;

trait ArrayTools
{
    public static function is_assoc($arr)
    {
        if (!is_array($arr)) {
            trigger_error("Argument should be an array for is_assoc", E_USER_WARNING);
            return false;
        }
        return count(array_filter(array_keys($arr), 'is_string')) > 0;
    }

    public static function explode_first($delimeter, $str)
    {
        $a = explode($delimeter, $str);
        return array_shift($a);
    }

    public static function explode_last($delimeter, $str, $howMany = 1)
    {
        $a = explode($delimeter, $str);
        for ($i = 1; $i < $howMany; $i++) {
            array_pop($a);
        }
        return end($a);
    }
}
