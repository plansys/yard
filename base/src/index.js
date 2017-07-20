import './lib/path';
import 'babel-regenerator-runtime';
import React from 'react';
import ReactDOM from 'react-dom';
import registerServiceWorker from './lib/registerServiceWorker';

import Root from './Root';

const render = (pageName) => {
  ReactDOM.render(<Root name={pageName} />, document.getElementById('root'));
};

window.Root = Root;
window.render = render;

if (window.yard.offline) {
    registerServiceWorker();
}

document.body.className = document.body.className.replace("body-loading","");