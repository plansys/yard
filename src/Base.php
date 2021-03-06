<?php

namespace Yard;

class Base
{
    use \Yard\Lib\ArrayTools;

    public $offline = false;
    public $host = '';
    public $dir = [
        'root' => '',
        'base' => '',
        'cache' => ''
    ];
    public $url = [
        'base' => '',
        'page' => '',
        'cache' => ''
    ];
    public $baseFile = '';
    public $settings = [];
    public $modules = [];
    public $pageNamespace = 'Pages\\';

    const PLANSYS_MODULES = ['db', 'ui', 'user', 'builder', 'jasper'];

    function __construct($baseFile = '')
    {
        if (!is_string($baseFile)) {
            throw new \Exception('baseFile must be a string');
        }

        if (!file_exists($baseFile)) {
            throw new \Exception('baseFile doesn\'t exists');
        }

        $this->baseFile = $baseFile;
        $conf = include($baseFile);

        if (!is_array($conf)) {
            throw new \Exception('Invalid baseFile');
        }

        $conf['dir'] = $this->validateDir(@$conf['dir']);
        $this->validateUrl(@$conf['url']);
        $this->validatePages(@$conf['modules']);

        foreach ($conf['url'] as $k => $url) {
            if (strpos($url, 'http') !== 0) {
                $conf['url'][$k] = substr($url, 0, 3) . str_replace("//", "/", substr($url, 3));
            } else {
                $conf['url'][$k] = substr($url, 0, 7) . str_replace("//", "/", substr($url, 7));
            }
        }

        if (isset($conf['offline'])) {
            $this->offline = $conf['offline'];
        }

        if (isset($conf['settings'])) {
            if (is_object($conf['settings'])) {
                $this->settings = $conf['settings'];
            } else if (!is_array($conf['settings'])) {
                throw new \Exception('Base Configuration Error, settings key must be an array!');
            } else {
                $app = new \StdClass();
                foreach ($conf['settings'] as $k => $v) {
                    $app->{$k} = $v;
                }

                $this->settings = $app;
            }
        }

        $this->host = $conf['host'];
        $this->name = @$conf['name'];
        $this->dir = $conf['dir'];
        $this->url = $conf['url'];
        $this->modules = $conf['modules'];

        if (!isset($this->dir['root'])) {
            $this->dir['root'] = dirname($this->baseFile);
        }


        # load yard modules pages
        $vurl = strtr($this->url['page'], [
            '[page]' => 'vendor...vendor'
        ]);
        if (strpos($vurl, '?') === false) {
            $vurl = $vurl . "?_v_dr=";
        } else {
            $vurl = $vurl . "&_v_dr=";
        }
        $d = DIRECTORY_SEPARATOR;
        $this->modules['yard'] = [
            'dir' => realpath(dirname(__FILE__) . $d . "..") . $d . 'pages',
            'url' => $vurl . "/plansys/yard/pages"
        ];

        # load plansys modules pages
        $this->loadPlansysModules();
    }

    private function loadPlansysModules()
    {
        foreach (self::PLANSYS_MODULES as $m) {
            $m = strtolower($m);
            $class = '\Plansys\\' . ucfirst($m) . '\Init';

            if (class_exists($class)) {
                $base = $class::getBase($this->host);
                $this->modules[$m] = $base;
            }
        }
    }

    public function isPage($tag)
    {
        $tag = str_replace(".", DIRECTORY_SEPARATOR, $tag);
        $tags = explode(":", $tag);
        $shortcut = '';
        if (count($tags) > 1) {
            $shortcut = array_shift($tags);
            $tag = implode(":", $tags);
        }
        $file = @$this->modules[$shortcut]['dir'] . DIRECTORY_SEPARATOR . $tag . '.php';

        if (is_file($file)) {
            $len = strlen(realpath(@$this->modules[$shortcut]['dir']) . DIRECTORY_SEPARATOR);
            $actualTag = str_replace(".php", "", substr(realpath($file), $len));

            return $actualTag == $tag;
        } else {
            return false;
        }
    }

    public function getRootUrl($module = '')
    {
        $url = $this->modules['']['url'];
        if (isset($this->modules[$module])) {
            $url = $this->modules[$module]['url'];
        }
        return $url;
    }

    public function newPage($alias, $isRoot = true, $showDeps = true)
    {
        $rp = $this->resolve($alias, false);
        if (!class_exists($rp['class'], false)) {
            require($rp['fullPath']);
        }
        if (!class_exists($rp['class'], false)) {
            throw new \Exception('Page ' . $alias . ' not found');
        }
        $new = new $rp['class']($alias, $isRoot, $showDeps, $this);

        if ($isRoot && is_string($new->masterpage)) {
            $master = null;
            $new->isRoot = false;

            $mp = $this->resolve($new->masterpage, false);
            if (!class_exists($mp['class'], false)) {
                require($mp['fullPath']);
            }
            if (!class_exists($mp['class'], false)) {
                throw new \Exception('Masterpage not found: ' . $new->masterpage);
            }

            $master = new $mp['class']($new->masterpage, true, $new, $this);

            $this->validateReduxStore($new, $master);

            return $master;
        }
        return $new;
    }

    public function renderUrl()
    {
        $pages = [];
        foreach ($this->modules as $k => $v) {
            if (isset($v['url'])) {
                $pages[$k] = $v['url'];        
            }
        }

        return [
            'base' => $this->url['base'],
            'page' => $this->url['page'],
            'pages' => $pages
        ];
    }

    public function getServerHost()
    {
        if (isset($_SERVER['HTTPS'])) {
            $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
        } else {
            $protocol = 'http';
        }
        return $protocol . "://" . $_SERVER['HTTP_HOST'];
    }

    public function resolve($alias, $returnAsString = true)
    {
        $parr = explode(":", $alias);
        if (count($parr) == 1 && isset($this->modules[''])) {
            $baseDir = $this->modules['']['dir'];
            $path = str_replace(".", DIRECTORY_SEPARATOR, $alias) . ".php";
            $class = str_replace(".", '\\', $alias);
            $shortcutNs = '';
        } elseif (count($parr) > 1) {
            if ($this->modules[$parr[0]]) {
                $baseDir = $this->modules[$parr[0]]['dir'];
                $path = str_replace(".", DIRECTORY_SEPARATOR, $parr[1]) . ".php";
                $class = str_replace(".", '\\', $parr[1]);
                $shortcutNs = $parr[0] . '\\';
            } else {
                throw new \Exception('Pages directory not found: ' . $parr[0]);
            }
        } else {
            throw new \Exception('Page not found: ' . $alias);
        }

        if (is_file($baseDir . DIRECTORY_SEPARATOR . $path)) {
            if (!$returnAsString) {
                return [
                    'class' => '\\' . $shortcutNs . trim($this->pageNamespace, '\\') . '\\' . $class,
                    'path' => $path,
                    'baseDir' => $baseDir,
                    'fullPath' => $baseDir . DIRECTORY_SEPARATOR . $path
                ];
            }
            return $baseDir . DIRECTORY_SEPARATOR . $path;
        } else {
            throw new \Exception('File not found for Page `' . $alias . '`: ' . $baseDir . DIRECTORY_SEPARATOR . $path);
        }
    }

    private function validateReduxStore($new, $master)
    {
        if (is_array($new->store) && is_array($master->store) && !empty($new->store)) {
            $ms = [];

            foreach ($master->store as $raws) {
                $ss = explode(".", $raws);
                if (!isset($ms[$ss[0]])) {
                    $ms[$ss[0]] = [];
                }

                $ms[$ss[0]][$ss[1]] = $raws;
            }

            foreach ($new->store as $s) {
                $ss = explode(".", $s);

                if (!isset($ms[$ss[0]][$ss[1]])) {
                    if (!isset($ms[$ss[0]]['*'])) {
                        throw new \Exception("Store {$s} is not declared in Page: " . $master->alias . '. You should add `public $store = [\'' . $ss[0] . '.*\'];` in ' . $master->alias . '.php ');
                    }
                }
            }
        }
    }

    private function validatePages($pages)
    {
        if (is_array($pages)) {
            if (!self::is_assoc($pages)) {
                throw new \Exception('Pages should be in [key=>value] format');
            }
        }
    }

    private function validateDir($dir)
    {
        if (!is_array($dir)) {
            throw new \Exception('Failed to instantiate a base: dir key is not an array!');
        }

        foreach ($this->dir as $k => $d) {
            if (!isset($dir[$k])) {
                throw new \Exception("Failed to instantiate a base: dir.{$k} key is missing in dir");
            }
            $is = 'is_' . gettype($d);
            $isvalid = $is($dir[$k]);

            if ($k == 'base' && strpos($dir[$k], "http") === 0) {
                continue;
            }

            if (!$isvalid) {
                throw new \Exception("Failed to instantiate a base: dir.{$k} key is not a " . gettype($d));
            } else {
                if ($is == 'is_string') {
                    if (!is_dir($dir[$k])) {
                        if (!mkdir($dir[$k], 0777, true)) {
                            throw new \Exception("Failed to create directory: {$dir[$k]}");
                        }
                    }

                    $dir[$k] = realpath($dir[$k]);
                } elseif ($is == 'is_array') {
                    foreach ($dir[$k] as $kd => $dd) {
                        if (!is_dir($dd)) {
                            throw new \Exception("Failed to instantiate a base: {$dd} is not a directory");
                        }
                    }
                }
            }
        }

        return $dir;
    }

    private function validateUrl($dir)
    {
        if (!is_array($dir)) {
            throw new \Exception('Failed to instantiate a base: url key is not an array!');
        }

        foreach ($this->url as $k => $d) {
            if (!isset($dir[$k])) {
                throw new \Exception("Failed to instantiate a base: url.{$k} key is missing in url");
            }

            $is = 'is_' . gettype($d);
            $isvalid = $is($dir[$k]);
            if (!$isvalid) {
                throw new \Exception("Failed to instantiate a base: url.{$k} key is not a " . gettype($d));
            }
        }
    }
}
