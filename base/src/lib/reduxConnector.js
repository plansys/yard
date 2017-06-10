import Loader from './../Loader';

export const mapInput = function (input) {
    var list = input();
    return function (store, props) {
        var result = {};

        for (var p in list) {
            switch (typeof list[p]) {
                case "string":

                    // eslint-disable-next-line
                    result[p] = function (store) {
                        var keys = Object.keys(store);
                        var values = Object.values(store);
                        var alias = list[p].replace('.', '__');

                        // eslint-disable-next-line
                        return (new Function(...keys, `return ${alias}`)).bind(this)(...values);
                    }(store, props)
                    break;
                case "function":

                    // eslint-disable-next-line
                    result[p] = function (store, props) {
                        return list[p](store, props);
                    }(store, props)
                    break;
                default:
                break;
            }
        }

        return result;
    }
}


const printKeys = function (obj, stack = '', result = '') {
    for (var property in obj) {
        if (obj.hasOwnProperty(property)) {
            if (typeof obj[property] === "object") {
                result += printKeys(obj[property], stack + '.' + property, result);
            } else {
                result += "   - " + stack.substr(1) + '.' + property + "\n";
            }
        }
    }
    return result;
}

export const mapAction = function (action) {
    var list = action();
    var result = {};

    for (var p in list) {
        var act = list[p]
        switch (typeof act) {
            case "string":
                result[p] = function () {
                    var actions = Loader.redux.actionCreators
                    var keys = Object.keys(actions);
                    var values = Object.values(actions);

                    // eslint-disable-next-line
                    var func = (new Function(...keys, `return ${this}`))(...values);
                    if (typeof func !== "function") {
                        console.error("Action [" + this + "] is not defined!\n Avilable actions are: \n\n" +
                                printKeys(actions));
                        return { type: "~~ERROR~~"};
                    }
                    
                    return func(...arguments);
                }.bind(act);
                break;
            default:
                break;
        }
    }


    return result;
}
