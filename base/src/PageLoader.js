// loader
import React from 'react';
import {Page, createPage} from './Page';
import {addJS, addCSS} from './lib/injectTag';
import {loadConf, parseConf} from './lib/componentLoader';
import {mapInput, mapAction} from './lib/reduxConnector';

// redux
import initStore from './lib/initStore';
import {connect} from 'react-redux';
import {importReducers} from './lib/reduxImport';
import createSagaMiddleware from 'redux-saga';
import * as reduxSagaEffects from 'redux-saga/effects';

// router
import createHistory from 'history/createBrowserHistory'
import {routerReducer, routerMiddleware} from 'react-router-redux'

class PageLoader extends React.Component {
    static page = {
        title: '',
        conf: {},
        confpromise: {},
        css: [],
    };
    static loaders = [];
    static ui = {
        promise: {},
        loaded: {Page},
    };

    static history = null;
    static redux = {};

    constructor() {
        super(...arguments)
        this.state = {
            loaded: false
        }
        this.loadPage(this.props['[[name]]'], this.props['[[isRoot]]'] || false);
    }

    loadPage(name, isRoot = false) {
        if (typeof name !== 'string') {
            throw new Error('alias must be a string');
        }

        this.isRoot = isRoot;
        this.name = name.replace(/[^0-9a-z.:_]/gi, '');

        this.conf = null;
        this.pageComponent = Page;

        this.init = this
            .initConf(this.name)
            .then(this.loadDependecies.bind(this))
            .then(this.prepareRedux.bind(this))
            .then(this.bindRenderer.bind(this))
            .then(conf => {
                this.setState({loaded: true})
            })

        return this.init;
    }

    initConf(name) {
        return new Promise((resolve, reject) => {
            if (!PageLoader.page.conf[name]) {
                loadConf(name, this.isRoot)
                    .catch(res => {
                        reject(res);
                    })
                    .then(rawconf => {
                        let conf = parseConf(rawconf, name);
                        PageLoader.page.conf[conf.alias] = conf;
                        if (conf.alias !== name && conf.dependencies.pages[name]) {
                            PageLoader.page.conf[name] = conf.dependencies.pages[name];
                        }
                        this.name = conf.alias;

                        const deps = [];

                        let includeCSS = (alias, shouldLoad) => {
                            if (shouldLoad && PageLoader.page.css.indexOf(alias) < 0) {
                                var url = window.plansys.url.page
                                    .replace('[page]', alias + '...css');

                                PageLoader.page.css.push(alias);

                                deps.push(new Promise(resolve => {
                                    addCSS(url, function () {
                                        resolve(url);
                                    });
                                }))
                            }
                        }

                        includeCSS(conf.alias, conf.css);
                        if (conf.dependencies) {
                            for (let p in conf.dependencies.pages) {
                                includeCSS(p, conf.dependencies.pages[p].css);
                            }
                        }

                        let includeJS = (conf) => {
                            if (conf.includeJS) {
                                conf.includeJS.forEach(js => {
                                    deps.push(new Promise(resolvejs => {
                                        addJS(js, js, function () {
                                            resolvejs(js);
                                        });
                                    }))
                                })
                            }
                        }

                        includeJS(conf);
                        if (conf.dependencies) {
                            for (let p in conf.dependencies.pages) {
                                includeJS(conf.dependencies.pages[p]);
                            }
                        }

                        Promise.all(deps).then(() => {
                            resolve(conf);
                        })
                    })
            } else {
                resolve(PageLoader.page.conf[name]);
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
                PageLoader.page.conf[page] = conf.dependencies.pages[page];
            }

            // Load tag with uppercased first char as a Page,
            // Commented because the page is already included in php dependencies...
            // We have to add ReactJs native component manually without component loader
            //
            // conf.dependencies.elements.forEach(el => {
            //     if (el[0] && el[0] === el[0].toUpperCase()) {
            //         PageLoader.ui.promise[el] = (tag) => componentLoader(el);
            //     }
            // })

            const tags = Object.keys(PageLoader.ui.promise);
            if (tags.length > 0) {
                Promise
                    .all(tags.map(tag => PageLoader.ui.promise[tag](tag))) // import all ui dependencies
                    .then((result) => {
                        PageLoader.ui.promise = {};

                        // mark all page conf as loaded
                        // move all ui element it to Loader.ui.loaded
                        tags.forEach((tag, idx) => {
                            PageLoader.ui.loaded[tag] = result[idx];
                            delete PageLoader.ui.promise[tag];
                        });

                        resolve(conf);
                    });

                return;
            }

            resolve(conf);

        })
    }

    prepareRedux(conf) {
        return new Promise(resolve => {
            if (this.isRoot !== false) {
                PageLoader.history = createHistory()
            }

            if (this.isRoot !== false && conf.redux) {
                if (typeof conf.redux.actionCreators === 'function') {
                    PageLoader.redux.actionCreators = conf.redux.actionCreators();
                }

                PageLoader.redux.reducers = function () {
                };
                this._reducersScope = {};
                if (typeof conf.redux.reducers === 'function') {
                    PageLoader.redux.reducers = importReducers.bind(this._reducersScope)(conf.redux.reducers(), {
                        route: routerReducer
                    });
                }

                const sagaMiddleware = createSagaMiddleware();
                PageLoader.redux.store = initStore(PageLoader.redux.reducers, [
                    routerMiddleware(PageLoader.history),
                    sagaMiddleware
                ]);

                let keys = Object.keys(reduxSagaEffects)
                    .concat('Page')
                    .concat('conf');

                let values = Object.keys(reduxSagaEffects)
                    .map((key) => reduxSagaEffects[key])
                    .concat(Page)
                    .concat(conf);

                const entire = conf.redux.sagas.toString();
                const body = entire.slice(entire.indexOf("{") + 1, entire.lastIndexOf("}"));

                //eslint-disable-next-line
                const sagasStore = (new Function(...keys, body))(...values);

                Object.keys(sagasStore).forEach(k => {
                    Object.keys(sagasStore[k]).forEach(f => {
                        sagaMiddleware.run(sagasStore[k][f]);
                    })
                })
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

            if (this.isRoot) {
                document.body.className = document.body.className.replace("body-loading", "");
            }
        })
    }

    render() {
        if (!this.conf) {
            return null;
        }

        return createPage(this.props['[[name]]'], this, this.props);
    }

}

export default PageLoader
