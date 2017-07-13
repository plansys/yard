<?php

namespace Yard\Page;

trait Cache
{
    public function generateCSS($css)
    {
        if (trim($css) == '') {
            return [
                'file' => '',
                'glob' => '',
                'css' => '',
                'hash' => 'false'
            ];
        }

        $info = $this->getCssInfo($css);
        
        if (!is_file($info['file'])) {
            $this->cleanCssCache($info['glob']);
            file_put_contents($info['file'], $css);
        }

        return $info;
    }

    public function getCssCacheContent()
    {
        $prefix = ".css-";
        $alias = str_replace(":", "~", $this->alias);
        $d = DIRECTORY_SEPARATOR;
        $glob = glob($this->base->dir['cache'] . $d . $alias. $prefix . "*.css");

        if (!empty($glob)) {
            foreach ($glob as $f) {
                return file_get_contents($f);
            }
        }

        $info = $this->generateCSS($this->css());

        if ($info['file'] != '') {
            return $info['css'];
        }

        return $info['file'];
    }

    private function getCssInfo($css)
    {
        $hash = crc32($css);
        $prefix = ".css-";
        $alias = str_replace(":", "~", $this->alias);
        $d = DIRECTORY_SEPARATOR;
        $file = $this->base->dir['cache'] . $d . $alias. $prefix . $hash . ".css";
        $file = str_replace("/", DIRECTORY_SEPARATOR, $file);

        return [
            'file' => $file,
            'glob' => $this->base->dir['cache'] . $d . $alias. $prefix . '*.css',
            'hash' => $hash,
            'css' => $css
        ];
    }

    private function cleanCssCache($globPattern)
    {
        $glob = glob($globPattern);
        if (count($glob) > 0) {
            foreach ($glob as $f) {
                unlink($f);
            }
        }
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
    private $_cacheHash;
    private $_cacheUrl;
    private $_conf;
    public function getCacheFile($useCache = true)
    {
        if ($this->_cacheFile && $useCache) {
            return $this->_cacheFile;
        }

        $this->_conf = $this->renderConf();

        $hash = crc32($this->_conf);
        $prefix = ".conf-" . ($this->isRoot ? "root-" : "");
        $alias = str_replace(":", "~", $this->alias);
        $d = DIRECTORY_SEPARATOR;
        $this->_cacheFile = $this->base->dir['cache'] . $d . $alias. $prefix . $hash . ".js";
        $this->_cacheFile = str_replace("/", DIRECTORY_SEPARATOR, $this->_cacheFile);
        $this->_cacheUrl = strtr($this->base->url['cache'], [
            '[file]' => $alias. $prefix . $hash . ".js"
        ]);
        $this->_cacheHash = $hash;
        return $this->_cacheFile;
    }

    public function getCacheUrl()
    {
        if (!$this->_cacheUrl) {
            $this->getCacheFile();
        }

        return $this->_cacheUrl;
    }

    public function getCacheHash()
    {
        if (!$this->_cacheHash) {
            $this->getCacheFile();
        }

        return $this->_cacheHash;
    }
}
