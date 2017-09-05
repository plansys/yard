if (!!window.plansys) {
    if (window.plansys.url.base.substr(-1) !== '/') window.plansys.url.base += '/';
    
    //eslint-disable-next-line
    __webpack_public_path__ = window.plansys.url.base;
} 