let requestLatest = latest(request);

this.state = {
    tag: "div",
    result: null,
    requery: false,
    loading: false,
    debug: false,
    debugPos: {
        x: 0,
        y: 0
    },
    debugStatusCode: 0,
    debugMax: true,
    drag: false,
    params: null,
    paramsText: '',
    paramsValid: true
}
this.page = false;
this.promise = false;

this.query = (params, execDone = true) => {
    if (typeof params === "undefined") {
        params = this.props.params;
    }

    if (this.state.debug && !this.state.debugMax) {
        this.setState({ debugMax: true });
    }

    this.setState({
        loading: true
    });

    let page = this.props.page || this.page;
    let method = this.props.method || '';

    this.promise = requestLatest(
        {
            url: window.plansys.url.page.replace('[page]', page + "::" + method),
            method: "POST",
            headers: {
                "Content-type": "application/json"
            },
            body: JSON.stringify(params)
        })
        .catch(result => {
            if (this.state.debug) {
                this.setState({
                    requery: false,
                    loading: false,
                    result
                });
            }
        })
        .then(res => {
            let result = res;

            try {
                result = JSON.parse(res)
            } catch (error) { }

            this.setState({
                requery: false,
                loading: false,
                result
            });

            if (execDone && typeof this.props.onDone === 'function') {
                this.props.onDone(result);
            }
            return res;
        });

    return this.promise;
}

this.on('componentWillMount', () => {
    let parent = this._getOwner();

    if (!parent.props['[[name]]'] || !parent.props['[[loader]]']) {
        throw new Error('Parent is not a Page! You should use yard:Api inside Yard Page');
    }
    this.page = parent.props['[[name]]'];

    if (this.props.tag) {
        this.setState({
            tag: this.props.tag,
        });
    }

    if (this.props.debug) {
        this.setState({
            params: this.props.params,
            paramsText: JSON.stringify(this.state.params, null, 2),
            paramsValid: true
        });
    }
})

this.on('componentWillUpdate', (nextProps) => {
    if (this.state.debug != nextProps.debug) {
        this.setState({ debug: nextProps.debug });

        if (nextProps.debug) {
            this.setState({
                params: nextProps.params,
                paramsText: JSON.stringify(this.state.params, null, 2),
                paramsValid: true
            });
        }
    }
})

this.paramsChange = e => {
    this.setState({
        paramsText: e.target.value
    });

    try {
        this.setState({
            paramsValid: !!(JSON.parse(e.target.value)),
            params: JSON.parse(e.target.value)
        })
    } catch (e) {
        this.setState({
            paramsValid: false
        });
    }
}

this.dragPos = {
    x: 0,
    y: 0
}
this.mouseDown = e => {
    this.setState({ drag: true });
    this.dragPos = {
        x: e.pageX,
        y: e.pageY,
        oldx: this.state.debugPos.x,
        oldy: this.state.debugPos.y,
    }
}
this.mouseMove = e => {
    if (this.state.drag) {
        var pX = e.pageX;
        var pY = e.pageY;
        var debugPos = {
            x: this.dragPos.oldx + pX - this.dragPos.x,
            y: this.dragPos.oldy + pY - this.dragPos.y
        };
        this.setState({
            drag: true,
            debugPos
        })
    }
}
this.doneDragging = e => {
    this.setState({ drag: false });
    this.dragPos = {
        x: 0,
        y: 0
    }
}
this.debugStyle = () => {
    return {
        transform: 'translate(' + this.state.debugPos.x + 'px,' + this.state.debugPos.y + 'px)'
    };
}
this.debugToggle = () => {
    this.setState({
        debugMax: !this.state.debugMax
    });
}
