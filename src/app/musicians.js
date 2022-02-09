/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de
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
import * as Notification from './notification.js';
import { selected as selectedValues } from './select-utils.js';
import { token as pmeToken } from './pme-selectors.js';
import { busyIcon as pageBusyIcon } from './page.js';

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');
require('sepa-bank-accounts.scss');

/**
 * Add several musicians.
 *
 * @param {jQuery} $form TBD.
 *
 * @param {Object} post TBD.
 */
const addMusicians = function($form, post) {
  const projectId = $form.find('input[name="projectId"]').val();
  const projectName = $form.find('input[name="projectName"]').val();
  if (typeof post === 'undefined') {
    post = $form.serialize();
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
        ProjectParticipants.loadProjectParticipants($form);
        return;
      }
      console.log(data);
      // Notification.messages(data.message);
      if (data.musicians.length === 1) {
        // open single person change dialog
        const musicianId = data.musicians[0];
        // alert('data: '+CAFEVDB.print_r(musician, true));
        ProjectParticipants.loadProjectParticipants(
          $form,
          undefined,
          function() {
            Notification.messages(data.message);
            ProjectParticipants.personalRecordDialog(
              {
                projectId,
                musicianId,
              },
              {
                projectId,
                projectName,
                initialValue: 'Change',
                modified: true,
              });
          });
      } else {
        // load the instrumentation table, initially restricted to the new musicians
        ProjectParticipants.loadProjectParticipants($form, data.musicians, function() {
          Notification.messages(data.message);
        });
      }
    });
};

/**
 * Add auto-complete and validation handlers to musician input-data,
 * in particular personal data. In principle this is only relevant in
 * change and add mode.
 *
 * @param {jQuery|string} container TBD.
 */
const contactValidation = function(container) {

  const $container = container || $('body');

  const $form = $container.find('form.' + pmeToken('form'));

  // "read-only" forms do not need contact validation handlers
  if ($form.hasClass(pmeToken('list'))
      || $form.hasClass(pmeToken('view'))
      || $form.hasClass(pmeToken('delete'))
  ) {
    return;
  }

  $form.find('input.phone-number')
    .not('.pme-filter')
    .off('blur')
    .on('blur', function(event) {

      event.stopImmediatePropagation();

      const submitDefer = PHPMyEdit.deferReload(container);

      const phones = $form.find('input.phone-number');
      const post = $form.serialize();
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
          const message = Array.isArray(data.message)
            ? data.message.join('<br>')
            : data.message;
          if (message !== '') {
            Dialogs.alert(
              message,
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

  $form.find('input.email')
    .not('.pme-filter')
    .off('blur')
    .on('blur', function(event) {

      event.stopImmediatePropagation();

      const submitDefer = PHPMyEdit.deferReload(container);

      const email = $form.find('input.email');
      const post = $form.serialize();
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
          $form.find('input[name$="email"]').val(data.email);
          const message = Array.isArray(data.message)
            ? data.message.join('<br>')
            : data.message;
          if (message !== '') {
            Dialogs.alert(
              message,
              t(appName, 'Email Validation'),
              cleanup, true, true);
            Dialogs.debugPopup(data);
          } else {
            cleanup();
          }
        });
    });

  const address = $form.find('input.musician-address');
  const city = address.filter('.city');
  const street = address.filter('.street');
  const postalCode = address.filter('.postal-code');

  const $countrySelect = $form.find('select.musician-address.country');
  const $allAddressFields = $(address).add($countrySelect);

  $allAddressFields.each(function() {
    const $this = $(this);
    $this.data('oldValue', $this.val());
  });

  const updateAutocompleteData = function() {
    postalCode.data('oldValue', null);
    postalCode.trigger('blur');
  };

  const needAutocompleteUpdate = function() {
    return ($countrySelect.data('oldValue') !== $countrySelect.val()
            || city.data('oldValue') !== city.val()
            || postalCode.data('oldValue') !== postalCode.val());
  };

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
      select(event, ui) {
        const $input = $(event.target);
        $input.val(ui.item.value);
        $input.blur();
      },
    })
    .on('focus, click', function() {
      if (!$(this).autocomplete('widget').is(':visible')) {
        $(this).autocomplete('search', '');
      }
    });

  // Inject a text input element for possible suggestions for the country setting.
  const $countryInput = $('<input type="text"'
                         + ' class="musician-address country"'
                         + ' id="country-autocomplete"'
                         + ' placeholder="' + t(appName, 'Suggestions') + '" />');
  $countryInput.hide();
  $countrySelect.before($countryInput);

  $countryInput
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
        $countryInput.val(country);
        $countryInput.trigger('blur');
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

      $countrySelect.data('oldValue', selectedValues($countrySelect, self.val(), true));

      return false;
    });

  let lockCountry = false;
  $countrySelect.on('change', function(event) {
    if (needAutocompleteUpdate()) {
      updateAutocompleteData();
    }
    lockCountry = !!selectedValues($countrySelect);
    return false;
  });

  let autocompletePlaceRequest = null;
  let autocompleteStreetRequest = null;

  const fetchPlaceAutocompletion = function() {

    const submitDefer = PHPMyEdit.deferReload(container);

    const post = $form.serialize();

    if (autocompletePlaceRequest) {
      autocompletePlaceRequest.abort('cancelled');
    }

    pageBusyIcon(true);

    const cleanup = function() {
      submitDefer.resolve();
      autocompletePlaceRequest = null;
      if (!autocompleteStreetRequest) {
        pageBusyIcon(false);
      } else {
        $.when(autocompleteStreetRequest).then(() => pageBusyIcon(false));
      }
    };

    autocompletePlaceRequest = $.post(
      generateUrl('validate/musicians/autocomplete/place'),
      post)
      .fail(function(xhr, status, errorThrown) {
        if (status !== 'cancelled') {
          console.error('Auto-complete update failed', xhr, status, errorThrown);
        } else {
          console.error('Auto-complete update cancelled');
        }
        cleanup();
      })
      .done(function(data) {
        if (!data || !data.cities || !data.countries || !data.postalCodes) {
          console.error('Auto-complete request does not contain the requested data.', data);
          cleanup();
          return;
        }

        city.autocomplete('option', 'source', data.cities);
        postalCode.autocomplete('option', 'source', data.postalCodes);

        address.each(function() {
          const $this = $(this);
          const sourceSize = $this.autocomplete('option', 'source').length;
          $this.autocomplete('option', 'minLength', sourceSize > 20 ? 3 : 0);
        });

        const selectedCountry = selectedValues($countrySelect);
        const countries = data.countries;
        $countryInput.hide();
        $countryInput.autocomplete('option', 'source', []);
        if (countries.length === 1 && countries[0] !== selectedCountry && !lockCountry) {

          // if we have just one matching country, we force the
          // country-select to hold this value.
          $countrySelect.data('oldValue', selectedValues($countrySelect, countries));

        } else if (countries.length > 1) {
          // provide the user with some more choices.
          $countryInput.autocomplete('option', 'source', countries);
          $countryInput.show();
        }
        lockCountry = false;

        Notification.messages(data.message);

        cleanup();

      });

    return autocompletePlaceRequest;
  };

  const fetchStreetAutocompletion = function() {

    // const submitDefer = PHPMyEdit.deferReload(container);

    const post = $form.serialize();

    if (autocompleteStreetRequest) {
      autocompleteStreetRequest.abort('cancelled');
    }

    pageBusyIcon(true);

    const cleanup = function() {
      // submitDefer.resolve();
      autocompleteStreetRequest = null;
      if (!autocompletePlaceRequest) {
        pageBusyIcon(false);
      } else {
        $.when(autocompletePlaceRequest).then(() => pageBusyIcon(false));
      }
    };

    autocompleteStreetRequest = $.post(
      generateUrl('validate/musicians/autocomplete/street'),
      post)
      .fail(function(xhr, status, errorThrown) {
        if (status !== 'cancelled') {
          console.error('Auto-complete update failed', xhr, status, errorThrown);
          cleanup();
        } else {
          console.error('Auto-complete update cancelled');
        }
      })
      .done(function(data) {
        if (!data || !data.streets) {
          console.error('Auto-complete request does not contain the requested data.', data);
          cleanup();
          return;
        }

        if (street.autocomplete('instance') === undefined) {
          console.error('STREET INPUT ELEMENT LACKS AUTOCOMPLETE WIDGET', street);
        } else {
          street.autocomplete('option', 'source', data.streets);
          const sourceSize = street.autocomplete('option', 'source').length;
          street.autocomplete('option', 'minLength', sourceSize > 20 ? 3 : 0);

          Notification.messages(data.message);
        }

        cleanup();

      });

    PHPMyEdit.pushCancellable(container, autocompleteStreetRequest);

    return autocompleteStreetRequest;
  };

  address.on('blur', function(event) {
    const self = $(this);

    if (self.hasClass('street')) {
      // too costly
      return true;
    }

    if (self.data('oldValue') === self.val()) {
      // avoid refresh when the value has not changed
      return true;
    }

    self.data('oldValue', self.val());

    // this is somehow needed here ...
    event.stopImmediatePropagation();

    if (self.autocomplete('widget').is(':visible')) {
      // don't validate while select box is open
      return false;
    }

    fetchPlaceAutocompletion();
    fetchStreetAutocompletion();

    return false;
  });

  // force an update of the autocomplete data
  updateAutocompleteData();
};

const ready = function(container) {

  // sanitize
  const $container = PHPMyEdit.container(container);

  contactValidation($container);

  const $form = $container.find('form.pme-form');
  // const nameInputs = form.find('input.musician-name');

  let nameValidationActive = false;

  // avoid duplicate entries in the DB, but only when adding new
  // musicians.
  $form
    .find('input.musician-name.add-musician')
    .off('blur')
    .on('blur', function(event) {

      if (nameValidationActive) {
        event.stopImmediatePropagation();
        return false;
      }

      nameValidationActive = true;

      const post = $form.serialize();

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
                  const $mainContainer = $($container.data('ambientContainer'));
                  const $mainForm = $mainContainer.find(PHPMyEdit.formSelector());
                  $container.dialog('close');
                  if (numDuplicates === 1) {
                    const projectId = $mainForm.find('input[name="ProjectId"]').val();
                    const projectName = $mainForm.find('input[name="ProjectName"]').val();
                    ProjectParticipants.personalRecordDialog(
                      ids[0],
                      {
                        table: 'Musicians',
                        initialValue: 'View',
                        projectId: projectId || -1,
                        projectName,
                      }
                    );
                  } else {
                    ProjectParticipants.loadMusicians($mainForm, ids, null);
                  }
                }, true, true);
            }
            return false;
          }
          const message = Array.isArray(data.message)
            ? data.message.join('<br>')
            : data.message;
          if (message !== '') {
            Dialogs.alert(message, t(appName, 'Possible Duplicate!'), cleanup, true, true);
          } else {
            cleanup();
          }
        });

      return false;
    });

  $form
    .find('input.register-musician')
    .off('click')
    .on('click', function(event) {
      const projectId = $form.find('input[name="projectId"]').val();
      const projectName = $form.find('input[name="projectName"]').val();
      const musicianId = $(this).data('musician-id');

      addMusicians($form, {
        projectId,
        projectName,
        musicianId,
      });
      return false;
    });

  $form
    .find(['input', 'bulkcommit', pmeToken('misc'), pmeToken('commit')].join('.'))
    .addClass('pme-custom')
    .prop('disabled', false)
    .off('click')
    .on('click', function(event) {
      addMusicians($form);
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
