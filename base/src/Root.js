import React from 'react';
import PageLoader from './PageLoader';

class Root extends React.Component {
    render() {
        if (!window.plansys) {
            return (<div style={{textAlign: 'center'}}>
                <h1>Welcome To Yard</h1>

                <pre>This is yard base development build</pre>

                <pre>To start using this base build please
                    <br/> configure your base.php to {window.location.href}</pre>

                <pre>For more information please visit:
                <br/> <a href="https://github.com/plansys/yard">Yard Github Page</a></pre>
            </div>);
        }
        return <PageLoader name={this.props.name} isRoot={true}/>;
    }
}

export default Root;
