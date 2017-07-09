import React from 'react';
import PropTypes from 'prop-types';
import h from 'react-hyperscript';
import { Provider } from 'react-redux';
import PageLoader from './PageLoader';
import map from 'lodash.mapvalues';

export class Page extends React.Component {

    static history = {
        push: function (page) {
            if (!PageLoader.redux) {
                throw new Error('PageLoader.redux is not defined!');
            }

            var url = window.yard.url.page
                .replace('[page]', page);

            window.yard.page.name = page;
            PageLoader.redux.history.push(url);
        },
        replace: function (page) {
            if (!PageLoader.redux) {
                throw new Error('PageLoader.redux is not defined!');
            }

            var url = window.yard.url.page
                .replace('[page]', page);

            window.yard.page.name = page;
            PageLoader.redux.history.replace(url);
        },
        redirect: function (page) {
            var url = window.yard.url.page
                .replace('[page]', page);

            window.location.href = url;
        },
        now: function () {
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
            '[[loaded]]': false
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
            this.setState({ '[[loaded]]': false });
            this.props.loader.loadPage(nextProps.name).then(conf => {
                if (!this._isMounted) return null;
                this.setState({ '[[loaded]]': true });
            });
        }
    }

    componentDidMount() {
        this._isMounted = true;
        this.applyEvent('componentDidMount', arguments);
        this.props.loader.init.then(conf => {
            this.setState({ '[[loaded]]': true });
        })
    }

    componentWillUnmount() {
        this._isMounted = false;
        this.applyEvent('componentWillUnmount', arguments);
    }

    render() {
        if (!this.state['[[loaded]]']) return null;

        let content = this.props.loader.conf.render.bind(this)(this.hswap.bind(this));
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

                return h(PageLoader, {
                    ...props,
                    children: !!children && children.length === 1 ? children[0] : children,
                    name: props.name
                });
            case "Placeholder":
                return h(PageLoader.ui.loaded[tag], {
                    history: PageLoader.redux.history
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

    if (loader.isRoot && PageLoader.redux.store) {
        return (
            <Provider store={PageLoader.redux.store}>
                <NewPage { ...newProps } />
            </Provider>
        )
    }
    return <NewPage { ...newProps } />
}
