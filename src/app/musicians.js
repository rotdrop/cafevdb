/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de
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

import { appName, $ } from './globals.js';
import generateUrl from './generate-url.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as ProjectParticipants from './project-participants.js';
import * as PHPMyEdit from './pme.js';

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');
require('sepa-bank-accounts.scss');

const addMusicians = function(form, post) {
  const projectId = form.find('input[name="projectId"]').val();
  const projectName = form.find('input[name="projectName"]').val();
  if (typeof post === 'undefined') {
    post = form.serialize();
  }

  // Open the change-musician dialog with the newly
  // added musician in case of success.
  $.post(generateUrl('projects/participants/add-musicians'),
    post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, function() {
        // ProjectParticipants.loadProjectParticipants(form);
      });
    })
    .done(function(data) {
      if (!Ajax.validateResponse(data, ['musicians'])) {
        // Load the underlying base-view in any case in order to go "back" ...
        ProjectParticipants.loadProjectParticipants(form);
        return;
      }
      console.log(data);
      if (data.musicians.length === 1) {
        // open single person change dialog
        const musicianId = data.musicians[0];
        // alert('data: '+CAFEVDB.print_r(musician, true));
        ProjectParticipants.loadProjectParticipants(
          form,
          undefined,
          function() {
            ProjectParticipants.personalRecordDialog(
              {
                projectId,
                musicianId,
              },
              {
                projectId,
                projectName,
                InitialValue: 'Change',
                modified: true,
              });
          });
      } else {
        // load the instrumentation table, initially restricted to the new musicians
        ProjectParticipants.loadProjectParticipants(form, data.musicians);
      }
    });
};

const contactValidation = function(container) {

  if (typeof container === 'undefined') {
    container = $('body');
  }

  container.find('input.phone-number')
    .not('.pme-filter')
    .off('blur')
    .on('blur', function(event) {

      event.stopImmediatePropagation();

      const submitDefer = PHPMyEdit.deferReload(container);

      const form = container.find('form.pme-form');
      const phones = form.find('input.phone-number');
      const post = form.serialize();
      const mobile = phones.filter('input[name$="mobile_phone"]');
      const fixedLine = phones.filter('input[name$="fixed_line_phone"]');

      phones.prop('disabled', true);

      const cleanup = function() {
        phones.prop('disabled', false);
        submitDefer.resolve();
      };

      $.post(
        generateUrl('validate/musicians/phone'),
        post)
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(
            data, [
              'message',
              'mobilePhone',
              'mobileMeta',
              'fixedLinePhone',
              'fixedLineMeta',
            ],
            cleanup)) {
            return false;
          }
          // inject the sanitized value into their proper input fields
          mobile.val(data.mobilePhone);
          fixedLine.val(data.fixedLinePhone);
          if (data.mobileMeta) {
            mobile.removeAttr('data-original-title');
            mobile.attr('title', data.mobileMeta);
            mobile.cafevTooltip();
          }
          if (data.fixedLineMeta) {
            fixedLine.removeAttr('data-original-title');
            fixedLine.attr('title', data.fixedLineMeta);
            fixedLine.cafevTooltip();
          }
          if (data.message !== '') {
            Dialogs.alert(
              data.message,
              t(appName, 'Phone Number Validation'),
              function() {
                phones.prop('disabled', false);
                submitDefer.resolve();
              }, true, true);
            Dialogs.debugPopup(data);
          } else {
            phones.prop('disabled', false);
            submitDefer.resolve();
          }
          return false;
        });
    });

  container.find('input.email')
    .not('.pme-filter')
    .off('blur')
    .on('blur', function(event) {

      event.stopImmediatePropagation();

      const submitDefer = PHPMyEdit.deferReload(container);

      const form = container.find('form.pme-form');
      const email = form.find('input.email');
      const post = form.serialize();
      email.prop('disabled', true);

      const cleanup = function() {
        email.prop('disabled', false);
        submitDefer.resolve();
      };

      $.post(
        generateUrl('validate/musicians/email'),
        post)
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['message', 'email'], cleanup)) {
            return;
          }
          // inject the sanitized value into their proper input fields
          form.find('input[name$="email"]').val(data.email);
          if (data.message !== '') {
            Dialogs.alert(
              data.message,
              t(appName, 'Email Validation'),
              cleanup, true, true);
            Dialogs.debugPopup(data);
          } else {
            cleanup();
          }
        });
    });

  const address = container.find('form.pme-form input.musician-address').not('.pme-filter');
  const city = address.filter('.city');
  const street = address.filter('.street');
  const zip = address.filter('.postal-code');

  address
    .autocomplete({
      source: [],
      minLength: 0,
      open(event, ui) {
        const $input = $(event.target);
        const $results = $input.autocomplete('widget');
        const top = $results.position().top;
        const height = $results.outerHeight();
        const inputHeight = $input.outerHeight();
        const newTop = top - height - inputHeight;

        $results.css('top', newTop + 'px');
      },
    })
    .on('focus, click', function() {
      if (!$(this).autocomplete('widget').is(':visible')) {
        $(this).autocomplete('search', '');
      }
    });

  // Inject a text input element for possible suggestions for the country setting.
  const countrySelect = container.find('select.musician-address.country');
  const countryInput = $('<input type="text"'
                         + ' class="musician-address country"'
                         + ' id="country-autocomplete"'
                         + ' placeholder="' + t(appName, 'Suggestions') + '" />');
  countryInput.hide();
  $('tr.musician-address.country td[class|="pme-value"] select').before(countryInput);
  //    countryInput = $('#country-autocomplete');
  countryInput
    .autocomplete({
      source: [],
      minLength: 0,
      open(event, ui) {
        const $input = $(event.target);
        const $results = $input.autocomplete('widget');
        const top = $results.position().top;
        const height = $results.outerHeight();
        const inputHeight = $input.outerHeight();
        const newTop = top - height - inputHeight;

        $results.css('top', newTop + 'px');
      },
      select(event, ui) {
        const country = ui.item.value;
        countryInput.val(country);
        countryInput.trigger('blur');
        return true;
      },
    })
    .on('focus, click', function() {
      if (!$(this).autocomplete('widget').is(':visible')) {
        $(this).autocomplete('search', '');
      }
    })
    .on('blur', function(event) {
      const self = $(this);

      event.stopImmediatePropagation();

      const country = self.val();
      countrySelect.find('option[value=' + country + ']').prop('selected', true);
      countrySelect.trigger('chosen:updated');
      countrySelect.trigger('change');

      return false;
    });

  let lockCountry = false;
  countrySelect.on('change', function(event) {
    address.filter('.city').trigger('blur');
    lockCountry = true;
    return false;
  });

  address.on('blur', function(event) {
    const self = $(this);

    if (self.hasClass('street')) {
      return true;
    }

    // this is somehow needed here ...
    event.stopImmediatePropagation();

    if (self.autocomplete('widget').is(':visible')) {
      // don't validate while select box is open
      return false;
    }

    const submitDefer = PHPMyEdit.deferReload(container);

    const form = container.find('form.pme-form');
    let post = form.serialize();
    post += '&' + $.param({ activeElement: self.attr('name') });

    const reload = container.find('.pme-navigation input.pme-reload');

    address.prop('disabled', true);
    reload.addClass('loading');

    const cleanup = function() {
      reload.removeClass('loading');
      address.prop('disabled', false);
      submitDefer.resolve();
    };

    $.post(
      generateUrl('validate/musicians/address'),
      post)
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown, cleanup);
      })
      .done(function(data) {
        if (!Ajax.validateResponse(
          data, ['message', 'city', 'zip', 'street', 'suggestions'], cleanup)) {
          return false;
        }

        city.val(data.city);
        street.val(data.street);
        zip.val(data.zip);

        const suggestions = data.suggestions;

        city.autocomplete('option', 'source', suggestions.cities);
        zip.autocomplete('option', 'source', suggestions.postalCodes);
        street.autocomplete('option', 'source', suggestions.streets);
        const selectedCountry = countrySelect.find('option:selected').val();
        const countries = suggestions.countries;
        countryInput.hide();
        countryInput.autocomplete('option', 'source', []);
        if (countries.length === 1 && countries[0] !== selectedCountry && !lockCountry) {
          // if we have just one matching country, we force the
          // country-select to hold this value.
          countrySelect.find('option[value=' + countries[0] + ']').prop('selected', true);
          countrySelect.trigger('chosen:updated');
          // alert('selected: '+selectedCountry+' matching: '+countries[0]);
        } else if (countries.length > 1) {
          // provide the user with some more choices.
          // alert('blah');
          countryInput.autocomplete('option', 'source', countries);
          countryInput.show();
        }
        lockCountry = false;

        // data.message += CAFEVDB.print_r(citySuggestions, true);
        if (data.message !== '') {
          Dialogs.alert(
            data.message, t(appName, 'Address Validation'), cleanup, true, true);
          Dialogs.debugPopup(data);
        } else {
          cleanup();
        }
      });
    return false;
  });
};

const ready = function(container) {

  // sanitize
  container = PHPMyEdit.container(container);

  contactValidation(container);

  const form = container.find('form.pme-form');
  // const nameInputs = form.find('input.musician-name');

  let nameValidationActive = false;

  // avoid duplicate entries in the DB, but only when adding new
  // musicians.
  container
    .find('form.pme-form input.musician-name.add-musician')
    .off('blur')
    .on('blur', function(event) {

      if (nameValidationActive) {
        event.stopImmediatePropagation();
        return false;
      }

      nameValidationActive = true;

      const post = form.serialize();

      const cleanup = function() {
        nameValidationActive = false;
      };

      $.post(
        generateUrl('validate/musicians/duplicates'),
        post)
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['message'], cleanup)) {
            return;
          }

          if (!$.isEmptyObject(data.duplicates)) {
            let numDuplicates = 0;
            const ids = [];
            for (const id in data.duplicates) {
              ++numDuplicates;
              ids.push(id);
            }
            if (numDuplicates > 0) {
              Dialogs.confirm(
                t(appName,
                  'You definitely do not want to add duplicates to the database.'
                  + '\n'
                  + 'Please answer "no" in order to add a new musician,'
                  + '\n'
                  + 'otherwise answer "yes". If you react in a positive manner'
                  + '\n'
                  + 'you will be redirected to a web form in order to bring'
                  + '\n'
                  + 'the personal data of the respective musician up-to-date.')
                  .replace(/(?:\r\n|\r|\n)/g, '<br/>'),
                t(appName, 'Avoid Possible Duplicate?'),
                function(answer) {
                  nameValidationActive = false;
                  if (!answer) {
                    return;
                  }
                  const mainContainer = $(container.data('AmbientContainer'));
                  const form = mainContainer.find(PHPMyEdit.formSelector());
                  container.dialog('close');
                  if (numDuplicates === 1) {
                    const projectId = form.find('input[name="ProjectId"]').val();
                    const projectName = form.find('input[name="ProjectName"]').val();
                    ProjectParticipants.personalRecordDialog(
                      ids[0],
                      {
                        table: 'Musicians',
                        InitialValue: 'View',
                        projectId: projectId || -1,
                        projectName,
                      }
                    );
                  } else {
                    ProjectParticipants.loadMusicians(form, ids, null);
                  }
                }, true, true);
            }
            return false;
          }
          if (data.message !== '') {
            Dialogs.alert(data.message, t(appName, 'Possible Duplicate!'), cleanup, true, true);
          } else {
            cleanup();
          }
        });

      return false;
    });

  container
    .find('input.register-musician')
    .off('click')
    .on('click', function(event) {
      const form = container.find('form.pme-form');
      const projectId = form.find('input[name="projectId"]').val();
      const projectName = form.find('input[name="projectName"]').val();
      const musicianId = $(this).data('musician-id');

      addMusicians(form, {
        projectId,
        projectName,
        musicianId,
      });
      return false;
    });

  // container.find('input.bulkcommit.pme-misc').addClass('formsubmit');
  container
    .find('input.bulkcommit.pme-misc')
    .addClass('pme-custom')
    .prop('disabled', false)
    .off('click')
    .on('click', function(event) {

      const form = container.find('form.pme-form');
      addMusicians(form);
      return false;
    });

};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {
    if ($('div#cafevdb-page-body.musicians').length > 0) {
      ready();
    }
  });
};

export {
  ready,
  documentReady,
  contactValidation,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
