<?php

namespace Pages;

class Test extends \Yard\Page {

    public function render() {
return <<<HTML
    <div> 
        Hello asd as, 
        <Router.Link to="Coba">ini mau coba</Router.Link>
        ini aku coba lagi 
        asdas & World! 
    </div>
HTML;
    }
    
}