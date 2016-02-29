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
  var ProjectExtra = function() {};

  ProjectExtra.ready = function(selector, resizeCB) {
    var container = $(selector);

    var tableTab = container.find('select.tab');
    var newTab = container.find('input.new-tab');
    newTab.prop('readonly', !!tableTab.find(':selected').val());
    container.on('change', 'select.tab', function(event) {
      newTab.prop('readonly', !!tableTab.find(':selected').val());
      return false;
    });

    var handleFieldType = function(data) {
      if (!data) {
        return;
      }
      //
      var simpleClass = 'simple-value-field';
      var singleClass = 'single-value-field';
      var multiClass = 'multi-value-field';
      var parallelClass = 'parallel-value-field';
      var surchargeClass = 'surcharge-field';
      var generalClass = 'general-field';
      var groupClass = 'group-field';
      var groupsClass = 'groups-field';
      //
      var row = container.find('tr.field-type');
      //
      row.removeClass(multiClass);
      row.removeClass(simpleClass);
      row.removeClass(singleClass);
      row.removeClass(parallelClass);
      row.removeClass(surchargeClass);
      row.removeClass(generalClass);
      row.removeClass(groupClass);
      row.removeClass(groupsClass);
      //
      switch(data.Multiplicity) {
      case 'groupsofpeople':
        row.addClass(groupsClass);
        break;
      case 'groupofpeople':
        row.addClass(groupClass);
        break;
      case 'parallel':
        row.addClass(parallelClass);
      case 'multiple':
        row.addClass(multiClass);
        break;
      case 'single':
        row.addClass(singleClass);
        break;
      case 'simple':
        row.addClass(simpleClass);
        break;
      }
      switch(data.Group) {
      case 'surcharge':
        row.addClass(surchargeClass);
        break;
      case 'general':
        row.addClass(generalClass);
        break;
      }
    };

    var fieldTypeData = function(elem) {
      var data = null;
      if (typeof elem === 'undefined') {
        elem = container.find('select.field-type');
        if (elem.length === 0) {
          elem = container.find('td.pme-value.field-type .data');
        } else {
          elem = elem.find(':selected');
        }
        if (elem.length <= 0) {
          return null;
        }
      }
      data = elem.data('data');
      return data;
    };

    handleFieldType(fieldTypeData());

    var allowedHeaderVisibility = function() {
      var allowedValuesTable = container.find('table.allowed-values');
      if (allowedValuesTable.find('tbody tr:visible').length >= 2) {
        allowedValuesTable.find('thead').show();
      } else {
        allowedValuesTable.find('thead').hide();
      }
    };

    allowedHeaderVisibility();

    // synthesize resize events for textareas.
    CAFEVDB.textareaResize(container, 'textarea.field-tooltip, textarea.extra-field-tooltip');

    // Field-Type Selector
    container.on('change', 'select.field-type', function(event) {
      handleFieldType(fieldTypeData($(this).find(':selected')));
      allowedHeaderVisibility();
      resizeCB();
      return false;
    });

    container.on('keypress', 'tr.allowed-values input[type="text"]', function(event) {
      var pressed_key;
      if (event.which) {
        pressed_key = event.which;
      } else {
        pressed_key = event.keyCode;
      }
      if (pressed_key == 13) { // enter pressed
        event.stopImmediatePropagation();
        $(this).blur();
        return false;
      }
      return true; // other key pressed
    });

    container.on('change', '#allowed-values-show-deleted', function(event) {
      if ($(this).prop('checked')) {
        container.find('table.allowed-values').addClass('show-deleted');
      } else {
        container.find('table.allowed-values').removeClass('show-deleted');
      }
      $.fn.cafevTooltip.remove();
      allowedHeaderVisibility();
      resizeCB();
    });

    container.on('change', '#allowed-values-show-data', function(event) {
      if ($(this).prop('checked')) {
        container.find('table.allowed-values').addClass('show-data');
      } else {
        container.find('table.allowed-values').removeClass('show-data');
      }
      $.fn.cafevTooltip.remove();
      resizeCB();
    });

    container.on('change', 'select.default-multi-value', function(event) {
      var self = $(this);
      container.find('input.pme-input.default-value').val(self.find(':selected').val());
      return false;
    });

    container.on('blur', 'input.pme-input.default-value', function(event) {
      var self = $(this);
      var dfltSelect = container.find('select.default-multi-value');
      dfltSelect.children('option[value="'+self.val()+'"]').prop('selected', true);
      dfltSelect.trigger('chosen:updated');
      return false;
    });

    container.on('click', 'tr.allowed-values input.delete-undelete', function(event) {
      var self = $(this);
      var row = self.closest('tr.allowed-values');
      var used = row.data('used');
      used = !(!used || used === 'unused');
      if (row.data('flags') === 'deleted') {
        // undelete
        row.data('flags', 'active');
        row.switchClass('deleted', 'active');
        row.find('input.field-flags').val('active');
        row.find('input[type="text"], textarea').prop('readonly', false);
        var key = row.find('input.field-key');
        var label = row.find('input.field-label');
        if (used) {
          key.prop('readonly', true);
        }
        var dfltSelect = container.find('select.default-multi-value');
        var option = '<option value="'+key.val()+'">'+label.val()+'</option>';
        dfltSelect.children('option').first().after(option);
        dfltSelect.trigger('chosen:updated');
      } else {
        var key = row.find('input.field-key').val();
        if (!used) {
          // just remove the row
          row.remove();
          $.fn.cafevTooltip.remove();
          allowedHeaderVisibility();
          resizeCB();
        } else {
          // must not delete, mark as inactive
          row.data('flags', 'deleted');
          row.switchClass('active', 'deleted');
          row.find('input.field-flags').val('deleted');
          row.find('input[type="text"], textarea').prop('readonly', true);
        }
        var dfltSelect = container.find('select.default-multi-value');
        dfltSelect.find('option[value="'+key+'"]').remove();
        dfltSelect.trigger('chosen:updated');
      }
      return false;
    });

    // single-value toggle input for data (i.e. amount of money)
    container.on('blur', 'tr.allowed-values-single input[type="text"]', function(event) {
      var self = $(this);
      if (self.prop('readonly')) {
        return false;
      }
      var amount = self.val().trim();
      if (amount === '') {
        self.val('');
        return false;
      }

      // defer submit until after validation.
      var submitDefer = PHPMYEDIT.deferReload(container);
      self.prop('readonly', true);

      $.post(OC.filePath('cafevdb', 'ajax/projects', 'extra-fields.php'),
             {
               request: 'ValidateAmount',
               value: { amount: amount }
             },
             function (data) {
               if (!CAFEVDB.ajaxErrorHandler(data, [ 'Amount' ],
                                             function() {
                                               self.prop('readonly', false);
                                               submitDefer.resolve();
                                             })) {
                 return;
               }
               var amount = data.data.Amount;
               self.val(amount);
               self.prop('readonly', false);
               submitDefer.resolve();
             });
      return false;
    });

    // multi-field input matrix
    container.on('blur', 'tr.allowed-values input[type="text"], tr.allowed-values textarea', function(event) {
      var self = $(this);
      if (self.prop('readonly')) {
        return false;
      }
      var row = self.closest('tr.allowed-values');
      var placeHolder = row.hasClass('placeholder');
      if (placeHolder && self.val().trim() === '') {
        // don't add empty fields (but of course allow to remove field data)
        self.val('');
        return false;
      }

      // associated data items
      var data = $.extend({}, fieldTypeData(), row.data());

      // fetch all available keys, server validation will enforce
      // unique keys.
      var keys = [];
      var tbody = self.closest('tbody');
      var skipKey = placeHolder ? false : row.find('input.field-key').val().trim();
      tbody.find('tr.data-line').not('.placeholder').each(function(index) {
        var key = $(this).find('input.field-key').val().trim();
        if (key === skipKey) {
          skipKey = false; // skip once.
        } else if (key !== '') {
          keys.push(key);
        }
      });
      console.log('keys', keys, typeof keys);

      var allowed = row.find('input[type="text"], input[type="hidden"], textarea');

      var dflt = container.find('select.default-multi-value');
      var oldDflt = dflt.find(':selected').val();

      var postData = {
        request: 'AllowedValuesOption',
        value: {
          selected: oldDflt ? oldDflt : '',
          data: data,
          keys: keys.length > 0 ? keys : 0
        }
      };

      postData = $.param(postData);
      postData += '&'+allowed.serialize();

      // defer submit until after validation.
      var submitDefer = PHPMYEDIT.deferReload(container);
      allowed.prop('readonly', true);

      $.post(OC.filePath('cafevdb', 'ajax/projects', 'extra-fields.php'),
             postData,
             function (data) {
               if (!CAFEVDB.ajaxErrorHandler(data,
                                             [ 'AllowedValueOption',
                                               'AllowedValueInput',
                                               'AllowedValue'
                                             ],
                                             function() {
                                               allowed.prop('readonly', false);
                                               submitDefer.resolve();
                                             })) {
                 return;
               }
               var option = data.data.AllowedValueOption;
               var input  = data.data.AllowedValueInput;
               var value  = data.data.AllowedValue; // sanitized
               $.fn.cafevTooltip.remove();
               if (placeHolder) {
                 row.parents('table').find('thead').show();
                 row.before(input).prev().find('input, textarea').cafevTooltip({placement:'auto right'});
                 self.val('');
                 row.data('index', row.data('index')+1); // next index
                 resizeCB();
               } else {
                 var next = row.next();
                 row.replaceWith(input);
                 next.prev().find('input, textarea').cafevTooltip({placement:'auto right'});;
               }
               // get the key <-> value connection right for the default selector
               var newValue = $(option).val();
               var oldOption = dflt.find('option[value="'+newValue+'"]');
               if (oldOption.length > 0) {
                 oldOption.replaceWith(option);
               } else {
                 dflt.children('option').first().after(option);
               }
               dflt.trigger('chosen:updated');
               allowed.prop('readonly', false);

               if (CAFEVDB.toolTipsEnabled) {
                 $.fn.cafevTooltip.enable();
               } else {
                 $.fn.cafevTooltip.disable();
               }

               submitDefer.resolve();
             });
      return false;
    });

    // When a reader-group is removed, we also deselect it from the
    // writers. This -- of course -- only works if initially
    // the readers and writers list is in a sane state ;)
    container.on('change', 'select.readers', function(event) {
         console.log('readers change');
         var self = $(this);

         var changed = false;
         var writers = container.find('select.writers');
         self.find('option').not(':selected').each(function() {
                                                    var writer = writers.find('option[value="'+this.value+'"]');
                                                    if (writer.prop('selected')) {
                                                      writer.prop('selected', false);
                                                      changed = true;
                                                    }
                                                  });
         if (changed) {
           writers.trigger('chosen:updated');
         }
         return false;
       });

    // When a writer-group is added, then add it to the
    // readers as well ;)
    container.on('change', 'select.writers', function(event) {
                             console.log('writers change');
                             var self = $(this);

                             var changed = false;
                             var readers = container.find('select.readers');
                             self.find('option:selected').each(function() {
                                var reader = readers.find('option[value="'+this.value+'"]');
                                if (!reader.prop('selected')) {
                                    reader.prop('selected', true);
                                  changed = true;
                                }
                              });
                             if (changed) {
                               readers.trigger('chosen:updated');
                             }
                             return false;
                           });

    var tableContainerId = PHPMYEDIT.pmeIdSelector('table-container');
    container.on('chosen:showing_dropdown', tableContainerId+' select', function(event) {
                                                     console.log('chosen:showing_dropdown');
                                                     var widget = container.cafevDialog('widget');
                                                     var tableContainer = container.find(tableContainerId);
                                                         widget.css('overflow', 'visible');
                                                     container.css('overflow', 'visible');
                                                     tableContainer.css('overflow', 'visible');
                                                     return true;
                                                   });

    container.on('chosen:hiding_dropdown', tableContainerId+' select', function(event) {
                                                   console.log('chosen:hiding_dropdown');
                                                   var widget = container.cafevDialog('widget');
                                                   var tableContainer = container.find(tableContainerId);
                                                   tableContainer.css('overflow', '');
                                                   container.css('overflow', '');
                                                   widget.css('overflow', '');
                                                   return true;
                                                 });

    container.on('chosen:update', 'select.writers, select.readers', function(event) {
                resizeCB();
                return false;
            });

    resizeCB();
  };

  CAFEVDB.ProjectExtra = ProjectExtra;

})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  CAFEVDB.addReadyCallback(function() {
    var container = PHPMYEDIT.container();
    if (!container.hasClass('project-extra')) {
      return; // not for us
    }

  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
