
const toQueryString = function (params, url) {
    if (!params) return "";
    
    if (params.r) {
        delete params.r;
    }

    var query = Object
        .keys(params)
        .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
        .join('&');

    if (query.length > 0) {
        query = '&' + query
    }

    if (url.indexOf('?') > 0) {
        return url + query;
    } else {
        return url + '?' + query;
    }
}

const api = {
    get: function (endpoint, params, mode = 'text') {
        var url = window.yardurl.page
                .replace('[page]', endpoint)
                .replace('[mode]', 'api');
        var promise = fetch(toQueryString(params, url));
        
        if (mode === 'text') {
            return promise.then(res => res.text());
        } else {
            return promise;
        }
    },
    getJSON: function(endpoint, params) {
        var promise = api.get(endpoint, params, 'json');
        return promise.then(res => res.json());
    }
}

export default api;