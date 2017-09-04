import './lib/path';
import 'babel-regenerator-runtime';
import React from 'react';
import ReactDOM from 'react-dom';
import registerServiceWorker from './lib/registerServiceWorker';

import Root from './Root';

const render = (pageName) => {
    ReactDOM.render(<Root name={pageName}/>, document.getElementById('root'));
};

window.Root = Root;
window.render = render;
window.React = React;
window.ReactDOM = ReactDOM;


if (!window.yard) {
    render();
}

if (window.yard && window.yard.offline) {
    registerServiceWorker();
}
