/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Page from './page.js';
import * as Musicians from './musicians.js';
import * as Notification from './notification.js';
import * as SepaDebitMandate from './sepa-debit-mandate.js';
import * as Photo from './inlineimage.js';
import * as FileUpload from './file-upload.js';
import { data as pmeData } from './pme-selectors.js';
import * as PHPMyEdit from './pme.js';
import * as SelectUtils from './select-utils.js';
import generateUrl from './generate-url.js';
import pmeExportMenu from './pme-export.js';
import selectValues from './select-values.js';

require('../legacy/nextcloud/jquery/octemplate.js');
require('project-participants.css');

/**
 * Open a dialog in order to edit the personal reccords of one
 * musician.
 *
 * @param {int} record The record id. This is either the Id from the
 * Musiker table or the Id from the Besetzungen table, depending on
 * what else is passed in the second argument
 *
 * @param {Object} options Additional option. In particular ProjectId
 * and ProjectName are honored, and the optiones IntialValue and
 * ReloadValue which should be one of 'View' or 'Change' (though
 * 'Delete' should also work).
 */
const myPersonalRecordDialog = function(record, options) {
  if (typeof options === 'undefined') {
    options = {
      InitialValue: 'View',
      ReloadValue: 'View',
      projectId: -1,
    };
  }
  if (typeof options.InitialValue === 'undefined') {
    options.InitialValue = 'View';
  }
  if (typeof options.ReloadValue === 'undefined') {
    options.ReloadValue = options.InitialValue;
  }
  if (typeof options.Project !== 'undefined') {
    options.projectName = options.project;
  } else if (typeof options.projectName !== 'undefined') {
    options.project = options.projectName;
  }

  const pmeOperation = PHPMyEdit.sys('operation');
  const pmeRecord = PHPMyEdit.sys('rec');

  let tableOptions = {
    projectId: -1,
    projectName: '',
    ambientContainerSelector: PHPMyEdit.selector(),
    DialogHolderCSSId: 'personal-record-dialog',
    // Now special options for the dialog popup
    InitialViewOperation: options.InitialValue === 'View',
    InitialName: pmeOperation,
    InitialValue: 'View',
    ReloadName: pmeOperation,
    ReloadValue: 'View',
    ModalDialog: true,
    modified: false,
  };

  tableOptions[pmeOperation] = options.ReloadValue + '?' + pmeRecord + '=' + record;
  tableOptions[pmeRecord] = record;

  // Merge remaining options in.
  tableOptions = $.extend(tableOptions, options);

  if (tableOptions.table === 'Musicians') {
    const projectMode = options.projectId > 0;
    tableOptions.template = projectMode ? 'add-musicians' : 'all-musicians';
    tableOptions.templateRenderer = Page.templateRenderer(tableOptions.template);
  } else if (options.projectId > 0) {
    tableOptions[pmeOperation] =
      options.ReloadValue + '?' + pmeRecord + '[project_id]=' + record.projectId + '&' + pmeRecord + '[musician_id]=' + record.musicianId;
    tableOptions.table = 'ProjectParticipants';
    tableOptions.template = 'project-participants';
    tableOptions.templateRenderer = Page.templateRenderer(tableOptions.template);
  } else {
    tableOptions.table = 'Musicians';
    tableOptions.template = 'all-musicians';
    tableOptions.templateRenderer = Page.templateRenderer(tableOptions.template);
  }

  // alert('options: ' + CAFEVDB.print_r(tableOptions, true));

  PHPMyEdit.tableDialogOpen(tableOptions);
};

/**
 * Trigger server-side validation and fetch the result.
 *
 * @param {jQuery} container object for the curren active
 * form-container (i.e. the div the form is wrapped into)
 *
 * @param {jQuery} selectMusicianInstrument The select box with the list of
 * the musicians arguments.
 *
 * @param {String} ajaxScript The URL to the script that actually validates the
 * data.
 *
 * @param {Function} finalizeCB Callback called at the end, before submitting
 * the current form to the servre.
 *
 * @param {Function} errorCB TBD.
 *
 * Would perhaps be snappier to only submit the form to the
 * server if something changed. However, the validation is triggered
 * by a change event. So what.
 */
const validateInstrumentChoices = function(
  container,
  selectMusicianInstrument,
  ajaxScript,
  finalizeCB,
  errorCB) {

  Notification.hide(function() {
    $.post(ajaxScript, {
      recordId: PHPMyEdit.rec(container),
      instrumentValues: selectMusicianInstrument.val(),
    })
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown, errorCB);
      })
      .done(function(data) {
        if (!Ajax.validateResponse(data, ['message'], errorCB)) {
          return;
        }
        let timeout = 10;

        // Oops. Perhaps only submit on success.
        finalizeCB();

        if (data.notice !== '') {
          timeout = 15;
        }
        const info = (data.message + (data.notice ? ' ' + data.notice : '')).trim();
        if (info !== '') {
          Notification.show(info, { timeout });
        }
      });
  });
};

/**
 * Pseudo-submit an underlying PME-form with tweaked form data.
 *
 * @param {jQuery} form A form with additional input data which is
 * submitted as well. Submit buttons are omitted.
 *
 * @param {Object} formData Data for hidden input elements which replace the
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
    const self = $(this);
    const name = self.attr('name');
    if (name) {
      if (typeof formData[name] === 'undefined') {
        formData[name] = self.val();
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
 * @param {Object} formData Data for hidden input elements which replace the
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
 * @param {bool} projectMode @c true, @c false, @c null or omitted.
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

const myReady = function(selector, resizeCB) {
  selector = PHPMyEdit.selector(selector);
  const container = PHPMyEdit.container(selector);

  Musicians.contactValidation(container);

  // Enable the controls, in order not to bloat SQL queries these PME
  // fields are flagged virtual which disables all controls initially.
  const selectMusicianInstruments = container.find('.pme-value select.musician-instruments');
  const selectProjectInstruments = container.find('.pme-value select.pme-input.project-instruments');
  const selectGroupOfPeople = container.find('.pme-value select.pme-input.groupofpeople');
  const inputGroupOfPeopleId = container.find('input.pme-input.groupofpeople-id');
  const selectVoices = container.find('.pme-value select.pme-input.instrument-voice');
  const form = container.find(PHPMyEdit.classSelector('form', 'form'));

  let musicianId = form.find(PHPMyEdit.sysNameSelector('input', 'rec[musician_id]'));
  musicianId = musicianId.length === 1 ? musicianId.val() : -1;

  let projectId = form.find(PHPMyEdit.sysNameSelector('input', 'rec[project_id]'));
  projectId = projectId.length === 1 ? projectId.val() : -1;

  const selectedVoices = selectVoices.val();
  selectVoices.data('selected', selectedVoices || []);

  // This overly complicated piece of code turns a multi-select into
  // a per-group single select for the unlikely case that a musician
  // has multiple instruments for the project.
  selectVoices.off('change').on('change', function(event) {
    const self = $(this);
    if (!self.prop('multiple')) {
      return true;
    }

    let selected = self.val();
    if (!selected) {
      selected = [];
    }
    const prevSelected = self.data('selected');
    const instruments = selectProjectInstruments.val();

    const prevVoices = {};
    const voices = {};
    for (let i = 0; i < instruments.length; ++i) {
      voices[instruments[i]] = [];
      prevVoices[instruments[i]] = [];
    }

    for (let i = 0; i < selected.length; ++i) {
      const item = selected[i].split(':');
      voices[item[0]].push(item[1]);
    }

    for (let i = 0; i < prevSelected.length; ++i) {
      const item = prevSelected[i].split(':');
      prevVoices[item[0]].push(item[1]);
    }

    // Now loop over old values. Unset multiple selections.
    let changed = false;
    for (const instrument in voices) {
      const values = voices[instrument];
      const prevValues = prevVoices[instrument];
      if (values.length < 2) {
        continue;
      }
      for (let i = 0; i < prevValues.length; ++i) {
        console.debug('option: ' + 'option[value="' + instrument + ':' + i + '"]');
        self.find('option[value="' + instrument + ':' + prevValues[i] + '"]').prop('selected', false);
        changed = true;
      }
    }

    self.data('selected', self.val() ? self.val() : []);

    if (changed) {
      self.trigger('chosen:updated');
    }

    return false;
  });

  selectProjectInstruments.data(
    'selected',
    selectProjectInstruments.val()
      ? selectProjectInstruments.val()
      : []);
  selectProjectInstruments.on('change', function(event) {
    const self = $(this);

    selectMusicianInstruments.prop('disabled', true);
    selectMusicianInstruments.trigger('chosen:updated');

    validateInstrumentChoices(
      container, selectProjectInstruments,
      generateUrl('projects/participants/change-instruments/project'),
      function() {
        // Reenable, otherwise the value will not be submitted
        selectMusicianInstruments.prop('disabled', false);
        selectMusicianInstruments.trigger('chosen:updated');

        // save current instruments
        self.data('selected', self.val() ? self.val() : []);

        // should we?
        PHPMyEdit.submitOuterForm(selector);
      },
      function(data) {
        const oldInstruments = data.oldInstruments || self.data('selected');
        // failure case
        const selected = {};
        for (let i = 0; i < oldInstruments.length; ++i) {
          selected[oldInstruments[i]] = true;
        }
        self.find('option').each(function(idx) {
          const self = $(this);
          if (typeof selected[self.val()] !== 'undefined') {
            self.prop('selected', true);
          } else {
            self.prop('selected', false);
          }
        });
        // Reenable, otherwise the value will not be submitted
        selectMusicianInstruments.prop('disabled', false);
        selectMusicianInstruments.trigger('chosen:updated');
      });

    return false;
  });

  selectMusicianInstruments.data(
    'selected',
    selectMusicianInstruments.val()
      ? selectMusicianInstruments.val()
      : []);
  selectMusicianInstruments.on('change', function(event) {
    const self = $(this);

    selectProjectInstruments.prop('disabled', true);
    selectProjectInstruments.trigger('chosen:updated');

    validateInstrumentChoices(
      container, selectMusicianInstruments,
      generateUrl('projects/participants/change-instruments/musician'),
      function() {
        // Reenable, otherwise the value will not be submitted
        selectProjectInstruments.prop('disabled', false);
        selectProjectInstruments.trigger('chosen:updated');

        // save current instruments
        self.data('selected', self.val() ? self.val() : []);

        // submit the form with the "right" button,
        // i.e. save any possible changes already
        // entered by the user. The form-submit
        // will then also reload with an up to date
        // list of instruments
        PHPMyEdit.submitOuterForm(selector);
      },
      function(data) {
        const oldInstruments = data.oldInstruments || self.data('selected');
        // failure case
        const selected = {};
        for (let i = 0; i < oldInstruments.length; ++i) {
          selected[oldInstruments[i]] = true;
        }
        self.find('option').each(function(idx) {
          const self = $(this);
          if (typeof selected[self.val()] !== 'undefined') {
            self.prop('selected', true);
          } else {
            self.prop('selected', false);
          }
        });
        // Reenable, otherwise the value will not be submitted
        selectProjectInstruments.prop('disabled', false);
        selectProjectInstruments.trigger('chosen:updated');
        selectMusicianInstruments.trigger('chosen:updated');
      });

    return false;
  });

  // enable or disable ungrouped items
  const maskUngrouped = function(select, disable) {
    select.find('option').each(function(index) {
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
    const self = $(this);
    const curSelected = self.val() || [];
    self.data('selected', curSelected);
    const name = self.attr('name');
    const nameParts = name.split(/[@:]/);
    console.log('NAME PARTS', nameParts);
    const label = nameParts[0];
    const fieldId = nameParts[1];
    // const column = nameParts[2];
    const groupFieldName = label + '@' + fieldId + ':' + 'option_key';
    console.log('group id name', groupFieldName);
    self.data('groupField', form.find('[name="' + groupFieldName + '"]'));
    self.data('fieldId', fieldId);
    self.data('groups', self.closest('td').data('groups'));
    self.data('groupField')
      .data('membersField', self)
      .data('fieldId', fieldId);

    if (self.hasClass('predefined') && curSelected.indexOf(musicianId) < 0) {
      maskUngrouped(self, true);
      self.trigger('chosen:updated');
    }
  });

  // @todo maybe not needed
  inputGroupOfPeopleId.off('change').on('change', function(event) {
    // const self = $(this);
  });

  selectGroupOfPeople.off('change').on('change', function(event) {
    const self = $(this); // just the current one

    let curSelected = self.val() || [];
    const prevSelected = self.data('selected');

    const added = curSelected.filter(x => prevSelected.indexOf(x) < 0);
    // const removed = prevSelected.filter(x => curSelected.indexOf(x) < 0);

    const musicianSelectedCur = curSelected.indexOf(musicianId) >= 0;
    const musicianSelectedPrev = prevSelected.indexOf(musicianId) >= 0;

    let changed = false;

    console.log('prevSelected', prevSelected);
    console.log('curSelected', curSelected);

    if (musicianSelectedPrev && !musicianSelectedCur) {
      // just removed the current key from the group, undefine group
      // and empty select-box
      self.find('option:selected').prop('selected', false);
      self.data('groupField').val('');
      self.nextAll('span.allowed-option').removeClass('selected');
      if (self.hasClass('predefined')) {
        maskUngrouped(self, true);
      }
      changed = true;
    } else {
      if (!musicianSelectedPrev && !musicianSelectedCur && curSelected.length > 0) {
        // add current record
        console.log('add record', musicianId);
        SelectUtils.optionByValue(self, musicianId).prop('selected', true);
        changed = true;
      }
      if (added.length === 1) {
        const singleNewOption = SelectUtils.optionByValue(self, added[0]);
        console.log('other people group option', singleNewOption);
        console.log('key', musicianId);
        const data = singleNewOption.data('data');
        console.log('option data', data);
        if (+data.groupId !== -1) {
          console.log('group: ', data.groupId);
          selectGroup(self, data.groupId);
          self.data('groupField').val(data.groupId);
          self.nextAll('span.allowed-option').removeClass('selected');
          // @todo optimize
          self.nextAll('span.allowed-option[data-key="' + data.groupId + '"]').addClass('selected');
          maskUngrouped(self, false);
          changed = true;
        }
      }
    }

    // deselect "add to group" options
    self.find('option:selected').each(function(index) {
      const option = $(this);
      if (option.val() < 0) {
        option.prop('selected', false);
        changed = true;
      }
    });

    curSelected = self.val() || [];
    self.data('selected', curSelected);

    const groupId = self.data('groupField').val();
    const limit = groupId ? self.data('groups')[groupId].limit : -1;
    if (limit > 0 && curSelected.length > limit) {
      Notification.showTemporary(
        t('cafevdb',
          'Too many group members, allowed are {limit}, you specified {count}.'
          + 'You will not be able to save this configuration.',
          { limit, count: curSelected.length }),
        { isHTML: true, timeout: 30 }
      );
      console.log('exceeding limit');
      selectValues(self, prevSelected);
    } else {
      Notification.hide();
    }

    if (changed) {
      self.trigger('chosen:updated');
    }
    return false;
  });

  container
    .find('form.pme-form input.pme-add')
    .addClass('pme-custom').prop('disabled', false)
    .off('click').on('click', function(event) {

      myLoadAddMusicians($(this.form));

      return false;
    });

  if (typeof resizeCB === 'function') {
    container.on('chosen:update', 'select', function(event) {
      resizeCB();
      return false;
    });
  }

  // Handle buttons to update or delete recurrent receivables
  container
    .find('form.pme-form tr.participant-field.recurring td.operations input.regenerate')
    .on('click', function(event) {
      const $this = $(this);
      const row = $this.closest('tr');
      const fieldId = row.data('fieldId');
      const optionKey = row.data('optionKey');
      const cleanup = function() {};
      const request = 'option/regenerate';
      $.post(
        generateUrl('projects/participant-fields/' + request), {
          data: {
            fieldId,
            key: optionKey,
            musicianId,
          },
        })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['amounts'], cleanup)) {
            return;
          }
          if (data.amounts[musicianId]) {
            row.find('input.pme-input.service-fee').val(data.amounts[musicianId]);
          }
          cleanup();
          Notification.messages(data.message);
        });
      return false;
    });

  container
    .find('form.pme-form tr.participant-field.recurring td.operations input.delete-undelete')
    .on('click', function(event) {
      const $this = $(this);
      const row = $this.closest('tr');
      // const fieldId = row.data('fieldId');
      const optionKey = row.data('optionKey');

      // could also search for name with field-id
      const inputs = container
        .find('input[value="' + optionKey + '"]')
        .add(row.find('.pme-input, .operation.regenerate'));

      if (row.hasClass('deleted')) {
        inputs.prop('disabled', false);
        row.removeClass('deleted');
      } else {
        inputs.prop('disabled', true);
        row.addClass('deleted');
      }

      return false;
    });

  container
    .find('form.pme-form tr.participant-field.recurring td.operations input.regenerate-all')
    .on('click', function(event) {
      const $this = $(this);
      const row = $this.closest('tr');
      const fieldId = row.data('fieldId');
      const cleanup = function() {};
      const request = 'option/regenerate';
      $.post(
        generateUrl('projects/participant-fields/' + request), {
          data: {
            fieldId,
            musicianId,
          },
        })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, [], cleanup)) {
            return;
          }
          // just trigger reload
          container.find('form.pme-form input.pme-reload').first().trigger('click');
          cleanup();
          Notification.messages(data.message);
        });
      return false;
    });

  const fileUploadTemplate = $('#fileUploadTemplate');
  container
    .find('form.pme-form tr.participant-field.file-data td.pme-value .file-upload-row')
    .each(function(index) {
      const $this = $(this);
      const fieldId = $this.data('fieldId');
      const optionKey = $this.data('optionKey');
      const uploadPolicy = $this.data('uploadPolicy');
      const fileBase = $this.data('fileBase');
      const subDir = $this.data('subDir');
      const widgetId = 'file-upload-' + optionKey;
      const uploadUi = fileUploadTemplate.octemplate({
        wrapperId: widgetId,
        formClass: 'file-upload-form',
        accept: '*',
        uploadName: 'files[' + optionKey + ']',
        projectId,
        musicianId,
        uploadData: JSON.stringify({
          fieldId,
          optionKey,
          uploadPolicy,
          subDir,
          fileBase,
        }),
      });
      const $oldUploadForm = $('#' + widgetId);
      if ($oldUploadForm.length === 0) {
        $('body').append(uploadUi);
      } else {
        $oldUploadForm.replaceWith(uploadUi);
        // uploadUi.replaceAll($oldUploadForm);
      }

      const $parentFolder = $this.find('.operation.open-parent');
      const $deleteUndelete = $this.find('.operation.delete-undelete');
      const $downloadLink = $this.find('a.download-link');

      FileUpload.init({
        url: generateUrl('projects/participants/files/upload'),
        doneCallback(file, index, container) {
          $downloadLink.attr('href', file.meta.download);
          $downloadLink.html(file.meta.baseName);
          $deleteUndelete.prop('disabled', $downloadLink.attr('href') === '');
          $parentFolder.prop('disabled', $downloadLink.attr('href') === '');
        },
        stopCallback: null,
        dropZone: $this,
        containerSelector: '#' + widgetId,
        inputSelector: 'input[type="file"]',
        multiple: false,
      });

      $deleteUndelete.prop('disabled', $downloadLink.attr('href') === '');
      $parentFolder.prop('disabled', $downloadLink.attr('href') === '');
      $this.find('input.upload-placeholder, input.upload-replace')
        .on('click', function(event) {
          const $fileUpload = $('#' + widgetId + ' input[type="file"]');
          $fileUpload.trigger('click');
          return false;
        });

      $deleteUndelete.on('click', function(event) {
        const cleanup = function() {
          Page.busyIcon(false);
          CAFEVDB.modalizer(false);
        };

        CAFEVDB.modalizer(true);
        Page.busyIcon(true);

        $.post(
          generateUrl('projects/participants/files/delete'), {
            musicianId,
            projectId,
            fieldId,
            optionKey,
          })
          .fail(function(xhr, status, errorThrown) {
            Ajax.handleError(xhr, status, errorThrown, cleanup);
          })
          .done(function(data) {
            if (!Ajax.validateResponse(data, ['message'], cleanup)) {
              return;
            }
            $downloadLink.attr('href', '');
            $downloadLink.html('');
            $deleteUndelete.prop('disabled', $downloadLink.attr('href') === '');
            Notification.messages(data.message);
            cleanup();
          });
      });
    });

};

const myDocumentReady = function() {

  PHPMyEdit.addTableLoadCallback('project-participants', {
    callback(selector, parameters, resizeCB) {

      if (parameters.reason === 'tabChange') {
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
      });

      myReady(selector, resizeCB);

      container.find('div.photo, .cafevdb_inline_image_wrapper')
        .off('click', 'img.zoomable')
        .on('click', 'img.zoomable', function(event) {
          event.preventDefault();
          Photo.popup(this);
          return false;
        });

      $(':button.musician-instrument-insurance')
        .off('click')
        .on('click', function(event) {
          event.preventDefault();
          const values = $(this).attr('name');

          CAFEVDB.formSubmit(generateUrl(''), values, 'post');

          return false;
        });

      if (container.find('#contact_photo_upload').length > 0) {
        const idField = container.find('input[name="' + pmeData('musician_id') + '"]');
        let recordId = -1;
        if (idField.length > 0) {
          recordId = idField.val();
        }
        const imageId = -1;
        Photo.ready(recordId, imageId, 'MusicianPhoto', resizeCB);
      } else {
        container.find('div.photo, span.photo').imagesLoaded(resizeCB);
      }
    },
    context: {},
    parameters: [],
  });

  CAFEVDB.addReadyCallback(function() {
    if ($('div#cafevdb-page-body.project-participants').length > 0) {
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

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
