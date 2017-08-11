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

        $tagRegex = '/<[\w\W]+?>/im';

        // Select tag
        $render = preg_replace_callback($tagRegex, function($matches) {
          $tag = $matches[0];

          // Plansys attribute to JSX
          $tag = preg_replace_callback('/(=?)"((js:|)(([^"]|(?R))*))"[\w\W]?(\s|>)/im', function($matches) {
            $fullMatch = $matches[0];
            $hasCloseTagSign = preg_match('/>$/im', $fullMatch, $m, PREG_OFFSET_CAPTURE);
            $isValidAttribute = $matches[1] !== "" && $matches[3] !== "";
            $value = $matches[4];
            if ($isValidAttribute) {
              $ending = $hasCloseTagSign ? " >" : " ";
              return "={" . str_replace("\"","'", $value) . " }" . $ending;
            }
            return $fullMatch;
          }, $tag);

          return $tag;
        }, $render);


        $replacerJSX = [
            // ============ TAG LEVEL =============== //
            [
                // Replace return <If confition={expression}>expression</If>
                "regex" => '/<If condition={([\w\W]+?)}>([\w\W]+?)<\/If>/im',
                "replacement" => 'if (${1}) return <el>${2}</el>',
            ],
            // ============ OUTSIDE =============== //
            [
                // Replace return ( expression )
                "regex" => '/return\s?\(([\w\W]+?)\)/im',
                "replacement" => 'return <el>${1}</el>',
            ],
        ];


        foreach ($replacerJSX as $key => $item) {
          $render = preg_replace($item["regex"], $item["replacement"], $render);
        }

        // Global Brackets
        // https://stackoverflow.com/a/14952740/6086756
        $globalBracketsRegex = '/(=?)({(([^{}]+|(?R))*)})/im';

        // Select tag
        $render = preg_replace_callback($tagRegex, function($matches) use ($globalBracketsRegex) {
          $tag = $matches[0];

          // Plansys attribute to JSX
          $tag = preg_replace_callback($globalBracketsRegex, function($matches) {
            $fullMatch = $matches[0];
            // var_dump($matches);
            $isAttribute = $matches[1] !== "";
            $value = $matches[3];
            if ($isAttribute) {
              // $hasSpread = preg_match('/\.\.\./im', $value, $m, PREG_OFFSET_CAPTURE);
              // if ($hasSpread) return '="js: {' . str_replace("\"","'", $value) . '}"';
              return '="js: ' . str_replace("\"","'", $value) . '"';
            } else {
              // Is Spread props in tags
              return 'js:spread="{' . $value . '}"';
            }
          }, $tag);

          return $tag;
        }, $render);

        $bracketsOutside = '/(js:\s*|spread=")?({(([^{}]+|(?R))*)})/im';
        $render = preg_replace_callback($bracketsOutside, function($matches) {
            $fullMatch = $matches[0];
            $isOutside = $matches[1] === "";
            $value = $matches[3];
            if ($isOutside) return "<js>" . $value . "</js>";
            return $fullMatch;
        }, $render);

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
