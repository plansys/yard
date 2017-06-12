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

    public function render($alias, $mode = 'html')
    {
        $isRoot = strtok($mode, '|') == 'r';
        $mode = self::explode_last("|", $mode);
        $this->page = $this->base->newPage($alias, $isRoot);

        $content = "";
        switch ($mode) {
            case "html":
                $content = $this->renderHTML();
                break;
            case "css":
                header("Content-type: text/css");
                $content = $this->renderCSS();
                break;
            case "js":
                $cache = $this->page->getCacheFile();
                if (is_file($cache)) {
                    header("Location: " . $this->page->getCacheUrl());
                } else {
                    header("Content-type: text/javascript");
                    $content = $this->renderJS();
                }
                break;
            case "post":
                $post = file_get_contents("php://input");
                $this->page->updateCache($post);
                break;
            case "api": 
                break;
        }

        return $content;
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
        
        $baseUrl = $this->base->url['base'] .'/';
        $html = file_get_contents($path);
        $html = strtr($html, [
            '[name]' => $this->base->name,
            'href="/' => 'href="' . $baseUrl,
            'src="/' => 'src="' . $baseUrl,
            'href="./' => 'href="' . $baseUrl,
            'src="./' => 'src="' . $baseUrl,
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
