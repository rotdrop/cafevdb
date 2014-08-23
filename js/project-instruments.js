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

$(document).ready(function(){

  // Emulate a pull-down menu via chosen jQuery plugin

  $('select.pme-instrumentation-actions-choice').chosen({ disable_search:true });  
  $('select.pme-instrumentation-actions-choice').change(function (event) {
    event.preventDefault();

    //return CAFEVDB.tableExportMenu($(this));
    var select = $(this);

    var selected = select.find('option:selected').val();
    var values = select.attr('name');
    var optionValues = selected.split('?');

    selected = optionValues[0];

    switch (selected) {
    case 'transpose':
      var isTransposed = false;
      if ($('input[name="Transpose"]').val() == 'transposed' ||
          $('#pme-transpose-up').hasClass('pme-transposed') ||
          $('#pme-transpose-down').hasClass('pme-transposed') ||
          $('#pme-transpose').hasClass('pme-transposed')) {
        isTransposed = true;
      }
      CAFEVDB.PME.maybeTranspose(!isTransposed);
      break;
    case 'transfer-instruments':
      post = optionValues[1];
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
                     if (false) {
                       // Regular form transmit should suffice?
                       var post = $('form.pme-form').serialize();
                       post += '&'+optionValues[1];
                       $.post("", post, function(data) {
                         $(document.body).html(data);
                       });
                     }
                     $('form.pme-form').submit();
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
  });

  $('select.pme-instrumentation-actions-choice').on('chosen:showing_dropdown', function (chosen) {
    $('ul.chosen-results li.active-result').tipsy({gravity:'w', fade:true});
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
