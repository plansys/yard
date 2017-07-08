import React from 'react';
import PropTypes from 'prop-types';
import PageLoader from './PageLoader';

class Root extends React.Component {
    render() {
        return <PageLoader name={this.props.name} root={true} />;
    }
}

Root.propTypes = {
    name: PropTypes.string.isRequired
}

export default Root;
