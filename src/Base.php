<?php

namespace Yard;

class Base
{
    use \Yard\Lib\ArrayTools;

    public $offline = false;
    public $host = '';
    public $dir = [
        'base' => '',
        'cache' => ''
    ];
    public $url = [
        'base' => '',
        'page' => '',
        'cache' => ''
    ];
    public $settings = [];
    public $pages = [];
    public $pageNamespace = 'Pages\\';

    function __construct($conf = [])
    {
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
        $this->pages = $conf['modules'];

        $d = DIRECTORY_SEPARATOR;

        # load sample yard pages
        $vurl = strtr($this->url['page'], [
            '[page]' => 'vendor...vendor'
        ]);

        if (strpos($vurl, '?') === false) {
            $vurl = $vurl . "?_v_dr=";
        } else {
            $vurl = $vurl . "&_v_dr=";
        }

        $this->pages['yard'] = [
            'dir' => dirname(__FILE__) . $d . 'Sample',
            'url' => $vurl . "/plansys/yard/src/Sample"
        ];

        # load db if exists
        if (class_exists('\Plansys\Db\Init')) {
            $base = \Plansys\Db\Init::getBase($this->host);
            $this->pages['db'] = $base;
        }

        # load ui if exists
        if (class_exists('\Plansys\Ui\Init')) {
            $base = \Plansys\Ui\Init::getBase($this->host);
            $this->pages['ui'] = $base;
        }

        # load user if exists
        if (class_exists('\Plansys\User\Init')) {
            $base = \Plansys\User\Init::getBase($this->host);
            $this->pages['user'] = $base;
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
        $file = @$this->pages[$shortcut]['dir'] . DIRECTORY_SEPARATOR . $tag . '.php';

        if (is_file($file)) {
            $len = strlen(realpath(@$this->pages[$shortcut]['dir']) . DIRECTORY_SEPARATOR);
            $actualTag = str_replace(".php", "", substr(realpath($file), $len));

            return $actualTag == $tag;
        } else {
            return false;
        }
    }

    public function getRootUrl($shortcut = '')
    {
        $url = $this->pages['']['url'];
        if (isset($this->pages[$shortcut])) {
            $url = $this->pages[$shortcut]['url'];
        }
        return $url;
    }

    public function newPage($alias, $isRoot = true, $showDeps = true)
    {
        $rp = $this->resolve($alias, false);
        if (!class_exists($rp['class'], false)) {
            require($rp['fullPath']);
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
        foreach ($this->pages as $k => $v) {
            $pages[$k] = $v['url'];
        }

        return [
            'base' => $this->url['base'],
            'page' => $this->url['page'],
            'pages' => $pages
        ];
    }

    public function resolve($alias, $returnAsString = true)
    {
        $parr = explode(":", $alias);
        if (count($parr) == 1 && isset($this->pages[''])) {
            $baseDir = $this->pages['']['dir'];
            $path = str_replace(".", DIRECTORY_SEPARATOR, $alias) . ".php";
            $class = str_replace(".", '\\', $alias);
            $shortcutNs = '';
        } elseif (count($parr) > 1) {
            if ($this->pages[$parr[0]]) {
                $baseDir = $this->pages[$parr[0]]['dir'];
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
        if (!empty($new->store)) {
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
