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
  var projectInstruments = function() {};
  
  projectInstruments.actions = function(select, container) {
  
    var selected = select.find('option:selected').val();
    var values = select.attr('name');
    var optionValues = selected.split('?');

    selected = optionValues[0];

    switch (selected) {
    case 'transpose':
      var isTransposed = (container.find('input[name="Transpose"]').val() == 'transposed' ||
                          container.find('#pme-transpose-up').hasClass('pme-transposed') ||
                          container.find('#pme-transpose-down').hasClass('pme-transposed') ||
                          container.find('#pme-transpose').hasClass('pme-transposed'));
      var inhibitTranspose = container.find('input[name="InhibitTranspose"]').val() == 'true';
      if (!inhibitTranspose) {
        PHPMYEDIT.maybeTranspose(!isTransposed, container);
      }
      break;
    case 'transfer-instruments':
      var post = optionValues[1];
      OC.Notification.hide(function() {
        $.post(OC.filePath('cafevdb', 'ajax/instruments', 'adjustInstrumentation.php'),
               post,
               function (data) {
                 var rqData = '';
                 if (data.status == 'success') {
                   rqData = data.data;
                   if (rqData.message != '') {
                     OC.Notification.show(rqData.message);
                   }
                 } else if (data.status == 'error') {
                   rqData = data.data;
                   if (rqData.error != 'exception') {
                     if (rqData.message == '') {
                       rqData.message = t('cafevdb', 'Unkown Error');
                     }
                     OC.Notification.show(rqData.message);
                   } else {
                     OC.dialogs.alert(rqData.exception+rqData.trace,
                                      t('cafevdb', 'Caught a PHP Exception'),
                                      null, true);
                   }
                 }
                 setTimeout(function() {
                   OC.Notification.hide(function() {
                     // Anyhow, reload and see what happens.
                     if (!PHPMYEDIT.triggerSubmit('morechange')) {
                       PHPMYEDIT.pseudoSubmit(container.find('form.pme-form'), $(),
                                              PHPMYEDIT.container(container));
                     }
                   });
                 }, 1000);
               });
      });
      break;
    default:
      OC.dialogs.alert(t('cafevdb', 'Unknown operation:')
                       +' "'+selected+'"',
                       t('cafevdb', 'Unimplemented'),
                       null, true);
      break;
    }

    // Cheating. In principle we mis-use this as a simple pull-down
    // menu, so let the text remain at its default value. Make sure to
    // also remove and re-attach the tool-tips, otherwise some of the
    // tips remain, because chosen() removes the element underneath.
    
    select.children('option').each(function(i, elm) {
      $(elm).removeAttr('selected');
    });
    $('.tipsy').remove();

    select.trigger("chosen:updated");

    $('div.chosen-container').tipsy({gravity:'sw', fade:true});
    $('li.active-result').tipsy({gravity:'w', fade:true});

    // Seemingly, this needs to be adjusted again after tweaking tipsy.
    if (CAFEVDB.toolTips) {
      $.fn.tipsy.enable();
    } else {
      $.fn.tipsy.disable();
    }

    return false;
  };

  // Emulate a pull-down menu via chosen jQuery plugin
  projectInstruments.actionMenu = function(containerSel) {
    var container = PHPMYEDIT.container(containerSel);
    var actions = container.find('select.pme-instrumentation-actions-choice');

    actions.chosen({ disable_search:true });  
    actions.off('change'); // safeguard
    actions.change(function (event) {
      event.preventDefault();

      return projectInstruments.actions($(this), container);
    });

    actions.off('chosen:showing_dropdown');
    actions.on('chosen:showing_dropdown', function (chosen) {
      container.find('ul.chosen-results li.active-result').tipsy({gravity:'w', fade:true});
    });
  };

  projectInstruments.openAddInstrumentsDialog = function(selector) {
    var container = PHPMYEDIT.container(selector);

    //$('#add-instruments-button').hide();
    //$('#add-instruments-block div.chosen-container').show();
    container.find('#add-instruments-block').dialog({
      title: t('cafevdb', 'Change Project Instrumentation'),
      dialogClass: 'cafevdb-project-instruments no-close',
      modal: true,
      closeOnEscape: false,
      width: 'auto',
      height: 'auto',
      resizable: false,
      buttons: [
        { text: t('cafevdb', 'Save'),
          class: 'save',
          title: t('cafevdb', 'Save the new instrumentation and '+
                   'continue adjusting the instrumentation numbers. '+
                   'The input-form will reload and display the updated list of instruments.'),
          click: function() {
            var self = this;

            var projectId = container.find('input[name="ProjectId"]').val();
            var recordId = -1;
            if (!projectId) {
              recordId = container.find('input[name="PME_sys_rec"]').val();
              projectId = -1;
            }
            OC.Notification.hide(function () {
              $.post(OC.filePath('cafevdb', 'ajax/instruments', 'changeInstrumentation.php'),
                     {
                       projectId: projectId,
                       recordId: recordId,
                       projectInstruments: $(self).find('select').val()
                     },
                     function (data) {
                       var rqData;
                       if (data.status == 'success') {
                         rqData = data.data;
                         if (rqData.message != '') {
                           OC.Notification.show(rqData.message);
                         }
                         // Oops. Perhaps only submit on success.
                         setTimeout(function() {
                           OC.Notification.hide(function() {
                             // Close the dialog
                             $(self).dialog("close");

                             // submit the form with the "right" button,
                             // i.e. save any possible changes already
                             // entered by the user. The form-submit
                             // will then also reload with an up to date
                             // list of instruments
                             PHPMYEDIT.triggerSubmit('morechange', container);
                           });
                         }, 800);

                       } else if (data.status == 'error') {
                         rqData = data.data;
                         if (rqData.error != 'exception') {
                           if (rqData.message == '') {
                             rqData.message = t('cafevdb', 'Unkown Error');
                           }
                           OC.Notification.show(rqData.message);
                         } else {
                           OC.dialogs.alert(rqData.exception+rqData.trace,
                                            t('cafevdb', 'Caught a PHP Exception'),
                                            null, true);
                         }
                         if (rqData.debug != '') {
                           OC.dialogs.alert(rqData.debug, t('cafevdb', 'Debug Information'), null, true);
                         }
                       }
                     }, 'json');
            });

            return false;
          }
        },
        { text: t('cafevdb', 'Cancel'),
          class: 'cancel',
          title: t('cafevdb',
                   'Discard the current choice of instruments and close the dialog. '+
                   'The instrumentation of the project will remain unchanged.'),
          click: function() {
            $(this).dialog("close");
          }
        }
      ],
      open: function () {

        $('button').tipsy({gravity:'ne', fade:true});
        $('input').tipsy({gravity:'ne', fade:true});
        $('label').tipsy({gravity:'ne', fade:true});
        
        if (CAFEVDB.toolTips) {
          $.fn.tipsy.enable();
        } else {
          $.fn.tipsy.disable();
        }

        container.find('#add-instruments-block div.chosen-container').show();
        container.find('#add-instruments-block div.chosen-container').trigger('blur');
      },
      close: function () {
        $('.tipsy').remove(); // avoid orphan tooltips
        $(this).dialog('destroy'); //.remove();
      }
    });
  };

  projectInstruments.ready = function(selector) {
    var container = PHPMYEDIT.container(selector);

    var self = this;

    // Enable the controls, in order not to bloat SQL queries these PME
    // fields are flagged virtual which disables all controls initially.
    container.find('#add-instruments-block select').removeAttr('disabled');
    container.find('#add-instruments-block select').trigger('chosen:updated');

    container.find('#add-instruments-button').off('click');
    container.find('#add-instruments-button').click(function(event) {
      event.preventDefault();

      self.openAddInstrumentsDialog(container);

      return false;
    });
    this.actionMenu(container);
  }

  CAFEVDB.projectInstruments = projectInstruments;
  
})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  PHPMYEDIT.addTableLoadCallback('ProjectInstruments',
                                 {
                                   callback: CAFEVDB.projectInstruments.ready,
                                   context: CAFEVDB.projectInstruments,
                                   parameters: []
                                 });

  CAFEVDB.projectInstruments.ready();

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
