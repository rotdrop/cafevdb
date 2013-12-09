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
  PHPMYEDIT.filterSelectNoResult    = 'No values match';
  PHPMYEDIT.filterSelectChosen      =  true;
  PHPMYEDIT.filterSelectChosenTitle = 'Select from the pull-down menu. Double-click will submit the form.';
  PHPMYEDIT.inputSelectPlaceholder = 'Select an option';
  PHPMYEDIT.inputSelectNoResult    = 'No values match';
  PHPMYEDIT.inputSelectChosen      =  true;
  PHPMYEDIT.inputSelectChosenTitle = 'Select from the pull-down menu.';
  PHPMYEDIT.chosenPixelWidth        = [];
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
      return PHPMYEDIT.filterHandler(this.form, event);
    });

    $("input[type='submit'].pme-save").click(function(event) {
      return PHPMYEDIT.filterHandler(this.form, event);
    });

    $("input[type='submit'].pme-more").click(function(event) {
      return PHPMYEDIT.filterHandler(this.form, event);
    });

    if (this.filterSelectChosen) {
      var noRes = this.filterSelectNoResult;

      $("select[class^='"+pmepfx+"-comp-filter']").chosen({width:"auto", disable_search_threshold: 10});

      // Provide a data-placeholder and also remove the match-all
      // filter, which is not needed when using chosen.
      $("select[class^='"+pmepfx+"-filter']").attr("data-placeholder", this.filterSelectPlaceholder);
      $("select[class^='"+pmepfx+"-filter']").unbind('change');
      $("select[class^='"+pmepfx+"-filter'] option[value='*']").remove();

      // Play a dirty trick in order not to pass width:auto to chosen
      // for some particalar thingies
      var k;
      for (k = 0; k < PHPMYEDIT.chosenPixelWidth.length; ++k) {
        var tag = PHPMYEDIT.chosenPixelWidth[k];
        var pxlWidth = Math.round($("td[class^='"+pmepfx+"-filter-"+tag+"']").width());
        $("select[class^='"+pmepfx+"-filter-"+tag+"']").chosen({width:pxlWidth+60+'px',
                                                                no_results_text:noRes});
      }
        
      // Then the general stuff
      $("select[class^='"+pmepfx+"-filter']").chosen({width:'100%',
                                                      no_results_text:noRes});

      $("td[class^='"+pmepfx+"-filter'] ul.chosen-choices li.search-field input[type='text']").dblclick(function(event) {
        return $("form[class^='"+pmepfx+"-form']").submit();
//        return this.form.submit();
      });

      $("td[class^='"+pmepfx+"-filter'] div.chosen-container").dblclick(function(event) {
        return $("form[class^='"+pmepfx+"-form']").submit();
      });

      $("td[class^='"+pmepfx+"-filter'] div.chosen-container").attr("title",this.filterSelectChosenTitle);
    }

    if (this.inputSelectChosen) {
      var noRes = this.inputSelectNoResult;

      // Provide a data-placeholder and also remove the match-all
      // filter, which is not needed when using chosen.
      $("select[class^='"+pmepfx+"-input']").attr("data-placeholder", this.inputSelectPlaceholder);
      $("select[class^='"+pmepfx+"-input']").unbind('change');
      $("select[class^='"+pmepfx+"-input'] option[value='*']").remove();

      // Play a dirty trick in order not to pass width:auto to chosen
      // for some particalar thingies
      var k;
      for (k = 0; k < PHPMYEDIT.chosenPixelWidth.length; ++k) {
        var tag = PHPMYEDIT.chosenPixelWidth[k];
        var pxlWidth = Math.round($("td[class^='"+pmepfx+"-input-"+tag+"']").width());
        $("select[class^='"+pmepfx+"-input-"+tag+"']").chosen({width:pxlWidth+'px',
                                                               disable_search_threshold: 10,
                                                               no_results_text:noRes});
      }
        
      // Then the general stuff
      $("select[class^='"+pmepfx+"-input']").chosen({//width:'100%',
                                                     disable_search_threshold: 10,
                                                     no_results_text:noRes});

      $("td[class^='"+pmepfx+"-input'] div.chosen-container").attr("title",this.inputSelectChosenTitle);
    }


  };
})(window, jQuery, PHPMYEDIT);

$(document).ready(function(){

  PHPMYEDIT.init('pme');

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
