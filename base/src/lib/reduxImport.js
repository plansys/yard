import { combineReducers } from 'redux';
import map from 'lodash.mapvalues';

const extractFunc = function(func) {
    var f = func.toString();
    f = f.substr(f.indexOf('{') + 1);
    f = f.substr(0, f.lastIndexOf('}'));
    return f;
}

const switchReducers = function(reducers) {
    var results = [];
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
    var results = lib.map(l => {
        return `const ${l} = import("./../redux/${l}")`;
    });
    
    return results.join("\n");
}

export const importReducers = function (rawReducers, additionalReducers) {
    
    var reducers = {};
    
    // flatten reducers
    map(rawReducers, (rawstore, rawkey) => {
        return map(rawstore, (store, key) => {
            reducers[rawkey + '__' + key] = store;
        })
    });
    
    var results = {};
    
    map(reducers, (r, key) => {
        var init = extractFunc(r.init);
        var switchtype = switchReducers(r.reducers);
        var importLib = extractLib(r.import);
        
        // eslint-disable-next-line
        results[key] = new Function('state', '{ payload, type }', `
            ${importLib}
            
            if (typeof state === 'undefined') {
                ${init}
            }
        
            switch (type) {
                ${switchtype}
            }
            
            return state;
        `);
    })
    
    if (typeof additionalReducers !== "undefined") {
        for (var j in additionalReducers) {
            results[j] = additionalReducers[j];
        }
    }

    return combineReducers(results);
}