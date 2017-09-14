import PageLoader from './../PageLoader';

export const mapInput = function (input) {
    let list = input();
    return function (store, props) {
        let result = {};

        for (let p in list) {
            switch (typeof list[p]) {
                case "string":
                    // eslint-disable-next-line
                    result[p] = function (store) {
                        let keys = Object.keys(store);
                        let values = Object.keys(store).map((key) => store[key]);
                        let alias = list[p];

                        // eslint-disable-next-line
                        return (new Function(...keys, `return ${alias}`)).bind(this)(...values);
                    }(store, props);
                    break;
                case "function":
                    // eslint-disable-next-line
                    result[p] = function (store, props) {
                        return list[p](store, props);
                    }(store, props);
                    break;
                default:
                    break;
            }
        }

        return result;
    }
}


const printKeys = function (obj, stack = '', result = '') {
    for (let property in obj) {
        if (obj.hasOwnProperty(property)) {
            if (typeof obj[property] === "object") {
                result += printKeys(obj[property], stack + '.' + property, result);
            } else {
                result += "   - " + stack.substr(1) + '.' + property + "\n";
            }
        }
    }
    
    let cleanedResult = [];
    result.split('\n').forEach(item => {
        if (cleanedResult.indexOf(item) < 0) {
            cleanedResult.push(item);
        }
    })
    return cleanedResult.join("\n");
};

export const mapAction = function (action) {
    let list = action();
    let result = {};

    for (let p in list) {
        let act = list[p];
        switch (typeof act) {
            case "string":
                result[p] = function () {
                    let actions = PageLoader.redux.actionCreators;

                    let keys = Object.keys(actions).map(key => key.replace('.', '_'));
                    let values = Object.keys(actions).map((key) => actions[key]);

                    // eslint-disable-next-line
                    let func = (new Function(...keys, `return ${this}`))(...values);
                    if (typeof func !== "function") {
                        console.error("Action [" + this + "] is not defined!\n Available actions are: \n\n" +
                            printKeys(actions));
                        return { type: "~~ERROR~~" };
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
