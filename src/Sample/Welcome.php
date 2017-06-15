<?php

namespace Pages;

class Welcome extends \Yard\Page
{

    public function render()
    {
        return <<<HTML
    <div> Hello,  asdas asd
        <Page name="yard:Test"></Page>
    </div>
HTML;
    }
}
