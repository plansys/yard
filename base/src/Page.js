import React from 'react';
import PropTypes from 'prop-types';
import h from 'react-hyperscript';
import { Provider } from 'react-redux';
import Loader from './Loader';

export class Page extends React.Component {
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

        this.props.loader.conf.js.bind(this)();
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
        return React.Children.only(this.props.loader.conf.render.bind(this)(this.hswap.bind(this)))
    }

    on(event, func) {
        this._events[event] = func.bind(this);
        if (!this[event]) {
            this[event] = func.bind(this);
        }
    }

    applyEvent(event, args) {
        if (this._events[event]) {
            this._events[event](args);
        }
    }

    loadSubPage() {
        this.setState({
          isLoaded: false,
        });
        
        this._loaders = [];
        
        if (this.props.loader.conf.loaders) {
            this.props.loader.conf.loaders.forEach((pageName) => {
                this._loaders.push(new Loader(pageName));
            });
        }
        
        Promise
            .all(this._loaders.map(l => l.init))
            .then(results => {
                if (!this._isMounted) return null;
                this.setState({isLoaded: true});
            })
    }
    hswap(tag, props, children) {
        switch (tag) {
            case "js":
                return props.bind(this)(this.hswap.bind(this));
            case "Page":
                let idx = this._loadersIdx++;
                let loader = this._loaders[idx];
                
                if (!loader || (loader && !loader.conf)) {
                    loader = this._loaders[idx] = new Loader(props.name)
                    loader.init.then(conf => {
                        this.forceUpdate();
                    })
                    return null;
                }

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
    
    if (loader.isRoot && Loader.redux.store) {
        return ( 
            <Provider store={ Loader.redux.store }>
                <loader.pageComponent loader={loader} { ...props } />
            </Provider> 
        )
    }
    return <loader.pageComponent loader={loader} { ...props } />
}
