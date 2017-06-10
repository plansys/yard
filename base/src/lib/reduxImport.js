import { combineReducers } from 'redux';
import Immutable from 'immutable';

export const importReducers = function (rawReducers, additionalReducers) {
    var reducers = {};

    for (var i in rawReducers) {
        var store = rawReducers[i];

        for (var k in store) {
            var r = store[k];
            var key = i + '__' + k;
            this[key] = {};

            // eslint-disable-next-line
            reducers[key] = function (state, { payload, type }) {
                if (typeof state === "undefined") { 
                    state = r.init.bind(this[key])(Immutable);
                }
                
                for (var x in r.reducers) {
                    var item = r.reducers[x];
                    if (item.type === type) {
                        state = item.reducer.bind(this[key])(state, payload, Immutable);
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