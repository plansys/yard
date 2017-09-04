export const addJS = function (href, id, onload) {
    (function (d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) {
            onload();
            return;
        }
        js = d.createElement(s);
        js.id = id;
        js.onload = function () {
            // remote script has loaded
            if (typeof onload === 'function') {
                onload();
            }
        };
        js.src = href;
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', id));
}

export const addCSS = function (href, onload) {
    var id = href.replace(/\W/g, '');

    (function (d, s, id) {
        var link, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) {
            onload();
            return;
        }
        link = d.createElement(s);
        link.id = id;
        link.onload = function () {
            // remote script has loaded
            if (typeof onload === 'function') {
                onload();
            }
        };
        link.onerror = function () {
            if (typeof onload === 'function') {
                onload();
            }
        }

        link.type = 'text/css'
        link.rel = 'stylesheet'
        link.href = href
        fjs.parentNode.appendChild(link);
    }(document, 'link', id));
}