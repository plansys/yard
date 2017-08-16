<?php

namespace Yard\Lib;

class HtmlToJson
{
    use ArrayTools;

    public static function log($value)
    {
      header("Content-Type: application/json");
      echo json_encode($value);
      die();
    }

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


        $makeValidTextContent = function ($tag) {
          $childStructure = $child[0];
          $childTagName = $childStructure[0];
          $childContent = $childStructure[1];
          $trimContent = trim($tag->child[1]);
          $replaceQuote = str_replace("'", "\"", $trimContent);
          $child[0][1] = $replaceQuote;
          return $child;
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

        $makeResultTag = function ($structure) {
          return   [
            "el",
            [$structure],
            null,
          ];
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


        $recursive = function ($currentTag, $recursive, $nextTag = false, $group = false, $index = false) use ($convertBack, $cleanJSProps, $makeValidTextContent, $defineTagStructure, $makeResultTag, $coverChild) {
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
                    $makeResultTag($convertBack($child)),
                    "\t\n}",
                  ],

                  // Null
                  null
                ];

                if ($nextTag && $nextTag[0] === "Else" || $nextTag[0] === "else") {
                  $elseTag = $defineTagStructure($nextTag);
                  $elseTagChild = $coverChild($elseTag);
                  array_push($newStructure[1], " else { \n\t return ");
                  array_push($newStructure[1], $makeResultTag($convertBack($elseTagChild)));
                  array_push($newStructure[1], "\t\n}");
                  unset($group[$index + 1]);
                  unset($nextTag);
                }

                $currentTag = $newStructure;
                $tag = $defineTagStructure($currentTag);
              }
            }

            if (($tag->name === "Else" || $tag->name === "else") && $tag->child) {
              $child = $coverChild($tag);
              $newStructure = [
                "jstext", // tag name
                "",
              ];
              $currentTag = $newStructure;
              $tag = $defineTagStructure($currentTag);
            }

            if (($tag->name === "For" || $tag->name === "for") && $tag->child && $tag->props) {
              if ($requireProps("each", $tag) && $requireProps("of", $tag)) {
                $each = $tag->props->each;
                $of = $cleanJSProps($tag->props->of);
                $index = $requireProps("index", $tag) ? $tag->props->index : false;

                $child = $coverChild($tag);
                $newStructure = [
                  "js", // tag name

                  // Children
                  [
                    "\n" . $of . ".map((" . $each . ($index ? "," . $index : "") . ") => { \n\t return ",
                    $makeResultTag($convertBack($child)),
                    "})",
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
                for ($i=0; $i < $childLength; $i++) {
                  $openingSwitch = ($i === 0 ? "switch (" . $evaluate . ") { \n" : "");
                  $closingSwitch = ($i === ($childLength - 1) ? "\n}" : "");

                  $currentChild = $tag->child[$i];
                  $currentChildTag = $defineTagStructure($currentChild);

                  $caseOrDefault =  $currentChildTag->name === "Default" || $currentChildTag->name === "default" ? "default" : "case";

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
                // self::log($cases);

                $newStructure = [
                  "js", // tag name

                  // Children
                  $cases,

                  // Null
                  null
                ];
                // self::log($newStructure);

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
        // self::log($json);
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
        // self::log($json);
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
