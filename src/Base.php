<?php

namespace Yard {
  class Base {
    function __construct($setting = []) {
      if (!isset($setting['html'])) {
        throw new Exception('html key should be provided to create a base');
      }
      if (!isset($setting['pages'])) {
        throw new Exception('pages key should be provided to create a base');
      }
      if (!isset($setting['redux'])) {
        throw new Exception('redux key should be provided to create a base');
      }

      if (!is_dir($setting['pages'])) {
        throw new Exception("Directory {$setting['pages']} does not exists!");
      }
      
      if (!is_dir($setting['redux'])) {
        throw new Exception("Directory {$setting['redux']} does not exists!");
      }
      
      if (!is_file($setting['html'])) {
        throw new Exception("File {$setting['html']} does not exists!");
      }
    }
  }
}