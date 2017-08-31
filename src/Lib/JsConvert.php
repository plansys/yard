<?php

namespace Yard\Lib;

trait JsConvert
{
    use ArrayTools;

    static function findTag(array $arr, $tag)
    {
        foreach ($arr as $key => $value) {
            if (is_array($value) && $value[0] == 'Page') {
                if ($value[1]->name == $tag) {
                    return $value;
                }
            }

            if (count($value) == 2 && is_array($value[1]) && count($value[1]) > 0) {
                $res = self::findTag($value[1], $tag);
                if ($res !== false) {
                    return $res;
                }
            }

            if (count($value) == 3 && is_array($value[2]) && count($value[2]) > 0) {
                $res = self::findTag($value[2], $tag);
                if ($res !== false) {
                    return $res;
                }
            }
        }

        return false;
    }

    static function toJs(array $arr, $sequential_keys = false, $quotes = false, $beautiful_json = true)
    {
        $object = self::is_assoc($arr);
        $output = $object ? "{" : "[";
        $count = 0;
        foreach ($arr as $key => $value) {
            if (self::is_assoc($arr) || (!self::is_assoc($arr) && $sequential_keys == true)) {
                if ($key == 'js:spread') {
                    $output .= '...' . $value;
                } else {
                    if (!ctype_alnum($key)) {
                        $output .= '"' . $key . '"' . ": ";
                    } else {
                        $output .= ($quotes ? '"' : '') . $key . ($quotes ? '"' : '') . ': ';
                    }
                }
            }

            if (!is_string($key) || $key != 'js:spread') {
                if (is_array($value)) {
                    $output .= self::toJs($value, $sequential_keys, $quotes, $beautiful_json);
                } elseif (is_bool($value)) {
                    $output .= ($value ? 'true' : 'false');
                } elseif (is_numeric($value)) {
                    $output .= $value;
                } else {
                    if (strpos(trim($value), "js:") === 0) {
                        $output .= trim(substr(trim($value), 3));
                    } else {
                        $value = str_replace('"', '\"', $value);
                        $output .= ($quotes || $beautiful_json ? '"' : '') . $value . ($quotes || $beautiful_json ? '"' : '');
                    }
                }
            }
            if (++$count < count($arr)) {
                $output .= ', ';
            }
        }
        $output .= $object ? "}" : "]";

        return $output;
    }
}
