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
  var SepaDebitMandate = function() {};
  
  SepaDebitMandate.projectId = -1;
  SepaDebitMandate.projectName = '';
  SepaDebitMandate.musicianId = -1;
  SepaDebitMandate.musicianName = '';
  SepaDebitMandate.SepaDebitMandate = { /* nothing */ };
  SepaDebitMandate.UI = {
    /**Initialize the mess with contents
     *
     * @param[in] data JSON response with the fields data.status,
     *                 data.data.contents,
     *                 data.data.message is place in an error-popup if status != 'success'
     *                 data.data.debug. data.data.debug is placed
     *                 inside the '#debug' div.
     */
    init: function(data) {
      if (data.status == 'success') {
        $('#dialog_holder').html(data.data.contents);
        SepaDebitMandate.projectId = data.data.projectId;
        SepaDebitMandate.projectName = data.data.projectName;
        SepaDebitMandate.musicianId = data.data.musicianId;
        SepaDebitMandate.musicianName = data.data.musicianName;
      } else {
	var info = '';
	if (typeof data.data.message != 'undefined') {
	  info = data.data.message;
	} else {
	  info = t('cafevdb', 'Unknown error :(');
	}
	if (typeof data.data.error != 'undefined' && data.data.error == 'exception') {
	  info += '<p><pre>'+data.data.exception+'</pre>';
	  info += '<p><pre>'+data.data.trace+'</pre>';
	}
        OC.dialogs.alert(info, t('cafevdb', 'Error'));
      }
      if (typeof data.data.debug != 'undefined') {
	$('div.debug').html(data.data.debug);
	$('div.debug').show();
      }
      
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
            class: 'change',
            text: t('cafevdb', 'Change'),
            title: t('cafevdb', 'Change the SEPA mandate. Note that the SEPA mandate-reference is automatically computed and cannot be changed.'),
            click: function() {}
          },
          {
            class: 'save',
            text: t('cafevdb', 'Save'),
            title: t('cafevdb', 'Close the form and save the data in the underlying data-base storage.'),
            click: function() {}
          },
          {
            class: 'apply',
            text: t('cafevdb', 'Apply'),
            title: t('cafevdb', 'Save the data in the underlying data-base storage. Keep the form open.'),
            click: function() {}
          },
          {
            class: 'cancel',
            text: t('cafevdb', 'Cancel'),
            title: t('cafevdb', 'Discard all filled-in data and close the form. Note that this will not undo any changes previously stored in the data-base by pressing the `Apply\' button.'),
            click: function() {
              $(this).dialog('close');
            }
          },
        ],
        open: function(){
          //$('.tipsy').remove();
          
          $('button').tipsy({gravity:'ne', fade:true});
          $('input').tipsy({gravity:'ne', fade:true});
          $('label').tipsy({gravity:'ne', fade:true});

          if (CAFEVDB.toolTips) {
            $.fn.tipsy.enable();
          } else {
            $.fn.tipsy.disable();
          }

          $('#sepa-debit-mandate-form input[class$="Date"]').datepicker({
            dateFormat : 'yy-mm-dd', // this is 4-digit year
            minDate: '1990-01-01',
            beforeShow: function(input) {
              $(input).unbind('blur');
            },
            onSelect: function(dateText, inst) {
              $(this).blur(CAFEVDB.SepaDebitMandate.UI.validate);
              $(this).focus();
              $(this).blur();
            }
          });

          $('#sepa-debit-mandate-form input[type="text"]').blur(CAFEVDB.SepaDebitMandate.UI.validate);

        },
        close: function(event, ui) {
          $('.tipsy').remove();
          $('#sepa-debit-mandate-dialog').dialog('close');
          $(this).dialog('destroy').remove();
        }
      });
    },
    validate: function(event) {
      var element = this;
      var dialogId = '#sepa-debit-mandate-dialog';

      event.preventDefault();
      $('div.statusmessage').hide();
      $('span.statusmessage').hide();    
      
      // should also provide the hidden inputs
      var post;
      post = $('#sepa-debit-mandate-form').serialize();
      post += "&"+$.param( { 'changed': $(this).attr('name') } );
      
      $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-settings.php'),
             post,
             function (data) {
               if (data.status == "success") {
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
                 if ($(dialogId+' #suggestion').html() !== '') {
	           $(dialogId+' #suggestion').show();
                 }
                 return true;
               } else {
                 if (data.data.suggestion !== '') {
	           $(dialogId+' #suggestion').html(data.data.suggestion);
                 }
	         $(dialogId+' #msg').html(data.data.message);
	         $(dialogId+' #msg').show();
                 if ($(dialogId+' #suggestion').html() !== '') {
	           $(dialogId+' #suggestion').show();
                 }
                 return false;
               }
             }, 'json');
    }
  };

  CAFEVDB.SepaDebitMandate = SepaDebitMandate;
})(window, jQuery, CAFEVDB);

