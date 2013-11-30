/**Orchestra member, musicion and project management application.
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

(function(window, $, CAFEVDB, undefined) {
  'use strict';
  var Page = function() {};
  /**Optionally collapse the somewhat lengthy text at the head of db pages.
   */
  Page.collapseHeader = function() {
    var pfx    = 'div.'+CAFEVDB.name+'-page-';
    var box    = $(pfx+'header-box');
    var header = $(pfx+'header');
    var body   = $(pfx+'body');
    var button = $(pfx+'header-box #viewtoggle');
    
    box.removeClass('expanded').addClass('collapsed');
    header.removeClass('expanded').addClass('collapsed');
    body.removeClass('expanded').addClass('collapsed');
    button.removeClass('expanded').addClass('collapsed');

    CAFEVDB.broadcastHeaderVisibility('collapsed');
  };
  /**Optionally expand the somewhat lengthy text at the head of db pages.
   */
  Page.expandHeader = function() {
    var pfx    = 'div.'+CAFEVDB.name+'-page-';
    var box    = $(pfx+'header-box');
    var header = $(pfx+'header');
    var body   = $(pfx+'body');
    var button = $(pfx+'header-box #viewtoggle');

    box.addClass('expanded').removeClass('collapsed');
    header.addClass('expanded').removeClass('collapsed');
    body.addClass('expanded').removeClass('collapsed');
    button.addClass('expanded').removeClass('collapsed');

    CAFEVDB.broadcastHeaderVisibility('expanded');
  };

  CAFEVDB.Page = Page;

})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  $('input[class^="page-input-"][class$="-birthday"]').datepicker({
    dateFormat : 'dd.mm.yy'
  });

  $('td[class$="-money"]').filter(function() {
    return $.trim($(this).text()).indexOf("-") == 0;
  }).addClass("negative");
  
  $('#cafevdb-page-header-box .viewtoggle').click(function(event) {
    event.preventDefault();

    var pfx    = 'div.'+CAFEVDB.name+'-page-';
    var box    = $(pfx+'header-box');
    var header = $(pfx+'page-header');
    var body   = $(pfx+'body');

    if (CAFEVDB.headervisibility == 'collapsed') {
      CAFEVDB.Page.expandHeader();
    } else {
      CAFEVDB.Page.collapseHeader();
    }

    return false;
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
