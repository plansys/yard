<?php

namespace Yard\Redux;

abstract class Reducer {
    abstract public function init();
    abstract public function reducers();
}