<p align="center"><img src="https://github.com/plansys/yard/raw/master/base/public/favicon.ico"/></p>

# Yard: ReactJS Component in PHP

This library allows you to write ReactJS Component in PHP Class.

It works by creating React Component on-the-fly based on javascript generated by PHP. 


## Vanilla PHP Installation

You can install Yard using composer: 

```
composer require plansys\yard
```

Follow these steps to use yard:
 
 1. Create `my-project` directory
 2. Open terminal and execute `composer require plansys\yard` in that directory
 1. Create `pages`, `tmp`  and `redux` directory in `my-project` directory 
 4. Make sure `/tmp` directory is writeable by web server process (`chmod 755` into this directory)
 3. Create  `base.php` and `index.php` file
 
After those steps, your directory structure should look like this:

```
my-project
  ├─ tmp         (writable)
  ├─ pages 
  ├─ redux 
  └─ vendors
     ├─ ...
     └─ yard
        ├─ base    (copy to /bases/default)
        ├─ src
        └─ ...
     └─ ...
  └─ base.php        
  └─ index.php   
 ```
 
 Put this code into `base.php` file:
 ```php
 <?php
 
 $host = str_replace("index.php", "", strtok($_SERVER["REQUEST_URI"], '?'));
 return [
    'name' => 'PLANSYS',
    'offline' => false,
    'settings' => null,
    'host' => $host,
    'modules' => [
        '' => [
            'dir'=> dirname(__FILE__) . '/pages',
            'url' => $host . '/pages',
            'redux' => dirname(__FILE__) . '/redux'
        ]
    ],
    'dir' => [
        'dir'=> dirname(__FILE__) . '/pages',
        'base' => dirname(__FILE__) . '/vendor/plansys/yard/base/build',
        'cache' => dirname(__FILE__) . '/tmp',
        'root' => dirname(__FILE__) 
    ],
    'url' => [
        'base' => $host . '/vendor/plansys/yard/base/build',
        'cache' => $host . '/tmp/[file]',
        'page' => $host . '/index.php?p=[page]',
    ]
 ];
 ```
 
 And put this code into `index.php` file:
 
 ```php
<?php

require("vendor/autoload.php");

$base = new \Yard\Base(dirname(__FILE__) . DIRECTORY_SEPARATOR . "base.php");
$yard = new \Yard\Renderer($base);

$parr = explode('...', @$_GET['p']);
$modearr = count($parr) > 1 ? explode(".", $parr[1]) : [''];
$mode = count($modearr) > 1 ? $modearr[1] : $modearr[0];

if ($mode == 'css') {
    header('Content-type: text/css');
} else if (in_array($mode, ['js', 'jsdev', 'sw'])) {
    header('Content-type: text/javascript');
}
$page = isset($_GET['p']) ? $_GET['p'] : 'builder:Index';
echo $yard->render($page);

 ```
 
Then open `index.php` in your browser.

<hr/>


Icons made by <a href="http://www.freepik.com" title="Freepik">Freepik</a> from <a href="http://www.flaticon.com" title="Flaticon">www.flaticon.com</a> is licensed by <a href="http://creativecommons.org/licenses/by/3.0/" title="Creative Commons BY 3.0" target="_blank">CC 3.0 BY</a>
 
