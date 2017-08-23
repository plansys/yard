<?php

namespace Yard\Lib;

class HSerializer extends \FluentDOM\Serializer\Json\JsonML
{
    public $base;

    /**
     * @param \DOMNode $node
     * @param int $options
     * @param int $depth
     */
    public function __construct($base, \DOMNode $node, $options = 0, $depth = 512)
    {
        parent::__construct($node, $options, $depth);
        $this->base = $base;
    }

    /**
     * @param \DOMElement $node
     * @return array
     */
    protected function getNode(\DOMElement $node)
    {
        $result = [
            $node->nodeName,
        ];

        $attributes = array_merge(
            $this->getNamespaces($node),
            $this->getAttributes($node)
        );

        if (!empty($attributes)) {
            $result[] = $attributes;
        }

        if ($this->base->isPage($result[0])) {
            $result = $this->getPage($result);
        }

        $childs = [];
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement) {
                $c = $this->getNode($childNode);
                if ($c[0] == 'js') {
                    $content = [];

                    foreach ($c[1] as $j) {
                        if ($j[0] == 'jstext') {
                            $content[] = $j[1];
                        } elseif ($j[0] == 'el') {

                            if (count($j[1]) > 1) {
                                $jc = ['div', []];
                                foreach ($j[1] as $jj) {
                                    $jc[1][] = $jj;
                                }
                                $content[] = $jc;
                            } else {
                                $content[] = $j[1][0];
                            }
                        }
                    }

                    $c = [$c[0], $content];
                }

                if ($this->base->isPage($c[0])) {
                    $c = $this->getPage($c);
                }

                if (is_array($childs)) {
                    $childs[] = $c;
                } else {
                    $childs = $c;
                }
            }

            if ($childNode instanceof \DOMText || $childNode instanceof \DOMCdataSection) {
                $c = $this->getValue($childNode->data);

                if (is_array($childs)) {
                    if (is_array($c)) {
                        $childs[] = $c;
                    } else {
                        if (trim($c) != '') {
                            $childs[] = ['jstext', $c];
                        }
                    }
                }
            }
        }
        $result[] = $childs;

        if (isset($result[1]['__postRender']) && is_object($result[1]['__postRender'])) {
            $htmlChild = $node->saveHtml();
            $htmlChild = preg_replace("/<\/?" . $result[1]['name'] . "[^>]*\>/i", "", $htmlChild);
            $postRenderedHtml = $result[1]['__postRender']->postRender($result[1], $htmlChild);
            if (is_string($postRenderedHtml) && trim($postRenderedHtml) != '') {
                $result[2] = [HtmlToJson::doConvert($this->base, $postRenderedHtml, true)];
            }

            unset($result[1]['__postRender']);
        }

        return $result;
    }

    /**
     * @param \DOMElement $node
     * @return array|NULL
     */
    private function getAttributes(\DOMElement $node)
    {
        $result = [];
        foreach ($node->attributes as $name => $attribute) {
            $result[$name] = $this->getValue($attribute->value);
        }
        return $result;
    }

    private function getPage($c)
    {
        $newc = ['Page', ['name' => $c[0]]];
        if (count($c) > 1) {
            if (isset($c[1][0])) {
                $newc[] = $c[1];
            } else {
                foreach ($c[1] as $k => $cc) {
                    $newc[1][$k] = $cc;
                }
                $newc[1]['name'] = $c[0];

                if (count($c) > 2) {
                    $newc[] = $c[2];
                }
            }
        }

        $page = $this->base->newPage($c[0]);

        if ($page->executePostRender) {
            $newc[1]['__postRender'] = $page;
        }

        return $newc;
    }

    /**
     * Get value prepared for Json data structure
     *
     * @param mixed $value
     * @return mixed
     */
    private function getValue($value)
    {
        if ($this->isBoolean($value)) {
            return (strtolower($value) === 'true');
        } elseif ($this->isInteger($value)) {
            return (int)$value;
        } elseif ($this->isNumber($value)) {
            return (float)$value;
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isInteger($value)
    {
        return (bool)preg_match('(^[1-9]\d*$)D', $value);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isNumber($value)
    {
        return (bool)preg_match('(^(?:\\d+\\.\\d+|[1-9]\d*)$)D', $value);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isBoolean($value)
    {
        return (bool)preg_match('(^(?:true|false)$)Di', $value);
    }
}
