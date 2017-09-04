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

    public function renderInitJs()
    {
        $page = $this->page;
        ob_start();
        include('template/initjs.php');
        return ob_get_clean();
    }

    public function render()
    {

        $indent = 1;
        $page = $this->page;
        $renderParsed = self::parseRender($page);
        $contents = $this->renderComponent($renderParsed);

        $cssInfo = $this->page->generateCSS($this->page->css());
        $css = $cssInfo['hash'];

        $js = $this->renderJS();

        $includeJS = $this->renderIncludeJS();

        $mapStore = $this->renderMapStore();
        $mapAction = $this->renderMapAction();

        $originalLoaders = Dependency::parseLoaders($renderParsed);
        $loaders = count($originalLoaders) > 0 ? self::toJs($originalLoaders) : '[]';

        $propTypes = $page->propTypes();
        if (is_array($propTypes)) {
            $propTypes = '    propTypes: ' . self::toJs($propTypes) . ",\n";
        }

        $deps = [];
        $placeholder = "";
        if ($page->isRoot || $page->showDeps) {
            $deps = Dependency::printPage($page, $renderParsed);

            if (!is_null($page->placeholder)) {
                $pdeps = Dependency::printPage($page->placeholder, self::parseRender($page->placeholder));
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
            $reducers = $this->renderReduxReducers($originalLoaders);
            $actionCreators = $this->renderReduxActions($originalLoaders);
            $sagas = $this->renderReduxSagas($originalLoaders);
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
        $finalizedJs = $this->page->finalizeJs($js);

        if (!is_null($finalizedJs)) {
            $js = $finalizedJs;
        }

        $js = explode("\n", $js);
        $js = implode("\n" . $pad, $js);
        $js = trim($js) . "\n";

        return $js;
    }

    public function getJSUrl($js, $module = '')
    {
        if (strpos($js, 'http') !== 0) {
            $moduleDir = $this->page->base->getRootUrl($module);
            if ($moduleDir[strlen($moduleDir) - 1] != '/') {
                $moduleDir .= '/';
            }

            $path = $this->page->path();
            if (strpos($js, '/') === 0) {
                $path = '';
            } else if (strpos($js, '..') === 0) {
                $patharr = explode('/', trim($path));
                $jsarr = explode('/', $js);
                $newjsarr = [];
                foreach ($jsarr as $j) {
                    if ($j == '..' && count($patharr) > 0) array_pop($patharr);
                    else $newjsarr[] = $j;
                }

                $js = implode('/', $newjsarr);
                $path = implode('/', $patharr);
            }

            if (strlen($path) == 0 || $path[strlen($path) - 1] != '/') {
                $path .= '/';
            }

            $return = array_merge(explode('/', $moduleDir),
                explode('/', $path),
                explode('/', $js));
                $return = array_filter($return);

            return '/' . implode('/', $return);
        }
        return $js;
    }

    private function renderIncludeJS()
    {
        $js = $this->page->includeJS();

        if (is_array($js) && !empty($js)) {
            foreach ($js as $k => $v) {
                $js[$k] = $this->getJSUrl($v, $this->page->currentModule());
            }

            return $this->toJs($js);
        } else {
            return "";
        }
    }

    public function getServiceWorkerFiles()
    {
        $files = [];
        $pageFiles = [];

        if ($this->page->masterpage != '') {
            $master = $this->page->base->newPage($this->page->masterpage);
            $pageFiles = Dependency::parseFiles($master, self::parseRender($master));
            array_unshift($pageFiles, Dependency::parseRootFileItem($master, $master->alias));
        }

        $pageFiles = array_merge($pageFiles, Dependency::parseFiles($this->page, self::parseRender($this->page)));
        array_unshift($pageFiles, Dependency::parseRootFileItem($this->page, $this->page->alias));
        foreach ($pageFiles as $pfs) {
            foreach ($pfs as $f) {
                $files[] = $f;
            }
        }

        return $files;
    }
}
