<?php
namespace Yard {
  class Renderer {
    function __construct($base) {
      if (!is_subclass_of($base,'\Yard\Base')) {
        throw new Exception ('base must be an instance of \Yard\base');
      }
    }

    public function render($page, $mode) {
      echo $page . $mode;
    }
  }
}