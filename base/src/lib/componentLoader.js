/*global Babel */
import { addJS } from './injectTag';

// helper function to execute the actual import component
export const componentLoader = (tag) => {
    var etag = tag.replace(/\./g, '/');

    return import (`./../ui/${etag}/index.js`)
        .then(res => {
            if (res.default) {
                return res.default;
            }
            else {
                return res;
            }
        })
}

export const loadConf = (alias, isRoot) => {
    var url = window.yardurl.page
                .replace('[page]', alias)
                .replace('[mode]', (isRoot ? 'r|' : '') + 'js');

    return fetch(url)
        .then(res => {
            return res.text().then(text => {
                var trimmedText = text;
                if (trimmedText.length < 10) {
                    throw new Error('empty response');
                }
                // if the text is already formatted in babel(es2015), then return it
                if (trimmedText.indexOf('use strict') >= 0) {
                    return new Promise(resolve => {
                        resolve(text)
                    });
                }

                // if request is not redirected then it is not in babel (es2015) format,
                // we need to convert it to babel and then send the formatted js
                // to php server, so they can cache it and serve it to us later.
                // 
                // we need to do this in client side, because it is too complex to convert
                // es5 to es2015 in php (server side)
                return new Promise((resolve, reject) => {
                    const babelUrl = window.yardurl.base + '/babel.min.js';
                    const postUrl = window.yardurl.page
                                        .replace('[page]', alias)
                                        .replace('[mode]', (isRoot ? 'r|' : '')+ 'post');
                    const clearUrl = window.yardurl.page
                                        .replace('[page]', alias)
                                        .replace('[mode]', (isRoot ? 'r|' : '')+ 'clean');

                    addJS(babelUrl, 'babel', () => {
                        var success = true;
                        try {
                            console.log("Transpiling: " + alias );
                            var output = Babel.transform('var vconf = ' + text, { /* eslint-disable-line rule-name */
                                presets: ['es2015', 'react', 'stage-1']
                            }).code;
                        }
                        catch (e) {
                            success = false;
                            fetch(clearUrl);
                            throw e;
                            // eslint-disable-next-line
                            return;
                        }

                        if (success) {
                            fetch(postUrl, {
                                method: "POST",
                                body: output
                            })

                            resolve(output);
                        }
                    });
                })
            });
        });
}

export const parseConf = (rawconf, alias) => {
    // eslint-disable-next-line
    return new Function(rawconf + ";\n return vconf;")();
}
