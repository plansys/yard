<?php

namespace Yard;

class Page
{
    use Page\Cache;
    use \Yard\Lib\JsConvert;
    
    public $conf;
    public $alias = "";
    public $isRoot = false;
    public $showDeps = false;
    public $base;
    public $store;
    public $masterpage = false;
    public $norender = false;
    public $placeholder = null;
    public $url = "";
    public $props = [];

    public function mapStore()
    {
        return [];
    }

    public function mapAction()
    {
        return [];
    }

    public function includeJS()
    {
        return [];
    }

    public function js()
    {
    }

    public function css()
    {
    }

    public function render()
    {
    }

    public function propTypes()
    {
    }

    function __construct($alias, $isRoot, $showDeps, $base)
    {
        $this->alias = $alias;
        $this->isRoot = $isRoot;
        $this->showDeps = $showDeps;
        $this->base = $base;
        
        $this->url = @$base->pages['']['url'];
        $this->conf = new Page\Configuration($this);
    }
    
    public function loadFile($file)
    {
        $reflector = new \ReflectionClass(get_class($this));
        $dir =  dirname($reflector->getFileName());
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (realpath($path)) {
            return file_get_contents($path);
        } else {
            return "";
        }
    }

    public function urlFor($url)
    {
        return $this->url . $url;
    }

    public function getServiceWorkerFiles()
    {
        return $this->conf->getServiceWorkerFiles();
    }

    public function renderConf()
    {
        return $this->conf->render();
    }

    public function renderInitJS()
    {
        return $this->conf->renderInitJS();
    }
    
    public function renderCSS($indent = 0)
    {
        $css = $this->css();

        if (!$css) {
            return "";
        }

        $pad = "";
        if ($indent > 0) {
            $pad = str_pad("    ", ($indent) * 4);
        }
        $css = explode("\n", $css);
        $css = implode("\n" . $pad, $css);
        return $pad . $css;
    }
}
