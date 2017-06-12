<?php

namespace Yard\Lib;

class HSerializer extends \FluentDOM\Serializer\Json\JsonML
{
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

        $childs = [];
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement) {
                $c = $this->getNode($childNode);
                if ($c[0] == 'js') {
                    $content = [];

                    foreach ($c[1] as $j) {
                        if ($j[0] == 'span') {
                            $content[] = $j[1];
                        } elseif ($j[0] == 'el') {
                            foreach ($j[1] as $jj) {
                                $content[] = $jj;
                            }
                        }
                    }

                    $c = [$c[0], $content];
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
                            $childs[] = ['span', $c];
                        }
                    }
                }
            }
        }
        $result[] = $childs;

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
            return (int) $value;
        } elseif ($this->isNumber($value)) {
            return (float) $value;
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isInteger($value)
    {
        return (bool) preg_match('(^[1-9]\d*$)D', $value);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isNumber($value)
    {
        return (bool) preg_match('(^(?:\\d+\\.\\d+|[1-9]\d*)$)D', $value);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function isBoolean($value)
    {
        return (bool) preg_match('(^(?:true|false)$)Di', $value);
    }
}
