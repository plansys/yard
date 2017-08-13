<?php

namespace Yard\Lib;

class HtmlToJson
{
    use ArrayTools;

    public static function preConvert($render) {
        return $render;
    }

    public static function postConvert($json)
    {

      $cleanJSProps = function ($props) {
        $clean = preg_replace("/js:\s*?([\w\W]+?)/im", "\${1}", $props);
        return $clean;
      };

      $recursive = function ($curentTag, $recursive) use ($cleanJSProps) {
        $TAG = 0;
        $CHILDREN_OR_PROPS = 1;
        $tagName = $curentTag[$TAG];

        $isText = !isset($curentTag[$CHILDREN_OR_PROPS]);
        if ($isText) return false;

        $hasProps = is_object($curentTag[$CHILDREN_OR_PROPS]);
        $child = false;

        if ($hasProps) {
          $CHILD_INDEX = $CHILDREN_OR_PROPS + 1;
          $props = $curentTag[$CHILDREN_OR_PROPS];
          $child = $curentTag[$CHILD_INDEX];
        }
        else {
          $CHILD_INDEX = $CHILDREN_OR_PROPS;
          $child = $curentTag[$CHILDREN_OR_PROPS];
        }

        $hasChild = is_array($child);

        if (($tagName === "If" || $tagName === "if") && $hasChild && $hasProps) {
          $condition = isset($props->{"condition"}) ? $props->{"condition"} : false;
          if ($condition) {
            $validCondition = $cleanJSProps($condition);
            $newStructure = [
              "js",
              [
                "if (" . $validCondition . ") {",
                $child,
                "}"
              ],
            ];
            $curentTag = $newStructure;
          }
        }

        // SWITCH nya entar (masih mikir enaknya gimana)
        // if (($tagName === "Choose" || $tagName === "choose") && $hasChild && $hasProps) {
        //   $condition = isset($props->{"condition"}) ? $props->{"condition"} : false;
        //   if ($condition) {
        //     $validCondition = $cleanJSProps($condition);
        //     $newStructure = [
        //       "js",
        //       [
        //         "if (" . $validCondition . ") {",
        //         $child,
        //         "}"
        //       ],
        //     ];
        //     $curentTag = $newStructure;
        //   }
        // }

        if (($tagName === "For" || $tagName === "for") && $hasChild && $hasProps) {
          $each = isset($props->{"each"}) ? $props->{"each"} : false;
          $index = isset($props->{"index"}) ? $props->{"index"} : false;
          $of = isset($props->{"of"}) ? $props->{"of"} : false;
          if ($of && $each) {
            $validOf = $cleanJSProps($of);
            $newStructure = [
              "js",
              [
                $validOf . ".map((" . $each . "" . ($index ? ", " . $index : "") . ") => { return (",
                  $child,
                  ")})"
                ],
              ];
              $curentTag = $newStructure;
          }
        }

        if ($hasChild) {
          $callback = function ($child) use ($recursive) {
            return $recursive($child, $recursive);
          };
          $child = array_map($callback, $child);
          $curentTag[$CHILD_INDEX] = $child;
        }
        return $curentTag;
      };
      $json = $recursive($json, $recursive);
      return $json;
    }

    private static function doConvert($base, $render)
    {
        $render = str_replace('&', '~^AND^~', $render); #replace '&' with something.
        $render = self::preConvert($render);

        # parse the dom
        $fdom = \FluentDom::load($render, 'text/xml', ['libxml'=> LIBXML_COMPACT ]);
        $json = new HSerializer($base, $fdom);
        $json = self::postConvert(json_decode($json));
        var_dump($json);
        $json = json_encode($json)

        # turn it to string again, and then un-format it
        return str_replace('~^AND^~', '&', $json); #un-replace '&'
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
