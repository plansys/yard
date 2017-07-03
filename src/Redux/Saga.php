<?php

namespace Yard\Redux;

class Saga {
    public function includeDir($dir) {
        $rc = new \ReflectionClass($this);
        $dir = dirname($rc->getFileName()) . DIRECTORY_SEPARATOR . 'sagas';
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.js');
        
        $sagas = [];
        
        foreach ($files as $f) {
            $saga = explode(".js", basename($f))[0];
            $sagas[$saga] = file_get_contents($f);
        }

        return $sagas;
    }
}