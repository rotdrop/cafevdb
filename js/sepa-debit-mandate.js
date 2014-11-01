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

(function(window, $, CAFEVDB, undefined) {
  'use strict';
  var SepaDebitMandate = function() {};
  SepaDebitMandate.projectId = -1;
  SepaDebitMandate.projectName = '';
  SepaDebitMandate.musicianId = -1;
  SepaDebitMandate.musicianName = '';
  SepaDebitMandate.mandateId = -1;
  SepaDebitMandate.mandateReference = '';
    /**Initialize the mess with contents
     *
     * @param[in] data JSON response with the fields data.status,
     *                 data.data.contents,
     *                 data.data.message is place in an error-popup if status != 'success'
     *                 data.data.debug. data.data.debug is placed
     *                 inside the '#debug' div.
     */
  SepaDebitMandate.init = function(data, reloadCB) {
    var self = SepaDebitMandate;

    if (!CAFEVDB.ajaxErrorHandler(data, [
      'contents',
      'projectId', 'projectName',
      'musicianId', 'musicianName',
      'mandateId', 'mandateReference'
    ])) {
      return false;
    }

    if (typeof reloadCB != 'function') {
      reloadCB = function() {};
    }

    $('#dialog_holder').html(data.data.contents);
    self.projectId = data.data.projectId;
    self.projectName = data.data.projectName;
    self.musicianId = data.data.musicianId;
    self.musicianName = data.data.musicianName;
    self.mandateId = data.data.mandateId;
    self.mandateReference = data.data.mandateReference;

    CAFEVDB.debugPopup(data);
    
    var popup = $('#sepa-debit-mandate-dialog').dialog({
      position: { my: "middle top+50%",
                  at: "middle bottom",
                  of: "#controls" },
      width : 550,
      height: "auto",
      modal: true,
      resizable: false,
      closeOnEscape: false,
      dialogClass: 'no-close',
      buttons: [
        {
          'class': 'change',
          id: 'sepaMandateChange',
          text: t('cafevdb', 'Change'),
          title: t('cafevdb', 'Change the SEPA mandate. Note that the SEPA mandate-reference is automatically computed and cannot be changed.'),
          click: function() {
            // enable the form, disable the change button
            $(this).dialog("widget").find('button.save').attr("disabled", false);
            $(this).dialog("widget").find('button.apply').attr("disabled", false);
            $(this).dialog("widget").find('button.delete').attr("disabled", false);
            $(this).dialog("widget").find('input[class^="bankAccount"]').attr("disabled", false);
            $(this).dialog("widget").find('input.mandateDate').attr("disabled", false);
            $(this).dialog("widget").find('button.change').attr("disabled", true);
            $('.tipsy').remove(); // clean up left-over balloons
          }
        },
        {
          'class': 'save',
          id: 'sepaMandateSave',
          text: t('cafevdb', 'Save'),
          title: t('cafevdb', 'Close the form and save the data in the underlying data-base storage.'),
          click: function() {
            var dlg = this;
            self.store(function () {
              $('#sepa-debit-mandate-'+self.musicianId+'-'+self.projectId).val(self.mandateReference);
              $(dlg).dialog('close');
              reloadCB();
            });
          }
        },
        {
          'class': 'apply',
          text: t('cafevdb', 'Apply'),
          title: t('cafevdb', 'Save the data in the underlying data-base storage. Keep the form open.'),
          click: function(event) {
            var dlg = this;
            self.store(function () {
              $('#sepa-debit-mandate-'+self.musicianId+'-'+self.projectId).val(self.mandateReference);
              // Disable everything and enable the change button
              // If we are about to display an existing mandate, first
              // disable all inputs and leave only the "close" and
              // "change" buttons enabled, and the lastUsed date.
              $(dlg).dialog("widget").find('button.save').attr("disabled", true);
              $(dlg).dialog("widget").find('button.apply').attr("disabled", true);
              $(dlg).dialog("widget").find('button.delete').attr("disabled", true);
              $(dlg).dialog("widget").find('input[class^="bankAccount"]').attr("disabled", true);
              $(dlg).dialog("widget").find('input.mandateDate').attr("disabled", true);
              $(dlg).dialog("widget").find('button.change').attr("disabled", false);
              $('.tipsy').remove(); // clean up left-over balloons
              reloadCB();
            });
          }
        },
        {
          'class': 'delete',
          text: t('cafevdb', 'Delete'),
          title: t('cafevdb', 'Delete this mandate from the data-base. Normally, this should only be done in case of desinformation or misunderstanding. Use with care.'),
          click: function() {
            var dlg = this;
            self.delete(function () {
              $('#sepa-debit-mandate-'+self.musicianId+'-'+self.projectId).val(t('cafevdb', 'SEPA Debit Mandate'));
              $(dlg).dialog('close');
              reloadCB();
            });
          }
        },
        {
          'class': 'close',
          text: t('cafevdb', 'Close'),
          title: t('cafevdb', 'Discard all filled-in data and close the form. Note that this will not undo any changes previously stored in the data-base by pressing the `Apply\' button.'),
          click: function() {
            $(this).dialog('close');
            //$('form.pme-form').submit();
          }
        }
      ],
      open: function(){
        //$('.tipsy').remove();
        
        if (self.mandateId > 0) {
          // If we are about to display an existing mandate, first
          // disable all inputs and leave only the "close" and
          // "change" buttons enabled, and the lastUsed date.
          $(this).dialog("widget").find('button.save').attr("disabled", true);
          $(this).dialog("widget").find('button.apply').attr("disabled", true);
          $(this).dialog("widget").find('button.delete').attr("disabled", true);
          $(this).dialog("widget").find('input[class^="bankAccount"]').attr("disabled", true);
          $(this).dialog("widget").find('input.mandateDate').attr("disabled", true);
        } else {
          $(this).dialog("widget").find('button.change').attr("disabled", true);
        }

        $('button').tipsy({gravity:'ne', fade:true});
        $('input').tipsy({gravity:'ne', fade:true});
        $('label').tipsy({gravity:'ne', fade:true});

        if (CAFEVDB.toolTips) {
          $.fn.tipsy.enable();
        } else {
          $.fn.tipsy.disable();
        }

        $('#sepa-debit-mandate-form input[class$="Date"]').datepicker({
          dateFormat : 'dd.mm.yy', // this is 4-digit year
          minDate: '01.01.1990',
          beforeShow: function(input) {
            $(input).unbind('blur');
          },
          onSelect: function(dateText, inst) {
            $(this).blur(self.validate);
            $(this).focus();
            $(this).blur();
          }
        });

        $('#sepa-debit-mandate-form input[type="text"]').blur(self.validate);

      },
      close: function(event, ui) {
        $('.tipsy').remove();
        $('#sepa-debit-mandate-dialog').dialog('close');
        $(this).dialog('destroy').remove();
      }
    });
    return false;
  };

  // Store the form data. We assume that validation already has been
  // done
  SepaDebitMandate.store = function(callbackOk) {
    var dialogId = '#sepa-debit-mandate-dialog';

    $('div.statusmessage').hide();
    $('span.statusmessage').hide();    

    // "submit" the entire form
    var post = $('#sepa-debit-mandate-form').serialize();

    $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-store.php'),
           post,
           function (data) {
	     $(dialogId+' #msg').html(data.data.message);
	     $(dialogId+' #msg').show();
             if (data.status == "success") {
               callbackOk();
               return true;
             } else {
               return false;
             }
           });
  };

  // Delete a mandate
  SepaDebitMandate.delete = function(callbackOk) {
    var dialogId = '#sepa-debit-mandate-dialog';

    $('div.statusmessage').hide();
    $('span.statusmessage').hide();    

    // "submit" the entire form
    var post = $('#sepa-debit-mandate-form').serialize();

    $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-delete.php'),
           post,
           function (data) {
	     $(dialogId+' #msg').html(data.data.message);
	     $(dialogId+' #msg').show();
             if (data.status == "success") {
               callbackOk();
               return true;
             } else {
               return false;
             }

           });
  };

  /**Validate version for our popup-dialog. */
  SepaDebitMandate.validate = function(event) {
    var element = this;
    var dialogId = '#sepa-debit-mandate-dialog';

    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();    
    
    // we "submit" the entire form in order to do some automatic
    // fill-in in checks for the bank accounts.
    var post;
    post = $('#sepa-debit-mandate-form').serialize();
    post += "&"+$.param( { 'changed': $(this).attr('name') } );

    $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-settings.php'),
           post,
           function (data) {
             if (!CAFEVDB.ajaxErrorHandler(data, [ 'suggestions', 'message' ])) {
               if (data.data.suggestions !== '') {
                 var hints = t('cafevdb', 'Suggested alternatives based on common human mis-transcriptions:')
                           + ' '
                           + data.data.suggestions
                           + '. '
                           + t('cafevdb', 'Please do not accept these alternatives lightly!');
	         $(dialogId+' #suggestions').html(hints);
               }
               // One special case: if the user has submitted an IBAN
               // and the BLZ appeared to be valid after all checks,
               // then inject it into the form. Seems to be a common
               // case, more or less.
               if (data.data.blz) {
                 $('input.bankAccountBLZ').val(data.data.blz);
               }

	       $(dialogId+' #msg').html(data.data.message);
	       $(dialogId+' #msg').show();
               if ($(dialogId+' #suggestions').html() !== '') {
	         $(dialogId+' #suggestions').show();
               }
               return false;
             }

             if (data.data.value) {
               $(element).val(data.data.value);
             }
             if (data.data.iban) {
               $('input.bankAccountIBAN').val(data.data.iban);
             }
             if (data.data.blz) {
               $('input.bankAccountBLZ').val(data.data.blz);
             }
             if (data.data.bic) {
               $('input.bankAccountBIC').val(data.data.bic);
             }
	     $(dialogId+' #msg').html(data.data.message);
	     $(dialogId+' #msg').show();
             if (data.data.suggestions !== '') {
               var hints = t('cafevdb', 'Suggested alternatives based on common human mis-transcriptions:')
                         + ' '
                         + data.data.suggestions
                         + '. '
                         + t('cafevdb', 'Please do not accept these alternatives lightly!');
	       $(dialogId+' #suggestions').html(hints);
	       $(dialogId+' #suggestions').show();
             } else {
	       $(dialogId+' #suggestions').html('');
	       $(dialogId+' #suggestions').hide();
             }
             return true;

           }, 'json');
  };

  /**Validate version for the PME dialog. */
  SepaDebitMandate.validatePME = function(event) {
    var element = this;
    event.preventDefault();

    // we use the same Ajax validation script; we remap the form
    // elements. We need
    //
    // MusicianId
    // ProjectId
    // mandateReference
    // nonrecurring
    // bankAccountOwner
    // bankAccountIBAN
    // bankAccountBLZ
    // bankAccountBIC
    // mandateDate
    // lastUsedDate
    var inputMapping = {
        PME_data_lastUsedDate: 'lastUsedDate',
        PME_data_mandateDate: 'mandateDate',
        PME_data_bankAccountOwner: 'bankAccountOwner',
        PME_data_IBAN: 'bankAccountIBAN',
        PME_data_BIC: 'bankAccountBIC',
        PME_data_BLZ: 'bankAccountBLZ'
    };
    var changed = $(this).attr('name');
    changed = inputMapping[changed];

    var mandateData = {
      mandateReference: $('input[name="PME_data_mandateReference"]').val(),
      mandateDate: $('input[name="PME_data_mandateDate"]').val(),
      bankAccountOwner: $('input[name="PME_data_bankAccountOwner"]').val(),
      lastUsedDate: $('input[name="PME_data_lastUsedDate"]').val(),
      MusicianId:  $('select[name="PME_data_musicianId"] option[selected="selected"]').val(),
      ProjectId:  $('select[name="PME_data_projectId"] option[selected="selected"]').val(),
      bankAccountIBAN: $('input[name="PME_data_IBAN"]').val(),
      bankAccountBIC: $('input[name="PME_data_BIC"]').val(),
      bankAccountBLZ: $('input[name="PME_data_BLZ"]').val(),
      changed: changed
    };

    var post = $.param(mandateData);
    $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-settings.php'),
           post,
           function (data) {
             if (!CAFEVDB.ajaxErrorHandler(data, [ 'suggestions', 'message' ])) {
               if (data.data.blz) {
                 $('input.bankAccountBLZ').val(data.data.blz);
               }
               return false;
             }

             $('#cafevdb-page-debug').html(data.data.message);
             $('#cafevdb-page-debug').show();
             if (data.data.value) {
               $(element).val(data.data.value);
             }
             if (data.data.iban) {
               $('input[name="PME_data_IBAN"]').val(data.data.iban);
             }
             if (data.data.bic) {
               $('input[name="PME_data_BIC"]').val(data.data.bic);
             }
             if (data.data.blz) {
               $('input[name="PME_data_BLZ"]').val(data.data.blz);
             }
             return true;
           }, 'json');
  };

  SepaDebitMandate.popupInit = function(selector) {
    var self = this;

    var containerSel = PHPMYEDIT.selector(selector);
    var container = PHPMYEDIT.container(containerSel);
    var pmeReload = container.find('form.pme-form input.pme-reload').first();

    container.find(':button[class$="sepa-debit-mandate"]').
      off('click').
      on('click', function(event) {
      event.preventDefault();
      if (container.find('#sepa-debit-mandate-dialog').dialog('isOpen') == true) {
        container.find('#sepa-debit-mandate-dialog').dialog('close').remove();
      } else {
        // We store the values in the data attribute.
        var values = $(this).data('debitMandate');
        //alert('data: ' + CAFEVDB.print_r(values, true));
        $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-mandate.php'),
               values, function(data) {
                         self.init(data, function() {
                           if (pmeReload.length > 0) {
                             pmeReload.trigger('click');
                           }
                         });
                       },
               'json');
      }
      return false;
    });
  };

  SepaDebitMandate.ready = function(selector) {
    var self = this;

    var containerSel = PHPMYEDIT.selector(selector);
    var container = PHPMYEDIT.container(containerSel);

    // bail out if not for us.
    var form = container.find('form[class^="pme-form"]');
    var dbTable = form.find('input[value="SepaDebitMandates"]');
    if (dbTable.length == 0) {
      return;
    }
    var table = form.find('table[summary="SepaDebitMandates"]');
    table.find('input[type="text"]').
      not('.pme-filter').
      off('blur').
      on('blur', function(event) {
      alert('hello');
      self.validatePME(event);
    });

    CAFEVDB.exportMenu(containerSel);

    table.find('input[class$="-sepadate"]').datepicker({
      dateFormat : 'dd.mm.yy', // this is 4-digit year
      minDate: '01.01.1990',
      beforeShow: function(input) {
        $(input).unbind('blur');
      },
      onSelect: function(dateText, inst) {
        $(this).blur(self.validatePME);
        $(this).focus();
        $(this).blur();
      }
    });
  };

  CAFEVDB.SepaDebitMandate = SepaDebitMandate;
})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  PHPMYEDIT.addTableLoadCallback('SepaDebitMandates',
                                 {
                                     callback: function(selector, resizeCB) {
                                         this.ready(selector);
                                         resizeCB();
                                         //alert("Here I am: "+selector);
                                     },
                                     context: CAFEVDB.SepaDebitMandate,
                                     parameters: []
                                 });

  CAFEVDB.addReadyCallback(function() {
    CAFEVDB.SepaDebitMandate.ready(PHPMYEDIT.defaultSelector);
    CAFEVDB.SepaDebitMandate.popupInit(PHPMYEDIT.defaultSelector);
  });

});
