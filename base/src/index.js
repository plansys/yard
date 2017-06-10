import React from 'react';
import ReactDOM from 'react-dom';
import registerServiceWorker from './lib/registerServiceWorker';

import Root from './Root';
import Loader from './Loader';

const render = (pageName) => {
  ReactDOM.render(<Root name={pageName} />, document.getElementById('root'));
};

window.Root = Root;
window.Loader = Loader;
window.render = render;
registerServiceWorker();