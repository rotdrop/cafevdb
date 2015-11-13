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

  var Musicians = function() {};

  Musicians.addMusicians = function(form, post) {
    var projectId = form.find('input[name="ProjectId"]').val();
    var projectName = form.find('input[name="ProjectName"]').val();
    if (typeof post == 'undefined') {
      post = form.serialize();
    }

    // Open the change-musician dialog with the newly
    // added musician in case of success.
    $.post(OC.filePath('cafevdb', 'ajax/instrumentation', 'add-musicians.php'),
           post,
           function(data) {
             if (!CAFEVDB.ajaxErrorHandler(data, [
               'musicians'
             ])) {
               // Load the underlying base-view in any case in order to go "back" ...
               CAFEVDB.Instrumentation.loadDetailedInstrumentation(form);
               return false;
             }
             if (data.data.musicians.length == 1) {
               // open single person change dialog
               var musician = data.data.musicians[0];
               //alert('data: '+CAFEVDB.print_r(musician, true));
               CAFEVDB.Instrumentation.loadDetailedInstrumentation(
                 form,
                 undefined,
                 function() {
                   CAFEVDB.Instrumentation.personalRecordDialog(
                     musician.instrumentationId,
                     {
                       ProjectId: projectId,
                       ProjectName: projectName,
                       InitialValue: 'Change',
                       modified: true
                     });
                 });
             } else {
               // load the instrumentation table, initially restricted to the new musicians
               CAFEVDB.Instrumentation.loadDetailedInstrumentation(form, data.data.musicians);
             }
             return false;
           }
          );
    return false;
  };

  Musicians.contactValidation = function(container) {
    var self = Musicians;

    if (typeof container == 'undefined') {
      container = $('body');
    }

    container.find('input.phone-number').
      not('.pme-filter').
      off('blur').
      on('blur', function(event) {

      event.stopImmediatePropagation();

      var submitDefer = PHPMYEDIT.deferReload(container);

      var form = container.find('form.pme-form');
      var phones = form.find('input.phone-number');
      var post = form.serialize();
      var mobile = phones.filter('input[name$="MobilePhone"]');
      var fixedLine = phones.filter('input[name$="FixedLinePhone"]');

      phones.prop('disabled', true);

      $.post(OC.filePath('cafevdb', 'ajax/musicians', 'validatephone.php'),
             post,
             function (data) {
               if (!CAFEVDB.ajaxErrorHandler(
                 data, [ 'message',
                         'mobilePhone',
                         'mobileMeta',
                         'fixedLinePhone',
                         'fixedLineMeta' ],
                 function() {
                   phones.prop('disabled', false);
                   submitDefer.resolve();
                 })) {
                 return false;
               }
               // inject the sanitized value into their proper input fields
               mobile.val(data.data.mobilePhone);
               fixedLine.val(data.data.fixedLinePhone);
               if (data.data.mobileMeta) {
                 mobile.removeAttr('data-original-title');
                 mobile.attr('title', data.data.mobileMeta);
                 mobile.cafevTooltip();
               }
               if (data.data.fixedLineMeta) {
                 fixedLine.removeAttr('data-original-title');
                 fixedLine.attr('title', data.data.fixedLineMeta);
                 fixedLine.cafevTooltip();
               }
               if (data.data.message != '') {
                 OC.dialogs.alert(data.data.message,
                                  t('cafevdb', 'Phone Number Validation'),
                                  function() {
                                    phones.prop('disabled', false);
                                    submitDefer.resolve();
                                  }, true, true);
                 CAFEVDB.debugPopup(data);
               } else {
                 phones.prop('disabled', false);
                 submitDefer.resolve();
               }
               return false;
             });

      return false;
    });

    container.find('input.email').
      not('.pme-filter').
      off('blur').
      on('blur', function(event) {

      event.stopImmediatePropagation();

      var submitDefer = PHPMYEDIT.deferReload(container);

      var form = container.find('form.pme-form');
      var email = form.find('input.email');
      var post = form.serialize();
      email.prop('disabled', true);
      $.post(OC.filePath('cafevdb', 'ajax/musicians', 'validateemail.php'),
             post,
             function (data) {
               if (!CAFEVDB.ajaxErrorHandler(
                 data, [ 'message',
                         'email' ],
                 function() {
                   email.prop('disabled', false);
                   submitDefer.resolve();
                 })) {
                 return false;
               }
               // inject the sanitized value into their proper input fields
               form.find('input[name$="Email"]').val(data.data.email);
               if (data.data.message != '') {
                 OC.dialogs.alert(data.data.message,
                                  t('cafevdb', 'Email Validation'),
                                  function() {
                                    email.prop('disabled', false);
                                    submitDefer.resolve();
                                  }, true, true);
                 CAFEVDB.debugPopup(data);
               } else {
                 email.prop('disabled', false);
                 submitDefer.resolve();
               }
               return false;
             });

      return false;
    });

    var address = container.find('form.pme-form input.musician-address').not('.pme-filter');
    var city = address.filter('.city');
    var street = address.filter('.street');
    var zip = address.filter('.postal-code');

    address.autocomplete(
      {
        source: [],
        minLength: 0,
        open: function(event, ui) {
          var $input = $(event.target),
              $results = $input.autocomplete("widget"),
              top = $results.position().top,
              height = $results.outerHeight(),
              inputHeight = $input.outerHeight(),
              newTop = top - height - inputHeight;

          $results.css("top", newTop + "px");
        }
      }
    ).on('focus, click', function() {
      if (!$(this).autocomplete('widget').is(':visible')) {
        $(this).autocomplete('search', '');
      }
    });

    // Inject a text input element for possible suggestions for the country setting.
    var countrySelect = container.find('select.musician-address.country');
    var countryInput = $('<input type="text"'
                        + ' class="musician-address country"'
                        + ' id="country-autocomplete"'
                        + ' placeholder="'+t('cafevdb', 'Suggestions')+'" />');
    countryInput.hide();
    $('tr.musician-address.country td[class|="pme-value"] select').before(countryInput);
//    countryInput = $('#country-autocomplete');
    countryInput.autocomplete(
      {
        source: [],
        minLength: 0,
        open: function(event, ui) {
          var $input = $(event.target),
              $results = $input.autocomplete("widget"),
              top = $results.position().top,
              height = $results.outerHeight(),
              inputHeight = $input.outerHeight(),
              newTop = top - height - inputHeight;

          $results.css("top", newTop + "px");
        },
        select: function(event, ui) {
          var country = ui.item.value;
          countryInput.val(country);
          countryInput.trigger('blur');
          return true;
        }
      }
    ).on('focus, click', function() {
      if (!$(this).autocomplete('widget').is(':visible')) {
        $(this).autocomplete('search', '');
      }
    }).on('blur', function(event) {
      var self = $(this);

      event.stopImmediatePropagation();

      var country = self.val();
      countrySelect.find('option[value='+country+']').prop('selected', true);
      countrySelect.trigger('chosen:updated');
      countrySelect.trigger('change');

      return false;
    });

    var lockCountry = false;
    countrySelect.on('change', function(event) {
      address.filter('.city').trigger('blur');
      lockCountry = true;
      return false;
    });

    address.on('blur', function(event) {
      var self = $(this);

      if (self.hasClass('street')) {
        return true;
      }

      // this is somehow needed here ...
      event.stopImmediatePropagation();

      if (!!self.autocomplete('widget').is(':visible')) {
        // don't validate while select box is open
        return false;
      }

      var submitDefer = PHPMYEDIT.deferReload(container);

      var form = container.find('form.pme-form');
      var post = form.serialize();
      post += '&' + $.param({'ActiveElement': self.attr('name')});

      var reload = container.find('.pme-navigation input.pme-reload');

      address.prop('disabled', true);
      reload.addClass('loading');

      $.post(OC.filePath('cafevdb', 'ajax/musicians', 'validateaddress.php'),
             post,
             function(data) {
               if (!CAFEVDB.ajaxErrorHandler(
                 data,
                 [ 'message', 'city', 'zip', 'street', 'suggestions' ],
                 function() {
                   reload.removeClass('loading');
                   address.prop('disabled', false);
                   submitDefer.resolve();
                 })) {
                 return false;
               }

               data = data.data;

               city.val(data.city);
               street.val(data.street);
               zip.val(data.zip);

               var suggestions = data.suggestions;

               city.autocomplete('option', 'source', suggestions.cities);
               zip.autocomplete('option', 'source', suggestions.postalCodes);
               var selectedCountry = countrySelect.find('option:selected').val();
               var countries = suggestions.countries;
               countryInput.hide();
               countryInput.autocomplete('option', 'source', []);
               if (countries.length == 1 && countries[0] != selectedCountry && !lockCountry) {
                 // if we have just one matching country, we force the
                 // country-select to hold this value.
                 countrySelect.find('option[value='+countries[0]+']').prop('selected', true);
                 countrySelect.trigger('chosen:updated');
                 //alert('selected: '+selectedCountry+' matching: '+countries[0]);
               } else if (countries.length > 1) {
                 // provide the user with some more choices.
                 //alert('blah');
                 countryInput.autocomplete('option', 'source', countries);
                 countryInput.show();
               }
               lockCountry = false;

               //data.message += CAFEVDB.print_r(citySuggestions, true);
               if (data.message != '') {
                 OC.dialogs.alert(data.message,
                                  t('cafevdb', 'Address Validation'),
                                  function() {
                                    reload.removeClass('loading');
                                    address.prop('disabled', false);
                                    submitDefer.resolve();
                                  }, true, true);
                 CAFEVDB.debugPopup(data);
               } else {
                 reload.removeClass('loading');
                 address.prop('disabled', false);
                 submitDefer.resolve();
               }
               return false;
             });
      return false;
    });
  };

  Musicians.ready = function(container) {
    var self = this;

    // sanitize
    container = PHPMYEDIT.container(container);

    self.contactValidation(container);

    var form = container.find('form.pme-form');
    var nameInputs = form.find('input.musician-name');

    var nameValidationActive = false;

    // avoid duplicate entries in the DB, but only when adding new
    // musicians.
    container.find('form.pme-form input.musician-name.add-musician').
      off('blur').
      on('blur', function(event) {

      if (nameValidationActive) {
        event.stopImmediatePropagation();
        return false;
      }

      nameValidationActive = true;

      var post = form.serialize();

      $.post(OC.filePath('cafevdb', 'ajax/musicians', 'validate.php'),
             post,
             function (data) {
               if (!CAFEVDB.ajaxErrorHandler(data, [ 'message' ],
                                             function() {
                                               nameValidationActive = false;
                                             })) {
                 return false;
               }
               if (!$.isEmptyObject(data.data.duplicates)) {
                 var numDuplicates = 0;
                 var ids = [];
                 for (var id in data.data.duplicates) {
                   ++numDuplicates;
                   ids.push(id);
                 }
                 if (numDuplicates > 0) {
                   OC.dialogs.confirm(
                     t('cafevdb',
                       'You definitely do not want to add duplicates to the database.'+"\n"
                                 + 'Please answer "no" in order to add a new musician,'+"\n"
                                 + 'otherwise answer "yes". If you react in a positive manner'+"\n"
                                 + 'you will be redirected to a web form in order to bring'+"\n"
                                 + 'the personal data of the respective musician up-to-date.')
                     .replace(/(?:\r\n|\r|\n)/g, '<br/>'),
                     t('cafevdb', 'Avoid Possible Duplicate?'),
                     function(answer) {
                       nameValidationActive = false;
                       if (!answer) {
                         return;
                       }
                       var mainContainer = $(container.data('AmbientContainer'));
                       var form = mainContainer.find(PHPMYEDIT.formSelector());
                       container.dialog('close');
                       if (numDuplicates == 1) {
                         var projectId = form.find('input[name="ProjectId"]').val();
                         var projectName = form.find('input[name="ProjectName"]').val();
                         CAFEVDB.Instrumentation.personalRecordDialog(
                           ids[0],
                           {
                             Table: 'Musiker',
                             InitialValue: 'View',
                             ProjectId: projectId ? projectId : -1,
                             ProjectName: projectName
                           }
                         );
                       } else {
                         CAFEVDB.Instrumentation.loadMusicians(form, ids, null);
                       }
                     }, true, true);
                 }
                 return false;
               }
               if (data.data.message != '') {
                 OC.dialogs.alert(data.data.message,
                                  t('cafevdb', 'Possible Duplicate!'),
                                  function() {
                                    nameValidationActive = false;
                                  }, true, true);
                 return false;
               }
               nameValidationActive = false;
               return false;
             });

      return false;
    });

    container.find('input.register-musician').off('click').
      on('click', function(event) {

      var form = container.find('form.pme-form');
      var projectId = form.find('input[name="ProjectId"]').val();
      var projectName = form.find('input[name="ProjectName"]').val();
      var musicianId = $(this).data('musician-id');

      self.addMusicians(form, {
        'ProjectId': projectId,
        'ProjectName': projectName,
        'MusicianId': musicianId
      });
      return false;
    });

    //container.find('input.pme-bulkcommit').addClass('formsubmit');
    container.find('input.pme-bulkcommit').
      addClass('pme-custom').prop('disabled', false).
      off('click').on('click', function(event) {

      var form = container.find('form.pme-form');
      self.addMusicians(form);
      return false;
    });
  };

  CAFEVDB.Musicians = Musicians;

})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  CAFEVDB.addReadyCallback(function() {
    if ($('div#cafevdb-page-body.musicians').length > 0) {
      CAFEVDB.Musicians.ready();
    }
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
