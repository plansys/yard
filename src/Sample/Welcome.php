<?php

namespace yard\Pages;

class Welcome extends \Yard\Page
{
    
    public function render()
    {
        return <<<HTML
    <div> Welcome To Yard! </div>
HTML;
    }
}