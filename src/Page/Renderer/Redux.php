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
    private function getReducers()
    {
        if (!is_array($this->page->store)) {
            return [];
        }
        
        if (count($this->_reducers) > 0) {
            return $this->_reducers;
        }
        
        $reducers = [];
        foreach ($this->page->store as $storeRaw) {
            $store = self::explode_first(".", $storeRaw);
            $reducer = self::explode_last(".", $storeRaw);
            $storepath = $this->page->base->dir['redux'] . DIRECTORY_SEPARATOR . $store;
            
            if ($reducer != "*") {
                $reducerPath = $storepath . DIRECTORY_SEPARATOR . $reducer . 'Reducer.php';
                if (is_file($reducerPath)) {
                    $class = $reducer . 'Reducer';
                    if (!class_exists($class, false)) {
                        require($reducerPath);
                    }
                    $reducers[strtolower($reducer)] = [
                        'class' => new $class,
                        'file' => $reducerPath,
                        'store' => $store
                    ];
                } else {
                    throw new \Exception ("Redux Store Reducer [{$store}.{$reducer}] not found in {$reducerPath}!");
                }
            } else {
                $reducerPath = $storepath . DIRECTORY_SEPARATOR . '*Reducer.php';
                $glob = \Yard\Lib\FileTools::globRecursive($reducerPath);
                foreach ($glob as $file) {
                    if (is_dir($file)) {
                        continue;
                    }
                    $class = str_replace(".php", "", basename($file));
                    $reducer = str_replace("Reducer.php", "", basename($file));

                    if (!class_exists($class, false)) {
                        require($file);
                    }

                    $reducers[strtolower($reducer)] = [
                        'class' => new $class,
                        'file' => $file,
                        'store' => $store
                    ];
                }
            }
        }

        $this->_reducers = $reducers;
        return $reducers;
    }

    public function renderReduxActions()
    {
        $reducers = $this->getReducers();
        if (empty($reducers)) {
            return;
        }

        $list = [];
        foreach ($reducers as $k => $r) {
            $glob = glob(dirname($r['file']) . DIRECTORY_SEPARATOR . "*Action.php");
            foreach ($glob as $file) {
                $class = str_replace(".php", "", basename($file));
                if (!class_exists($class, false)) {
                    require($file);
                }

                $item = new $class;
                
                if (!isset($actions[$r['store']])) {
                    $list[$r['store']] = [];
                }
                $list[$r['store']][$k] = $item->actionCreators();
                $res = &$list[$r['store']][$k];
                foreach ($res as $kr => $kv) {
                    if (is_array($kv)) {
                        if (!isset($kv['params'])) {
                            $kv['params'] = "";
                        }

                        $script = isset($kv['script']) ? substr($kv['script'], 3) : '';

                        $return = self::toJs([
                            'type' => $kv['type'],
                            'payload' => @$kv['payload']
                        ]);
                        $res[$kr] = "js: function({$kv['params']}) {
                {$script}
                return {$return};
            }";
                    }
                }
            }
        }

        return "return " . self::toJs($list) . ";";
    }

    public function renderReduxReducers()
    {
        $reducers = $this->getReducers();
        if (empty($reducers)) {
            return;
        }

        $list = [];
        foreach ($reducers as $k => $r) {
            if (!isset($list[$r['store']])) {
                $list[$r['store']] = [];
            }
            
            $list[$r['store']][$k] = [
                'import' => property_exists($r['class'], 'import') ? $r['class']->import : [],
                'init' => 'js:' . $r['class']->init(),
                'reducers' => $r['class']->reducers()
            ];
        }

        return "return " . self::toJs($list) . ";";
    }

    public function renderReduxSagas()
    {
        $reducers = $this->getReducers();
        if (empty($reducers)) {
            return;
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
                    
                    if (!isset($actions[$r['store']])) {
                        $list[$r['store']] = [];
                    }
                    $list[$r['store']][$k] = $item->sagas();
                }
            }
        }

        return "return " . $this->toJs($list) . ";";
    }
}
