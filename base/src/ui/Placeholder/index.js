import React from 'react';
import PropTypes from 'prop-types';
import { Router } from 'react-router-dom';
import { Route } from 'react-router';
import { ConnectedRouter } from 'react-router-redux';
import { Page } from './../../Page';
import PageLoader from './../../PageLoader';

class Placeholder extends React.Component {
    render() {
        let router = <Route render={
            (route) => {
                const pageName = Page.history.now();
                if (!pageName) return null;
                
                return <PageLoader name={pageName} />;
            }
        } />;

        if (PageLoader.redux.reducers) {
            return (
                <ConnectedRouter history={this.props.history}>
                    {router}
                </ConnectedRouter>
            )
        } else {
            return (
                <Router history={this.props.history}>
                    {router}
                </Router>
            );
        }
    }
}

Placeholder.propTypes = {
    history: PropTypes.object
}

export default Placeholder;