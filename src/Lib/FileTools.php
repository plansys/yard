<?php

namespace Yard\Lib;

class FileTools
{
    public static function globRecursive($pattern, $flags = 0, $returnCount = false, $count = 0)
    {
        $files = glob($pattern, $flags);
        natsort($files);
        if ($returnCount) {
            $count = count($files);
        }
        $dirs = glob(dirname($pattern) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        $dirs = array_reverse($dirs);
        
        foreach ($dirs as $dir) {
            array_unshift($files, str_replace('/', DIRECTORY_SEPARATOR, $dir));
            $recureGlob = self::globRecursive($dir . DIRECTORY_SEPARATOR . basename($pattern), $flags, $returnCount);
            if ($returnCount) {
                $files = array_merge($files, $recureGlob['files']);
                $count--;
                $count += $recureGlob['count'];
            } else {
                $files = array_merge($files, $recureGlob);
            }
        }

        if ($returnCount) {
            return ['files' => $files, 'count' => $count];
        } else {
            return $files;
        }
    }
}
