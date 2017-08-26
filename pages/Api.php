<?php

namespace yard\Pages;

class Api extends \Yard\Page
{
    public $norender = true;

    public function js() {
        return $this->loadFile('ApiLib.js','Api.js');
    }

    public function css() {
        return $this->loadFile('Api.css');
    }

    public function propTypes() {
        return [
            'page' => 'string.isRequired',
            'tag' => 'string',
            'debug' => 'string',
            'params' => 'object',
            'onDone' => 'function',
        ];
    }

    public function render()
    {
        return $this->loadFile('Api.html');
    }
}
