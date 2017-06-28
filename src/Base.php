<?php

namespace Yard;

class Base
{
    use \Yard\Lib\ArrayTools;
    
    public $offline = false;
    public $host = '';
    public $dir = [
        'base' => '',
        'cache' => '',
        'redux' => ''
    ];
    public $url = [
        'base' => '',
        'page' => '',
        'cache' => ''
    ];
    public $pages = [];
    public $pageNamespace = 'Pages\\';

    function __construct($settings = [])
    {
        $settings['dir'] = $this->validateDir(@$settings['dir']);
        $this->validateUrl(@$settings['url']);
        $this->validatePages(@$settings['pages']);

        foreach ($settings['url'] as $k=>$url) {
            if (strpos($url, 'http') !== 0) {
                $settings['url'][$k] = substr($url, 0, 3) . str_replace("//", "/", substr($url, 3));
            } else {
                $settings['url'][$k] = substr($url, 0, 7) . str_replace("//", "/", substr($url, 7));
            }
        }
        
        if (isset($settings['offline'])) {
            $this->offline = $settings['offline'];
        }
        

        $this->host = $settings['host'];
        $this->name = @$settings['name'];
        $this->dir = $settings['dir'];
        $this->url = $settings['url'];
        $this->pages = $settings['pages'];

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
        
        # load redux-builder if exists
        $builderReduxDir = dirname(__FILE__) . "{$d}..{$d}builder-redux{$d}pages" ; 
        if (is_dir($builderReduxDir)) {
            $this->pages['builder-redux'] = [
                'dir' => $builderReduxDir,
                'url' => $vurl . "/plansys/builder-redux/src/pages"
            ];
        }
    }
    
    public function isPage($tag) {
        $tag = str_replace("." , DIRECTORY_SEPARATOR, $tag);
        $tags = explode(":", $tag);
        $shortcut = '';
        if (count($tags) > 1) {
            $shortcut = array_shift($tags);
            $tag = implode(":", $tags);
        }
        
        if (is_file($this->pages[$shortcut]['dir'] . DIRECTORY_SEPARATOR . $tag . '.php')) {
            return true;
        } else {
            return false;
        }
    }
    
    public function getRootUrl($shortcut = '') {
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
        } elseif (count($parr) > 1) {
            if ($this->pages[$parr[0]]) {
                $baseDir = $this->pages[$parr[0]]['dir'];
                $path = str_replace(".", DIRECTORY_SEPARATOR, $parr[1]) . ".php";
                $class = str_replace(".", '\\', $parr[1]);
            } else {
                throw new \Exception('Pages directory not found: ' . $parr[0]);
            }
        } else {
              throw new \Exception('Page not found: ' . $alias);
        }

        if (is_file($baseDir . DIRECTORY_SEPARATOR . $path)) {
            if (!$returnAsString) {
                return [
                    'class' => '\\' . trim($this->pageNamespace, '\\') . '\\' . $class,
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
    
    private function validateReduxStore($new, $master) {
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
                        throw new \Exception("Store {$s} is not declared in Page: " . $master->alias . '. You should add `public $store = [\''.$ss[0].'.*\'];` in ' . $master->alias . '.php ');
                    }
                }
            }
            
        }
    }
    
    private function validatePages($pages) {
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
                } else if ($is == 'is_array') {
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
