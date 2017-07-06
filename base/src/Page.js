import React from 'react';
import PropTypes from 'prop-types';
import h from 'react-hyperscript';
import { Provider } from 'react-redux';
import Loader from './Loader';
import map from 'lodash.mapvalues';

export class Page extends React.Component {
    
    static history = {
        push: function(page) {
            if (!Loader.redux) {
                throw new Error('Loader.redux is not defined!'); 
            }
            
            var url = window.yard.url.page
                        .replace('[page]', page);
        
            window.yard.page.name = page;
            Loader.redux.history.push(url);
        },
        replace: function(page) {
            if (!Loader.redux) {
                throw new Error('Loader.redux is not defined!'); 
            }
            
            var url = window.yard.url.page
                        .replace('[page]', page);
            
            window.yard.page.name = page;
            Loader.redux.history.replace(url);
        },
        redirect: function(page) {
            var url = window.yard.url.page
                        .replace('[page]', page);
        
            window.location.href = url;
        },
        now: function() {
            const rule = ".*" + window.yard.url.page.split("[page]").join("(.*)")
            const url = window.location.href.replace(/\/?$/, '/');
            
            const match = url.match(new RegExp(rule));
            const pageArr = match[1].split("...");
            
            return pageArr[0];
        }
    }
    
    constructor() {
        super(...arguments)
        this.state = {
            isLoaded: false
        }

        this._events = {};

        if (!this.props.loader.conf) {
            console.error('Loader conf is not loaded! You must have loader conf loaded before creating Page');
            return;
        }
        
        this.url = window.yard.url.pages[''];
        
        
        this.props.loader.conf.js.bind(this)(Page);
    }
    
    componentWillReceiveProps(nextProps) {
        this.applyEvent('componentWillReceiveProps', arguments);
        if (this.props.name !== nextProps.name) {
            this.setState({isLoaded: false});
            this.props.loader.loadPage(nextProps.name).then(conf => {
                if (!this._isMounted) return null;
                this.setState({isLoaded: true});
            });
        }
    }
    
    componentDidMount() {
        this._isMounted = true;
        this.loadSubPage();
        this.applyEvent('componentDidMount', arguments);
    }
    
    componentWillUnmount() {
        this._isMounted = false;
        this.applyEvent('componentWillUnmount', arguments);
    }
    
    render() {
        if (!this.state.isLoaded) return null;

        this._loadersIdx = 0;
        let content = this.props.loader.conf.render.bind(this)(this.hswap.bind(this));
        if (!content.type) return null;
        
        return React.Children.only(content);
    }

    on(event, func) {
        this._events[event] = func.bind(this);
        if (!this[event]) {
            this[event] = func.bind(this);
        }
    }

    applyEvent(event, args) {
        if (this._events[event]) {
            this._events[event](...args);
        }
    }

    loadSubPage() {
        this._loaders = [];
        
        if (this.props.loader.conf.loaders) {
            this.props.loader.conf.loaders.forEach((item) => {
                this._loaders.push(new Loader(item, false));
            });
        }
        
        if (this._loaders) {
            this.setState({
              isLoaded: false,
            });
            
            Promise
                .all(this._loaders.map(l => l.init))
                .then(results => {
                    if (!this._isMounted) return null;
                    this.setState({isLoaded: true});
                })
        }
        
    }
    hswap(tag, props, children) {
        switch (tag) {
            case "js":
                return props.bind(this)(this.hswap.bind(this));
            case "Page":
                let idx = this._loadersIdx++;
                let loader = this._loaders[idx];

                if (!loader || (loader && !loader.conf)) {
                    loader = this._loaders[idx] = new Loader(props.name, false)
                    loader.init.then(conf => {
                        this.forceUpdate();
                    })
                    return null;
                }
                
                props.children = children;
                
                return createPage(props.name, loader, props);
            case "Placeholder":
                return h(Loader.ui.loaded[tag], {
                    history: Loader.redux.history
                });
            default:
                var stag = tag;
                
                if (Loader.ui.loaded[tag]) {
                    stag = Loader.ui.loaded[tag];
                }
                
                return h(stag, props, children);
        }
    }

}

Page.propTypes = {
    loader: PropTypes.object.isRequired
}

export const createPage = function(name, loader, props) {    
    if (!loader) {
        return (<div>Cannot create page, no loader provided!</div>)
    }
    
    let NewPage = loader.pageComponent;
    if (loader.conf.propTypes) {
        NewPage.propTypes = {};
        map(loader.conf.propTypes, (types, tag) => {
            //eslint-disable-next-line
            (new Function('NewPage', 'PropTypes', `NewPage.propTypes["${tag}"] = PropTypes.${types}`))(NewPage, PropTypes)
        })
    }

    let newProps = {
        ...props,
        loader
    };

    if (loader.isRoot && Loader.redux.store) {
        return ( 
            <Provider store={ Loader.redux.store }>
                <NewPage { ...newProps } />
            </Provider> 
        )
    }
    return <NewPage { ...newProps } />
}
