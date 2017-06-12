<?php

namespace Yard;

class Page
{
    private $conf;
    public $alias = "";
    public $isRoot = false;
    public $showDeps = false;
    public $base;
    public $store;
    public $masterpage = false;
    public $placeholder = null;

    public function mapStore()
    {
        return [];
    }

    public function mapAction()
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

    function __construct($alias, $isRoot, $showDeps, $base)
    {
        $this->alias = $alias;
        $this->isRoot = $isRoot;
        $this->showDeps = $showDeps;
        $this->base = $base;

        $this->conf = new Page\Configuration($this);
    }

    public function updateCache($post)
    {
        $file = $this->getCacheFile(false);

        $alias = str_replace(":", "~", $this->alias);
        $prefix = ".conf-" . ($this->isRoot ? "root-" : "");
        $glob = glob($this->base->dir['cache'] . DIRECTORY_SEPARATOR . $alias. $prefix . "*.js");
        foreach ($glob as $g) {
            if ($g != $file) {
                unlink($g);
            }
        }

        file_put_contents($file, $post);
    }

    public function cleanCache() 
    {
        $prefix = ".conf-" . ($this->isRoot ? "root-" : "");
        $alias = str_replace(":", "~", $this->alias);
        $d = DIRECTORY_SEPARATOR;
        $glob = glob($this->base->dir['cache'] . $d . $alias.  "*.js");
        if (count($glob) > 0) {
            foreach ($glob as $f) {
                unlink($f);
            }
        }
    }

    private $_cacheFile;
    private $_cacheUrl;
    private $_conf;
    public function getCacheFile($useCache = true)
    {
        if ($this->_cacheFile && $useCache) {
            return $this->_cacheFile;
        }

        $this->_conf = $this->renderConf();
        $hash = md5($this->_conf);
        $prefix = ".conf-" . ($this->isRoot ? "root-" : "");
        $alias = str_replace(":", "~", $this->alias);
        $d = DIRECTORY_SEPARATOR;
        $this->_cacheFile = $this->base->dir['cache'] . $d . $alias. $prefix . $hash . ".js";
        $this->_cacheFile = str_replace("/", DIRECTORY_SEPARATOR, $this->_cacheFile);
        $this->_cacheUrl = strtr($this->base->url['cache'], [
            '[file]' => $alias. $prefix . $hash . ".js"
        ]);
        return $this->_cacheFile;
    }

    public function getCacheUrl() {
        return $this->_cacheUrl;
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
