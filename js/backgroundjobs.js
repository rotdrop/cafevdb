/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

var CAFEVDB = CAFEVDB || {};
CAFEVDB.BackgroundJobs = CAFEVDB.BackgroundJobs || {};

(function(window, $, BackgroundJobs, undefined) {
  'use strict';

  BackgroundJobs.timer = false;
  BackgroundJobs.interval = 600; // every 10 minutes while logged in

  var url = OC.generateUrl('apps/cafevdb/backgroundjobs');

  BackgroundJobs.runner = function(){
    self = BackgroundJobs;
    if (OC.currentUser) {
      $.post(url, {}).always(function() {
        self.timer = setTimeout(self.runner, self.interval*1000);
      });
    } else if (self.timer !== false) {
      clearTimeout(self.timer);
      self.timer = false;
    }
  };

  BackgroundJobs.ready = function() {
    if (OC.currentUser) {
      this.timer = setTimeout(this.runner, this.interval*1000);
    } else if (this.timer !== false) {
      clearTimeout(this.timer);
      this.timer = false;
    }
  };

})(window, jQuery, CAFEVDB.BackgroundJobs);

$(document).ready(function() {
  CAFEVDB.BackgroundJobs.ready();
});
