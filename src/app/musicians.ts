/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de
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

import $, { jq } from './jquery.ts';
import { appName, appPrefix } from '../config.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import { translate as t, translatePlural as n } from '@nextcloud/l10n';
import * as CAFEVDB from './cafevdb.ts';
import * as Page from './page.ts';
import * as Ajax from './ajax.ts';
import * as Dialogs from './dialogs.ts';
import * as ProjectParticipants from './project-participants.js';
import * as PHPMyEdit from './pme.ts';
import * as Notification from './notification.ts';
import { selected as selectedValues } from './select-utils.ts';
import {
  token as pmeToken,
  data as pmeData,
  sys as pmeSys,
  classSelectors as pmeClassSelectors,
  valueSelector as pmeValueSelector,
  classSelector as pmeClassSelector,
} from './pme-selectors.ts';
import pageBusyIcon from './busy-icon.ts';
import {
  lazyDecrypt,
  reject as rejectDecryptionPromise,
  promise as decryptionPromise,
} from './lazy-decryption.ts';
import debounce from './debounce.ts';
import type { Musician } from '../types/address-book.d.ts';

require('../legacy/nextcloud/jquery/octemplate.js');
require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');
require('sepa-bank-accounts.scss');
require('musicians.scss');

const CANCELLED_STATUS = 'cancelled';
type ErrorTextStatus = JQuery.Ajax.ErrorTextStatus|typeof CANCELLED_STATUS;

const submitSel = pmeClassSelectors('input', ['save', 'apply', 'more']);
const selectedOptionsKey = '_m_selectedOptions';

/**
 * Get an array representation for one musician.
 *
 * @param musicianId The database if of the musician.
 */
const getMusician = async (musicianId: number) => {
  try {
    return await $.get(generateAppUrl('musicians/details/' + musicianId)).promise();
  } catch (e) {
    const xhr = e as JQuery.jqXHR;
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
const addMusicians = ($form: JQuery<HTMLFormElement>, post?: string|JQuery.PlainObject) => {
  const projectId = +$form.find<HTMLInputElement>('input[name="projectId"]').val()!;
  const projectName = $form.find<HTMLInputElement>('input[name="projectName"]').val()!;
  const participationContext = $form.find('input[name="participationContext"]').val();
  if (typeof post === 'undefined') {
    post = $form.serialize();
  }

  // Open the change-musician dialog with the newly
  // added musician in case of success.
  $.post(generateAppUrl('projects/participants/add-musicians'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, function() {
        // ProjectParticipants.loadProjectParticipants(form);
      });
    })
    .done(async (data) => {
      if (!Ajax.validateResponse(data, ['musicians'])) {
        // Load the underlying base-view in any case in order to go "back" ...
        ProjectParticipants.loadProjectParticipants($form);
        return;
      }
      console.debug(data);
      if (data.musicians.length === 1) {
        // open single person change dialog
        const musicianId = data.musicians[0];
        ProjectParticipants.personalRecordDialog(
          musicianId, {
            projectId,
            projectName,
            initialValue: 'Change',
            modified: false,
            template: 'project-' + participationContext,
          },
        );
        await ProjectParticipants.loadProjectParticipants(
          $form,
          undefined,
        );
      } else {
        // load the instrumentation table, initially restricted to the new musicians
        await ProjectParticipants.loadProjectParticipants($form, data.musicians);
      }
      Notification.messages(data.message);
    });
};

/**
 * Add auto-complete and validation handlers to musician input-data,
 * in particular personal data. In principle this is only relevant in
 * change and add mode.
 *
 * @param container TBD.
 */
const contactValidation = function(container?: string|JQuery) {

  const $container = container ? jq(container) : $('body');

  const $form = $container.find('form.' + pmeToken('form'));

  if (!$form.hasClass(pmeToken('list'))) {
    const $expandProjectList = $form.find('input.projects-expand');
    console.info('EXPAND', $expandProjectList);
    $expandProjectList
      .off('click')
      .on('click', function() {
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

  const acceptGenderClass = 'accept-gender-detection';
  $form
    .find('button.' + acceptGenderClass + ', .button.' + acceptGenderClass)
    .off('click')
    .on('click', function() {
      const $this = $(this);
      const gender = $this.data('value');
      const $genderInput = $this.closest(pmeValueSelector).find<HTMLSelectElement>(pmeClassSelector('', 'input'));
      console.info('GENDER', gender, $genderInput);
      if ($genderInput.is('select')) {
        selectedValues($genderInput, gender);
      } else {
        $genderInput.val(gender);
      }
      return false;
    });

  $form.find('input.phone-number')
    .not('.pme-filter')
    .off('blur')
    .on('blur', function(event) {

      event.stopImmediatePropagation();

      const submitDefer = PHPMyEdit.deferReload($container);

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
        generateAppUrl('validate/musicians/phone'),
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
  const $allEmailsInput = $form.find<HTMLSelectElement>('[name$="MusicianEmailAddresses@all:address[]"]');

  $allEmailsInput
    .off('change')
    .on('change', function() {
      const allEmails = selectedValues($allEmailsInput) as string[];
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

      const submitDefer = PHPMyEdit.deferReload($container);

      const post = $form.serialize();
      $emailInput.prop('disabled', true);

      const cleanup = function() {
        $emailInput.prop('disabled', false);
        submitDefer.resolve();
      };

      $.post(
        generateAppUrl('validate/musicians/email'),
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
          if (message) {
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
    .on('click', function(_event, triggerData) {
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
      let onFail = (xhr: JQuery.jqXHR, status: string, errorThrown: string) => {
        Ajax.handleError(xhr, status, errorThrown, cleanup);
      };
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

      $.post(generateAppUrl('mailing-lists/' + operation), post)
        .fail(onFail)
        .done(function(data) {
          const status = data.status;
          $mailingListStatus.html(t(appName, status));

          $mailingListOperationsContainer.data('status', status);
          $mailingListOperationsContainer.attr(
            'class',
            $mailingListOperationsContainer.attr('class')!.replace(/(^|\s)status-\S+/, '$1status-' + status),
          );
          $mailingListOperations.each(function() {
            const $this = $(this);
            const visible = $this.hasClass('status-' + status + '-visible');
            const disabled = !visible || ($this.hasClass('expert-mode-only') && !$('body').hasClass(appPrefix('expert-mode')));
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

  const $address = $form.find<HTMLInputElement>('input.musician-address');
  const city = $address.filter('.city');
  const street = $address.filter('.street');
  const postalCode = $address.filter('.postal-code');

  const $countrySelect = $form.find<HTMLSelectElement>('select.musician-address.country');
  const $allAddressFields = $($address).add($countrySelect);

  $allAddressFields.each(function() {
    const $this = $(this);
    $this.data('oldValue', $this.val()!);
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

  $address
    .autocomplete({
      source: [],
      minLength: 0,
      open(event, _ui) {
        const $input = $(event.target);
        const $results = $input.autocomplete('widget');
        const top = $results.position().top;
        const height = $results.outerHeight()!;
        const inputHeight = $input.outerHeight()!;
        const newTop = top - height - inputHeight;

        $results.css('top', newTop + 'px');
      },
      select(event, ui) {
        const $input = $(event.target);
        $input.val(ui.item.value);
        $input.trigger('blur');
      },
    })
    .on('focus, click', function() {
      if (!$(this).autocomplete('widget').is(':visible')) {
        $(this).autocomplete('search', '');
      }
    });

  // Inject a text input element for possible suggestions for the country setting.
  const $countryInput = $<HTMLInputElement>('<input type="text"'
                         + ' class="musician-address country"'
                         + ' id="country-autocomplete"'
                         + ' placeholder="' + t(appName, 'Suggestions') + '" />');
  $countryInput.hide();
  $countrySelect.before($countryInput);

  $countryInput
    .autocomplete({
      source: [],
      minLength: 0,
      open(event, _ui) {
        const $input = $(event.target);
        const $results = $input.autocomplete('widget');
        const top = $results.position().top;
        const height = $results.outerHeight()!;
        const inputHeight = $input.outerHeight()!;
        const newTop = top - height - inputHeight;

        $results.css('top', newTop + 'px');
      },
      select(_event, ui) {
        const country = ui.item.value;
        $countryInput.val(country);
        $countryInput.trigger('blur');
        return true;
      },
    })
    .on('blur', function(event) {
      const $self = $(this);

      event.stopImmediatePropagation();

      $countrySelect.data('oldValue', selectedValues($countrySelect, $self.val()!, true));

      return false;
    })
    .on('focus, click', function() {
      if (!$(this).autocomplete('widget').is(':visible')) {
        $(this).autocomplete('search', '');
      }
    });

  let lockCountry = false;
  $countrySelect.on('change', function() {
    if (needAutocompleteUpdate()) {
      updateAutocompleteData();
    }
    lockCountry = !!selectedValues($countrySelect);
    return false;
  });

  let autocompletePlaceRequest: JQuery.jqXHR|null = null;
  let autocompleteStreetRequest: JQuery.jqXHR|null = null;

  const fetchPlaceAutocompletion = function() {

    const post = $form.serialize();

    if (autocompletePlaceRequest) {
      console.info('TRY CANCEL AUTOCOMPLETE PLACE');
      autocompletePlaceRequest.abort('cancelled');
    }

    console.info('INITIATE PLACE AUTOCOMPLETE');
    autocompletePlaceRequest = $.post(
      generateAppUrl('validate/musicians/autocomplete/place'),
      post)
      .fail(function(xhr, status: ErrorTextStatus, errorThrown) {
        if (status !== 'cancelled') {
          console.error('Auto-complete update failed', xhr, status, errorThrown);
        } else {
          console.error('Auto-complete update cancelled');
        }
      })
      .done(function(data) {
        if (!data || !data.cities || !data.countries || !data.postalCodes) {
          console.error('Auto-complete request does not contain the requested data.', data);
          return;
        }

        city.autocomplete('option', 'source', data.cities);
        postalCode.autocomplete('option', 'source', data.postalCodes);

        $address.each(function() {
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
      });

    console.info('PLACE AUTOCOMPLETE PROMISE', autocompletePlaceRequest);

    return autocompletePlaceRequest;
  };

  const fetchStreetAutocompletion = function() {
    console.info('ENTER STREET AUTOCOMPLETE');

    const post = $form.serialize();

    if (autocompleteStreetRequest) {
      console.info('TRY CANCEL AUTOCOMPLETE STREET');
      autocompleteStreetRequest.abort('cancelled');
    }

    console.info('INITIATE STREET AUTOCOMPLETE');
    autocompleteStreetRequest = $.post(
      generateAppUrl('validate/musicians/autocomplete/street'),
      post)
      .fail(function(xhr, status: ErrorTextStatus, errorThrown) {
        if (status !== 'cancelled') {
          console.error('Auto-complete update failed', xhr, status, errorThrown);
        } else {
          console.error('Auto-complete update cancelled');
        }
      })
      .done(function(data) {
        if (!data || !data.streets) {
          console.error('Auto-complete request does not contain the requested data.', data);
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
      });

    PHPMyEdit.pushCancellable($container, autocompleteStreetRequest);

    console.info('STREET AUTOCOMPLETE PROMISE', autocompleteStreetRequest);

    return autocompleteStreetRequest;
  };

  $address.on('blur', function(event) {
    const $self = $(this);

    if ($self.hasClass('street')) {
      // too costly
      return true;
    }

    if ($self.data('oldValue') === ($self.val() ?? null)) {
      // avoid refresh when the value has not changed
      return true;
    }

    $self.data('oldValue', $self.val() ?? null);

    // this is somehow needed here ...
    event.stopImmediatePropagation();

    if ($self.autocomplete('widget').is(':visible')) {
      // don't validate while select box is open
      return false;
    }

    const submitDefer = PHPMyEdit.deferReload($container);
    pageBusyIcon(true);

    Promise.allSettled([
      new Promise((resolve, reject) => fetchPlaceAutocompletion().done(resolve).fail(() => reject(new Error()))),
      new Promise((resolve, reject) => fetchStreetAutocompletion().done(resolve).fail(() => reject(new Error()))),
    ])
      .finally(() => {
        pageBusyIcon(false);
        submitDefer.resolve();
      });

    return false;
  });

  // force an update of the autocomplete data
  updateAutocompleteData();
};

type DuplicateMusician = Musician & {
  duplicatesPropability: number;
  reasons: string;
};

type DuplicatesResponse = {
  messages: string[];
  duplicates: Record<number, DuplicateMusician>;
};

let nameValidationActive = false;

const checkForDuplicateMusicians = ($container: JQuery, onCheckPassed: () => void = () => {}) => {

  if (nameValidationActive) {
    return;
  }

  nameValidationActive = true;

  const $form = $container.find('form.pme-form');
  const $submits = $form.find(submitSel);

  $submits.prop('disabled', true);

  const post = $form.serialize();

  const cleanup = function() {
    nameValidationActive = false;
    $submits.prop('disabled', false);
  };

  $.post(
    generateAppUrl('validate/musicians/duplicates'),
    post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, cleanup);
    })
    .done(function(data: DuplicatesResponse) {
      if (!Ajax.validateResponse(data, ['message'], cleanup)) {
        return;
      }

      Notification.messages(data.messages);

      const duplicates = data.duplicates ?? {};
      const ids = Object.keys(duplicates).map(x => +x);
      const numDuplicates = ids.length;
      if (numDuplicates === 0) {
        cleanup();
        onCheckPassed();
        return;
      }
      const $musicianViewTemplate = $('#musicianAddressViewTemplate');
      const $musicianViews = $('<div class="duplicate-musicians-view"></div>');
      let maxPropability = 0.0;
      const maxIds: number[] = [];
      for (const [musicianId, musician] of Object.entries(duplicates)) {
        const $musicianView = $musicianViewTemplate.octemplate(musician);
        $musicianViews.append($musicianView);
        if (musician.duplicatesPropability === maxPropability) {
          maxIds.push(+musicianId);
        } else if (musician.duplicatesPropability > maxPropability) {
          maxPropability = musician.duplicatesPropability;
          maxIds.length = 0;
          maxIds.push(+musicianId);
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
            const $mainForm = $mainContainer.find<HTMLFormElement>(PHPMyEdit.formSelector);
            $container.dialog('close');
            if (maxIds.length === 1) {
              const projectId = $mainForm.find<HTMLInputElement>('input[name="projectId"]').val();
              const projectName = $mainForm.find<HTMLInputElement>('input[name="projectName"]').val();
              ProjectParticipants.personalRecordDialog(
                ids[0], {
                  table: 'Musicians',
                  initialValue: 'View',
                  projectId: +(projectId ?? -1),
                  projectName,
                  [pmeSys('cur_tab')]: 1,
                });
            } else {
              ProjectParticipants.loadMusicians($mainForm, maxIds);
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
            const $mainForm = $mainContainer.find<HTMLFormElement>(PHPMyEdit.formSelector);
            $container.dialog('close');
            if (numDuplicates === 1) {
              const projectId = $mainForm.find<HTMLInputElement>('input[name="ProjectId"]').val();
              const projectName = $mainForm.find<HTMLInputElement>('input[name="ProjectName"]').val();
              ProjectParticipants.personalRecordDialog(
                ids[0], {
                  table: 'Musicians',
                  initialValue: 'View',
                  projectId: +(projectId ?? -1),
                  projectName,
                  [pmeSys('cur_tab')]: 1,
                });
            } else {
              ProjectParticipants.loadMusicians($mainForm, ids);
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

const ready = function(container?: string|JQuery) {

  // sanitize
  const $container = PHPMyEdit.container(container);

  contactValidation($container);

  const $selectMusicianInstruments = $container.find<HTMLSelectElement>('.pme-value select.musician-instruments');

  $selectMusicianInstruments.data(
    selectedOptionsKey,
    selectedValues($selectMusicianInstruments),
  );

  $selectMusicianInstruments.on('change', function(this: HTMLSelectElement) {
    const $self = $(this);

    PHPMyEdit.tableDialogLock($container, true);
    PHPMyEdit.tableDialogLoadIndicator($container, true);

    const fail = (data: Ajax.AjaxFailData & { oldInstruments?: string[] }) => {
      // failure case

      const oldInstruments = data.oldInstruments ?? $self.data(selectedOptionsKey) as string[];

      console.info('SELECTED MUSICIAN INSTRUMENTS', $self.data(selectedOptionsKey));

      selectedValues($self, oldInstruments);

      PHPMyEdit.tableDialogLoadIndicator($container, false);
      PHPMyEdit.tableDialogLock($container, false);
    };

    ProjectParticipants.validateInstrumentChoices({
      $container,
      $selectElement: $selectMusicianInstruments,
      validationUrl: generateAppUrl('projects/participants/validate/instruments/musician'),
      participationContext: 'participants',
      done() {
        // save current instruments
        const failureData = {
          oldInstruments: [...$self.data(selectedOptionsKey)],
        };
        $self.data(selectedOptionsKey, selectedValues($self));
        // submit the form with the "right" button,
        // i.e. save any possible changes already
        // entered by the user. The form-submit
        // will then also reload with an up to date
        // list of instruments
        (PHPMyEdit.submitOuterForm($container) as Promise<unknown>)
          .then(
            (result) => console.info('RELOAD COMPLETED', { result }),
            (error: Ajax.AjaxFailData) => fail({ ...error, ...failureData }),
          );
      },
      fail,
    });

    return false;
  });

  rejectDecryptionPromise();
  console.time('DECRYPTION PROMISE');
  decryptionPromise.done((maxJobs) => {
    console.timeEnd('DECRYPTION PROMISE');
    console.info('MAX DECRYPTION JOBS HANDLED', maxJobs);
  });
  lazyDecrypt($container);

  const $form = $container.find<HTMLFormElement>('form.pme-form');

  if (container) {
    // const submits = $form.find(submitSel);

    $form
      .off('click', submitSel)
      .on('click', submitSel, function() {
        const $this = $(this);
        if (novalidateSubmits.findIndex(submit => $this.attr('name')!.indexOf(submit) >= 0) < 0) {
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
    .on('blur', function() {
      if ($(this).val() === '') {
        return;
      }

      checkForDuplicateMusicians($container);
    });

  $form
    .find('input.register-musician')
    .off('click')
    .on('click', debounce(function(this: HTMLInputElement) {
      const projectId = $form.find('input[name="projectId"]').val();
      const projectName = $form.find('input[name="projectName"]').val();
      const participationContext = $form.find('input[name="participationContext"]').val();
      const musicianId = $(this).data('musician-id');

      addMusicians($form, {
        projectId,
        projectName,
        musicianId,
        participationContext,
      });
      return false;
    }));

  $form
    .find(['input', 'bulkcommit', pmeToken('misc'), pmeToken('commit')].join('.'))
    .addClass(pmeToken('custom'))
    .prop('disabled', false)
    .off('click')
    .on('click', debounce(function() {
      addMusicians($form);
      return false;
    }));

  $form
    .find('a.musician-instrument-insurance')
    .off('click')
    .on('click', function() {
      const href = $(this).attr('href')!;
      const queryString = href.split('?')[1];
      Page.loadPage(queryString);
      return false;
    });
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(async () => {
    if ($('div#' + appPrefix('page-body') + '.musicians').length > 0) {
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
