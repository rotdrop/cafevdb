/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  var Instrumentation = function() {};

  /**Open a dialog in order to edit the personal reccords of one
   * musician.
   *
   * @param record The record id. This is either the Id from the
   * Musiker table or the Id from the Besetzungen table, depending on
   * what else is passed in the second argument
   *
   * @param options Object. Additional option. In particular ProjectId
   * and ProjectName are honored, and the optiones IntialValue and
   * ReloadValue which should be one of 'View' or 'Change' (though
   * 'Delete' should also work).
   *
   */
  Instrumentation.personalRecordDialog = function(record, options) {

    if (typeof options == 'undefined') {
      options = {
        InitialValue: 'View',
        ReloadValue: 'View',
        ProjectId: -1
      };
    }
    if (typeof options.InitialValue == 'undefined') {
      options.InitialValue = 'View';
    }
    if (typeof options.ReloadValue == 'undefined') {
      options.ReloadValue = options.InitialValue;
    }
    if (typeof options.Project != 'undefined') {
      options.ProjectName = options.Project;
    } else if (typeof options.ProjectName != 'undefined') {
      options.Project = options.ProjectName;
    }

    var pme = PHPMYEDIT;
    var pmeOperation = pme.pmeSys('operation');
    var pmeRecord = pme.pmeSys('rec');

    var tableOptions = {
      ProjectId: -1,
      ProjectName: '',
      AmbientContainerSelector: pme.selector(),
      DialogHolderCSSId: 'personal-record-dialog',
      // Now special options for the dialog popup
      InitialViewOperation: options.InitialValue == 'View',
      InitialName: pmeOperation,
      InitialValue: 'View',
      ReloadName: pmeOperation,
      ReloadValue: 'View',
      ModalDialog: true,
      modified: false
    };

    tableOptions[pmeOperation] = options.ReloadValue + '?'+pmeRecord+'='+record;
    tableOptions[pmeRecord] = record;

    // Merge remaining options in.
    tableOptions = $.extend(tableOptions, options);

    if (tableOptions.Table == 'Musiker') {
      var projectMode = options.ProjectId > 0;
      tableOptions.Template =  projectMode ? 'add-musicians' : 'all-musicians';
      tableOptions.DisplayClass = 'Musicians';
      tableOptions["ClassArguments[0]"] = projectMode ? "1" : "0";
    } else if (options.ProjectId > 0) {
      tableOptions.Table = options.projectName+'View';
      tableOptions.Template = 'detailed-instrumenation'
      tableOptions.DisplayClass = 'DetailedInstrumentation';
    } else {
      tableOptions.Table = 'Musiker';
      tableOptions.Template = 'all-musicians';
      tableOptions.DisplayClass = 'Musicians';
    }

    //alert('options: '+CAFEVDB.print_r(tableOptions, true));

    PHPMYEDIT.tableDialogOpen(tableOptions);
  };

  /**Trigger server-side validation and fetch the result.
   *
   * @param container jQuery object for the curren active
   * form-container (i.e. the div the form is wrapped into)
   *
   * @param selectMusicianInstrument The select box with the list of
   * the musicians arguments.
   *
   * @param ajaxURL The URL to the script that actually validates the
   * data.
   *
   * @param finanlizeCB Callback called at the end, before submitting
   * the current form to the servre.
   *
   * @note Would perhaps be snappier to only submit the form to the
   * server if something changed. However, the validation is triggered
   * by a change event. So what.
   */
  Instrumentation.validateInstrumentChoices = function(container,
                                                       selectMusicianInstrument,
                                                       ajaxScript,
                                                       finalizeCB,
                                                       errorCB) {
    var projectId = container.find('input[name="ProjectId"]').val();
    var recordId = container.find('input[name="PME_sys_rec"]').val();

    OC.Notification.hide(function () {
      $.post(ajaxScript,
             {
               projectId: projectId,
               recordId: recordId,
               instrumentValues: selectMusicianInstrument.val()
             },
             function (data) {
               if (!CAFEVDB.ajaxErrorHandler(data, [ 'message' ], function() {
                      if (typeof errorCB == 'function' &&
                          typeof data.data != 'undefined' &&
                          typeof data.data.instruments != 'undefined') {
                        errorCB(data.data.instruments);
                      }
                    })) {
                 return false;
               }
               var rqData;
               var timeout = 10000;

               // Oops. Perhaps only submit on success.
               finalizeCB();

               rqData = data.data;
               if (rqData.notice != '') {
                 timeout = 15000;
               }
               var info = rqData.message + ' ' + rqData.notice;
               info = info.trim();
               if (info != '') {
                 OC.Notification.show(info);
                 setTimeout(function() {
                   OC.Notification.hide();
                 }, timeout);
               }

               return false;
             }, 'json');
    });
  };

  /**Pseudo-submit an underlying PME-form with tweaked form data.
   *
   * @param[in] form A form with additional input data which is
   * submitted as well. Submit buttons are omitted.
   *
   * @param formData Data for hidden input elements which replace the
   * form's "native" data. Example:
   *
   * formData = {
   *   Template: "detailed-instrumentation",
   *   Table: "Musiker",
   *   DisplayClass: "Musicians",
   *   'ClassArguments[0]': "1"
   * };
   *
   * The form is submitted with an empty pseudo-submit button.
   *
   * @param[in] afterLoadCallback Optional. A function called after the
   * table-view has been loaded.
   */
  Instrumentation.loadPMETable = function(form, formData, afterLoadCallback) {
    var pmeSys = PHPMYEDIT.pmeSys('');
    form.find('input').not('[name^="'+pmeSys+'"]').each(function(idx) {
      var self = $(this);
      var name = self.attr('name');
      if (name) {
        if (typeof formData[name] == 'undefined') {
          formData[name] = self.val();
        }
      }
    });
    CAFEVDB.Page.loadPage(formData, afterLoadCallback);
  };

  /**Pseudo-submit an underlying PME-form with tweaked form data, like
   * loadPMETable(), but restrict the display to the ids passed in the
   * flat array @a ids.
   *
   * @param formData Data for hidden input elements which replace the
   * form's "native" data. Example:
   *
   * formData = {
   *   Template: "detailed-instrumentation",
   *   Table: "Musiker",
   *   DisplayClass: "Musicians",
   *   'ClassArguments[0]': "1"
   * };
   *
   * The form is submitted with an empty pseudo-submit button.
   *
   * @param[in] ids An array containing the ids that will be
   * displayed. If ids is empty or contains an entry @c -1 then no filtering will
   * take place.
   *
   * @param[in] afterLoadCallback Optional. A function called after the
   * table-view has been loaded.
   */
  Instrumentation.loadPMETableFiltered = function(form, formData, ids, afterLoadCallback) {
    if (typeof ids === 'undefined' || !ids) {
      ids = [];
    }

    var pmeSys = PHPMYEDIT.pmeSys('');
    var filterData = {};
    for (var idx = 0; idx < ids.length; ++idx) {
      var name = pmeSys+'qf0_idx['+idx+']';
      var value = ids[idx];
      if (value == -1) {
        filterData = {};
        break;
      }
      filterData[name] = value;
    }
    $.extend(formData, filterData);

    Instrumentation.loadPMETable(form, formData, afterLoadCallback);
  };

  /**Load the table of all musicians, possibly in "project" mode and
   * possibly restricted to subset of the musicians by providing an
   * array with selected ids.
   *
   * @param form The current PME form.
   *
   * @param[in] ids An array containing the ids that will be
   * displayed. If ids is empty or contains an entry -1 then no filtering will
   * take place.
   *
   * @param[in] projectMode @c true, @c false, @c null or omitted.
   * If @c null or not present, then @a form will be searched for an input element with
   * name @c ProjectId, if present and its value is positive, the main musisians table is
   * loaded in project mode, allowing for adding new participants to the respective project.
   *
   * @param[in] afterLoadCallback Optional. A function called after the
   * table-view has been loaded.
   */
  Instrumentation.loadMusicians = function(form, ids, projectMode, afterLoadCallback) {
    if (typeof projectMode == 'undefined' || projectMode === null) {
      // Check whether form contains an input element for a
      // ProjectId. If its value is positive, switch to project mode,
      // otherwise assume all-musicians mode.
      var projectId = form.find('input[name="ProjectId"]').val();
      projectMode = projectId > 0;
    }
    var inputTweak = {
      Template: projectMode ? "add-musicians" : "all-musicians",
      Table: "Musiker",
      DisplayClass: "Musicians",
      "ClassArguments[0]": projectMode ? "1" : "0"
    };

    Instrumentation.loadPMETableFiltered(form, inputTweak, ids, afterLoadCallback);
  };

  /**Load the table of all musicians in the "add musician to project"
   * perspective. The underlying Musicians PHP class will take care of
   * constructing a suitable filter restricting the initial view to
   * all musicians @b not yet registered for the project.
   *
   * @param[in] form The current PME form.
   *
   * @param[in] afterLoadCallback An optional callback executed after
   * the PME table has been loaded.
   */
  Instrumentation.loadAddMusicians = function(form, afterLoadCallback) {
    Instrumentation.loadMusicians(form, [], true, afterLoadCallback);
  };

  /**Load the detailed instrumentation view.
   *
   * @param[in] form The current PME form.
   *
   * @param[in] musicians Optional. An array of musician ids. The
   * table view will be restricted to these ids by constructing a
   * suitable filter expression.
   *
   * @param[in] afterLoadCallback An optional callback executed after
   * the PME table has been loaded.
   */
  Instrumentation.loadDetailedInstrumentation = function(form, musicians, afterLoadCallback) {
    var projectName = form.find('input[name="ProjectName"]').val();
    var table = projectName+'View';

    var inputTweak = {
      Template: "detailed-instrumentation",
      Table: table,
      DisplayClass: "DetailedInstrumentation"
    };

    var ids = [ -1 ];
    if (typeof musicians != 'undefined') {
      ids = musicians.map(function(musician) { return musician.instrumentationId; });
    }

    Instrumentation.loadPMETableFiltered(form, inputTweak, ids, afterLoadCallback);
  };

  Instrumentation.ready = function(selector, resizeCB) {
    selector = PHPMYEDIT.selector(selector);
    var container = PHPMYEDIT.container(selector);

    var self = this;

    CAFEVDB.Musicians.contactValidation(container);

    // Enable the controls, in order not to bloat SQL queries these PME
    // fields are flagged virtual which disables all controls initially.
    var selectMusicianInstruments = container.find('.pme-value select.musician-instruments');
    var selectProjectInstrument = container.find('.pme-value select.pme-input.project-instrument');
    var selectGroupOfPeople = container.find('.pme-value select.pme-input.groupofpeople');
    var selectVoices = container.find('.pme-value select.pme-input.instrument-voice');
    var form = container.find(PHPMYEDIT.pmeClassSelector('form', 'form'));

    var recKey = form.find(PHPMYEDIT.pmeSysNameSelector('input', 'rec'));
    recKey = recKey.length === 1 ? recKey.val() : -1;

    var selectedVoices = selectVoices.val();
    selectVoices.data('selected', selectVoices ? selectedVoices : []);

    // This overly complicated piece of code turns a multi-select into
    // a per-group single select for the unlikely case that a musician
    // has multiple instruments for the project.
    selectVoices.off('change').on('change', function(event) {
      var self = $(this);
      if (!self.prop('multiple')) {
        return true;
      }

      var selected = self.val();
      if (!selected) {
        selected = [];
      }
      var prevSelected = self.data('selected');
      var instruments = selectProjectInstrument.val();

      var prevVoices = {};
      var voices = {};
      for(i = 0; i < instruments.length; ++i) {
        voices[instruments[i]] = [];
        prevVoices[instruments[i]] = [];
      }

      var i;
      for(i = 0; i < selected.length; ++i) {
        var item = selected[i].split(':');
        voices[item[0]].push(item[1]);
      }

      for(i = 0; i < prevSelected.length; ++i) {
        var item = prevSelected[i].split(':');
        prevVoices[item[0]].push(item[1]);
      }

      // Now loop over old values. Unset multiple selections.
      var changed = false;
      var instrument;
      for(instrument in voices) {
        var values     = voices[instrument];
        var prevValues = prevVoices[instrument];
        if (values.length < 2) {
          continue;
        }
        for (i = 0; i < prevValues.length; ++i) {
          console.log('option: '+'option[value="'+instrument+':'+i+'"]');
          self.find('option[value="'+instrument+':'+prevValues[i]+'"]').prop('selected', false);
          changed = true;
        }
      }

      self.data('selected', self.val());

      if (changed) {
        self.trigger('chosen:updated');
      }

      return false;
    });

    selectProjectInstrument.on('change', function(event) {
      event.preventDefault();

      selectMusicianInstruments.prop('disabled', true);
      selectMusicianInstruments.trigger('chosen:updated');

      self.validateInstrumentChoices(
        container, selectProjectInstrument,
        OC.filePath('cafevdb', 'ajax/instrumentation', 'change-project-instrument.php'),
        function () {

          // Reenable, otherwise the value will not be submitted
          selectMusicianInstruments.prop('disabled', false);
          selectMusicianInstruments.trigger('chosen:updated');

          // Mmmh.
          PHPMYEDIT.submitOuterForm(selector);
        });

      return false;
    });


    selectMusicianInstruments.on('change', function(event) {
      event.preventDefault();

      selectProjectInstrument.prop('disabled', true);
      selectProjectInstrument.trigger('chosen:updated');

      self.validateInstrumentChoices(
        container, selectMusicianInstruments,
        OC.filePath('cafevdb', 'ajax/instrumentation', 'change-musician-instruments.php'),
        function () {

          // Reenable, otherwise the value will not be submitted
          selectProjectInstrument.prop('disabled', false);
          selectProjectInstrument.trigger('chosen:updated');

          // submit the form with the "right" button,
          // i.e. save any possible changes already
          // entered by the user. The form-submit
          // will then also reload with an up to date
          // list of instruments
          PHPMYEDIT.submitOuterForm(selector);
        },
        function(oldInstruments) {
          var i;
          var selected = {};
          for (i = 0; i < oldInstruments.length; ++i) {
            selected[oldInstruments[i]] = true;
          }
          selectMusicianInstruments.find('option').each(function(idx) {
            var self = $(this);
            if (typeof selected[self.val()] != 'undefined') {
              self.prop('selected', true);
            } else {
              self.prop('selected', false);
            }
          });
          // Reenable, otherwise the value will not be submitted
          selectProjectInstrument.prop('disabled', false);
          selectProjectInstrument.trigger('chosen:updated');
          selectMusicianInstruments.trigger('chosen:updated');
        });

      return false;
    });

    // enable or disable ungrouped items
    var maskUngrouped = function(select, disable) {
      select.find('option').each(function(index) {
        var option = $(this);
        var data = option.data('data');
//console.log('option', option);
        if (data.GroupId == -1) {
          option.prop('disabled', disable);
        }
      });
    };

    var selectGroup = function(select, group, doSelect) {
      if (typeof doSelect === 'undefined') {
        doSelect = true;
      }
      select.find('option').each(function(index) {
        var option = $(this);
        var data = option.data('data');
        if (data.GroupId === group) {
          option.prop('selected', doSelect);
        }
      });
    };

    // foreach group remember the current selection of people and the
    // group
    selectGroupOfPeople.each(function(idx) {
      var self = $(this);
      var curSelected = self.val();
      self.data('selected', curSelected ? curSelected : []);
      var name = self.attr('name');
      console.log('group df name', name);
      var groupFieldName = name.slice(0, -2)+'Id';
      console.log('group id name', name);
      self.data('groupField', form.find('[name="'+groupFieldName+'"]'));
      console.log('#groupIdFields', self.data('groupField').length);
      //console.log('groupField', self.data('groupField'));

      if (self.hasClass('predefined') && recKey > 0 &&
          (!curSelected || curSelected.indexOf(recKey) < 0)) {
        maskUngrouped(self, true);
        self.trigger('chosen:updated');
      }
    });

    selectGroupOfPeople.off('change').on('change', function(event) {
      var self = $(this); // just the current one

      var selected = self.val();
      if (!selected) {
        selected = [];
      }
      var prevSelected = self.data('selected');
      var recPrev = prevSelected.indexOf(recKey) >= 0;
      var recCur  = selected.indexOf(recKey) >= 0;

      var changed = false;

      console.log('prevSelected', prevSelected);
      console.log('selected', selected);

      var groupOption = null;
      self.find('option:selected').each(function(index) {
        var option = $(this);
        if (option.val() < 0) {
          groupOption = option;
          return false;
        }
        return true;
      });

      if (recPrev && !recCur) {
        // just removed the current key from the group, undefine group
        // and empty select-box
        self.find('option:selected').prop('selected', false);
        CAFEVDB.selectValues(self.data('groupField'), false);
        maskUngrouped(self, true);
        changed = true;
      } else {
        if (!recPrev && !recCur && selected.length > 0) {
          // add current record
          console.log('add record', recKey);
          self.find('option[value="'+recKey+'"]').prop('selected', true);
          selected.push(recKey);
          changed = true;
        }
        if (selected.length >= prevSelected.length &&
            (selected.length == 1+(recCur === recPrev) || groupOption)) {
          // single new item which is not the current one, potentially
          // add the entire group.
          console.log('single new item');
          var singleNewOption = null;
          self.find('option:selected').each(function(idx) {
            var self = $(this);
            if (self.val() != recKey) {
              singleNewOption = self;
              return false;
            }
            return true;
          });
console.log('other people group option', singleNewOption.length);
console.log('key', recKey);
          var data = singleNewOption.data('data');
          if (data.GroupId != -1) {
            console.log('group: ', data.GroupId);
            selectGroup(self, data.GroupId);
            CAFEVDB.selectValues(self.data('groupField'), data.GroupId);
            maskUngrouped(self, false);
            changed = true;
          }
        }
      }

      self.find('option:selected').each(function(index) {
        var option = $(this);
        if (option.val() < 0) {
console.log('deselect', option.val());
          option.prop('selected', false);
          changed = true;
        }
      });

      var curSelected = self.val();
      curSelected = curSelected ? curSelected : [];
      self.data('selected', curSelected);

      var limit = -1;
      var groupData =  self.closest('td').data('groups');
      if (typeof groupData.Limit !== 'undefined') {
        // emit a warning if the limit is exhausted.
        limit = self.closest('td').data('groups');
        limit = limit.Limit;
      } else {
        var groupId = CAFEVDB.selectValues(self.data('groupField'));
        console.log('Id', groupId);
        for (var i = 0; i < groupData.length; ++i) {
          console.log('data', groupData[i]);
          if (groupData[i].key === groupId) {
            console.log('limit', limit);
            limit = groupData[i].limit;
          }
        }
      }

      console.log('group limit', limit);

      if (limit > 0 && curSelected.length > limit) {
        OC.Notification.showTemporary(t('cafevdb',
                                        'Too many group members, allowed are {limit}, you specified {count}.'
                                                  + 'You will not be able to save this configuration.',
                                        { limit: limit, count: curSelected.length }),
                                      { isHTML: true, timeout: 30 }
                                     );
        console.log('exceeding limit');
        CAFEVDB.selectValues(self, prevSelected);
      } else {
        OC.Notification.hide();
      }

      if (changed) {
        self.trigger('chosen:updated');
      }
      return false;
    });

    container.find('form.pme-form input.pme-add').
      addClass('pme-custom').prop('disabled', false).
      off('click').on('click', function(event) {

      Instrumentation.loadAddMusicians($(this.form));

      return false;
    });

    if (typeof resizeCB === 'function') {
      container.on('chosen:update', 'select', function(event) {
        resizeCB();
        return false;
      });
    }
  };

  CAFEVDB.Instrumentation = Instrumentation;

})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  PHPMYEDIT.addTableLoadCallback('DetailedInstrumentation', {
    callback: function(selector, parameters, resizeCB) {

      if (parameters.reason == 'tabChange') {
        resizeCB();
        return;
      }

      var container = $(selector);
      CAFEVDB.exportMenu(selector);
      CAFEVDB.SepaDebitMandate.popupInit(selector);

      this.ready(selector, resizeCB);

      container.find('div.photo, #cafevdb_inline_image_wrapper').
        off('click', 'img.zoomable').
        on('click', 'img.zoomable', function(event) {
        event.preventDefault();
        CAFEVDB.Photo.popup(this);
        return false;
      });

      $(':button.musician-instrument-insurance').
        off('click').
        on('click', function(event) {
        event.preventDefault();
        var values = $(this).attr('name');

        CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), values, 'post');

        return false;
      });

      if (container.find('#contact_photo_upload').length > 0) {
        var idField = container.find('input[name="PME_data_MusikerId"]');
        var recordId = -1;
        if (idField.length > 0) {
          recordId = idField.val();
        }
        CAFEVDB.Photo.ready(recordId, 'Musiker', resizeCB);
      } else {
        container.find('div.photo, span.photo').imagesLoaded(resizeCB);
      }
    },
    context: CAFEVDB.Instrumentation,
    parameters: []
  });

  CAFEVDB.addReadyCallback(function() {
    if ($('div#cafevdb-page-body.detailed-instrumentation').length > 0) {
      CAFEVDB.Instrumentation.ready();
    }
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// js3-indent-level: 2 ***
// js3-label-indent-offset: -2 ***
// End: ***
