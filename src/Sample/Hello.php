<?php

namespace Pages;

class Hello extends \Yard\Page
{

    public function render()
    {
        return <<<HTML
    <div> Hello Yard!</div>
HTML;
    }
}
