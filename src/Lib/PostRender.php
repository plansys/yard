<?php

namespace Yard\Lib;

trait PostRender
{
    public function addRefToProps(&$props, $refCallback, $modifyOldRef = "oldRef(ref)")
    {
        $oldRef = '';
        if (isset($props['ref'])) {
            if (strpos($props['ref'], 'js') !== false) {
                $oldRef = str_replace('js:', '', $props['ref']);
                $oldRef = "let oldRef = ({$oldRef});";
                $oldRef .= 'if (typeof oldRef === "function") { ' . $modifyOldRef . ' }';
            }
        }

        $props['ref'] = "js: function(ref) {
            {$refCallback}
            {$oldRef}
        }.bind(this)";
    }

    public function replaceTagContent($tag, $content, $func, $retainOriginalTags = false)
    {
        $regex = '/(<' . $tag . '[^>]*>)(.*?)(<\/' . $tag . '>)/is';
        preg_match($regex, $content, $matches);

        if (!isset($matches[2])) {
            return $content;
        }

        $children = $func($matches[2]);

        if ($retainOriginalTags) {
            $res = preg_replace($regex, '$1' . $children . '$3', $content);
        } else {
            $res = preg_replace($regex, $children, $content);
        }

        return $res;
    }

    private function addKeyAttr(&$tag, $keyProp = 'key')
    {
        $addKey = function (&$tag) use ($keyProp) {
            if (is_array($tag[1])) {
                if (!self::is_assoc($tag[1])) {
                    $tag[2] = $tag[1];
                    $tag[1] = new \stdClass();
                    $tag[1]->key = "js: " . $keyProp;
                } else {
                    $tag[1]['key'] = 'js: ' . $keyProp;
                }
            } else if (is_object($tag[1])) {
                $tag[1]->key = "js: " . $keyProp;
            }
        };

        if (count($tag) == 3) {
            if (is_array($tag[2])) {
                if (!isset($tag[2][0][1]->key)) {
                    $addKey($tag[2][0]);
                }
            }
        } else if (count($tag) == 2) {
            if (is_array($tag[1]) && !self::is_assoc($tag[1])) {
                $addKey($tag[1][0]);
            }
        }
    }

    private function removeTailChild(&$tag)
    {
        if (count($tag) == 3) {
            if (is_array($tag[2]) && count($tag[2]) > 1) {
                array_splice($tag[2], 1);
            }
        }
    }

    protected function addKeyInTagChild($tagName, $keyProp, &$childArray)
    {
        $tag = &$this->findTagInArray($tagName, $childArray);
        if (!$tag) {
            return $this->renderComponentAsHtml($childArray);
        }
        $this->addKeyAttr($tag, $keyProp);
        $this->removeTailChild($tag);
        $this->replaceTagInArray($tagName, $tag, $childArray);
        return $this->renderComponentAsHtml($childArray);
    }

    private function cleanSingleHtmlArray(&$v)
    {
        if (self::is_assoc($v)) {
            foreach ($v as $i => $j) {
                $v[$i] = str_replace('~^AND^~', '&', $v[$i]);
            }
        } else {

            $count = count($v);
            if ($count >= 2) {
                $isobject = is_object($v[1]);
                if ($isobject || is_array($v[1])) {
                    foreach ($v[1] as $k => &$attr) {
                        if (is_array($attr)) {
                            if ($isobject) {
                                $this->cleanSingleHtmlArray($v[1]->{$k});
                            } else {
                                $this->cleanSingleHtmlArray($v[1][$k]);
                            }
                        } else if (is_string($attr)) {
                            if ($isobject) {
                                $v[1]->{$k} = str_replace('~^AND^~', '&', $attr);
                            } else {
                                $v[1][$k] = str_replace('~^AND^~', '&', $attr);
                            }
                        }
                    }
                }

                if (is_string($v[1])) {
                    $v[1] = str_replace('~^AND^~', '&', $v[1]);
                }

                if ($count == 3 && is_array($v[2])) {
                    foreach ($v[2] as $k => &$attr) {
                        if (is_array($attr)) {
                            $this->cleanSingleHtmlArray($v[2][$k]);
                        } else if (is_string($attr)) {
                            $v[2][$k] = str_replace('~^AND^~', '&', $attr);
                        }
                    }
                }
            }
        }
    }

    private function cleanHtmlArray(&$array)
    {
        if (is_array($array) && is_string($array[0])) {
            $this->cleanSingleHtmlArray($array);
            return $array;
        }

        foreach ($array as $k => &$v) {
            $this->cleanSingleHtmlArray($array[$k]);
        }

        return $array;
    }

    public function renderHtmlAsComponent($content)
    {
        if (is_string($content)) {
            $htmlArray = HtmlToJson::doConvert($this->base, '<dummy>' . $content . '</dummy>', true);
            $htmlArray = $this->cleanHtmlArray($htmlArray);
            return $htmlArray[2];
        }
    }

    public function renderComponentAsHtml($content, $level = 0)
    {
        if ($level == 0 && is_array($content) && !self::is_assoc($content)) {
            $html = [];
            foreach ($content as $t) {
                $html[] = $this->renderComponentAsHtml($t, $level + 1);
            }
            return implode("\n", $html);
        }

        $attr = [];
        $childStr = '';
        $renderedTag = $content[0];

        if ($renderedTag == 'jstext') {
            return count($content) == 2 && is_string($content[1]) ? $content[1] : '';
        }

        if ($renderedTag == 'js') {
            if (count($content) == 2) {
                if (is_string($content[1])) {
                    return "<js>" . $content[1] . "</js>";
                } else if (is_array($content[1])) {
                    $result = [];
                    foreach ($content[1] as $jscontent) {
                        if (is_string($jscontent)) {
                            $result[] = $jscontent;
                        } else if (is_array($jscontent)) {
                            if ($jscontent[0] == 'jstext') {
                                $result[] = $jscontent[1];
                            } else {
                                $result[] = $this->renderComponentAsHtml($jscontent, $level + 1) . ';';
                            }
                        }
                    }
                    return "<js>\n" . implode(" ", $result) . "\n</js>";
                }
            }
        }

        if (count($content) > 1) {
            if (is_object($content[1]) || (is_array($content[1]) && self::is_assoc($content[1]))) {
                foreach ($content[1] as $k => $v) {
                    $isJs = '';
                    if (is_array($v)) {
                        $v = $this->toJs($v);
                        $isJs = 'js: ';
                    }
                    if (strpos($v, '"') !== false) {
                        $attr[] = "$k='{$isJs}$v'";
                    } else {
                        $attr[] = "$k=\"{$isJs}$v\"";
                    }
                }
            }

            $hasChild2 = (count($content) == 3 && is_array($content[2]));
            $hasChild1 = (count($content) == 2 && is_array($content[1]) && !self::is_assoc($content[1]));
            if ($hasChild1 || $hasChild2) {
                $rawChild = $hasChild2 ? $content[2] : $content[1];

                $renderedChild = [];
                foreach ($rawChild as $c) {
                    $renderedChild[] = $this->renderComponentAsHtml($c, $level + 1);
                }
                $childStr = implode(" ", $renderedChild);
            }

            if (trim($childStr) === '' && is_string($content[1])) {
                $childStr = $content[1];
            }
        }

        if ($renderedTag == 'Page') {
            $renderedTag = $content[1]->{'[[name]]'};
            foreach ($attr as $k => $v) {
                if (strpos($v, '[[name]]=') === 0) {
                    unset($attr[$k]);
                }
            }
        }
        $space = count($attr) > 0 ? ' ' : '';
        if (trim($renderedTag) === '') return '';

        return '<' . $renderedTag . $space . implode(' ', $attr) . '>' . $childStr . '</' . $renderedTag . '>';
    }

    public function &replaceTagInArray($findTag, $replaceWith, &$tags)
    {
        $result = false;
        if (self::is_assoc($tags)) {
            $tag = $tags;
            $tags = [];
            if (count($tag) == 2 && is_array($tag[1]) && self::is_assoc($tag[1])) {
                $tags = $tag[1];
            } else if (count($tag) == 3 && is_array($tag[2])) {
                $tags = $tag[2];
            }
        }

        foreach ($tags as $k => &$tag) {
            if ($findTag == $tag[0] ||
                ($tag[0] == 'Page' && $tag[1]->{'[[name]]'} == $findTag)) {
                array_splice($tags, $k, 1, [$replaceWith]);
                return $tags[$k];
            }

            if (count($tag) == 2) {
                if (is_array($tag[1]) && !self::is_assoc($tag[1])) {
                    $result = $this->replaceTagInArray($findTag, $replaceWith, $tag[1]);
                    if ($result) {
                        return $result;
                    }
                }
            }

            if (count($tag) == 3) {
                if (is_array($tag[2]) && !self::is_assoc($tag[2])) {
                    $result = $this->replaceTagInArray($findTag, $replaceWith, $tag[2]);
                    if ($result) {
                        return $result;
                    }
                }
            }
        }

        return $result;
    }

    public function &findTagInArray($findTag, &$tags)
    {
        $result = false;
        foreach ($tags as &$tag) {
            if ($findTag == $tag[0] ||
                ($tag[0] == 'Page' && $tag[1]->{'[[name]]'} == $findTag)) {
                return $tag;
            }

            if (count($tag) == 2) {
                if (is_array($tag[1]) && !self::is_assoc($tag[1])) {
                    $result = $this->findTagInArray($findTag, $tag[1]);
                    if ($result) {
                        return $result;
                    }
                }
            }

            if (count($tag) == 3) {
                if (is_array($tag[2]) && !self::is_assoc($tag[2])) {
                    $result = $this->findTagInArray($findTag, $tag[2]);
                    if ($result) {
                        return $result;
                    }
                }
            }
        }

        return $result;
    }

    public function postRender($props, $childStr, $instanceIdx, $childArray)
    {
        return $childStr;
    }

    public function convertJsToArray($js, $replaceWith = [])
    {
        $keyword = array_keys($replaceWith);
        $keywordregex = str_replace("~~~~", ")|(", preg_quote(implode('~~~~', $keyword)));
        $arr = preg_split("[($keywordregex)]", $js, null, PREG_SPLIT_DELIM_CAPTURE);

        $result = [''];
        foreach ($arr as $a => $b) {
            if (trim($b) == '') continue;

            foreach ($replaceWith as $k => $v) {
                if (trim($b) === trim($k)) {
                    $arr[$a] = is_array($v) ? $v : trim("$v");
                }
            }

            $lastres = count($result) - 1;
            if (is_string($arr[$a]) && is_string($result[$lastres])) {
                $result[$lastres] .= $arr[$a];
            } else {
                $result[] = $arr[$a];
            }
        }

        return $result;
    }

}
