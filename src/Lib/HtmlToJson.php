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
            $clean = trim($clean);
            return $clean;
        };

        $defineTagStructure = function ($tag) {
            $TAG = 0;
            $CHILDREN_OR_PROPS = 1;
            $tagName = $tag[$TAG];
            $isText = !isset($tag[$CHILDREN_OR_PROPS]);
            if ($isText) return false;

            $hasProps = is_object($tag[$CHILDREN_OR_PROPS]);

            $props = null;
            if ($hasProps) {
                $CHILD_INDEX = $CHILDREN_OR_PROPS + 1;
                $props = $tag[$CHILDREN_OR_PROPS];
                $child = $tag[$CHILD_INDEX];
            } else {
                $CHILD_INDEX = $CHILDREN_OR_PROPS;
                $child = $tag[$CHILDREN_OR_PROPS];
            }

            $hasArrayChild = is_array($child);
            if ($hasArrayChild && count($child) == 1) {
                if (is_string($child[0])) {
                    $hasArrayChild = false;
                }
            }

            $define = new \stdClass();
            $define->name = $tagName;
            $define->props = $hasProps ? $props : false;
            $define->child = $child;

            $define->recursiveable = $hasArrayChild;
            $define->convertable = $hasArrayChild;

            return $define;
        };

        $convertBack = function ($tag) {
            $hasProps = $tag->props;
            $structure = [
                $tag->name,
                ($hasProps ? $tag->props : $tag->child),
            ];
            if ($hasProps) {
                array_push($structure, $tag->child);
            }
            return $structure;
        };


        $coverChild = function ($tag) use ($defineTagStructure) {
            # try to detect if child is a string.
            $singleChild = count($tag->child) === 1;
            if ($singleChild) {
                $firstChild = $tag->child[0];
                $child = $defineTagStructure($firstChild);
            } else {
                $child = $defineTagStructure(['div', $tag->child]);
            }
            return $child;
        };

        $recursive = function ($currentTag, $recursive, $nextTag = false, $group = false, $index = false) use ($convertBack, $cleanJSProps, $defineTagStructure, $coverChild) {
            if (!$currentTag) {
                return false;
            }

            $tag = $defineTagStructure($currentTag);

            $requireProps = function ($props, $tag) {
                return property_exists($tag->props, $props);
            };


            if (($tag->name === "If" || $tag->name === "if") && $tag->child && $tag->props) {
                if ($requireProps("condition", $tag)) {
                    $condition = $cleanJSProps($tag->props->condition);

                    $child = $coverChild($tag);
                    $newStructure = [
                        "js", // tag name

                        // Children
                        [
                            "\nif (" . $condition . ") { \n\t return ",
                            $convertBack($child),
                            "\t\n}",
                        ],

                        // Null
                        null
                    ];

                    if ($nextTag && $nextTag[0] === "Else" || $nextTag[0] === "else") {
                        $elseTagChild = $coverChild($defineTagStructure($nextTag));
                        array_push($newStructure[1], " else { \n\t return ");
                        array_push($newStructure[1], $convertBack($elseTagChild));
                        array_push($newStructure[1], "\t\n}");
                        unset($group[$index + 1]);
                        unset($nextTag);
                    }

                    $currentTag = $newStructure;
                    $tag = $defineTagStructure($currentTag);
                }
            }

            if (($tag->name === "Else" || $tag->name === "else") && $tag->child) {
                $coverChild($tag);
                $newStructure = [
                    "jstext", // tag name
                    "",
                ];
                $currentTag = $newStructure;
                $tag = $defineTagStructure($currentTag);
            }

            if (($tag->name === "For" || $tag->name === "for") && $tag->child && $tag->props) {
                if ($requireProps("each", $tag) && $requireProps("as", $tag)) {
                    $each = $cleanJSProps($tag->props->each);
                    $as = $tag->props->as;
                    $index = $requireProps("index", $tag) ? $tag->props->index : '__idx';

                    $child = $coverChild($tag);
                    if (is_object($child)) {
                        if (!isset($child->props->key) && is_object($child->props)) {
                            $child->props->key = 'js:' . $index;
                        } else {
                            $child->props = new \stdClass();
                            $child->props->key = 'js:' . $index;
                        }
                    }


                    $eachChecker = '';
                    if (preg_match('/[a-z0-9_\.]/i', $each) && strpos($each, '.') !== false) {
                        $eachArr = explode(".", $each);
                        $eachArrRes = [];
                        foreach ($eachArr as $k => $e) {
                            $ear = [];
                            for ($i = 0; $i <= $k; $i++) {
                                $ear[] = $eachArr[$i];
                            }
                            $eachArrRes[] = implode(".", $ear);
                        }
                        $eachChecker = implode(" && ", $eachArrRes);
                        $eachChecker = "if (!({$eachChecker})) return [];\n";
                    }

                    $newStructure = [
                        "js", // tag name
                        // Children
                        [
                            "(function() {",
                            $eachChecker,
                            "   let __each = $each;",
                            "   let __mapfunc = ({$as},{$index}) => { return ",
                            $convertBack($child),
                            "   };\n",
                            "   if (typeof __each === 'object' && __each != null) {      
                                    if (__each.length) { return __each.map(__mapfunc); }
                                    else {
                                        let __result = [];
                                        for (let __i in __each) {
                                            if (__each.hasOwnProperty(__i)) {
                                                __result.push(__mapfunc(__each[__i], __i));
                                            }
                                        }
                                        return __result;
                                    }
                                }
                            ",
                            "}.bind(this)())"
                        ],

                        // Null
                        null
                    ];

                    $currentTag = $newStructure;
                    $tag = $defineTagStructure($currentTag);
                }
            }

            if (($tag->name === "Switch" || $tag->name === "switch") && $tag->child && $tag->props) {
                if ($requireProps("evaluate", $tag)) {
                    $evaluate = $cleanJSProps($tag->props->evaluate);

                    $cases = [];
                    $childLength = count($tag->child);
                    for ($i = 0; $i < $childLength; $i++) {
                        $openingSwitch = ($i === 0 ? "switch (" . $evaluate . ") { \n" : "");
                        $closingSwitch = ($i === ($childLength - 1) ? "\n}" : "");

                        $currentChild = $tag->child[$i];
                        $currentChildTag = $defineTagStructure($currentChild);

                        $caseOrDefault = $currentChildTag->name === "Default" || $currentChildTag->name === "default" ? "default" : "case";

                        $openingCase = "";
                        if ($caseOrDefault === "case") {
                            $is = $cleanJSProps($currentChildTag->props->is);
                            $openingCase = $openingSwitch . "\n\t" . $caseOrDefault . " (" . $is . "): { \n\treturn ";
                        } else {
                            $openingCase = $openingSwitch . "\n\t" . $caseOrDefault . ": { \n\treturn ";
                        }

                        array_push($cases, $openingCase);
                        array_push($cases, $currentChildTag->child[0]);
                        array_push($cases, "\nbreak;\n\t}" . $closingSwitch);
                    }

                    $newStructure = [
                        "js", // tag name

                        // Children
                        $cases,

                        // Null
                        null
                    ];

                    $currentTag = $newStructure;
                    $tag = $defineTagStructure($currentTag);
                }
            }

            if (isset($tag) && $tag->recursiveable) {
                $callback = function ($child, $index) use ($recursive, $tag) {
                    $nextTag = isset($tag->child[$index + 1]) ? $tag->child[$index + 1] : false;
                    $prevTag = isset($tag->child[$index - 1]) ? $tag->child[$index - 1] : false;
                    return $recursive($child, $recursive, $nextTag, $tag->child, $index);
                };
                // https://stackoverflow.com/a/5868491/6086756
                $newChild = array_map($callback, $tag->child, array_keys($tag->child));
                $tag->child = $newChild;
            }

            $currentTag = $tag->convertable ? $convertBack($tag) : $currentTag;
            return $currentTag;
        };

        $json = $recursive($json, $recursive);


        if ($json[0] != 'js' && count($json) >= 2) {
            if (count($json) == 2) {
                $json[] = $json[1];
                $json[1] = [];
            }

            if (is_object($json[1])) {
                if (!isset($json[1]->className)) {
                    $json[1]->className = 'js: this.props.className || null';
                }

                if (!isset($json[1]->style)) {
                    $json[1]->style = 'js: this.props.style || null';
                }
            } else if (is_array($json[1])) {
                if (!isset($json[1]['className'])) {
                    $json[1]['className'] = 'js: this.props.className || null';
                }

                if (!isset($json[1]['style'])) {
                    $json[1]['style'] = 'js: this.props.style || null';
                }
            }
        }

        return $json;
    }

    public static function doConvert($base, $render, $returnAsArray = false)
    {
        try {
            $render = str_replace('&', '~^AND^~', $render); #replace '&' with something.
            $render = self::preConvert($render);

            # parse the dom
            $fdom = \FluentDom::load($render, 'text/xml', ['libxml' => LIBXML_COMPACT, 'is_string' => true]);
            $json = new HSerializer($base, $fdom);
            $json = self::postConvert(json_decode($json->__toString()));

            if ($returnAsArray) {
                return $json;
            }
            $json = json_encode($json);

            # turn it to string again, and then un-format it
            return str_replace('~^AND^~', '&', $json); #un-replace '&'
        } catch (\Exception $e) {
            $render = explode("\n", \Yard\Lib\HtmlToJson::preConvert($render));
            $row = self::explode_first(" ", self::explode_last('in line ', $e->getMessage()));
            $col = self::explode_first(":", self::explode_last('character ', $e->getMessage()));

            if (is_numeric($col)) {
                $col = $col + 1;
            } else {
                throw $e;
            }

            $tab = "    ";

            $errors = [];

            $style = ['style' => ['borderBottom' => '1px solid red']];
            $errors[] = ['div', $style, $e->getMessage()];
            foreach ($render as $ln => $line) {
                $num = str_pad($ln + 1, 4, " ", STR_PAD_LEFT) . " | ";
                if ($ln == $row - 1) {
                    $style = ['style' => ['background' => 'red', 'color' => 'white']];
                    $errors[] = ['div', $style, $num . str_replace("\t", $tab, $line)];

                    for ($k = 1; $k < strlen($line); $k++) {
                        if ($k != $col) {
                            $line[$k] = $line[$k] === "\t" ? $tab : " ";
                        } else {
                            $line[$k] = "^";
                        }
                    }

                    $errors[] = ['div', "     | " . str_replace("\t", $tab, $line)];
                } else {
                    $errors[] = ['div', $num . str_replace("\t", $tab, $line)];
                }
            }

            return ['pre', ['style' => [
                'border' => '1px solid red',
                'position' => 'fixed',
                'background' => 'white',
                'color' => 'black',
                'zIndex' => '9999',
                'maxWidth' => '800px',
                'overflowX' => 'auto',
                'fontSize' => '11px'
            ]], $errors];
        }
    }

    public static function convert($base, $render)
    {
        return self::doConvert($base, $render);
    }
}
