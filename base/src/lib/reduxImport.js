import {combineReducers} from 'redux';
import map from 'lodash.mapvalues';

const extractFunc = function (func) {

    let f = func.toString();
    f = f.substr(f.indexOf('{') + 1);
    f = f.substr(0, f.lastIndexOf('}'));
    return f;
};

const switchReducers = function (reducers) {
    let results = [];
    reducers.map(item => {
        return results.push(`
        case '${item.type}':
            ${extractFunc(item.reducer)}
        break;
        `)
    });

    return results.join("\n");
};

const extractLib = function (lib) {
    let results = lib.map(l => {
        return `const ${l} = import("./../redux/${l}")`;
    });

    return results.join("\n");
};

export const importReducers = function (rawReducers, additionalReducers) {
    let results = {};
    map(rawReducers, (r, key) => {
        let keyarr = key.split(".");
        let cursor = results;
        for (let i in keyarr) {
            let k = keyarr[i];
            if (!cursor[k]) {
                cursor[k] = {};
            }
            if (i < keyarr.length - 1) {
                cursor = cursor[k];
            } else {
                let init = extractFunc(r.init);
                let switchtype = switchReducers(r.reducers);
                let importLib = extractLib(r.import);

                //eslint-disable-next-line
                cursor[k] = new Function('state', '{ payload, type }', `
                    ${importLib}

                    let initState = function () {
                        ${init}
                    }.bind(this)();

                    if (typeof state === 'undefined') {
                        return initState;
                    }

                    switch (type) {
                        ${switchtype}
                    }

                    return state;
                `);
            }
        }
    });

    if (typeof additionalReducers !== "undefined") {
        for (let j in additionalReducers) {
            results[j] = additionalReducers[j];
        }
    }

    let combine = (reducers) => {
        for (let i in reducers) {
            if (typeof reducers[i] === "object") {
                reducers[i] = combine(reducers[i]);
            }
        }

        return combineReducers(reducers);
    };

    return combine(results);
};