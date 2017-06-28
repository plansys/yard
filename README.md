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
 1. Create `bases`, `pages`, `cache`  and `redux` directory in `my-project` directory 
 2. Copy `/vendor/yard/base` into `/bases`, and rename it to default
 4. Make sure `/cache` directory is writeable by web server process (`chmod 755` into this directory)
 3. Create `index.php` file
 
After those steps, your directory structure should look like this:

```
my-project
  └─ bases
     └─ default    (copied from /vendor/yard/base)
  ├─ cache         (writable)
  ├─ pages 
  ├─ redux 
  └─ vendors
     ├─ ...
     └─ yard
        ├─ base    (copy to /bases/default)
        ├─ src
        └─ ...
     └─ ...
  └─ index.php   
 ```
 
 And put this code into `index.php` file:
 
 ```php
<?php

require("vendor/autoload.php");

$host = str_replace("index.php", "", strtok($_SERVER["REQUEST_URI"], '?'));
$base = new \Yard\Base([
  'name' => 'Welcome to Yard',
  'dir' => [
    'base' => dirname(__FILE__) . '/bases/default/build',
    'cache' => dirname(__FILE__) . '/cache',
    'pages' => ['' => dirname(__FILE__) . '/pages'],
    'redux' => dirname(__FILE__) . '/redux',
  ],
  'url' => [
    'root' => $host . '/pages',
    'base' => $host . '/bases/default/build',
    'page' => $host . '/?p=[page]',
    'cache' => $host . '/cache/[file]'
  ]
]);

$yard = new \Yard\Renderer($base);
$page = isset($_GET['p']) ? $_GET['p'] : 'yard:Welcome';
$mode = isset($_GET['m']) ? $_GET[','] : 'html';

$yard->render($page, $mode);

 ```
 
Then open `index.php` in your browser.

This will render `Welcome` Page in `/vendor/yard/src/sample/Welcome.php` file. 

<hr/>


Icons made by <a href="http://www.freepik.com" title="Freepik">Freepik</a> from <a href="http://www.flaticon.com" title="Flaticon">www.flaticon.com</a> is licensed by <a href="http://creativecommons.org/licenses/by/3.0/" title="Creative Commons BY 3.0" target="_blank">CC 3.0 BY</a>
 
