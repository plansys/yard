import React from 'react';
import PropTypes from 'prop-types';
import { Route } from 'react-router'; 
import { ConnectedRouter } from 'react-router-redux';
import { Page, createPage } from './../../Page';
import Loader from './../../Loader';

class Placeholder extends React.Component {
    constructor() {
        super(...arguments)
        
        this._loader = {};
        this.state = {
            currentPage: null
        };
    }
    
    render() {
        return (
            <ConnectedRouter history={ this.props.history }>
                <Route render={ (route) => {
                        const pageName = Page.history.now();
                        
                        if (!this._loader[pageName]) {
                            this._loader[pageName] = new Loader(pageName, false)
                            this._loader[pageName].init
                            .then(conf => {
                                this.setState({currentPage: pageName });
                            })
                            return null;
                        }
                        
                        if (this._loader[pageName] && this._loader[pageName].conf) {
                            return createPage(pageName, this._loader[pageName])
                        } else {
                            return null
                        }
                    }
                } />
            </ConnectedRouter>
        )
    }
}

Placeholder.propTypes = {
    history: PropTypes.object.isRequired
}

export default Placeholder;