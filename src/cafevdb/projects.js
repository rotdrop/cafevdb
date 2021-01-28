import CAFEVDB from './core.js';

let Projects = CAFEVDB.Projects || {};
CAFEVDB.Projects = Projects;

(function(window, CAFEVDB, Projects) {
    Projects.method = function() {};
})(window, CAFEVDB, Projects);

export { Projects as default };
