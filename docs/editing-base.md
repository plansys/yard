### 1. Change Base Declaration

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

### 2. Install NodeJS and Yarn

Yard base is an [create-react-app](https://github.com/facebookincubator/create-react-app) project, you can view [it's guide here](https://github.com/facebookincubator/create-react-app/blob/master/packages/react-scripts/template/README.md)

You must install these tools in order to run development server:

1. [NodeJS](https://nodejs.org)
2. [Yarn](https://yarnpkg.com)

After installing those tools, you should install base dependency by running `yarn` on your base directory:  
![](/docs/assets/editing-base-1.png)

> **Temporary Additional Step:**
>
> Because our development server host and port will be different with create-react-app development host, we have to edit some of react-scripts and react-dev-utils filein node\_modules:
>
> **in \[your-base-dir\]/node\_modules/react-scripts/config/webpack.config.dev.js, line 27: **  
>    change `const publicPath = '/'` into `const publicPath = 'http://localhost:3000/'`
>
> **in \[your-base-dir\]/node\_modules/react-dev-utils/webpackHotDevClient.js, line 165:**  
>    change `port: window.location.port,`  into `port: 3000,`  
>   
> We need to do this until react-scripts and react-dev-utils incorporate our needs.

And then, run your development server by running `yarn start` on your base directory:

![](/docs/assets/editing-base-2.png)

