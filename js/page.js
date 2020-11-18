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

  var Page = function() {};

  Page.historyPosition = 0;
  Page.historySize = 1;

  // overrides from PHP, see config.js
  $.extend(Page, CAFEVDB.initialState.Page);

  Page.busyIcon = function(on) {
    if (on) {
      $('#reloadbutton img.number-0').hide();
      $('#reloadbutton img.number-1').show();
    } else {
      $('#reloadbutton img.number-1').hide();
      $('#reloadbutton img.number-0').show();
    }
  };

  /**Load a page through the history-aware AJAX page loader. */
  Page.loadPage = function(post, afterLoadCallback) {
    const container = $('div#content');
    $('body').removeClass('dialog-titlebar-clicked');
    CAFEVDB.modalizer(true),
    Page.busyIcon(true);
    var action;
    var parameter;
    if (post.historyOffset !== undefined) {
      action = 'recall';
      parameter = post.historyOffset;
    } else {
      action = 'remember';
      parameter = 'blank'
    }
    $.post(CAFEVDB.generateUrl('page/' + action + '/' + parameter), post)
    .fail(function(xhr, status, errorThrown) {
      const errorData = CAFEVDB.handleAjaxError(xhr, status, errorThrown);
      // If the error response contains history data, use it. Othewise
      // reset the history
      if (action == 'recall') {
        if (errorData.history !== undefined) {
          CAFEVDB.Page.historyPosition = errorData.history.position;
          CAFEVDB.Page.historySize = errorData.history.size;
        } else {
          CAFEVDB.Page.historyPosition = 0;
          CAFEVDB.Page.historySize = 0;
        }
        CAFEVDB.Page.updateHistoryControls();
      }
      CAFEVDB.modalizer(false),
      Page.busyIcon(false);
    })
    .done(function(htmlContent, textStatus, request) {
      //console.log(data);
      const historySize = request.getResponseHeader('X-'+CAFEVDB.appName+'-history-size');
      const historyPosition = request.getResponseHeader('X-'+CAFEVDB.appName+'-history-position');

      // if (!CAFEVDB.validateAjaxResponse(data, [ 'contents', 'history' ])) {
      //   // re-enable inputs on error
      //   if (false) {
      //     container.find('input').prop('disabled', false);
      //     container.find('select').prop('disabled', false);
      //     container.find('select').trigger('chosen:updated');
      //   }
      //   CAFEVDB.modalizer(false),
      //   Page.busyIcon(false);
      //   return false;
      // }

      // Remove pending dialog when moving away from the page
      $('.ui-dialog-content').dialog('destroy').remove();

      CAFEVDB.Page.historyPosition = historyPosition;
      CAFEVDB.Page.historySize = historySize;

      // remove left-over notifications
      CAFEVDB.Notification.hide();

      // remove left-over tool-tips
      $.fn.cafevTooltip.remove();

      // This is a "complete" page reload, so inject the
      // contents into #contents.
      //
      // avoid overriding event handler, although this should
      // be somewhat slower than replacing everything in one run.

      const appGeneralId = CAFEVDB.appName+'-general';
      const newContent = $('<div>'+htmlContent+'</div>');
      const newAppContent = newContent.find('#'+appGeneralId).children();
      const newAppNavigation = newContent.find('#app-navigation').children();

      $('#app-navigation').empty().prepend(newAppNavigation);
      $('#'+appGeneralId).empty().prepend(newAppContent);

      CAFEVDB.snapperClose();
      CAFEVDB.modalizer(false),
      Page.busyIcon(false);

      CAFEVDB.runReadyCallbacks();
      if (typeof afterLoadCallback == 'function') {
        afterLoadCallback();
      }

      return false;
    });
  };

  Page.updateHistoryControls = function() {
    var redo = $('#personalsettings .navigation.redo');
    var undo = $('#personalsettings .navigation.undo');

    // console.info(undo);

    //alert('history: '+CAFEVDB.Page.historyPosition+' size '+CAFEVDB.Page.historySize);
    redo.prop('disabled', CAFEVDB.Page.historyPosition == 0);
    undo.prop('disabled', CAFEVDB.Page.historySize - CAFEVDB.Page.historyPosition <= 1);
  };

  /**
   * Optain the service-key for querying the app-container for the
   * renderer class for the given template.
   */
  Page.renderTag = 'template:';
  Page.templateRenderer = function(template) {
    return Page.renderTag + template;
  };

  Page.templateFromRenderer = function(templateRenderer) {
    return templateRenderer.replace(Page.renderTag, '');
  };

  CAFEVDB.Page = Page;

})(window, jQuery, CAFEVDB);

$(function(){

  var appInnerContent = $('#app-inner-content');

  appInnerContent.on('click', '.ui-dialog-titlebar', function(event) {
    $('body').toggleClass('dialog-titlebar-clicked');
    return false;
  });

  $('#app-navigation-toggle').on('click', function() {
    $('body').removeClass('dialog-titlebar-clicked');
    $(this).cafevTooltip('hide');
  });

  appInnerContent.on('click keydown',
             '#personalsettings .navigation.reload',
             function(event) {
               event.stopImmediatePropagation();
               var pmeReload = appInnerContent.find('form.pme-form input.pme-reload').first();
               if (pmeReload.length > 0) {
                 // remove left-over notifications
                 CAFEVDB.Notification.hide();
                 pmeReload.trigger('click');
                 $('body').removeClass('dialog-titlebar-clicked');
               } else {
                 CAFEVDB.Page.loadPage({
                   'historyOffset': 0
                 });
               }
               return false;
             });

  appInnerContent.on('click keydown',
             '#personalsettings .navigation.undo',
             function(event) {
               event.stopImmediatePropagation();
               CAFEVDB.Page.loadPage({
                 'historyOffset': 1
               });
               return false;
             });

  appInnerContent.on('click keydown',
             '#personalsettings .navigation.redo',
             function(event) {
               event.stopImmediatePropagation();
               CAFEVDB.Page.loadPage({
                 'historyOffset': -1
               });
               return false;
             });

  CAFEVDB.addReadyCallback(function() {
    //content.find('form.pme-form input.pme-reload').hide();
    $('#app-navigation-toggle').
      attr('title', t(CAFEVDB.appName, 'Display the application menu and settings side-bar')).
      cafevTooltip({
        placement: 'auto',
        container: '#app-content'
      });
    CAFEVDB.Page.updateHistoryControls();
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
