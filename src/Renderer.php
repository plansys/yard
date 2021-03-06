<?php

namespace Yard;

class Renderer
{
    use \Yard\Lib\ArrayTools;

    private $base;
    private $page;

    function __construct($base)
    {
        if (!($base instanceof Base)) {
            throw new \Exception ('$base must be an instance of \Yard\Base');
        }

        $this->base = $base;
    }

    public function render($rawAlias)
    {
        if (strpos($rawAlias, '::') !== false) {
            $mode = 'api';
            $aliasarr = explode("::", $rawAlias);
        } else {
            $aliasarr = explode("...", $rawAlias);
            $mode = count($aliasarr) > 1 ? $aliasarr[1] : 'html';
        }

        $isRoot = false;
        if (strpos($mode, '.') !== false) {
            $modearr = explode(".", $mode);
            if (count($modearr) > 1) {
                $mode = $modearr[1];
                $isRoot = true;
            }
        }

        $alias = $aliasarr[0];

        if ($mode != 'vendor') {
            $this->page = $this->base->newPage($alias, $isRoot);
        }

        switch ($mode) {
            case "api":
                $api = 'api' . ucfirst(strtolower($aliasarr[1]));
                if (!method_exists($this->page, $api)) {
                    return "Method does not exists";
                }

                $params = array_merge($_GET, $_POST);
                $post = json_decode(file_get_contents("php://input"), true);
                if (is_array($post)) {
                    $params = array_merge($params, $post);
                }

                $output = $this->page->{$api}($params);
                return is_string($output) ? $output : json_encode($output);
                break;
            case "html":
                if ($this->page->library == false) {
                    $this->renderHTML();
                }
                break;
            case "css":
                $this->renderCSS();
                break;
            case "js":
                $cache = $this->page->getCacheFile();
                if (is_file($cache)) {
                    echo file_get_contents($cache);
                } else {
                    $post = file_get_contents("php://input");
                    $this->renderJS();
                }
                break;
            case "jsdev":
                $post = file_get_contents("php://input");
                $this->renderJS();
                break;
            case "post":
                $post = file_get_contents("php://input");
                $this->page->updateCache($post);
                break;
            case "sw":
                $swjs = @file_get_contents($this->base->dir['base'] . '/service-worker.js');
                $start = strpos($swjs, 'var precacheConfig=') + strlen('var precacheConfig=');
                $stop = strpos($swjs, ',cacheName="');
                $swtxt = substr($swjs, $start, $stop - $start);
                $sw = json_decode($swtxt, true);
                $files = $this->page->getServiceWorkerFiles();
                if (is_array($sw)) {
                    foreach ($sw as $k => $s) {
                        $sw[$k][0] = $this->base->url['base'] . '/' . $s[0];
                    }
                    $sw = array_merge($sw, $files);

                    $swjs = str_replace($swtxt, json_encode($sw), $swjs);
                }
                echo $swjs;
                break;
            case "vendor":
                if (isset($_GET['_v_dr'])) {
                    $d = DIRECTORY_SEPARATOR;
                    $dir = realpath(dirname(__FILE__) . "{$d}..{$d}..{$d}..{$d}");

                    if (is_file($dir . $_GET['_v_dr'])) {
                        $info = new \SplFileInfo($dir . $_GET['_v_dr']);
                        if ($info->getExtension() != "php") {
                            $file = file_get_contents($dir . $d . $_GET['_v_dr']);
                            header("Content-Type:" . mime_content_type($dir . $d . $_GET['_v_dr']));
                            echo $file;
                            die();
                        }
                    }
                }
                break;
        }
    }

    public function renderHTML()
    {
        $d = DIRECTORY_SEPARATOR;
        $path = $this->base->dir['base'] . "{$d}index.html";

        if (strpos($this->base->dir['base'], 'http') === 0) {
            $path = $this->base->dir['base'] . "/index.html";
        } else {
            if (!is_file($path)) {
                throw new \Exception("Base file not found: " . $path);
            }
        }

        $baseUrl = $this->base->url['base'] . '/';

        if (strpos($path, 'https:') === 0) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $path);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $html = curl_exec($ch);
            curl_close($ch);
        } else {
            $html = file_get_contents($path);
        }

        $html = strtr($html, [
            '[name]' => $this->base->name,
            'href="./' => 'href="' . $baseUrl,
            'src="./' => 'src="' . $baseUrl,
            'href="/' => 'href="' . $baseUrl,
            'src="/' => 'src="' . $baseUrl,
            'url(' => 'url(' . $baseUrl,
        ]);

        $first = substr($html, 0, strpos($html, '<script'));
        $last = substr($html, strlen($first));
        $html = $first . $this->page->renderInitJS() . $last;

        echo $html;
    }

    public function renderCSS()
    {
        echo $this->page->renderCSS();
    }

    public function renderJS()
    {
        echo $this->page->renderConf();
    }
}
