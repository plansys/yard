import { combineReducers } from 'redux';

export const importReducers = function (rawReducers, additionalReducers) {
    var reducers = {};

    for (var i in rawReducers) {
        var store = rawReducers[i];

        for (var k in store) {
            var r = store[k];
            var key = i + '__' + k;
            this[key] = {};

            var importReduxLib = r.import.map(lib => {
                return import('./../redux/' + lib);
            });
            
            // eslint-disable-next-line
            reducers[key] = function (state, { payload, type }) {
                if (typeof state === "undefined") { 
                    // eslint-disable-next-line
                    state = (new Function(...[...r.import, 'r'] , `return r.init()`)).bind(this[key])(...[...importReduxLib, r]);
                }
                
                for (var x in r.reducers) {
                    var item = r.reducers[x];
                    if (item.type === type) {
                        // eslint-disable-next-line
                        state = (new Function(...[...r.import, 'item','state', 'payload'], `return item.reducer(state, payload)`)).bind(this[key])(...[...importReduxLib, item, state, payload]);
                    }
                }
                
                return state;
            }.bind(this)
        }
    }

    if (typeof additionalReducers !== "undefined") {
        for (var j in additionalReducers) {
            reducers[j] = additionalReducers[j];
        }
    }

    return combineReducers(reducers);
}