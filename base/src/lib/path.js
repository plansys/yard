if (!!window.yard) {
    if (window.yard.url.base.substr(-1) !== '/') window.yard.url.base += '/';
    
    //eslint-disable-next-line
    __webpack_public_path__ = window.yard.url.base;
} 