<?php

namespace Yard\Lib;

trait JsConvert
{
    use ArrayTools;

    static function toJs(array $arr, $sequential_keys = false, $quotes = false, $beautiful_json = true)
    {
        $object = self::is_assoc($arr);
        $output = $object ? "{" : "[";
        $count = 0;
        foreach ($arr as $key => $value) {
            if (self::is_assoc($arr) || (!self::is_assoc($arr) && $sequential_keys == true)) {
                if (!ctype_alnum($key)) {
                    $output .= '"' . $key . '"' . ": ";
                } else {
                    $output.= ($quotes ? '"' : '') . $key . ($quotes ? '"' : '') . ': ';
                }
            }
            if (is_array($value)) {
                $output.= self::toJs($value, $sequential_keys, $quotes, $beautiful_json);
            } elseif (is_bool($value)) {
                $output.= ($value ? 'true' : 'false');
            } elseif (is_numeric($value)) {
                $output.= $value;
            } else {
                if (strpos(trim($value), "js:") === 0) {
                    $output .= trim(substr(trim($value), 3));
                } elseif (strpos(trim($value), "php:") === 0) {
                    $value = eval('return print_r(' . trim(substr(trim($value), 4)) . ', true);');
                    $output .= ($quotes || $beautiful_json ? '"' : '') . $value . ($quotes || $beautiful_json ? '"' : '');
                } else {
                    $value = str_replace('"', '\"', $value);
                    $output.= ($quotes || $beautiful_json ? '"' : '') . $value . ($quotes || $beautiful_json ? '"' : '');
                }
            }
            if (++$count < count($arr)) {
                $output.= ', ';
            }
        }
        $output .= $object ? "}" : "]";
        return $output;
    }
}
