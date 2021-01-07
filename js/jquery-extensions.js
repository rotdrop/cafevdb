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
/**@file
 *
 * Collect some jQuery tweaks in this file.
 *
 */

var CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {

  console.log("jquery-extensions");

  /**We leave it to the z-index-plane to disallow interaction. Every
   * input element above any modal dialog is allowed to interact with
   * the user.
   */
  $.widget('ui.dialog', $.ui.dialog, {
    _allowInteraction: function(event) {
      return true;
    }
  });

  /**Special dialog version which attaches the dialog to the
   * #content-wrapper div.
   */
  $.fn.cafevDialog = function(argument) {
    if (arguments.length == 1 && typeof argument == 'object' && argument != null) {
      var options = {
        appendTo: '#cafevdb-general'
        //appendTop: 'body',
      };
      argument = $.extend({}, options, argument);
      if (argument.dialogClass) {
        argument.dialogClass += ' cafev cafevdb';
      } else {
        argument.dialogClass = 'cafev cafevdb';
      }
      if ($('#appsettings_popup').length == 0) {
        CAFEVDB.snapperClose();
      }
      console.log("will open dialog");
      $.fn.dialog.call(this, argument);
      if (this.dialog('option', 'draggable')) {
        console.log("Try to set containment");
        $.fn.dialog.call(this, 'widget').draggable('option', 'containment', '#app-content');
      }
    } else {
      return $.fn.dialog.apply(this, arguments);
    }
    return this;
  };

  // $.extend($.ui.dialog.prototype.options, {
  //   appendTo: '#content',
  //   containment: '#content'
  // });

  /**Determine whether scrollbars would be needed. */
  $.fn.needScrollbars = function() {
    var node = this.get(0);
    return {
      vertical: node.scrollHeight > node.offsetHeight,
      horizontal: node.scrollWidth > node.offsetWidth
    };
  }

  /**Determine whether scrollbars are actually present.
   *
   * We have here the problem that
   *
   * - node.boundingClientRect() does not return the scrollHeight/Width
   * - scrollHeight/Width is rounded
   * - clientHeight/Width is rounded
   *
   * Hence the +1 is an ugly tweak which seems to work a little bit.
   *
   */
  $.fn.hasScrollbars = function() {
    var node = this.get(0);
    return {
      vertical: node.scrollHeight > node.clientHeight + 1,
      horizontal: node.scrollWidth > node.clientWidth + 1
    };
  };

  /**Determine dimensions of scrollbars. */
  $.fn.scrollbarDimensions = function() {
    var node = this.get(0);
    return {
      height: node.offsetHeight - node.clientHeight + 1,
      width: node.offsetWidth - node.clientWidth + 1
    };
  };

  /**Determine whether we have a horizontal scrollbar. */
  $.fn.hasHorizontalScrollbar = function() {
    var node = this.get(0);
    return node.scrollWidth > node.clientWidth + 1;
  };

  /**Determine whether we have a vertical scrollbar. */
  $.fn.hasVerticalScrollbar = function() {
    var node = this.get(0);
    return node.scrollHeight > node.clientHeight + 1;
  };

  /**Determine vertical scrollbar width. */
  $.fn.verticalScrollbarWidth = function() {
    var node = this.get(0);
    return node.offsetWidth - node.clientWidth;
  }

  /**Determine horizontal scrollbar height. */
  $.fn.horizontalScrollbarHeight = function() {
    var node = this.get(0);
    return node.offsetHeight - node.clientHeight;
  }

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
        container: 'body',
        html: true,
        placement: 'auto',
        cssclass: [],
        fallbackPlacement: 'flip',
        boundary: 'viewport'
        //, delay: { "show": 500, "hide": 10000 }
      }
      argument = $.extend(true, {}, options, argument);
      if (typeof argument.placement == 'string' && !argument.placement.match(/auto/)) {
        argument.placement = argument.placement;
      }
      if (argument.cssclass && typeof argument.cssclass == 'string') {
        argument.cssclass = [ argument.cssclass ];
      }
      argument.cssclass.push('cafevdb');
      // iterator over individual element in order to pick up the
      // correct class-arguments.
      this.each(function(index) {
        var self = $(this);
        var selfOptions = $.extend(true, {}, argument);
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
                selfOptions.placement = placement[1];
                continue;
              }
              selfOptions.cssclass.push(tooltipClass);
            }
          }
        }
        $.fn.tooltip.call(self, 'dispose');
        var originalTitle = self.data('original-title');
        if (originalTitle && !self.attr('title')) {
          self.attr('title', originalTitle);
        }
        self.removeAttr('data-original-title');
        self.removeData('original-title');
        var title = self.attr('title');
        if (title == undefined || title.trim() == '') {
          self.removeAttr('title');
          self.cafevTooltip('destroy');
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
      if (argument === 'destroy') {
        argument = 'dispose';
      }
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

  // remove left-over tooltips
  $.fn.cafevTooltip.remove = function() {
    $('div.tooltip[role=tooltip]').each(function(index) {
      const tip = $(this);
      const id = tip.attr('id');
      $('[aria-describedby='+id+']').removeAttr('aria-describedby');
      $(this).remove();
    });
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

  /**Compute the maximum width of a set of elements */
  $.fn.maxWidth = function() {
    return Math.max.apply(null, this.map(function () {
                                  return $(this).width();
                                }).get());
  };

  /**Compute the maximum width of a set of elements */
  $.fn.maxOuterWidth = function(extended) {
    return Math.max.apply(null, this.map(function () {
                                  return $(this).outerWidth(extended);
                                }).get());
  };

  /**Compute the maximum height of a set of elements */
  $.fn.maxHeight = function() {
    return Math.max.apply(null, this.map(function () {
                                  return $(this).height();
                                }).get());
  };

  /**Compute the maximum height of a set of elements */
  $.fn.maxOuterHeight = function(extended) {
    return Math.max.apply(null, this.map(function () {
                                  return $(this).outerHeight(extended);
                                }).get());
  };

  /*--------------------------------------------------------------------
   * jQuery pixel/em conversion plugins: toEm() and toPx()
   * by Scott Jehl (scott@filamentgroup.com), http://www.filamentgroup.com
   * Copyright (c) Filament Group
   *
   * Dual licensed under the MIT
   * (filamentgroup.com/examples/mit-license.txt) or GPL
   * (filamentgroup.com/examples/gpl-license.txt) licenses.  Article:
   * http://www.filamentgroup.com/lab/update_jquery_plugin_for_retaining_scalable_interfaces_with_pixel_to_em_con/
   *
   * Options:
   *   scope: string or jQuery selector for font-size scoping
   * Usage Example: $(myPixelValue).toEm(); or $(myEmValue).toPx();
   --------------------------------------------------------------------*/

  $.fn.toEm = function(settings){
    settings = jQuery.extend({
      scope: 'body'
    }, settings);
    var that = parseInt(this[0],10),
	scopeTest = jQuery('<div style="display: none; font-size: 1em; margin: 0; padding:0; height: auto; line-height: 1; border:0;">&nbsp;</div>').appendTo(settings.scope),
	scopeVal = scopeTest.height();
    scopeTest.remove();
    return (that / scopeVal).toFixed(8) + 'em';
  };


  $.fn.toPx = function(settings){
    settings = jQuery.extend({
      scope: 'body'
    }, settings);
    var that = parseFloat(this[0]),
	scopeTest = jQuery('<div style="display: none; font-size: 1em; margin: 0; padding:0; height: auto; line-height: 1; border:0;">&nbsp;</div>').appendTo(settings.scope),
	scopeVal = scopeTest.height();
    scopeTest.remove();
    return Math.round(that * scopeVal) + 'px';
  };

})(window, jQuery, CAFEVDB);
