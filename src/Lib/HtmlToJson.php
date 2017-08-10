<?php

namespace Yard\Lib;

class HtmlToJson
{
    use ArrayTools;

    public static function preConvert($render) {

        $render = str_replace('&', '~^AND^~', $render); #replace '&' with something.

        // $openingTemplate = false;
        // $usingBeforeRender = preg_match('/([\w\W]+?)(return[\w\W]+)/im', $render, $matches, PREG_OFFSET_CAPTURE);
        // if ($usingBeforeRender) {
        //   $openingTemplate = $matches[1][0];
        //   $render = $matches[2][0];
        // }

        $replacerPlansys = [
            // ============ ATTRIBUTE =============== //
            [
                // Replace ="js: blablabla"
                "regex" => '/="js:([\w\W]+?)"/im',
                "replacement" => '{${1}}',
            ],
        ];

        $replacerJSX = [
            // ============ TAG LEVEL =============== //
            [
                // Replace { ... spread }
                "regex" => '/(<[\w\W]+?){(\s?\.\.\.([\w\W]+?\s?))}/im',
                "replacement" => '${1}js:spread="${3}"',
            ],
            [
                // Replace return <If confition={expression}>expression</If>
                "regex" => '/<If condition={([\w\W]+?)}>([\w\W]+?)<\/If>/im',
                "replacement" => 'if (${1}) return <el>${2}</el>',
            ],

            // ============ OUTSIDE =============== //
            [
                // Replace { expression }
                // https://stackoverflow.com/a/14952740/6086756
                "regex" => '/(=?)({([^{}]+|(?R))*})/im',
                "name" => "globalBrackets"
            ],

            [
                // Replace return ( expression )
                "regex" => '/return\s?\(([\w\W]+?)\)/im',
                "replacement" => 'return <el>${1}</el>',
            ],
        ];

        foreach ($replacerPlansys as $key => $item) {
            $render = preg_replace($item["regex"], $item["replacement"], $render);
        }

        foreach ($replacerJSX as $key => $item) {
            if (isset($item["name"]) && $item["name"] === "globalBrackets") {
                $render = preg_replace_callback($item["regex"], function($matches) {
                    $isAttribute = $matches[1] !== "";
                    $value = $matches[3];
                    if ($isAttribute) return "=\"js:" . str_replace("\"","'", $value) . "\"";
                    else return "<js>" . $value . "</js>";
                }, $render);
            } else {
                $render = preg_replace($item["regex"], $item["replacement"], $render);
            }
        }

        return $render;
    }

    private static function doConvert($base, $render)
    {
        $render = self::preConvert($render);

        # parse the dom
        $fdom = \FluentDom::load($render, 'text/xml', ['libxml'=> LIBXML_COMPACT ]);
        $json = new HSerializer($base, $fdom);

        # turn it to string again, and then un-format it
        return str_replace('~^AND^~', '&', $json->__toString()); #un-replace '&'
    }

    public static function convert($base, $render, $try = 0)
    {
        if ($try > 0) {
            # format string, so that DomDocument does not choke on 'weird' mark up
            return self::doConvert($base, $render);
        } else {
            try {
                return self::doConvert($base, $render);
            } catch (\Exception $e) {
                $row = self::explode_first(" ", self::explode_last('in line ', $e->getMessage()));
                $col = self::explode_first(":", self::explode_last('character ', $e->getMessage())) -1;
                $lines = explode("\n", self::preConvert($render));
                if (strpos($e->getMessage(), 'StartTag') !== false) {
                    $lines[$row - 1] = substr($lines[$row - 1], 0, $col -1) . '~^LT^~'. substr($lines[$row - 1], $col);
                    $newrender = implode("\n", self::preConvert($lines));
                    $json = self::convert($base, $newrender, $try + 1);

                    return str_replace('~^LT^~', '<', $json);
                    ;
                } else {
                    throw $e;
                }
            }
        }
    }
}
