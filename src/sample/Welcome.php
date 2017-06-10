<?php
namespace \Yard\sample;

class Welcome extends Page {

    public function render() {
return <<<HTML
    <div> Hello, World! </div>
HTML;
    }
    
}