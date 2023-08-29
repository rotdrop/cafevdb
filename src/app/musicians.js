/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import $ from './jquery.js';
import { appName } from './app-info.js';
import generateUrl from './generate-url.js';
import * as CAFEVDB from './cafevdb.js';
import * as Page from './page.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as ProjectParticipants from './project-participants.js';
import * as PHPMyEdit from './pme.js';
import * as Notification from './notification.js';
import { selected as selectedValues } from './select-utils.js';
import { token as pmeToken, data as pmeData, sys as pmeSys, classSelectors as pmeClassSelectors } from './pme-selectors.js';
import { busyIcon as pageBusyIcon } from './page.js';
import {
  lazyDecrypt,
  reject as rejectDecryptionPromise,
  promise as decryptionPromise,
} from './lazy-decryption.js';
import debounce from './debounce.js';

require('../legacy/nextcloud/jquery/octemplate.js');
require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');
require('sepa-bank-accounts.scss');
require('musicians.scss');

const submitSel = pmeClassSelectors('input', ['save', 'apply', 'more']);

/**
 * Get an array representation for one musician.
 *
 * @param {number} musicianId The database if of the musician.
 *
 * @returns {(Array|null)}
 */
const getMusician = async function(musicianId) {
  try {
    return await $.get(generateUrl('musicians/details/' + musicianId)).promise();
  } catch (xhr) {
    await new Promise((resolve) => Ajax.handleError(xhr, 'error', xhr.statusText, resolve));
    return null;
  }
};

/**
 * Add several musicians.
 *
 * @param {jQuery} $form TBD.
 *
 * @param {object} post TBD.
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
              musicianId, {
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

  if (!$form.hasClass(pmeToken('list'))) {
    const $expandProjectList = $form.find('input.projects-expand');
    console.info('EXPAND', $expandProjectList);
    $expandProjectList
      .off('click')
      .on('click', function(event) {
        console.info('HELLO');
        const $this = $(this);
        $this.closest('td').toggleClass('expanded');
        return false;
      });
  }

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

  const $emailInput = $form.find('[name$="email"]').filter('[name^="' + pmeData('') + '"]');
  const $allEmailsInput = $form.find('[name$="MusicianEmailAddresses@all:address[]"]');

  $allEmailsInput
    .off('change')
    .on('change', function(event) {
      const allEmails = selectedValues($allEmailsInput);
      console.info('ALL EMAILS', allEmails);
      if (allEmails.length > 0) {
        $emailInput.val(allEmails[0]).trigger('change');
      }
      $.fn.cafevTooltip.remove();
      return false;
    });

  $emailInput
    .off('blur, change')
    .on('blur, change', function(event) {

      event.stopImmediatePropagation();

      const submitDefer = PHPMyEdit.deferReload(container);

      const post = $form.serialize();
      $emailInput.prop('disabled', true);

      const cleanup = function() {
        $emailInput.prop('disabled', false);
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
          $emailInput.val(data.email);
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

  const $mailingListStatus = $form.find('span.mailing-list.announcements.status.status-label');
  const $mailingListOperationsContainer = $form.find('.mailing-list.announcements.dropdown-container');
  const $mailingListOperations = $mailingListOperationsContainer.find('.subscription-action');

  const $displayNameInput = $form.find('input[name$="display_name"]').filter('[name^="' + pmeData('') + '"]');

  $mailingListOperations
    .off('click')
    .on('click', function(event, triggerData) {
      const $this = $(this);
      triggerData = triggerData || { setup: false };

      const operation = $this.data('operation');
      if (!operation) {
        return;
      }

      const email = $emailInput.val();
      if (email === '') {
        if (!triggerData.setup) {
          Notification.messages(t(appName, 'Email-address is empty, cannot perform mailing list operations.'));
        }
        return false;
      }
      const displayName = $displayNameInput.val() || $displayNameInput.attr('placeholder');

      let cleanup = () => {
        $mailingListOperationsContainer.removeClass('busy');
        $this.removeClass('busy');
      };
      let onFail = (xhr, status, errorThrown) => Ajax.handleError(xhr, status, errorThrown, cleanup);
      if (triggerData.setup) {
        // don't annoy the user with an error message on page load.
        cleanup = () => {};
        onFail = () => {};
      } else {
        $this.addClass('busy');
        $mailingListOperationsContainer.addClass('busy');
      }

      $.fn.cafevTooltip.remove(); // remove pending tooltips ...

      const post = {
        list: 'announcements',
        email,
        displayName,
      };

      $.post(generateUrl('mailing-lists/' + operation), post)
        .fail(onFail)
        .done(function(data) {
          const status = data.status;
          $mailingListStatus.html(t(appName, status));

          $mailingListOperationsContainer.data('status', status);
          $mailingListOperationsContainer.attr(
            'class',
            $mailingListOperationsContainer.attr('class').replace(/(^|\s)status-\S+/, '$1status-' + status)
          );
          $mailingListOperations.each(function(index) {
            const $this = $(this);
            const visible = $this.hasClass('status-' + status + '-visible');
            const disabled = !visible || ($this.hasClass('expert-mode-only') && !$('body').hasClass('cafevdb-expert-mode'));
            $this.prop('disabled', disabled);
            $this.toggleClass('disabled', disabled);
          });
          cleanup();
        });
      return false;
    });
  // Trigger reload on page load. The problem is that meanwhile some
  // data-base fixups run on events after the legacy PME code has
  // generated its HTML output.
  $mailingListOperations.filter('.reload').trigger('click', [{ setup: true }]);

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

let nameValidationActive = false;

const checkForDuplicateMusicians = function($container, onCheckPassed) {

  if (nameValidationActive) {
    return;
  }

  nameValidationActive = true;

  const $form = $container.find('form.pme-form');
  const $submits = $form.find(submitSel);

  $submits.prop('disabled', true);

  const post = $form.serialize();

  if (typeof onCheckPassed !== 'function') {
    onCheckPassed = function() {};
  }

  const cleanup = function() {
    nameValidationActive = false;
    $submits.prop('disabled', false);
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

      Notification.messages(data.message);

      const duplicates = data.duplicates || {};
      const ids = Object.keys(duplicates);
      const numDuplicates = ids.length;
      if (numDuplicates === 0) {
        cleanup();
        onCheckPassed();
        return;
      }
      const $musicianViewTemplate = $('#musicianAddressViewTemplate');
      const $musicianViews = $('<div class="duplicate-musicians-view"></div>');
      let maxPropability = 0.0;
      const maxIds = [];
      for (const [musicianId, musician] of Object.entries(duplicates)) {
        const $musicianView = $musicianViewTemplate.octemplate(musician);
        $musicianViews.append($musicianView);
        if (musician.duplicatesPropability === maxPropability) {
          maxIds.push(musicianId);
        } else if (musician.duplicatesPropability > maxPropability) {
          maxPropability = musician.duplicatesPropability;
          maxIds.length = 0;
          maxIds.push(musicianId);
        }
      }

      if (maxPropability === 1.0) {
        // remove all none 100% people
        $musicianViews.empty();
        for (const musicianId of maxIds) {
          const $musicianView = $musicianViewTemplate.octemplate(duplicates[musicianId]);
          $musicianViews.append($musicianView);
        }

        Dialogs.alert(
          t(appName, 'I am refusing to add duplicates to the database.')
            + n(
              appName,
              'The following musician matches exactly your input:',
              'The following musicians match exactly your input:', numDuplicates)
            + $musicianViews.html()
            + t(appName, `When you click the 'OK'-button or close this alert-window
you will be redirected to the existing musician's data in order to inspect the sitution
and to add the existing musician to the project instead of generating a new duplicate
entry.`),
          t(appName, 'Duplicates Detected'),
          function() {
            cleanup();
            Notification.hide();
            const $mainContainer = $($container.data('ambientContainer'));
            const $mainForm = $mainContainer.find(PHPMyEdit.formSelector);
            $container.dialog('close');
            if (maxIds.length === 1) {
              const projectId = $mainForm.find('input[name="projectId"]').val();
              const projectName = $mainForm.find('input[name="projectName"]').val();
              ProjectParticipants.personalRecordDialog(
                ids[0], {
                  table: 'Musicians',
                  initialValue: 'View',
                  projectId: projectId || -1,
                  projectName,
                  [pmeSys('cur_tab')]: 1,
                });
            } else {
              ProjectParticipants.loadMusicians($mainForm, maxIds, null);
            }
          },
          true, // modal
          true, // html
        );
      } else {
        Dialogs.confirm(
          t(appName, 'You definitely do not want to add duplicates to the database.')
            + ' '
            + n(
              appName,
              'The following musician matches your input:',
              'The following musicians match also your input:', numDuplicates)
            + $musicianViews.html()
            + t(appName, `Please answer "YES" in order not to add a new musician,
otherwise answer "no" (but please do not do this). If you react in a positive manner
you will be redirected to a web form in order to bring
the personal data of the respective musician up-to-date.`),
          t(appName, 'Avoid Possible Duplicate?'),
          function(answer) {
            cleanup();
            Notification.hide();
            if (!answer) {
              onCheckPassed();
              return;
            }
            const $mainContainer = $($container.data('ambientContainer'));
            const $mainForm = $mainContainer.find(PHPMyEdit.formSelector);
            $container.dialog('close');
            if (numDuplicates === 1) {
              const projectId = $mainForm.find('input[name="ProjectId"]').val();
              const projectName = $mainForm.find('input[name="ProjectName"]').val();
              ProjectParticipants.personalRecordDialog(
                ids[0], {
                  table: 'Musicians',
                  initialValue: 'View',
                  projectId: projectId || -1,
                  projectName,
                  [pmeSys('cur_tab')]: 1,
                });
            } else {
              ProjectParticipants.loadMusicians($mainForm, ids, null);
            }
          },
          true, // modal
          true, // html
        );
      }
    }); // done callback

};

const novalidateSubmits = [
  'savedelete',
  'morechange',
  'savechange',
];

const ready = function(container) {

  // sanitize
  const $container = PHPMyEdit.container(container);

  contactValidation($container);

  rejectDecryptionPromise();
  console.time('DECRYPTION PROMISE');
  decryptionPromise.done((maxJobs) => {
    console.timeEnd('DECRYPTION PROMISE');
    console.info('MAX DECRYPTION JOBS HANDLED', maxJobs);
  });
  lazyDecrypt($container);

  const $form = $container.find('form.pme-form');

  if (container) {
    // const submits = $form.find(submitSel);

    $form
      .off('click', submitSel)
      .on('click', submitSel, function(event) {
        const $this = $(this);
        if (novalidateSubmits.findIndex(submit => $this.attr('name').indexOf(submit) >= 0) < 0) {
          checkForDuplicateMusicians($container, function() {
            $form.off('click', submitSel);
            $this.trigger('click');
          });
          return false;
        } else {
          return true;
        }
      });
  }

  // avoid duplicate entries in the DB, but only when adding new
  // musicians.
  $form
    .find('input.add-musician.duplicates-indicator')
    .on('blur', function(event) {
      if ($(this).val() === '') {
        return;
      }

      checkForDuplicateMusicians($container);
    });

  $form
    .find('input.register-musician')
    .off('click')
    .on('click', debounce(function(event) {
      const projectId = $form.find('input[name="projectId"]').val();
      const projectName = $form.find('input[name="projectName"]').val();
      const musicianId = $(this).data('musician-id');

      addMusicians($form, {
        projectId,
        projectName,
        musicianId,
      });
      return false;
    }));

  $form
    .find(['input', 'bulkcommit', pmeToken('misc'), pmeToken('commit')].join('.'))
    .addClass('pme-custom')
    .prop('disabled', false)
    .off('click')
    .on('click', debounce(function(event) {
      addMusicians($form);
      return false;
    }));

  $form
    .find('a.musician-instrument-insurance')
    .off('click')
    .on('click', function(event) {
      const href = $(this).attr('href');
      const queryString = href.split('?')[1];
      Page.loadPage(queryString);
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
  getMusician,
};
