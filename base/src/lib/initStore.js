import {
     applyMiddleware,
     compose,
     createStore
}
from 'redux';

export default function initStore(reducers, middlewares) {
     let middleware = applyMiddleware(...middlewares);

     if (process.env.NODE_ENV !== 'production' || window.yard.offline === false) {
          const devToolsExtension = window.devToolsExtension;
          if (typeof devToolsExtension === 'function') {
               middleware = compose(middleware, devToolsExtension());
          }
     }

     const store = createStore(reducers, middleware);
     return store;
}