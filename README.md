<p align="center"><img src="https://github.com/plansys/yard/raw/master/js/public/favicon.ico"/></p>

# Yard: ReactJS Component in PHP

This library allows you to write ReactJS Component in PHP Class.

It works by creating React Component on-the-fly based on javascript generated by PHP. 


## Vanilla PHP Installation

This library someday will be available in composer. In the meantime, you can just download this repository and follow these steps: 

 1. Create `my-project` directory
 2. Create `components` and `redux` directory in `my-project` directory 
 3. Extract `yard-master.zip` in that directory
 4. Rename `yard-master` directory into `yard`
 5. Create `index.php` file
 
After those steps, your directory structure should look like this:

```
my-project
  ├─ pages 
  ├─ redux
  └─ yard (~~ renamed from yard-master ~~)
     ├─ bridge
     ├─ core
     ├─ js
     ├─ sample
     ├─ vendor
     └─ ...
  └─ index.php   
 ```
 
 And put this code into `index.php` file:
 
 ```php
 <?php
 
 require("yard/bridge/vanilla.php");
 \Yard::render([
    'page' => $_GET['p'], 
    'mode' => isset($_GET['m']) ? $_GET['m'] : 'html'
 ]);
 ```
 

Then open `index.php?p=/yard/sample/Welcome` in your browser.

This will render `Welcome` Page in `/yard/sample/Welcome.php` file. 

<hr/>


Icons made by <a href="http://www.freepik.com" title="Freepik">Freepik</a> from <a href="http://www.flaticon.com" title="Flaticon">www.flaticon.com</a> is licensed by <a href="http://creativecommons.org/licenses/by/3.0/" title="Creative Commons BY 3.0" target="_blank">CC 3.0 BY</a>
 
