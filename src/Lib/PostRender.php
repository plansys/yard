<?php

namespace Yard\Lib;

trait PostRender
{
    public function replaceTagContent($tag, $content, $func, $retainOriginalTags = false)
    {
        $regex = '/(<' . $tag . '[^>]*>)(.*?)(<\/' . $tag . '>)/i';
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

    public function postRender($props, $childStr, $instanceIdx, $childArray)
    {
        return $childStr;
    }
}
