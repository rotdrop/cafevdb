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

var PHPMYEDIT = PHPMYEDIT || {};

(function(window, $, PHPMYEDIT, undefined) {
  'use strict';

  PHPMYEDIT.filterSelectPlaceholder = 'Select a filter Option';
  PHPMYEDIT.filterSelectChosen      =  true;
  PHPMYEDIT.filterHandler = function(theForm, theEvent) {
    var pressed_key = null;
    if (theEvent.which) {
      pressed_key = theEvent.which;
    } else {
      pressed_key = theEvent.keyCode;
    }
    if (pressed_key == 13) { // enter pressed
      theForm.submit();
      return false;
    }
    return true;
  };
  PHPMYEDIT.init = function(pmepfx) {
    $("input[type='checkbox']."+pmepfx+"-sort").change(function(event) {
      return this.form.submit();
    });
    
    $("select."+pmepfx+"-goto").change(function(event) {
      return this.form.submit();
    });
    
    $("select."+pmepfx+"-pagerows").change(function(event) {
      return this.form.submit();
    });

    $("select[class^='"+pmepfx+"-filter']").change(function(event) {
      return this.form.submit();
    });

    $("input[class^='"+pmepfx+"-filter']").keypress(function(event) {
      return this.filterHandler(this.form, event);
    });

    $("input[type='submit'].pme-save").click(function(event) {
      return this.filterHandler(this.form, event);
    });

    $("input[type='submit'].pme-more").click(function(event) {
      return this.filterHandler(this.form, event);
    });

    if (this.filterSelectChosen) {
      $("select[class^='"+pmepfx+"-comp-filter']").chosen({width:"auto",  disable_search_threshold: 10});

      // Provide a data-placeholder and also remove the match-all
      // filter, which is not needed when using chosen.
      $("select[class^='"+pmepfx+"-filter']").attr("data-placeholder", this.filterSelectPlaceholder);
      $("select[class^='"+pmepfx+"-filter']").unbind('change');
      $("select[class^='"+pmepfx+"-filter'] option[value='*']").remove();
      $("select[class^='"+pmepfx+"-filter']").chosen({width:"100%"});
    }
  };
})(window, jQuery, PHPMYEDIT);

$(document).ready(function(){

  PHPMYEDIT.init('pme');

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
