/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

(function(window, $, CAFEVDB, undefined) {
  'use strict';

  var Notification = function() {};

  Notification.rows = [];

  Notification.hide = function($row, callback) {
    if (_.isFunction($row)) {
      // first arg is the callback
      callback = $row
      $row = undefined
    }
    if (!$row) {
      this.rows.forEach(function(item, index) {
        OC.Notification.hide(item, callback);
      });
      this.rows = [];
    } else {
      OC.Nofication.hide($row, callback);
    }
  };

  Notification.show = function(text, options) {
    const row = OC.Notification.show(text, options);
    this.rows.push(row);
    return row;
  };

  Notification.showHtml = function(text, options) {
    const row = OC.Notification.showHtml(text, options);
    this.rows.push(row);
    return row;
  };

  Notification.showTemporary = function(text, options) {
    const row = OC.Notification.showTemporary(text, options);
    this.rows.push(row);
    return row;
  };

  CAFEVDB.Notification = Notification;

})(window, jQuery, CAFEVDB);
