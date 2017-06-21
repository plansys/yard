<?php

namespace Pages;

class Welcome extends \Yard\Page
{
    public function includeJS() {
        return [
            'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js',
            'yard:biasa.js'];
    }
    
    public function js() {
return <<<JS
    $("body").css('background', 'red');       
JS;
    }
    
    public function render()
    {
        return <<<HTML
    <div> Welcome To Yard! </div>
HTML;
    }
}