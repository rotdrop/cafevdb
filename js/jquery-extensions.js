/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**@file
 *
 * Collect some jQuery tweaks in this file.
 *
 */

var CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {

  /**Special dialog version which attaches the dialog to the
   * #content-wrapper div.
   */
  $.fn.cafevDialog = function(argument) {
    if (arguments.length == 1 && typeof argument == 'object' && argument != null) {
      var options = {
        appendTo: '#cafevdb-general',
      };
      argument = $.extend({}, options, argument);
      CAFEVDB.snapperClose();
      $.fn.dialog.call(this, argument);
      $.fn.dialog.call(this, 'widget').draggable('option', 'containment', '#content');
    } else {
      $.fn.dialog.apply(this, arguments);
    }
    return this;
  };

  // $.extend($.ui.dialog.prototype.options, {
  //   appendTo: '#content',
  //   containment: '#content'
  // });

  /**Extend the tooltips to honour some special class elements, and
   * attach user specified tooltip-... classes to the actual tooltip
   * popups.
   */
  $.fn.cafevTooltip = function(argument) {
    if (typeof argument == 'undefined') {
      argument = {};
    }
    if (typeof argument == 'object' && argument != null) {
      var options = {
        container:'body',
        html:true,
        placement:'auto top',
        cssclass:[]
      }
      argument = $.extend({}, options, argument);
      if (typeof argument.placement == 'string' && !argument.placement.match(/auto/)) {
        argument.placement = 'auto '+argument.placement;
      }
      if (argument.cssclass && typeof argument.cssclass == 'string') {
        argument.cssclass = [ argument.cssclass ];
      }
      argument.cssclass.push('cafevdb');
      // iterator over individual element in order to pick up the
      // correct class-arguments.
      this.each(function(index) {
        var self = $(this);
        var selfOptions = $.extend({}, argument);
        var classAttr = self.attr('class');
        if (classAttr) {
          if (classAttr.match(/tooltip-off/) !== null) {
            self.cafevTooltip('disable');
            return;
          }
          var tooltipClasses = classAttr.match(/tooltip-[a-z-]+/g);
          if (tooltipClasses) {
            var idx;
            for(idx = 0; idx < tooltipClasses.length; ++idx) {
              var tooltipClass = tooltipClasses[idx];
              var placement = tooltipClass.match(/^tooltip-(bottom|top|right|left)$/);
              if (placement && placement.length == 2 && placement[1].length > 0) {
                selfOptions.placement = 'auto '+placement[1];
                continue;
              }
              selfOptions.cssclass.push(tooltipClass);
            }
          }
        }
        $.fn.tooltip.call(self, 'destroy');
        var originalTitle = self.data('original-title');
        if (originalTitle && !self.attr('title')) {
          self.attr('title', originalTitle);
        }
        self.removeAttr('data-original-title');
        self.removeData('original-title');
        var title = self.attr('title');
        if (title == undefined || title.trim() == '') {
          self.removeAttr('title');
          self.cafevTooltip('disable');
          return;
        }
        if (!selfOptions.template) {
          selfOptions.template = '<div class="tooltip '
                               + selfOptions.cssclass.join(' ')
                               + '" role="tooltip">'
                               + '<div class="tooltip-arrow"></div>'
                               + '<div class="tooltip-inner"></div>'
                               + '</div>';
        }
        $.fn.tooltip.call(self, selfOptions);
      });
    } else {
      $.fn.tooltip.apply(this, arguments);
    }
    return this;
  };

  $.fn.cafevTooltip.enable = function() {
    $('[data-original-title]').cafevTooltip('enable');
  }

  $.fn.cafevTooltip.disable = function() {
    $('[data-original-title]').cafevTooltip('disable');
  }

  $.fn.cafevTooltip.remove = function() {
    $('div.tooltip[role=tooltip]').remove();
  };

  $.fn.cafevTooltip.hide = function() {
    $('[data-original-title]').cafevTooltip('hide');
  };

  $.extend({ alert: function (message, title) {
               $("<div></div>").dialog( {
                 buttons: { "Ok": function () { $(this).dialog("close"); } },
                 open: function(event, ui) {
                   $(this).css({'max-height': 800, 'overflow-y': 'auto', 'height': 'auto'});
                   $(this).dialog( "option", "resizable", false );
                 },
                 close: function (event, ui) { $(this).remove(); },
                 resizable: false,
                 title: title,
                 modal: true,
                 height: "auto"
               }).html(message);
             }
           });


})(window, jQuery, CAFEVDB);
