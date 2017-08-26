let request = obj => {
    let xhr = new XMLHttpRequest();
    let promise = new Promise((resolve, reject) => {
        xhr.open(obj.method || "GET", obj.url);
        if (obj.headers) {
            Object.keys(obj.headers).forEach(key => {
                xhr.setRequestHeader(key, obj.headers[key]);
            });
        }
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(xhr.response);
            } else {
                reject(xhr.response);
            }
        };
        xhr.onerror = () => reject(xhr.response);
        xhr.send(obj.body);
    });

    promise.cancel = () => {
        xhr.abort();
    }

    return promise;
};

let latest = function (fn) {
    let lastAdded
    let pending
    let resolve
    let reject
    return function (...args) {
        // in the future if/when promises gets cancellable, we could abort the previous here like this
        if (!!lastAdded && lastAdded.cancel) {
            lastAdded.cancel();
        }

        lastAdded = fn(...args)
        if (!pending) {
            pending = new Promise((_resolve, _reject) => {
                resolve = _resolve
                reject = _reject
            })
        }
        lastAdded.then(fulfill.bind(null, lastAdded), fail.bind(null, lastAdded))
        return pending
    }
    function fulfill(promise, value) {
        if (promise === lastAdded) {
            pending = null
            resolve(value)
        }
    }

    function fail(promise, error) {
        if (promise === lastAdded) {
            pending = null
            reject(error)
        }
    }
}