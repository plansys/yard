Assuming you want to run your development server on `http://localhost:3000`  \(the default value\), you have to edit your base declaration so it resemble something like this:

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

Yard base is an [create-react-app](https://github.com/facebookincubator/create-react-app) project, you can view [it's guide here](https://github.com/facebookincubator/create-react-app/blob/master/packages/react-scripts/template/README.md)

You must install these tools in order to run development server:

1. [NodeJS](https://nodejs.org)
2. [Yarn](https://yarnpkg.com)

After installing those tools, you should install base dependency by running `yarn` on your base directory:  
![](/docs/assets/editing-base-1.png)

And then, run your development server by running `yarn start` on your base directory:

![](/docs/assets/editing-base-2.png)

