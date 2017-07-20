import { combineReducers } from 'redux';
import map from 'lodash.mapvalues';

const extractFunc = function(func) {
    let f = func.toString();
    f = f.substr(f.indexOf('{') + 1);
    f = f.substr(0, f.lastIndexOf('}'));
    return f;
}

const switchReducers = function(reducers) {
    let results = [];
    reducers.map(item => {
        return results.push(`
        case '${item.type}':
            ${extractFunc(item.reducer)}
        break;
        `)
    });
    
    return results.join("\n");
}

const extractLib = function(lib) {
    let results = lib.map(l => {
        return `const ${l} = import("./../redux/${l}")`;
    });
    
    return results.join("\n");
}

export const importReducers = function (rawReducers, additionalReducers) {
    
    let reducers = {};
    
    // flatten reducers
    map(rawReducers, (rawstore, rawkey) => {
        return map(rawstore, (store, key) => {
            reducers[rawkey + '__' + key] = store;
        })
    });
    
    let results = {};
    
    map(reducers, (r, key) => {
        let init = extractFunc(r.init);
        let switchtype = switchReducers(r.reducers);
        let importLib = extractLib(r.import);
        let funcStr = `
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
        `;

        // eslint-disable-next-line
        results[key] = new Function('state', '{ payload, type }', funcStr);
    })
    
    if (typeof additionalReducers !== "undefined") {
        for (let j in additionalReducers) {
            results[j] = additionalReducers[j];
        }
    }

    return combineReducers(results);
}