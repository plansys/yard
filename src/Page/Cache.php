<?php

namespace Yard\Page;

trait Cache {
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
        $hash = md5($this->_conf);
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

    public function getCacheHash() {
        if (!$this->_cacheHash) {
            $this->getCacheFile();
        }

        return $this->_cacheHash;
    }
}