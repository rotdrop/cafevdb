/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import { appName, appPrefix } from './config.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Page from './page.js';
import * as Musicians from './musicians.js';
import * as Notification from './notification.js';
import * as Dialogs from './dialogs.js';
import * as SepaDebitMandate from './sepa-debit-mandate.js';
import initFileUploadRow from './pme-file-upload-row.js';
import participantFieldsHandlers from './project-participant-fields-display.js';
import { instrumentationNumbersPopup } from './projects.js';
import { rec as pmeRec, recordValue as pmeRecordValue } from './pme-record-id.js';
import * as PHPMyEdit from './pme.js';
import * as SelectUtils from './select-utils.js';
import generateUrl from './generate-url.js';
import pmeExportMenu from './pme-export.js';
import { pageRenderer } from './pme-state.js';
import {
  lazyDecrypt,
  reject as rejectDecryptionPromise,
  promise as decryptionPromise,
} from './lazy-decryption.js';
import {
  sys as pmeSys,
  formSelector as pmeFormSelector,
  classSelector as pmeClassSelector,
  token as pmeToken,
} from './pme-selectors.js';

require('../legacy/nextcloud/jquery/octemplate.js');
require('project-participant-fields-display.scss');
require('project-participants.scss');

const selectedOptionsKey = '_pp_selectedOptions';

/**
 * Open a dialog in order to edit the personal reccords of one
 * musician.
 *
 * @param {number|object} record The record id either as object or
 * musician id. The format will be converted as appropriate depending
 * on whether the ProjectParticipants table of the Musicians table is
 * queried.
 *
 * @param {object} options Additional option. In particular ProjectId
 * and ProjectName are honored, and the optiones IntialValue and
 * reloadValue which should be one of 'View' or 'Change' (though
 * 'Delete' should also work).
 */
const myPersonalRecordDialog = function(record, options) {
  if (typeof options === 'undefined') {
    options = {
      initialValue: 'View',
      reloadValue: 'View',
      projectId: -1,
    };
  }

  if (typeof options.initialValue === 'undefined') {
    options.initialValue = 'View';
  }
  if (typeof options.reloadValue === 'undefined') {
    options.reloadValue = options.initialValue;
  }

  // treat integer record id as musician id
  if (!isNaN(record)) {
    record = { musicianId: record };
  }
  if (record.id) {
    record.musicianId = record.id;
  }

  const pmeOperation = PHPMyEdit.sys('operation');
  const pmeRecord = PHPMyEdit.sys('rec');

  let tableOptions = {
    projectId: -1,
    projectName: '',
    ambientContainerSelector: PHPMyEdit.selector(),
    dialogHolderCSSId: 'personal-record-dialog',
    // Now special options for the dialog popup
    initialViewOperation: options.initialValue === 'View',
    initialName: pmeOperation,
    initialValue: 'View',
    reloadName: pmeOperation,
    reloadValue: 'View',
    modalDialog: true,
    modified: false,
  };

  // Merge remaining options in.
  tableOptions = $.extend(tableOptions, options);

  if (tableOptions.table === 'Musicians') {
    const projectMode = options.projectId > 0;
    tableOptions.template = projectMode ? 'add-musicians' : 'all-musicians';
    tableOptions.templateRenderer = Page.templateRenderer(tableOptions.template);

    // the proper record id is an object { id: ID }.
    record = { id: record.musicianId };
  } else if (options.projectId > 0) {
    tableOptions.table = 'ProjectParticipants';
    tableOptions.template = 'project-participants';
    tableOptions.templateRenderer = Page.templateRenderer(tableOptions.template);

    // the proper record id is an object { project_id, musician_id }.
    // eslint-disable-next-line camelcase
    record = { musician_id: record.musicianId, project_id: options.projectId };
  } else {
    tableOptions.table = 'Musicians';
    tableOptions.template = 'all-musicians';
    tableOptions.templateRenderer = Page.templateRenderer(tableOptions.template);
    // the proper record id is an object { id: ID }.
    record = { id: record.musicianId };
  }

  tableOptions[pmeRecord] = record; // will be converted by $.param
  tableOptions[pmeOperation] = options.reloadValue + '?' + pmeRecord + '=' + encodeURIComponent(JSON.stringify(record));

  // alert('options: ' + CAFEVDB.print_r(tableOptions, true));

  PHPMyEdit.tableDialogOpen(tableOptions);
};

/**
 * Trigger server-side validation and fetch the result.
 *
 * @param {object} options
 *
 * Would perhaps be snappier to only submit the form to the
 * server if something changed. However, the validation is triggered
 * by a change event. So what.
 */
const validateInstrumentChoices = function(options) {
  const container = options.container;
  const selectMusicianInstrument = options.selectElement;
  const ajaxScript = options.validationUrl;
  const finalizeCB = options.done;
  const errorCB = options.fail;

  Notification.hide();
  $
    .post(ajaxScript, {
      recordId: pmeRec(container),
      instrumentValues: SelectUtils.selected(selectMusicianInstrument),
    })
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, errorCB);
    })
    .done(function(data) {
      if (!Ajax.validateResponse(data, ['message'], errorCB)) {
        return;
      }
      finalizeCB();
      Notification.messages(data.message);
    });
};

/**
 * Pseudo-submit an underlying PME-form with tweaked form data.
 *
 * @param {jQuery} form A form with additional input data which is
 * submitted as well. Submit buttons are omitted.
 *
 * @param {object} formData Data for hidden input elements which replace the
 * form's "native" data. Example:
 *
 * formData = {
 *   template: "project-participants",
 *   templateRenderer: "template:project-participants"
 * };
 *
 * The form is submitted with an empty pseudo-submit button.
 *
 * @param {Function} afterLoadCallback Optional. A function called after the
 * table-view has been loaded.
 */
const loadPMETable = function(form, formData, afterLoadCallback) {
  const pmeSys = PHPMyEdit.sys('');
  form.find('input').not('[name^="' + pmeSys + '"]').each(function(idx) {
    const $self = $(this);
    const name = $self.attr('name');
    if (name) {
      if (typeof formData[name] === 'undefined') {
        formData[name] = $self.val();
      }
    }
  });
  Page.loadPage(formData, afterLoadCallback);
};

/**
 * Pseudo-submit an underlying PME-form with tweaked form data, like
 * loadPMETable(), but restrict the display to the ids passed in the
 * flat array @a ids.
 *
 * @param {jQuery} form A form with additional input data which is
 * submitted as well. Submit buttons are omitted.
 *
 * @param {object} formData Data for hidden input elements which replace the
 * form's "native" data. Example:
 *
 * formData = {
 *   template: "project-participants",
 *   templateRenderer: "template:project-participants"
 * };
 *
 * The form is submitted with an empty pseudo-submit button.
 *
 * @param {Array} ids An array containing the ids that will be
 * displayed. If ids is empty or contains an entry @c -1 then no filtering will
 * take place.
 *
 * @param {Function} afterLoadCallback Optional. A function called after the
 * table-view has been loaded.
 */
const loadPMETableFiltered = function(form, formData, ids, afterLoadCallback) {
  if (typeof ids === 'undefined' || !ids) {
    ids = [];
  }

  const pmeSys = PHPMyEdit.sys('');
  let filterData = {};
  for (let idx = 0; idx < ids.length; ++idx) {
    const indices = (typeof ids[idx] === 'object') ? ids[idx] : { 0: ids[idx] };
    for (const keyIndex in indices) {
      const name = pmeSys + 'qf' + keyIndex + '_idx[' + idx + ']';
      const value = indices[keyIndex];
      if (value === -1) {
        filterData = {};
        break;
      }
      // console.info('FILTER NAME', name);
      // console.info('FILTER VALUE', value);
      filterData[name] = value;
    }
  }
  $.extend(formData, filterData);

  loadPMETable(form, formData, afterLoadCallback);
};

/**
 * Load the table of all musicians, possibly in "project" mode and
 * possibly restricted to subset of the musicians by providing an
 * array with selected ids.
 *
 * @param {jQuery} form The current PME form.
 *
 * @param {Array} ids An array containing the ids that will be
 * displayed. If ids is empty or contains an entry -1 then no filtering will
 * take place.
 *
 * @param {boolean} projectMode @c true, @c false, @c null or omitted.
 * If @c null or not present, then @a form will be searched for an input element with
 * name @c ProjectId, if present and its value is positive, the main musisians table is
 * loaded in project mode, allowing for adding new participants to the respective project.
 *
 * @param {Function} afterLoadCallback Optional. A function called after the
 * table-view has been loaded.
 */
const myLoadMusicians = function(form, ids, projectMode, afterLoadCallback) {
  if (typeof projectMode === 'undefined' || projectMode === null) {
    // Check whether form contains an input element for a
    // ProjectId. If its value is positive, switch to project mode,
    // otherwise assume all-musicians mode.
    const projectId = form.find('input[name="projectId"]').val();
    projectMode = projectId > 0;
  }
  const template = projectMode ? 'add-musicians' : 'all-musicians';
  const inputTweak = {
    template,
    templateRenderer: Page.templateRenderer(template),
  };
  if (projectMode) {
    inputTweak[pmeSys('fl')] = 1;
  }

  loadPMETableFiltered(form, inputTweak, ids, afterLoadCallback);
};

/**
 * Load the table of all musicians in the "add musician to project"
 * perspective. The underlying Musicians PHP class will take care of
 * constructing a suitable filter restricting the initial view to
 * all musicians @b not yet registered for the project.
 *
 * @param {jQuery} form The current PME form.
 *
 * @param {Function} afterLoadCallback An optional callback executed after
 * the PME table has been loaded.
 */
const myLoadAddMusicians = function(form, afterLoadCallback) {
  myLoadMusicians(form, [], true, afterLoadCallback);
};

/**
 * Load the detailed instrumentation view.
 *
 * @param {jQuery} form The current PME form.
 *
 * @param {Array} musicians Optional. An array of musician ids. The
 * table view will be restricted to these ids by constructing a
 * suitable filter expression.
 *
 * @param {Function} afterLoadCallback An optional callback executed after
 * the PME table has been loaded.
 */
const myLoadProjectParticipants = function(form, musicians, afterLoadCallback) {
  // const projectName = form.find('input[name="projectName"]').val();
  // const projectId = form.find('input[name="projectId"]').val();

  const template = 'project-participants';
  const inputTweak = {
    template,
    templateRenderer: Page.templateRenderer(template),
  };

  let ids = [-1];
  if (typeof musicians !== 'undefined') {
    ids = musicians.map(function(musician) { return { /* '0': projectId, */ 1: musician }; });
  }

  loadPMETableFiltered(form, inputTweak, ids, afterLoadCallback);
};

const myReady = function(selector, dialogParameters, resizeCB) {
  selector = PHPMyEdit.selector(selector);
  const container = PHPMyEdit.container(selector);

  rejectDecryptionPromise(); // terminate previous calls
  console.time('DECRYPTION PROMISE');
  decryptionPromise.done((maxJobs) => {
    console.timeEnd('DECRYPTION PROMISE');
    console.info('MAX DECRYPTION JOBS HANDLED', maxJobs);
  });
  lazyDecrypt(container);

  Musicians.contactValidation(container);

  // Enable the controls, in order not to bloat SQL queries these PME
  // fields are flagged virtual which disables all controls initially.
  const selectMusicianInstruments = container.find('.pme-value select.musician-instruments');
  const selectProjectInstruments = container.find('.pme-value select.pme-input.project-instruments');
  const selectGroupOfPeople = container.find('.pme-value select.pme-input.groupofpeople');
  const inputGroupOfPeopleId = container.find('input.pme-input.groupofpeople-id');
  const selectVoices = container.find('.pme-value select.pme-input.instrument-voice');
  const inputVoices = container.find('.pme-value div.instrument-voice.request.container');
  const form = container.find(PHPMyEdit.classSelector('form', 'form'));

  const musicianId = pmeRecordValue(form, 'musicianId');
  const projectId = pmeRecordValue(form, 'projectId');

  const selectedVoices = SelectUtils.selected(selectVoices);
  selectVoices.data(selectedOptionsKey, selectedVoices);

  container.find('.pme-value li.nav.instrumentation-voices a.nav')
    .off('click')
    .on('click', function(event) {
      const data = $(this).data('json');
      instrumentationNumbersPopup(selector, data);
      return false;
    });

  // This overly complicated piece of code turns a multi-select into
  // a per-group single select for the unlikely case that a musician
  // has multiple instruments for the project.
  selectVoices.off('change').on('change', function(event) {
    const $self = $(this);

    PHPMyEdit.tableDialogLock(container, true);
    PHPMyEdit.tableDialogLoadIndicator(container, true);

    const lockOther = function(lock) {
      SelectUtils.locked(selectMusicianInstruments, lock);
      SelectUtils.locked(selectProjectInstruments, lock);
    };
    lockOther(true);

    let selected = SelectUtils.selected($self);
    if (!selected) {
      selected = [];
    }

    const prevSelected = $self.data(selectedOptionsKey);
    const instruments = SelectUtils.selected(selectProjectInstruments);

    const prevVoices = {};
    const voices = {};
    for (const instrument of instruments) {
      voices[instrument] = [];
      prevVoices[instrument] = [];
    }

    for (const voiceItem of selected) {
      const item = voiceItem.split(':');
      voices[item[0]].push(item[1]);
    }

    for (const voiceItem of prevSelected) {
      const item = voiceItem.split(':');
      prevVoices[item[0]].push(item[1]);
    }

    let doSubmitOuterForm = true;

    // Now loop over old values. Unset multiple selections.
    for (const instrument in voices) {
      const values = voices[instrument];
      const prevValues = prevVoices[instrument];
      const inputIndex = values.findIndex((v) => v === '?');
      if (inputIndex > -1) {
        const voice = '?';
        const selectCombo = selectVoices.parent();
        const inputCombo = inputVoices.filter('div.instrument-' + instrument);
        values.splice(inputIndex, 1);
        doSubmitOuterForm = false;
        SelectUtils.locked(selectVoices, true);
        selectCombo.hide();
        inputCombo.show();
        const voiceItem = instrument + ':' + voice;
        const index = selected.findIndex((v) => voiceItem === v);
        if (index > -1) {
          selected.splice(index, 1);
        }
      }
      if (values.length < 2) {
        continue;
      }
      for (const prevValue of prevValues) {
        const voiceItem = instrument + ':' + prevValue;
        const index = selected.findIndex((v) => voiceItem === v);
        if (index > -1) {
          selected.splice(index, 1);
        }
      }
    }
    SelectUtils.selected($self, selected);
    $self.data(selectedOptionsKey, selected);

    lockOther(false);

    if (doSubmitOuterForm) {
      // selected project instruments affect voices and section-leader:
      PHPMyEdit.submitOuterForm(selector);
    } else {
      PHPMyEdit.tableDialogLoadIndicator(container, false);
      PHPMyEdit.tableDialogLock(container, false);
    }

    return false;
  });

  const inputVoicesHandler = function(event, input) {
    const $this = $(input);

    PHPMyEdit.tableDialogLock(container, true);
    PHPMyEdit.tableDialogLoadIndicator(container, true);

    const lockOther = function(lock) {
      SelectUtils.locked(selectMusicianInstruments, lock);
      SelectUtils.locked(selectProjectInstruments, lock);
      SelectUtils.locked(selectVoices, lock);
    };
    lockOther(true);

    let doSubmitOuterForm = true;

    if ($this.val() === '') {
      doSubmitOuterForm = false;
    } else {
      const dataHolder = $this.closest('.container').find('input.data');
      const instrument = dataHolder.data('instrument');
      const voice = parseInt($this.val());

      dataHolder.val(instrument + ':' + voice);
      dataHolder.prop('disabled', false);

      // remove any other voice for the same instrument:
      const selectedVoices = SelectUtils.selected(selectVoices);
      const instrumentIndex = selectedVoices.findIndex((v) => ('' + instrument === '' + v.split(':')[0]));
      if (instrumentIndex >= 0) {
        selectedVoices.splice(instrumentIndex, 1);
        SelectUtils.selected(selectVoices, selectedVoices);
      }
    }

    if (doSubmitOuterForm) {
      // selected project instruments affect voices and section-leader:
      PHPMyEdit.submitOuterForm(selector);
    } else {
      PHPMyEdit.tableDialogLoadIndicator(container, false);
      PHPMyEdit.tableDialogLock(container, false);
      selectVoices.parent().show();
      $this.closest('.container').hide();
    }

    lockOther(false);
    SelectUtils.refreshWidget(selectVoices);

    return false;
  };

  inputVoices.on('blur', 'input.instrument-voice.input', function(event) {
    return inputVoicesHandler(event, this);
  });

  inputVoices.on('click', 'input.instrument-voice.confirm', function(event) {
    const instrument = $(this).data('instrument');
    return inputVoicesHandler(event, inputVoices.find('input.input.instrument-' + instrument));
  });

  selectProjectInstruments.data(
    selectedOptionsKey,
    SelectUtils.selected(selectProjectInstruments)
  );
  console.info('SELECTED PROJECT INSTRUMENTS', selectProjectInstruments.data(selectedOptionsKey));

  selectProjectInstruments.on('change', function(event) {
    const $self = $(this);

    console.info('SELECTED PROJECT INSTRUMENTS', $self.data(selectedOptionsKey));

    PHPMyEdit.tableDialogLock(container, true);
    PHPMyEdit.tableDialogLoadIndicator(container, true);

    const lockOther = function(lock) {
      SelectUtils.locked(selectMusicianInstruments, lock);
      SelectUtils.locked(selectVoices, lock);
    };
    lockOther(true);

    console.info('SELECTED INSTRUMENTS', $self.data(selectedOptionsKey));
    validateInstrumentChoices({
      container,
      selectElement: selectProjectInstruments,
      validationUrl: generateUrl('projects/participants/change-instruments/project'),
      done() {
        console.info('SELECTED PROJECT INSTRUMENTS', $self.data(selectedOptionsKey));
        // Reenable, otherwise the value will not be submitted
        lockOther(false);

        // save current instruments
        $self.data(selectedOptionsKey, SelectUtils.selected($self));

        // selected project instruments affect voices and section-leader:
        PHPMyEdit.submitOuterForm(selector);
      },
      fail(data) {
        console.info('SELECTED INSTRUMENTS', $self.data(selectedOptionsKey));
        const oldInstruments = data.oldInstruments || $self.data(selectedOptionsKey);

        // failure case
        SelectUtils.selected($self, oldInstruments);

        // Reenable, otherwise the value will not be submitted
        lockOther(false);

        PHPMyEdit.tableDialogLoadIndicator(container, false);
        PHPMyEdit.tableDialogLock(container, false);
      },
    });

    return false;
  });

  selectMusicianInstruments.data(
    selectedOptionsKey,
    SelectUtils.selected(selectMusicianInstruments)
  );
  console.info('SELECTED MUSICIAN INSTRUMENTS', selectMusicianInstruments.data(selectedOptionsKey));

  selectMusicianInstruments.on('change', function(event) {
    const $self = $(this);

    console.info('SELECTED MUSICIAN INSTRUMENTS', $self.data(selectedOptionsKey));

    PHPMyEdit.tableDialogLock(container, true);
    PHPMyEdit.tableDialogLoadIndicator(container, true);

    const lockOther = function(lock) {
      SelectUtils.locked(selectProjectInstruments, lock);
      SelectUtils.locked(selectVoices, lock);
    };
    lockOther(true);

    console.info('SELECTED MUSICIAN INSTRUMENTS', $self.data(selectedOptionsKey));
    validateInstrumentChoices({
      container,
      selectElement: selectMusicianInstruments,
      validationUrl: generateUrl('projects/participants/change-instruments/musician'),
      done() {
        // Reenable, otherwise the value will not be submitted
        lockOther(false);

        console.info('IN DONE HOOK', $self.data(selectedOptionsKey));
        // save current instruments
        $self.data(selectedOptionsKey, SelectUtils.selected($self));
        console.info('IN DONE HOOK', $self.data(selectedOptionsKey));

        // submit the form with the "right" button,
        // i.e. save any possible changes already
        // entered by the user. The form-submit
        // will then also reload with an up to date
        // list of instruments
        PHPMyEdit.submitOuterForm(selector);
      },
      fail(data) {
        // failure case

        const oldInstruments = data.oldInstruments || $self.data(selectedOptionsKey);

        console.info('SELECTED MUSICIAN INSTRUMENTS', $self.data(selectedOptionsKey));

        SelectUtils.selected($self, oldInstruments);

        // Reenable, otherwise the value will not be submitted
        lockOther(false);

        PHPMyEdit.tableDialogLoadIndicator(container, false);
        PHPMyEdit.tableDialogLock(container, false);
      },
    });

    return false;
  });

  // enable or disable ungrouped items
  const maskUngrouped = function($select, disable) {
    SelectUtils.options($select).each(function(index) {
      const option = $(this);
      const data = option.data('data');
      // console.log('option', option);
      if (data.groupId === -1) {
        option.prop('disabled', disable);
      }
    });
  };

  // select all options belonging to the same group
  const selectGroup = function(select, group, doSelect) {
    if (typeof doSelect === 'undefined') {
      doSelect = true;
    }
    select.find('option').each(function(index) {
      const option = $(this);
      const data = option.data('data');
      if (data.groupId === group) {
        option.prop('selected', doSelect);
      }
    });
  };

  // foreach group remember the current selection of people and the
  // group
  selectGroupOfPeople.each(function(idx) {
    const $self = $(this);
    const curSelected = SelectUtils.selected($self);
    $self.data(selectedOptionsKey, curSelected);
    const name = $self.attr('name');
    const nameParts = name.split(/[@:]/);
    console.log('NAME PARTS', nameParts);
    const label = nameParts[0];
    const fieldId = nameParts[1];
    // const column = nameParts[2];
    const groupFieldName = label
          + pageRenderer.valuesTableSep + fieldId
          + pageRenderer.joinFieldNameSeparator + 'option_key';
    console.log('group id name', groupFieldName);
    $self.data('groupField', form.find('[name="' + groupFieldName + '"]'));
    $self.data('fieldId', fieldId);
    $self.data('groups', $self.closest('td').data('groups'));
    $self.data('groupField')
      .data('membersField', $self)
      .data('fieldId', fieldId);

    if ($self.hasClass('predefined') && curSelected.indexOf(String(musicianId)) < 0) {
      maskUngrouped($self, true);
      $self.trigger('chosen:updated');
    }
  });

  // @todo maybe not needed
  inputGroupOfPeopleId.off('change').on('change', function(event) {
    // const self = $(this);
  });

  selectGroupOfPeople.off('change').on('change', function(event) {
    const $self = $(this); // just the current one

    let curSelected = $self.val() || [];
    const prevSelected = $self.data(selectedOptionsKey);

    const added = curSelected.filter(x => prevSelected.indexOf(x) < 0);
    // const removed = prevSelected.filter(x => curSelected.indexOf(x) < 0);

    const musicianSelectedCur = curSelected.indexOf(String(musicianId)) >= 0;
    const musicianSelectedPrev = prevSelected.indexOf(String(musicianId)) >= 0;

    let changed = false;

    console.debug('added', added);
    console.debug('prevSelected', prevSelected, musicianId, musicianSelectedPrev);
    console.debug('curSelected', curSelected, musicianId, musicianSelectedCur);

    if (musicianSelectedPrev && !musicianSelectedCur) {
      // just removed the current key from the group, undefine group
      // and empty select-box
      SelectUtils.deselectAll($self);
      $self.data('groupField').val('');
      $self.nextAll('span.allowed-option').removeClass('selected');
      if ($self.hasClass('predefined')) {
        maskUngrouped($self, true);
      }
      changed = true;
    } else {
      if (!musicianSelectedPrev && !musicianSelectedCur && curSelected.length > 0) {
        // add current record
        console.debug('group add record', musicianId);
        SelectUtils.optionByValue($self, musicianId).prop('selected', true);
        changed = true;
      }
      if (added.length === 1) {
        const singleNewOption = SelectUtils.optionByValue($self, added[0]);
        console.debug('other people group option', singleNewOption);
        console.debug('key', musicianId);
        const data = singleNewOption.data('data');
        console.debug('option data', data);
        if (parseInt(data.groupId) !== -1) {
          console.log('group: ', data.groupId);
          selectGroup($self, data.groupId);
          $self.data('groupField').val(data.groupId);
          $self.nextAll('span.allowed-option').removeClass('selected');
          // @todo optimize
          $self.nextAll('span.allowed-option[data-key="' + data.groupId + '"]').addClass('selected');
          maskUngrouped($self, false);
          changed = true;
        }
      }
    }

    // deselect "add to group" options
    SelectUtils.selectedOptions($self).each(function(index) {
      const option = $(this);
      if (option.val() < 0) {
        option.prop('selected', false);
        changed = true;
      }
    });

    curSelected = SelectUtils.selected($self);
    $self.data(selectedOptionsKey, curSelected);

    console.debug('DATA', $self.data());

    const groupId = $self.data('groupField').val();
    const limit = groupId ? $self.data('groups')[groupId].limit : -1;
    if (limit > 0 && curSelected.length > limit) {
      Notification.showTemporary(
        t(appName,
          'Too many group members, allowed are {limit}, you specified {count}.'
          + 'You will not be able to save this configuration.',
          { limit, count: curSelected.length }),
        { isHTML: true, timeout: 30 }
      );
      console.log('exceeding limit');
      SelectUtils.selected($self, prevSelected);
    } else {
      Notification.hide();
    }

    if (changed) {
      SelectUtils.refreshWidget($self);
    }
    return false;
  });

  // mailing list subscritions
  form.find('.mailing-list.project .subscription-dropdown .subscription-action').on('click', function(event, triggerData) {
    const $this = $(this);
    const operation = $this.data('operation');
    if (!operation) {
      return;
    }
    triggerData = triggerData || { setup: false };

    let cleanup = () => $this.removeClass('busy').closest('.dropdown-container').removeClass('busy');
    let onFail = (xhr, status, errorThrown) => Ajax.handleError(xhr, status, errorThrown, cleanup);

    if (triggerData.setup) {
      // don't annoy the user with an error message on page load.
      cleanup = () => {};
      onFail = () => {};
    } else {
      $this.addClass('busy').closest('.dropdown-container').addClass('busy');
    }

    const post = function(force) {
      $.post(
        generateUrl('projects/participants/mailing-list/' + operation), {
          projectId,
          musicianId,
          force,
        })
        .fail(onFail)
        .done(function(data, textStatus, request) {
          if (data.status === 'unconfirmed') {
            Dialogs.confirm(
              data.feedback,
              t(appName, 'Confirmation Required'), {
                callback(answer) {
                  if (answer) {
                    post(true);
                  } else {
                    Notification.showTemporary(t(appName, 'Unconfirmed, doing nothing.'));
                  }
                },
                modal: true,
                default: 'cancel',
              });
          } else {
            if (!triggerData.setup) {
              Notification.messages(data.message);
            }
            if (data.status !== 'unchanged') {
              const $statusDisplay = $this.closest('.pme-value').find('.mailing-list.project.status.status-label');
              const $statusDropDown = $this.closest('.pme-value').find('.mailing-list.project.status.dropdown-container');
              const oldStatus = $statusDropDown.data('status');
              $statusDropDown.data('status', data.statusTags);
              for (const oldFlag of oldStatus) {
                $statusDisplay.removeClass(oldFlag);
                $statusDropDown.removeClass(oldFlag);
              }
              for (const newFlag of data.statusTags) {
                $statusDisplay.addClass(newFlag);
                $statusDropDown.addClass(newFlag);
              }
              $statusDisplay.html(t(appName, data.summary));
              cleanup();
            }
          }
        });
    };
    post(false);
    return false;
  });

  // Trigger reload on page load. The problem is that meanwhile some
  // data-base fixups run on events after the legacy PME code has
  // generated its HTML output. This also speeds up things if the
  // mailing-list service is down.
  form.find('.mailing-list.project .subscription-dropdown .subscription-action-reload').trigger('click', [{ setup: true }]);

  const pmeForm = container.find(pmeFormSelector);
  console.debug('PME FORM', pmeForm, pmeFormSelector);

  // adding musicians
  pmeForm
    .find(pmeClassSelector('input', 'add'))
    .addClass(pmeToken('custom')).prop('disabled', false)
    .off('click').on('click', function(event) {

      myLoadAddMusicians($(this.form));

      return false;
    });

  if (typeof resizeCB === 'function') {
    container.on('chosen:update', 'select', function(event) {
      resizeCB(true); // keep locks
      return false;
    });
  }

  participantFieldsHandlers(container, musicianId, projectId, dialogParameters);

  pmeForm
    .find('tr.participant-field.cloud-file, tr.participant-field.db-file, tr.participant-field.cloud-folder')
    .find('td.pme-value .file-upload-row')
    .each(function() { // don't () => ..., no this binding!!!
      initFileUploadRow.call(this, projectId, musicianId, resizeCB);
    });

  pmeForm
    .find('tr.participant-field tr.field-datum td.documents')
    .each(function() {
      initFileUploadRow.call(this, projectId, musicianId, resizeCB);
    });

  pmeForm
    .find(
      'tr.participant-field.simple-valued.receivables'
        + ', '
        + 'tr.participant-field.simple-valued.liabilities'
    )
    .find('.documents')
    .each(function() {
      initFileUploadRow.call(this, projectId, musicianId, resizeCB);
    });
};

const myDocumentReady = function() {

  PHPMyEdit.addTableLoadCallback('project-participants', {
    callback(selector, parameters, resizeCB) {

      if (parameters.reason === 'tabChange') {
        resizeCB();
        return;
      }

      if (parameters.reason === 'dialogClose') {
        resizeCB();
        return;
      }

      const container = $(selector);
      pmeExportMenu(selector);
      SepaDebitMandate.popupInit(selector);
      container.find('#sepa-bank-accounts-show-deleted').on('change', function(event) {
        const $sepaTable = container.find('td.pme-value.sepa-bank-accounts table');
        if ($(this).prop('checked')) {
          $sepaTable.addClass('show-deleted').removeClass('hide-deleted');
        } else {
          $sepaTable.removeClass('show-deleted').addClass('hide-deleted');
        }
        resizeCB();
        return false;
      });

      myReady(selector, parameters, resizeCB);

      $(':button.musician-instrument-insurance')
        .off('click')
        .on('click', function(event) {
          event.preventDefault();
          const values = $(this).attr('name');

          CAFEVDB.formSubmit(generateUrl(''), values, 'post');

          return false;
        });

      container.find('.cloud-avatar').imagesLoaded(resizeCB);
    },
    context: {},
    parameters: [],
  });

  CAFEVDB.addReadyCallback(function() {
    if ($('div#' + appPrefix('page-body') + '.project-participants').length > 0) {
      myReady();
    }
  });

};

export {
  myReady as ready,
  myDocumentReady as documentReady,
  myLoadProjectParticipants as loadProjectParticipants,
  myPersonalRecordDialog as personalRecordDialog,
  myLoadMusicians as loadMusicians,
};
