<?php

namespace Yard;

class Page
{
    use Page\Cache;
    use \Yard\Lib\JsConvert;

    public $conf;
    public $alias = "";
    public $base;
    public $store;
    public $masterpage = false;
    public $norender = false;
    public $placeholder = null;
    public $url = "";
    public $props = [];
    public $app = null;
    public $executePostRender = false;

    public $isRoot = false;
    public $showDeps = false;

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

    public function finalizeJs()
    {
    }

    public function css()
    {
    }

    public function render()
    {
    }

    public function postRender($props, $children, $instanceIndex)
    {
        return $children;
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
        $this->app = $this->base->settings;;

        $this->url = @$base->modules[$this->currentModule()]['url'];
        $this->conf = new Page\Configuration($this);
    }

    public function currentModule()
    {
        $class = explode('\\', get_class($this));
        if ($class[0] == 'Pages') {
            return '';
        } else {
            return $class[0];
        }
    }

    public function loadFile()
    {
        $files = func_get_args();
        $reflector = new \ReflectionClass(get_class($this));
        $dir = dirname($reflector->getFileName());
        $results = [];
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (realpath($path)) {
                ob_start();
                include($path);
                $results[] = ob_get_clean();
            } else {
                throw new \Exception("Failed to load file {$path} not found!");
            }
        }

        return implode("\n\n", $results);
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
        return $this->getCssCacheContent();
    }
}
