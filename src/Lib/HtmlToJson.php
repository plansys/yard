<?php

namespace Yard\Lib;

class HtmlToJson
{
    use ArrayTools;

    public static function preConvert($render)
    {
        return $render;
    }

    public static function postConvert($json)
    {
        $cleanJSProps = function ($props) {
            if (!is_string($props)) {
                $props = json_encode($props);
            }

            $clean = preg_replace("/js:\s*?([\w\W]+?)/im", "\${1}", $props);
            return $clean;
        };

        $recursive = function ($currentTag, $recursive) use ($cleanJSProps) {
            $TAG = 0;
            $CHILDREN_OR_PROPS = 1;
            $tagName = $currentTag[$TAG];
            $isText = !isset($currentTag[$CHILDREN_OR_PROPS]);
            if ($isText) return false;

            $hasProps = is_object($currentTag[$CHILDREN_OR_PROPS]);

            $props = null;
            if ($hasProps) {
                $CHILD_INDEX = $CHILDREN_OR_PROPS + 1;
                $props = $currentTag[$CHILDREN_OR_PROPS];
                $child = $currentTag[$CHILD_INDEX];
            } else {
                $CHILD_INDEX = $CHILDREN_OR_PROPS;
                $child = $currentTag[$CHILDREN_OR_PROPS];
            }

            $hasChild = is_array($child);

            if (($tagName === "If" || $tagName === "if") && $hasChild && $hasProps) {
                if (property_exists($props, 'condition')) {
                    $condition = $cleanJSProps($props->{"condition"});

                    # try to detect if child is a string.
                    if (count($child) == 1) {
                        if ($child[0][0] == 'jstext') {
                            $child = "'" . str_replace("'", "\'", trim($child[0][1])) . "'";
                        }
                    }

                    # if child is a string then, just concat it to the if.
                    if (is_string($child)) {
                        $newStructure = [
                            "js",
                            [
                                "\nif (" . $condition . ") { \n\t return " . $child . ";\n}"
                            ],
                            null
                        ];
                    } else {
                        if (count($child) == 1) {
                            $child = $child[0];
                        } else {
                            if (property_exists($props, 'tag')) {
                                $child = [$props->tag, $child];
                            } else {
                                $child = ['div', $child];
                            }
                        }

                        $newStructure = [
                            "js",
                            [
                                "\nif (" . $condition . ") { \n\t return ",
                                $child,
                                "\t;\n}"
                            ],
                            null
                        ];
                    }
                    $currentTag = $newStructure;
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
                    $currentTag = $newStructure;
                }
            }

            $hasChild = is_array($child);
            if ($hasChild) {
                if ($currentTag[0] === 'js') {
                    if (count($currentTag) === 3) {
                        unset($currentTag[2]);
                    }
                } else {
                    $callback = function ($child) use ($recursive) {
                        return $recursive($child, $recursive);
                    };
                    $child = array_map($callback, $child);
                    $currentTag[$CHILD_INDEX] = $child;
                }
            }
            return $currentTag;
        };
        $json = $recursive($json, $recursive);
        return $json;
    }

    private
    static function doConvert($base, $render)
    {
        $render = str_replace('&', '~^AND^~', $render); #replace '&' with something.
        $render = self::preConvert($render);

        # parse the dom
        $fdom = \FluentDom::load($render, 'text/xml', ['libxml' => LIBXML_COMPACT]);
        $json = new HSerializer($base, $fdom);
        $json = self::postConvert(json_decode($json));
        $json = json_encode($json);

        # turn it to string again, and then un-format it
        return str_replace('~^AND^~', '&', $json); #un-replace '&'
    }

    public
    static function convert($base, $render, $try = 0)
    {
        if ($try > 0) {
            # format string, so that DomDocument does not choke on 'weird' mark up
            return self::doConvert($base, $render);
        } else {
            try {
                return self::doConvert($base, $render);
            } catch (\Exception $e) {
                $row = self::explode_first(" ", self::explode_last('in line ', $e->getMessage()));
                $col = self::explode_first(":", self::explode_last('character ', $e->getMessage())) - 1;
                $lines = explode("\n", self::preConvert($render));
                if (strpos($e->getMessage(), 'StartTag') !== false) {
                    $lines[$row - 1] = substr($lines[$row - 1], 0, $col - 1) . '~^LT^~' . substr($lines[$row - 1], $col);
                    $newrender = implode("\n", self::preConvert($lines));
                    $json = self::convert($base, $newrender, $try + 1);

                    return str_replace('~^LT^~', '<', $json);;
                } else {
                    throw $e;
                }
            }
        }
    }
}
