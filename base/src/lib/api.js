import Loader from './../Loader';

const toQueryString = function (params) {
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

    return query;
}

const api = {
    get: function (endpoint, params, mode = 'text') {
        var query = toQueryString(params);
        var promise = fetch(Loader.baseUrl + '/index.php?r=redux/api|' + endpoint + query);
        
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