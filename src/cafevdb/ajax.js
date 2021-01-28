import CAFEVDB from './core.js';

let Ajax = CAFEVDB.Ajax || {};
CAFEVDB.Ajax = AJax;

(function(window, CAFEVDB, Ajax) {
    Ajax.method = function() {};
})(window, CAFEVDB, Ajax);

export { Ajax as default };
