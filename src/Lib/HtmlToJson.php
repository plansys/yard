<?php

namespace Yard\Lib;

class HtmlToJson
{
    use ArrayTools;

    private static function doConvert($base, $render)
    {
        $render = str_replace('&', '~^AND^~', $render); #replace '&' with something.

        $init = $render;

        $replacer = [
          // ============ TAG LEVEL =============== //
          [
            // Replace { ... spread }
            "regex" => '/(<[\w\W]+?){(\s?\.\.\.([\w\W]+?\s?))}/im',
            "replacement" => '${1}js:spread="${3}',
          ],
          [
            // Replace return <If confition={expression}>expression</If>
            "regex" => '/<If condition={([\w\W]+?)}>([\w\W]+?)<\/If>/im',
            "replacement" => 'if (${1}) return <el>${2}</el>',
          ],

          // ============ ATTRIBUTE =============== //
          [
            // Replace ={ expression }
            "regex" => '/={([\w\W]+?)}/im',
            "replacement" => '="js:${1}"',
          ],
          [
            // Replace ={ expression }
            "regex" => '/={([\w\W]+?)}/im',
            "replacement" => '="js:${1}"',
          ],

          // ============ OUTSIDE =============== //
          [
            // Replace { expression }
            "regex" => '/{([\w\W]+?)}/im',
            "replacement" => '<js>${1}</js>',
          ],
          [
            // Replace return ( expression )
            "regex" => '/return\s?\(([\w\W]+?)\)/im',
            "replacement" => 'return <el>${1}</el>',
          ],
        ];

        foreach ($replacer as $key => $item) {
          $render = preg_replace($item["regex"], $item["replacement"], $render);
        }

        var_dump($init);
        var_dump($render);
        die();

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
                $lines = explode("\n", $render);
                if (strpos($e->getMessage(), 'StartTag') !== false) {
                    $lines[$row - 1] = substr($lines[$row - 1], 0, $col -1) . '~^LT^~'. substr($lines[$row - 1], $col);
                    $newrender = implode("\n", $lines);
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
