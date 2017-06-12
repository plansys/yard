import React from 'react';
import PropTypes from 'prop-types';
import { Link as RLink } from 'react-router-dom';

export default function Link(props, context) {
    var location = document.createElement('a');
    location.href = window.yardurl.page;
    location.search = location.search
                        .replace('[page]', props.to)
                        .replace('[mode]', '')

    if (!context.router) {
        return <div><b>&lt;Link&gt; must be placed inside &lt;Placeholder&gt;</b></div>
    }
    
    return (
       <RLink { ...props } to={ location }>
       </RLink>
    );
}


Link.contextTypes = {router: PropTypes.object};