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
            try {
                $jsontxt = \Yard\Lib\HtmlToJson::convert($page->base, $render);
                $json = json_decode($jsontxt, true);

                if ($json[0] == 'js') {
                    return ['jsdiv', $json[1]];
                }
                return $json;
            } catch (\Exception $e) {
                $render = explode("\n", \Yard\Lib\HtmlToJson::preConvert($render));
                $row = self::explode_first(" ", self::explode_last('in line ', $e->getMessage()));
                $col = self::explode_first(":", self::explode_last('character ', $e->getMessage())) + 1;
                $tab = "    ";

                $errors = [];

                $style = ['style' => ['borderBottom' => '1px solid red']];
                $errors[] = ['div', $style, $e->getMessage()];
                foreach ($render as $ln => $line) {
                    $num = str_pad($ln + 1, 4, " ", STR_PAD_LEFT) . " | ";
                    if ($ln == $row -1) {
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
                'position'=> 'fixed',
                'background' => 'white',
                'color' => 'black',
                'zIndex' => '9999',
                'fontSize' => '11px'
                ]], $errors];
            }
        }
    }

    private function renderComponent($content, $level = 0)
    {
        $attr = '';
        $child = '';
        $tag = $content[0];

        $renderSub = function ($ct, $level) {
            $els = [];
            foreach ($ct as $el) {
                $els[] = $this->renderComponent($el, $level + 1);
            }
            $bc = "";
            if ($level > 0) {
                $bc = str_pad("    ", ($level) * 4);
            }
            return ", [\n" . str_pad("    ", ($level + 1) * 4) . implode(",", $els) . "\n" . $bc . "]";
        };
        $count = count($content);
        if ($count > 1) {
            if ($count >= 2) {
                if (is_array($content[1])) {
                    if ($tag == 'js') {
                        $jsstr = "";
                        foreach ($content[1] as $jsc) {
                            if (is_string($jsc)) {
                                $jsstr .= $jsc;
                            } elseif (is_array($jsc)) {
                                $jsstr .= $this->renderComponent($jsc, $level + 1);
                            }
                        }

                        $noReturn = false;
                        $noReturnIf = ["if", "while", "return", "for", "switch", "console", "var", "let", "const"];
                        $jsstr = trim($jsstr);
                        foreach ($noReturnIf as $nr) {
                            if (strpos($jsstr, $nr) === 0) {
                                $noReturn = true;
                                break;
                            }
                        }

                        if ($noReturn) {
                            if (strpos($jsstr, "return ") === false && strpos($jsstr, "console") !== 0) {
                                $jscode = explode("\n", $jsstr);
                                foreach ($jscode as $ln => $v) {
                                    $num = str_pad($ln + 1, 4, " ", STR_PAD_LEFT) . " | ";
                                    $jscode[$ln] = $num . $v;
                                }
                                $jsstr = json_encode($jscode);
                                $jsstr = "console.error('The <js> should return <el> or string in Page [{$this->page->alias}]:', \"\\n\\n\" + {$jsstr}.join(\"\\n\"))";
                            }
                        } else {
                            if (trim($jsstr) == '') {
                                $jsstr = "''";
                            }

                            $jsstr = " return (" . $jsstr . ")";
                        }

                        $attr = "," . " function(h) { $jsstr }";
                    } else {
                        if (self::is_assoc($content[1])) {
                            $attr = ", " . self::toJS($content[1]);
                        } elseif ($count == 2) {
                            $child = $renderSub($content[1], $level);
                        }
                    }
                } else {
                    $child = ',' . json_encode($content[1]);
                }

                if ($count == 3) {
                    if (is_array($content[2]) && !self::is_assoc($content[2])) {
                        $child = $renderSub($content[2], $level);
                    } else {
                        $child = "," . json_encode($content[2]);
                    }
                }
            }
        }

        if (strpos($tag, 'js:') !== 0) {
            $tag = "'" . $tag . "'";
        } else {
            $tag = substr(trim($tag), 3);
        }

        if ($content[0] == "jstext") {
            return "`" . $content[1] . "`";
        }

        return "h({$tag}{$attr}{$child})";
    }
}
