<?php

namespace Yard\Page;

class Configuration
{
    use \Yard\Lib\JsConvert;
    use \Yard\Page\Renderer\Component;
    use \Yard\Page\Renderer\Redux;
  
    public $page;
    public $template = 'template/conf.php';
  
    function __construct($page)
    {
        $this->page = $page;
    }

    public function renderInitJs() {
        $page = $this->page;
        ob_start();
        include('template/initjs.php');
        return ob_get_clean();
    }

    public function render($indent = 1)
    {
        $page = $this->page;
        $renderParsed = self::parseRender($page);
        $contents = $this->renderComponent($renderParsed);

        $css = trim($this->page->css() == "") ? "false" : "true";
        $js = $this->renderJS();
        
        $includeJS = $this->renderIncludeJS();

        $mapStore = $this->renderMapStore();
        $mapAction = $this->renderMapAction();

        $loaders = Dependency::parseLoaders($renderParsed);
        $loaders = count($loaders) > 0 ? self::toJs($loaders) : '[]';

        $deps = [];
        $placeholder = "";
        if ($page->isRoot || $page->showDeps) {
            $deps = Dependency::print($page, $renderParsed);
      
            if (!is_null($page->placeholder)) {
                $pdeps = Dependency::print($page->placeholder, self::parseRender($page->placeholder));
                foreach ($pdeps['pages'] as $k => $p) {
                    if (!isset($deps['pages'][$k])) {
                        $deps['pages'][$k] = $p;
                    }
                }
                foreach ($pdeps['elements'] as $k => $p) {
                    if (!in_array($p, $deps['elements'])) {
                        $deps['elements'][] = $p;
                    }
                }
                $deps['pages'][$page->placeholder->alias] = 'js:' . $page->placeholder->renderConf();
                $placeholder = json_encode($page->placeholder->alias);
            }
        }
        $deps = self::toJs($deps);


        if ($page->isRoot) {
            $reducers = $this->renderReduxReducers();
            $actionCreators = $this->renderReduxActions();
            $sagas = $this->renderReduxSagas();
        }

        ## load conf template
        ob_start();
        include "template/conf.php";
        $conf = ob_get_clean();

        $pad = "";
        if ($indent > 0) {
            $pad = str_pad("    ", ($indent) * 4);
        }
        $conf = explode("\n", $conf);
        $conf = implode("\n" . $pad, $conf);
        
        return trim($conf);
    }

    private function renderJS($indent = 0)
    {
        $pad = "";
        if ($indent > 0) {
            $pad = str_pad("    ", ($indent) * 4);
        }

        $js = $this->page->js();
        $js = explode("\n", $js);
        $js = implode("\n" . $pad, $js);
        return trim($js) . "\n";
    }
    
    private function renderIncludeJS() {
        $js = $this->page->includeJS();
        
        if (is_array($js) && !empty($js)) {
            foreach ($js as $k => $v) {
                if (strpos($v, 'http') !== 0) {
                    $ex = explode(":", $v);
                    if (count($ex) > 1) {
                        $js[$k] = $this->page->base->getRootUrl($ex[0]) . '/' . $ex[1];
                    }
                }
            }
            
            return $this->toJs($js);
        } else {
            return "";
        }
    }

    public function getServiceWorkerFiles() {
        $files = [];
        $pageFiles = Dependency::parseFiles($this->page, self::parseRender($this->page));
        array_unshift($pageFiles, Dependency::parseRootFileItem($this->page, $this->page->alias));
        foreach ($pageFiles as $pfs) {
            foreach ($pfs as $f) {
                $files[] = $f;
            }
        }
        return $files;
    }
}
