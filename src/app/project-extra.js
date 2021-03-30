/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as PHPMyEdit from './pme.js';
import generateUrl from './generate-url.js';

// NB: much of the visibility stuff is handled by CSS, e.g. which
// input is shown for which multiplicity.
require('project-extra.css');

const ready = function(selector, resizeCB) {
  const container = $(selector);

  const tableTab = container.find('select.tab');
  const newTab = container.find('input.new-tab');
  newTab.prop('readonly', !!tableTab.find(':selected').val());
  container.on('change', 'select.tab', function(event) {
    newTab.prop('readonly', !!tableTab.find(':selected').val());
    return false;
  });

  const setFieldTypeCssClass = function(data) {
    if (!data) {
      return;
    }

    container.find('tr.multiplicity')
      .removeClass(function(index, className) {
        return (className.match(/\b(multiplicity|data-type)-\S+/g) || []).join(' ');
      })
      .addClass('multiplicity-' + data.multiplicity + ' ' + 'data-type-' + data.dataType);
  };

  const fieldTypeData = function() {
    const multiplicity = container.find('select.multiplicity');
    const dataType = container.find('select.data-type');
    if (multiplicity.length > 0 && dataType.length > 0) {
      return { multiplicity: multiplicity.val(), dataType: dataType.val() };
    }
    const elem = container.find('td.pme-value.field-type .data');
    if (elem.length <= 0) {
      return null;
    }
    return elem.data('data');
  };

  const allowedHeaderVisibility = function() {
    const allowedValuesTable = container.find('table.allowed-values');
    if (allowedValuesTable.find('tbody tr:visible').length >= 2) {
      allowedValuesTable.find('thead').show();
    } else {
      allowedValuesTable.find('thead').hide();
    }
  };

  // Field-Type Selectors
  container.on('change', 'select.multiplicity, select.data-type', function(event) {
    const multiplicity = container.find('select.multiplicity').val();
    const dataType = container.find('select.data-type').val();
    setFieldTypeCssClass({ multiplicity, dataType });
    allowedHeaderVisibility();
    resizeCB();

    if (dataType === 'service-fee' || dataType === 'deposit') {
      container.find('td.pme-value.default-value input').attr('type', 'number');
    } else {
      container.find('td.pme-value.default-value input').attr('type', 'text');
    }
    return false;
  });

  container.on('keypress', 'tr.allowed-values input[type="text"]', function(event) {
    let pressedKey;
    if (event.which) {
      pressedKey = event.which;
    } else {
      pressedKey = event.keyCode;
    }
    if (pressedKey === 13) { // enter pressed
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
    const self = $(this);
    container.find('input.pme-input.default-value').val(self.find(':selected').val());
    return false;
  });

  container.on('blur', 'input.pme-input.default-value', function(event) {
    const self = $(this);
    const dfltSelect = container.find('select.default-multi-value');
    dfltSelect.children('option[value="' + self.val() + '"]').prop('selected', true);
    dfltSelect.trigger('chosen:updated');
    return false;
  });

  container.on('click', 'tr.allowed-values input.delete-undelete', function(event) {
    const self = $(this);
    const row = self.closest('tr.allowed-values');
    let used = row.data('used');
    used = !(!used || used === 'unused');
    if (row.data('deleted') !== '') {
      // undelete
      row.data('deleted', '');
      row.switchClass('deleted', 'active');
      row.find('input.field-flags').val('active');
      row.find('input[type="text"], textarea').prop('readonly', false);
      const key = row.find('input.field-key');
      const label = row.find('input.field-label');
      if (used) {
        key.prop('readonly', true);
      }
      const dfltSelect = container.find('select.default-multi-value');
      const option = '<option value="' + key.val() + '">' + label.val() + '</option>';
      dfltSelect.children('option').first().after(option);
      dfltSelect.trigger('chosen:updated');
    } else {
      const key = row.find('input.field-key').val();
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
      const dfltSelect = container.find('select.default-multi-value');
      dfltSelect.find('option[value="' + key + '"]').remove();
      dfltSelect.trigger('chosen:updated');
    }
    return false;
  });

  // single-value toggle input for data (i.e. amount of money)
  container.on(
    'blur',
    'tr.multiplicity.data-type-service-fee ~ tr.allowed-values-single input[type="text"]'
      + ','
      + 'tr.multiplicity.data-type-service-fee ~ tr.allowed-values input.field-data[type="text"]',
    function(event) {
      const self = $(this);
      if (self.prop('readonly')) {
        return false;
      }
      const amount = self.val().trim();
      if (amount === '') {
        self.val('');
        return false;
      }

      // defer submit until after validation.
      const submitDefer = PHPMyEdit.deferReload(container);
      self.prop('readonly', true);

      const cleanup = function() {
        self.prop('readonly', false);
        submitDefer.resolve();
      };

      $.post(
        generateUrl('validate/general/monetary-value'), { value: amount })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['amount'], cleanup)) {
            return;
          }
          self.val(data.amount);
          cleanup();
        });
      return false;
    });

  // multi-field input matrix
  container.on('blur', 'tr.allowed-values input[type="text"], tr.allowed-values textarea', function(event) {
    const self = $(this);
    if (self.prop('readonly')) {
      return false;
    }
    const row = self.closest('tr.allowed-values');
    const placeHolder = row.hasClass('placeholder');
    const generator = row.hasClass('generator');
    if (placeHolder && self.val().trim() === '') {
      // don't add empty fields (but of course allow to remove field data)
      self.val('');
      return false;
    }

    // associated data items
    const data = $.extend({}, fieldTypeData(), row.data());

    const allowed = row.find('input[type="text"], input[type="hidden"], textarea');

    const dflt = container.find('select.default-multi-value');
    const oldDflt = dflt.find(':selected').val();

    let postData = {
      request: 'allowed-values-option',
      value: {
        selected: oldDflt,
        data,
      },
    };

    postData = $.param(postData);
    postData += '&' + allowed.serialize();

    // defer submit until after validation.
    const submitDefer = PHPMyEdit.deferReload(container);
    allowed.prop('readonly', true);
    const cleanup = function() {
      allowed.prop('readonly', false);
      submitDefer.resolve();
    };

    $.post(
      generateUrl('projects/extra-fields/allowed-values-option'),
      postData)
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown, cleanup);
      })
      .done(function(data) {
        if (!Ajax.validateResponse(
          data,
          ['dataOptionSelectOption', 'dataOptionFormInputs'],
          cleanup)) {
          return;
        }
        const option = data.dataOptionSelectOption;
        const input = data.dataOptionFormInputs;
        $.fn.cafevTooltip.remove();
        if (placeHolder) {
          row.parents('table').find('thead').show();
          row.before(input).prev().find('input, textarea').cafevTooltip({ placement: 'auto right' });
          self.val('');
          row.data('index', row.data('index') + 1); // next index
          resizeCB();
        } else if (generator) {
          alert('PLEASE IMPLEMENT ME!');
        } else {
          const next = row.next();
          row.replaceWith(input);
          next.prev().find('input, textarea').cafevTooltip({ placement: 'auto right' });
        }
        // get the key <-> value connection right for the default selector
        const newValue = $(option).val();
        const oldOption = dflt.find('option[value="' + newValue + '"]');
        if (oldOption.length > 0) {
          oldOption.replaceWith(option);
        } else {
          dflt.children('option').first().after(option);
        }
        dflt.trigger('chosen:updated');

        if (globalState.toolTipsEnabled) {
          $.fn.cafevTooltip.enable();
        } else {
          $.fn.cafevTooltip.disable();
        }

        cleanup();
      });
    return false;
  });

  // When a reader-group is removed, we also deselect it from the
  // writers. This -- of course -- only works if initially
  // the readers and writers list is in a sane state ;)
  container.on('change', 'select.readers', function(event) {
    console.log('readers change');
    const self = $(this);

    let changed = false;
    const writers = container.find('select.writers');
    self.find('option').not(':selected').each(function() {
      const writer = writers.find('option[value="' + this.value + '"]');
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
    const self = $(this);

    let changed = false;
    const readers = container.find('select.readers');
    self.find('option:selected').each(function() {
      const reader = readers.find('option[value="' + this.value + '"]');
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

  const tableContainerId = PHPMyEdit.idSelector('table-container');
  container.on('chosen:showing_dropdown', tableContainerId + ' select', function(event) {
    console.log('chosen:showing_dropdown');
    const widget = container.cafevDialog('widget');
    const tableContainer = container.find(tableContainerId);
    widget.css('overflow', 'visible');
    container.css('overflow', 'visible');
    tableContainer.css('overflow', 'visible');
    return true;
  });

  container.on('chosen:hiding_dropdown', tableContainerId + ' select', function(event) {
    console.log('chosen:hiding_dropdown');
    const widget = container.cafevDialog('widget');
    const tableContainer = container.find(tableContainerId);
    tableContainer.css('overflow', '');
    container.css('overflow', '');
    widget.css('overflow', '');
    return true;
  });

  container.on('chosen:update', 'select.writers, select.readers', function(event) {
    resizeCB();
    return false;
  });

  setFieldTypeCssClass(fieldTypeData());

  allowedHeaderVisibility();
  // synthesize resize events for textareas.
  CAFEVDB.textareaResize(container, 'textarea.field-tooltip, textarea.extra-field-tooltip');

  console.info('before resizeCB');
  resizeCB();
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {
    // const container = PHPMyEdit.container();
    // if (!container.hasClass('project-extra-fields')) {
    //   return; // not for us
    // }
    // ready(); // ????
  });

};

export { ready, documentReady };

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
