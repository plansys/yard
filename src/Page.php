<?php

namespace Yard;

class Page
{
    use Page\Cache;
    
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
