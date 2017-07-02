<?php

namespace Yard\Page;

class Dependency
{
    use \Yard\Lib\ArrayTools;
    use \Yard\Page\Renderer\Component;

    public static function print($page, $pageRender, $deps = false)
    {
        $tags = array_keys(self::parseTags($pageRender));
        $isRoot = false;
        if ($deps === false) {
            $isRoot = true;
            $deps = [
                'pages' => [],
                'elements' => [],
            ];
        }

        foreach ($tags as $tag) {
            if (strpos($tag, 'Page:') === 0) {
                $p = substr($tag, 5);
                if (strpos(trim($p), "js:") === 0) {
                    continue;
                }

                if (!isset($deps['pages'][$p])) {
                    if ($p != $page->alias) {
                        $np = $page->base->newPage($p, false, false);
                        $deps['pages'][$p] = $np;
                        $deps = Dependency::print($page, self::parseRender($np), $deps);
                    }
                }
            } else {
                if (!in_array($tag, $deps['elements'])) {
                    $deps['elements'][] = $tag;
                }
            }
        }

        if ($isRoot) {
            foreach ($deps['pages'] as $p => $v) {
                if ($p != $page->alias) {
                    $deps['pages'][$p] = "js:" . $v->renderConf() . "";
                }
            }
        }

        return $deps;
    }

   

    public static function addParsedFile($file, $hash, $array) {
        $trimmed = rtrim($file, '/');

        if ($file != $trimmed) {
            $array[] = [
                $trimmed,
                $hash
            ];
        }

        $array[] = [
            $file,
            $hash
        ];

        return $array;
    }

    public static function parseFileItem($base, $alias)
    {
        if (get_class($base) == 'Yard\Base') {
            $page = $base->newPage($alias);
        } else {
            $page = $base;
            $base = $page->base;
        }

        $files = [];
        $includejs = $page->includeJS();

        if (is_array($includejs)) {
            $host = $base->host;
            if (strpos($base->host, 'http') === false) {
                $host = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            }
            foreach($includejs as $js) {
                $path = $page->conf->getJSPath($js);
                $md5 = md5(file_get_contents($host . $path));
                $files = self::addParsedFile($path, $md5, $files);
            }
        }

        $files = self::addParsedFile(
                strtr($base->url['page'], [
                    "[page]" => $alias . '...js'
                ]), $page->getCacheHash(), $files);

        if (trim($page->css()) != "") {
            $files = self::addParsedFile(
                strtr($base->url['page'], [
                    "[page]" => $alias . '...css'
                ]), $page->getCacheHash(), $files);
        }
        return $files;
    }

     public static function parseRootFileItem($page, $alias)
    {
        $base = $page->base;
        $files = self::parseFileItem($page, $alias);

        $files = self::addParsedFile(
                strtr($base->url['page'], [
                    "[page]" => $alias
                ]), $page->getCacheHash(), $files);
                
        $files = self::addParsedFile(
                strtr($base->url['page'], [
                    "[page]" => $alias . '...r.js'
                ]), $page->getCacheHash(), $files);

        if (trim($page->css()) != "") {
            $files = self::addParsedFile(
                strtr($base->url['page'], [
                    "[page]" => $alias . '...r.css'
                ]), $page->getCacheHash(), $files);

        }
        return $files;
    }


    public static function parseFiles($page, $pageRender, $tags = false)
    {
        if ($tags === false) {
            $tag = $pageRender;
            $tags = [];
            if ($tag[0] == 'Page') {
                $tags[$tag[1]['name']] = self::parseFileItem($page->base, $tag[1]['name']);
            }
            if (count($tag) == 2 &&
              is_array($tag[1]) &&
              !self::is_assoc($tag[1])) {
                  $tags = self::parseFiles($page, $tag[1], $tags);
            } elseif (count($tag) == 3 &&
              is_array($tag[2]) &&
              !self::is_assoc($tag[2])) {
                $tags = self::parseFiles($page, $tag[2], $tags);
            }
        } else {
            foreach ($pageRender as $tag) {
                if ($tag[0] == 'Page') {
                    if (strpos(trim($tag[1]['name']), "js:") === 0) {
                        continue;
                    }
                    $tags[$tag[1]['name']] = self::parseFileItem($page->base, $tag[1]['name']);
                }

                if (count($tag) == 2 &&
                  is_array($tag[1]) &&
                  !self::is_assoc($tag[1])) {
                      $tags = self::parseFiles($page, $tag[1], $tags);
                } elseif (count($tag) == 3 &&
                  is_array($tag[2]) &&
                  !self::is_assoc($tag[2])) {
                    $tags = self::parseFiles($page, $tag[2], $tags);
                }
            }
        }

        return $tags;
    }

    private static function parseTags($pageRender, $tags = false)
    {
        if ($tags === false) {
            $tag = $pageRender;
            $tags = [];
            $tags[$tag[0]] = true;

            if (count($tag) == 2 &&
              is_array($tag[1]) &&
              !self::is_assoc($tag[1])) {
                  $tags = self::parseTags($tag[1], $tags);
            } elseif (count($tag) == 3 &&
              is_array($tag[2]) &&
              !self::is_assoc($tag[2])) {
                $tags = self::parseTags($tag[2], $tags);
            }
        } else {
            foreach ($pageRender as $tag) {
                if (!is_array($tag)) {
                    continue;
                }

                if ($tag[0] == 'Page') {
                    $tags["Page:" . $tag[1]['name']] = true;
                } else {
                    $tags[$tag[0]] = true;
                }

                if (count($tag) == 2 &&
                  is_array($tag[1]) &&
                  !self::is_assoc($tag[1])) {
                      $tags = self::parseTags($tag[1], $tags);
                } elseif (count($tag) == 3 &&
                  is_array($tag[2]) &&
                  !self::is_assoc($tag[2])) {
                      $tags = self::parseTags($tag[2], $tags);
                }
            }
        }

        return $tags;
    }
    
    public static function parseLoaders($pageRender, $tags = false)
    {

        if ($tags === false) {
            $tag = $pageRender;
            $tags = [];

            if ($tag[0] == 'Page') {
                $tags[] = [$tag[1]['name']];
            }

            if (count($tag) == 2 &&
              is_array($tag[1]) &&
              !self::is_assoc($tag[1])) {
                  $tags = self::parseLoaders($tag[1], $tags);
            } elseif (count($tag) == 3 &&
              is_array($tag[2]) &&
              !self::is_assoc($tag[2])) {
                $tags = self::parseLoaders($tag[2], $tags);
            }
        } else {
            foreach ($pageRender as $tag) {
                if ($tag[0] == 'Page') {
                    if (strpos(trim($tag[1]['name']), "js:") === 0) {
                        continue;
                    }

                    $tags[] = [$tag[1]['name']];
                }
                
                if (count($tag) == 2 &&
                  is_array($tag[1]) &&
                  !self::is_assoc($tag[1])) {
                      $tags = self::parseLoaders($tag[1], $tags);
                } elseif (count($tag) == 3 &&
                  is_array($tag[2]) &&
                  !self::is_assoc($tag[2])) {
                    $tags = self::parseLoaders($tag[2], $tags);
                }
            }
        }

        return $tags;
    }
}
