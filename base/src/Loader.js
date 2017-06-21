// loader
import { Page } from './Page';
import { addJS, addCSS } from './lib/injectTag';
import { componentLoader, loadConf, parseConf } from './lib/componentLoader';
import { mapInput, mapAction } from './lib/reduxConnector';

// redux
import initStore from './lib/initStore';
import { connect } from 'react-redux';
import { importReducers } from './lib/reduxImport';
import createSagaMiddleware from 'redux-saga';
import * as reduxSagaEffects from 'redux-saga/effects';
import api from './lib/api';

// router
import createHistory from 'history/createBrowserHistory'
import { routerReducer, routerMiddleware } from 'react-router-redux'

class Loader {
    static page = {
        title: '',
        conf: {},
        confpromise: {},
        css: [],
    };
    static loaders = [];
    static ui = {
        promise: {},
        loaded: { Page },
    };

    static redux = {};

    constructor(name, isRoot = false) {
        this.loadPage(name, isRoot);
    }

    loadPage(name, isRoot = false) {
        if (typeof name !== 'string') {
            throw new Error('alias must be a string');
        }

        this.isRoot = isRoot;
        this.name = name;
        this.conf = null;
        this.subpage = [];
        this.subpageidx = 0;
        this.pageComponent = Page;

        this.init = this
            .initConf(name)
            .then(this.loadDependecies.bind(this))
            .then(this.prepareRedux.bind(this))
            .then(this.bindRenderer.bind(this))

        return this.init;
    }

    initConf(name) {
        return new Promise(resolve => {
            if (!Loader.page.conf[name]) {
                loadConf(name, this.isRoot).then(rawconf => {
                    var conf = parseConf(rawconf, name);

                    Loader.page.conf[conf.alias] = conf;
                    if (conf.alias !== name && conf.dependencies.pages[name]) {
                        Loader.page.conf[name] = conf.dependencies.pages[name];
                    }
                    this.name = conf.alias;
                    
                    function includeCSS(alias, shouldLoad) {
                        if (shouldLoad && Loader.page.css.indexOf(alias) < 0) {
                            var url = window.yard.url.page
                                        .replace('[page]', alias)
                                        .replace('[mode]',  'css');
                            addCSS(url);
                            Loader.page.css.push(alias);
                        }
                    }
                    
                    
                    includeCSS(conf.alias, conf.css);
                    if (conf.dependencies) {
                        for (var p in conf.dependencies.pages) {
                            includeCSS(p, conf.dependencies.pages[p].css);
                        }
                    }

                    if (conf.includeJS) {
                        var jsdeps = [];
                        
                        conf.includeJS.forEach(js => {
                            jsdeps.push(new Promise(resolve => {
                                addJS(js, js, function() {
                                    resolve(js);
                                });
                            }))
                        })
                        
                        Promise.all(jsdeps).then(params => {
                            resolve(conf);
                        })
                    } else {
                        resolve(conf);
                    }
                })
            } else {
                resolve(Loader.page.conf[name]);
            }
        })
    }

    loadDependecies(conf) {
        return new Promise(resolve => {
            if (!conf.dependencies) {
                resolve(conf);
                return;
            }

            for (var page in conf.dependencies.pages) {
                Loader.page.conf[page] = conf.dependencies.pages[page];
            }

            conf.dependencies.elements.forEach(el => {
                if (el[0] === el[0].toUpperCase()) {
                    Loader.ui.promise[el] = (tag) => componentLoader(el);
                }
            })

            const tags = Object.keys(Loader.ui.promise);
            if (tags.length > 0) {
                Promise
                    .all(tags.map(tag => Loader.ui.promise[tag](tag))) // import all ui dependencies
                    .then((result) => {
                        Loader.ui.promise = {};

                        // mark all page conf as loaded
                        // move all ui element it to Loader.ui.loaded
                        tags.forEach((tag, idx) => {
                            Loader.ui.loaded[tag] = result[idx];
                            delete Loader.ui.promise[tag];
                        })

                        resolve(conf);
                    });

                return;
            }

            resolve(conf);

        })
    }

    prepareRedux(conf) {
        return new Promise(resolve => {

            // init store
            if (this.isRoot !== false && conf.redux) {
                if (typeof conf.redux.actionCreators === 'function') {
                    Loader.redux.actionCreators = conf.redux.actionCreators();
                }

                Loader.redux.reducers = function () { };
                this._reducersScope = {};
                if (typeof conf.redux.reducers === 'function') {
                    Loader.redux.reducers = importReducers.bind(this._reducersScope)(conf.redux.reducers(), {
                        route: routerReducer
                    });
                }

                const sagaMiddleware = createSagaMiddleware()
                Loader.redux.history = createHistory()
                Loader.redux.store = initStore(Loader.redux.reducers, [
                    routerMiddleware(Loader.redux.history),
                    sagaMiddleware
                ]);
                
                
                var keys = Object.keys(reduxSagaEffects)
                            .concat('api')
                            .concat('conf');
                var values = Object.values(reduxSagaEffects)
                            .concat(api)
                            .concat(conf);
                
                //eslint-disable-next-line
                const sagasStore =  (new Function(...keys,  'conf.redux.sagas()'))(...values);
                
                for (let t in sagasStore) {
                    let sagas = sagasStore[t];
                    for (let i in sagas) {
                        for (let s in sagas[i]) {
                            sagaMiddleware.run(sagas[i][s]);
                        }
                    }
                }
            }

            // prepare react-redux connect args (mapStateToProps and mapDispatchToProps)
            if (conf.map) {
                if (conf.map.input) {
                    this.mapStateToProps = mapInput.bind(this)(conf.map.input)
                }

                if (conf.map.action) {
                    this.mapDispatchToProps = mapAction.bind(this)(conf.map.action);
                }
            }
            resolve(conf);
        })
    }

    bindRenderer(conf) {
        return new Promise(resolve => {
            if (this.mapStateToProps || this.mapDispatchToProps) {
                this.pageComponent = connect(this.mapStateToProps, this.mapDispatchToProps)(this.pageComponent);
            }

            this.conf = conf;
            resolve(conf);
        })
    }

}

export default Loader
