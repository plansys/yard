Assuming you want to run your development server on `http://localhost:3000` you have to edit your base declaration, so it resemble something like this:

```php
$base = new \Yard\Base([
  'name' => '...',
  'dir' => [
    'base' => 'http://localhost:3000',
    'cache' => '...',
    'pages' => '...',
    'redux' =>'...',
  ],
  'url' => [
    'base' => 'http://localhost:3000',
    'page' => '...',
    'cache' => '...'
  ]
);
```



