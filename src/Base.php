<?php

namespace Yard;

class Base
{
    use \Yard\Lib\ArrayTools;
    
    public $offline = false;
    public $dir = [
        'base' => '',
        'cache' => '',
        'pages' => [],
        'redux' => ''
    ];
    public $url = [
        'base' => '',
        'page' => '',
        'cache' => ''
    ];
    public $pageNamespace = 'Pages\\';

    function __construct($settings = [])
    {
        $this->validateDir(@$settings['dir']);
        $this->validateUrl(@$settings['url']);

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
        
        $this->name = @$settings['name'];
        $this->dir = $settings['dir'];
        $this->url = $settings['url'];

        if (is_array($settings['dir']['pages'])) {
            if (self::is_assoc($settings['dir']['pages'])) {
                $this->pages = $settings['dir']['pages'];
            } else {
                throw new \Exception('Pages directory should be in [key=>value] format');
            }
        } else {
            $this->pages = [''=>$settings['dir']['pages']];
        }

        $this->pages['yard'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Sample';
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
            return $master;
        }
        return $new;
    }

    public function renderUrl()
    {
        return [
            'base' => $this->url['base'],
            'page' => $this->url['page'],
            'root' => $this->url['root']
        ];
    }

    public function resolve($alias, $returnAsString = true)
    {
        $parr = explode(":", $alias);
    
        if (count($parr) == 1 && isset($this->pages[''])) {
              $baseDir = $this->pages[''];
              $path = str_replace(".", DIRECTORY_SEPARATOR, $alias) . ".php";
              $class = str_replace(".", '\\', $alias);
        } elseif (count($parr) > 1) {
            if ($this->pages[$parr[0]]) {
                $baseDir = $this->pages[$parr[0]];
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
                        throw new \Exception("Failed to instantiate a base: {$dir[$k]} is not a directory");
                    }
                } else if ($is == 'is_array') {
                    foreach ($dir[$k] as $kd => $dd) {
                        if (!is_dir($dd)) {
                            throw new \Exception("Failed to instantiate a base: {$dd} is not a directory");
                        }
                    }
                }
            }
        }
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
