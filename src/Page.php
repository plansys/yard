<?php

namespace Yard;

class Page
{
    use Page\Cache;
    use \Yard\Lib\JsConvert;
    use \Yard\Lib\PostRender;

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

    public function propTypes()
    {
    }

    function __construct($alias, $isRoot, $showDeps, $base)
    {
        $this->alias = $alias;
        $this->isRoot = $isRoot;
        $this->showDeps = $showDeps;
        $this->base = $base;
        $this->app = $this->base->settings;

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

    public function path()
    {
        $class = get_class($this);
        $class = substr($class, strpos($class, '\Pages') + 6);
        $path = explode('\\', $class);
        array_pop($path);
        return implode('/', $path);
    }

    public function absolutePath($abs = false)
    {
        $moduledir = str_replace('\\', '/', $this->base->modules[$this->currentModule()]['dir']);
        if ($moduledir[strlen($moduledir) - 1] !== '/') {
            $moduledir .= '/';
        }

        $path = trim($this->path(), '/');
        if ($path == '/') {
            $path = '';
        }

        $result = $moduledir . $path;

        if ($result[strlen($result) - 1] === '/') {
            $result = substr($result, 0, strlen($result) - 1);
        }

        return $result;
    }

    public function resolveFile($file)
    {
        $file = str_replace("\\", "/", $file);
        $filearr = explode('/', $file);
        $dir = $this->absolutePath();
        $dirarr = explode('/', str_replace("\\", '/', $dir));

        if (strpos($file, '..') === 0) {
            $newfilearr = [];
            foreach ($filearr as $f) {
                if ($f == '..' && count($dirarr) > 0) array_pop($dirarr);
                else $newfilearr[] = $f;
            }
            $file = implode(DIRECTORY_SEPARATOR, $newfilearr);
            if ($file[0] != DIRECTORY_SEPARATOR) {
                $file = DIRECTORY_SEPARATOR . $file;
            }

            return implode(DIRECTORY_SEPARATOR, $dirarr) . $file;
        } else {
            return $dir . '/' . $file;
        }
    }

    public function loadFile()
    {
        $files = func_get_args();
        $results = [];
        foreach ($files as $file) {
            $path = $this->resolveFile($file);
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
