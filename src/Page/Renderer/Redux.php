<?php

namespace Yard\Page\Renderer;

trait Redux
{
    private $_reducers = [];

    private function renderMapStore()
    {
        $mapstore = self::toJs($this->page->mapStore());
        if (trim($mapstore) == "[]") {
            return "";
        }

        return "return " . $mapstore;
    }

    private function renderMapAction()
    {
        $mapaction = self::toJs($this->page->mapAction());
        if (trim($mapaction) == "[]") {
            return "";
        }

        return "return " . $mapaction;
    }

    private function getReducers($loaders)
    {
        $storeList = [];
        foreach ($loaders as $l) {
            $page = $this->page->base->newPage($l, false, false);
            if (is_array($page->store)) {
                $storeList = array_merge($storeList, $page->store);
            }
        }

        if (is_array($this->page->store)) {
            $storeList = array_merge($this->page->store);
        }

        if (empty($storeList)) {
            return [];
        }

        $storeList = array_filter($storeList);

        if (count($this->_reducers) > 0) {
            return $this->_reducers;
        }

        $reducers = [];
        foreach ($storeList as $storeRaw) {
            $module = '';
            $storeRaw = strtolower($storeRaw);
            $moduleArr = explode(":", $storeRaw);
            if (count($moduleArr) > 1) {
                $module = $moduleArr[0];
                $storeRaw = $moduleArr[1];
            }
            $storeArr = explode(".", $storeRaw);
            $storePath = $this->page->base->pages[$module]['redux'];
            if (count($storeArr) > 1) {
                $reducer = array_pop($storeArr);
                $storePath = $this->page->base->pages[$module]['redux'] . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $storeArr);
            } else {
                $reducer = $storeRaw;
            }
            $modulePrefix = $module != '' ? $module . '.' : '';

            $reducer = strtr($reducer, [
                '.*' => '',
                '*' => '',
                '.' => DIRECTORY_SEPARATOR
            ]);
            $reducerPath = $storePath . DIRECTORY_SEPARATOR . $reducer;

            if (is_dir($reducerPath)) {
                $globPath = $reducerPath . DIRECTORY_SEPARATOR . '*Reducer.php';
                $glob = \Yard\Lib\FileTools::globRecursive($globPath);
                foreach ($glob as $file) {
                    if (is_dir($file)) {
                        continue;
                    }
                    $class = str_replace(".php", "", basename($file));
                    $reducer = str_replace("Reducer.php", "", basename($file));
                    $storePrefix = self::explode_first(basename($file), substr($file, strlen($this->page->base->pages[$module]['redux'])));

                    if (strlen($storePrefix) > 1) {
                        $sep = $storePrefix[0];
                        $storePrefix = trim($storePrefix, $sep);
                        $storePrefix = str_replace($sep, ".", $storePrefix) . ".";
                    } else {
                        $storePrefix = "";
                    }

                    if (!class_exists($class, false)) {
                        require($file);
                    }

                    $reducers[$modulePrefix . $storePrefix . $reducer] = [
                        'class' => new $class,
                        'file' => $file
                    ];
                }
            } else if (is_file($reducerPath . 'Reducer.php')) {
                $class = $reducer . 'Reducer';
                if (!class_exists($class, false)) {
                    require($reducerPath . 'Reducer.php');
                }
                $reducers[$modulePrefix . $storeRaw] = [
                    'class' => new $class,
                    'file' => $reducerPath . 'Reducer.php'
                ];
            } else {
                throw new \Exception ("Redux Store Reducer [{$storeRaw}] not found!");
            }
        }

        $this->_reducers = $reducers;
        return $reducers;
    }

    public function renderReduxActions($loaders)
    {
        $reducers = $this->getReducers($loaders);
        if (empty($reducers)) {
            return;
        }

        $declareList = function (&$listItem) {
            foreach ($listItem as $kr => $kv) {
                if (is_array($kv)) {
                    if (!isset($kv['params'])) {
                        $kv['params'] = "";
                    }

                    $script = isset($kv['script']) ? substr($kv['script'], 3) : '';

                    $return = self::toJs([
                        'type' => $kv['type'],
                        'payload' => @$kv['payload']
                    ]);

                    if (trim($script) != '') {
                        $script = "
                $script";
                    }

                    $listItem[$kr] = "js: function({$kv['params']}) {{$script}
                return {$return};
            }
            ";
                }
            }
        };

        $list = [];

        foreach ($reducers as $k => $r) {
            $glob = glob(dirname($r['file']) . DIRECTORY_SEPARATOR . "*Action.php");
            foreach ($glob as $file) {
                $class = str_replace(".php", "", basename($file));
                if (!class_exists($class, false)) {
                    require($file);
                }

                $item = new $class;

                if (!isset($list[$k])) {
                    $list[$k] = [];
                }
                $list[$k] = $item->actionCreators();
                $declareList($list[$k]);
            }
        }
        return "return " . self::toJs($list) . ";";
    }

    public function renderReduxReducers($loaders)
    {
        $reducers = $this->getReducers($loaders);
        if (empty($reducers)) {
            return;
        }

        $declareList = function ($r, &$listItem) {
            $listItem = [
                'import' => property_exists($r['class'], 'import') ? $r['class']->import : [],
                'init' => "js: function() { \n" . $r['class']->init() . "\n}",
                'reducers' => $r['class']->reducers()
            ];
        };

        $list = [];
        foreach ($reducers as $k => $r) {
            if (!isset($list[$k])) {
                $list[$k] = [];
            }
            $declareList($r, $list[$k]);
        }

        return "return " . self::toJs($list) . ";";
    }

    public function renderReduxSagas($loaders)
    {
        $reducers = $this->getReducers($loaders);
        if (empty($reducers)) {
            return null;
        }

        $list = [];
        foreach ($reducers as $k => $r) {
            $glob = glob(dirname($r['file']) . DIRECTORY_SEPARATOR . "*Saga.php");
            foreach ($glob as $file) {
                $class = str_replace(".php", "", basename($file));

                if (!class_exists($class, false)) {
                    require_once($file);
                }

                if (class_exists($class, false)) {
                    $item = new $class;

                    $sagas = $item->sagas();
                    foreach ($sagas as $ks => $vs) {
                        if (strpos(trim($vs), 'js:') !== 0) {
                            $sagas[$ks] = 'js:' . $sagas[$ks];
                        }
                    }

                    $list[$k] = $sagas;
                }
            }
        }

        return "return " . $this->toJs($list) . ";";
    }
}
