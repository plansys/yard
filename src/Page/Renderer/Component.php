<?php

namespace Yard\Page\Renderer;

trait Component
{
    private static function parseRender($page)
    {
        $render = $page->render();

        if (is_array($render)) {
            return $render;
        } elseif (is_string($render)) {
            $jsontxt = \Yard\Lib\HtmlToJson::convert($page->base, $render);
            if (is_array($jsontxt)) {
                return $jsontxt;
            } else {
                $json = json_decode($jsontxt, true);

                return $json;
            }
        }
    }

    public function renderComponent($content, $level = 0)
    {
        if (is_string($content))
            return $content;

        $attr = '';
        $child = '';
        $tag = $content[0];

        $renderSub = function ($children, $level) {
            # concat child
            $results = [];
            foreach ($children as $k => $c) {
                if (is_array($c)) {
                    if ($c[0] == 'jstext') {
                        $results[] = "`" . $c[1] . "`";
                        continue;
                    }
                    $results[] = $this->renderComponent($c, $level + 1);
                } else {
                    $results[] = "`$c`";
                }
            }

            # prettify
            $tabs = str_pad("    ", ($level + 1) * 4);
            $innerTabs = "";
            if ($level > 0) {
                $innerTabs = str_pad("    ", ($level) * 4);
            }
            return ", [\n" . $tabs . implode(",", $results) . "\n" . $innerTabs . "]";
        };

        $renderJs = function ($children, $level, $returnFuncBody = false) use ($renderSub) {
            # concat child
            $results = [];
            foreach ($children as $k => $c) {
                if (is_array($c)) {
                    if ($c[0] == 'jstext') {
                        if (is_string($c[1])) {
                            $results[] = trim($c[1]);
                        }
                        continue;
                    }

                    $results[] = $this->renderComponent($c, $level + 1);
                } else {
                    $results[] = $c;
                }
            }

            # process function body
            $funcbody = trim(implode(' ', $results));
            $noReturnIf = ["if", "while", "return", "for", "switch", "console", "var", "let", "const"];
            $prependWithReturn = true;
            foreach ($noReturnIf as $keyword) {
                if (strpos($funcbody, $keyword) === 0) {
                    $prependWithReturn = false;
                }
            }
            if ($prependWithReturn) {
                $funcbody = 'return ' . $funcbody . ';';
            }

            # prettify and return
            $tabs = str_pad("    ", ($level + 1) * 4);
            $funcbody = explode("\n", $funcbody);
            $funcbody = "    " . trim(implode("\n    ", $funcbody));

            if ($returnFuncBody || $level == 0) {
                return $funcbody;
            }
            return " function(h) { \n{$tabs}" . $funcbody . "\n{$tabs}} ";
        };

        # render component
        $renderedTag = '"' . $tag . '"';
        $count = count($content);
        if ($count > 1) {
            if ($count >= 2) {
                if (is_array($content[1])) {
                    if (strpos($tag, 'js:') === 0) {
                        $renderedTag = substr($tag, 3);
                    }

                    if ($count == 2) {
                        $children = $content[1];
                        if ($tag == 'js') {

                            # if current is root, return the resulting js
                            if ($level == 0) {
                                return "        " . $renderJs($children, $level);
                            }

                            $child = "," . $renderJs($children, $level);
                        } else {

                            $child = $renderSub($children, $level);
                        }
                    } else if ($count == 3) {
                        if (self::is_assoc($content[1])) {
                            $attr = ', ' . self::toJS($content[1]);
                        }

                        if (!is_array($content[2])) {
                            $content[2] = [$content[2]];
                        }

                        $child = $renderSub($content[2], $level);
                    }
                } else {
                    $child = ',' . json_encode($content[1], JSON_PRETTY_PRINT);
                }
            }
        }

        return ($level === 0 ? 'return ' : '') . 'h(' . $renderedTag . $attr . $child . ")";
    }
}
