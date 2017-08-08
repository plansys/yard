import React from 'react';
import PropTypes from 'prop-types';
import h from 'react-hyperscript';
import { Provider } from 'react-redux';
import PageLoader from './PageLoader';
import Placeholder from './ui/Placeholder';
import ReactDOM from 'react-dom';

export class Page extends React.Component {

    static history = {
        push: function (page) {
            if (!PageLoader.redux) {
                throw new Error('PageLoader.redux is not defined!');
            }

            var url = window.yard.url.page
                .replace('[page]', page);

            window.yard.page.name = page;
            PageLoader.history.push(url);
            Page.history.doChangeListener(page);
        },
        replace: function (page) {
            if (!PageLoader.redux) {
                throw new Error('PageLoader.redux is not defined!');
            }

            var url = window.yard.url.page
                .replace('[page]', page);

            window.yard.page.name = page;
            PageLoader.history.replace(url);
            Page.history.doChangeListener(page);
        },
        redirect: function (page) {
            var url = window.yard.url.page
                .replace('[page]', page);
            
            Page.history.doChangeListener(page);
            window.location.href = url;
        },
        now: function () {
            const rule = ".*" + window.yard.url.page.split("[page]").join("(.*)")
            const url = window.location.href.replace(/\/?$/, '/');

            const match = url.match(new RegExp(rule));
            if (!match) {
                return window.yard.page.name;
            }

            const pageArr = match[1].split("...");
            return pageArr[0].split('/')[0];
        },
        onChange: function(func) {
            if (typeof func !== 'function') {
                throw new Error('Parameter must be a function');
            }

            window.onpopstate = function(e) {
                Page.history.doChangeListener(Page.history.now());
            };

            Page.history.changeListener.push(func);
        },
        doChangeListener: function(page) {
            Page.history.changeListener.forEach(c => {
                c(page);
            })
        },
        changeListener: []
    }

    constructor() {
        super(...arguments)
        this.state = {
            '[[loaded]]': false
        }

        this._events = {};

        if (!this.props.loader.conf) {
            console.error('Loader conf is not loaded! You must have loader conf loaded before creating Page');
            return;
        }

        this.url = window.yard.url.pages[''];
        this.props.loader.conf.js.bind(this)(Page, ReactDOM);
    }

    componentWillReceiveProps(nextProps) {
        this.applyEvent('componentWillReceiveProps', arguments);
        if (this.props.name !== nextProps.name) {
            this.setState({ '[[loaded]]': false });
            this.props.loader.loadPage(nextProps.name).then(conf => {
                if (!this._isMounted) return null;
                this.setState({ '[[loaded]]': true });
            });
        }
    }

    componentDidMount() {
        this._isMounted = true;
        this.props.loader.init.then(conf => {
            this.setState({ '[[loaded]]': true });
        })

        if (typeof this.props.refbind === 'function') {
            this.props.refbind(this);
        } 
        
        let args = arguments;
        setTimeout(() => {
            this.applyEvent('componentDidMount', args);        
        })
    }

    componentWillUnmount() {
        this._isMounted = false;
        this.applyEvent('componentWillUnmount', arguments);
    }

    render() {
        if (!this.state['[[loaded]]']) return null;
        if (!this.props.loader.conf) {
            console.log(this.props.loader);
            return null;
        }
        let content = this.props.loader.conf.render.bind(this)(this.hswap.bind(this), ReactDOM);
        if (!content) return null;        
        if (!content.type) return null;

        let render = React.Children.only(content);

        return render;
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

    hswap(tag, props, children) {
        switch (tag) {
            case "js":
                return props.bind(this)(this.hswap.bind(this));
            case "Page":
                let newProps = { ...props }

                if (newProps.ref) {
                    delete newProps.ref;
                    newProps.refbind = props.ref;
                }

                return h(PageLoader, {
                    ...newProps,
                    children: !!children && children.length === 1 ? children[0] : children,
                    name: props.name
                });
            case "Placeholder":
                return h(Placeholder, {
                    history: PageLoader.history
                });
            default:
                var stag = tag;

                if (PageLoader.ui.loaded[tag]) {
                    stag = PageLoader.ui.loaded[tag];
                }

                return h(stag, props, children);
        }
    }

}

Page.propTypes = {
    loader: PropTypes.object.isRequired
}

export const createPage = function (name, loader, props) {
    if (!loader) {
        return (<div>Cannot create page, no loader provided!</div>)
    }

    let NewPage = loader.pageComponent;
    // if (loader.conf.propTypes) {
    //     NewPage.propTypes = {};
    //     map(loader.conf.propTypes, (types, tag) => {
    //         //eslint-disable-next-line
    //         (new Function('NewPage', 'PropTypes', `NewPage.propTypes["${tag}"] = PropTypes.${types}`))(NewPage, PropTypes)
    //     })
    // }

    let newProps = {
        ...props,
        loader
    };

    if (loader.isRoot && PageLoader.redux.store) {
        return (
            <Provider store={PageLoader.redux.store}>
                <NewPage { ...newProps } />
            </Provider>
        )
    }
    return <NewPage { ...newProps } />
}
