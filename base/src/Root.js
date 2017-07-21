import React from 'react';
import PropTypes from 'prop-types';
import PageLoader from './PageLoader';

class Root extends React.Component {

    componentDidMount() {
        document.body.className = document.body.className.replace("body-loading","");
    }

    render() {
        return <PageLoader name={this.props.name} isRoot={true} />;
    }
}

Root.propTypes = {
    name: PropTypes.string.isRequired
}

export default Root;
