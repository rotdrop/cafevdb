/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013, 2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName, $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Notification from './notification.js';
import * as Ajax from './ajax.js';
import * as Page from './page.js';
import * as SepaDebitMandate from './sepa-debit-mandate.js';
import * as PHPMyEdit from './pme.js';
import * as SelectUtils from './select-utils.js';
// import * as SelectUtils from './select-utils.js';
import generateUrl from './generate-url.js';
import fileDownload from './file-download.js';
import pmeExportMenu from './pme-export.js';

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

require('instrument-insurances.scss');

const lang = $('html').attr('lang');

const pmeAutocomplete = function($input) {
  const autocompleteData = $input.data('autocomplete');
  if (autocompleteData) {
    $input
      .autocomplete({
        source: autocompleteData.map(x => String(x)),
        minLength: 0,
        open(event, ui) {
          const $input = $(event.target);
          const $results = $input.autocomplete('widget');
          // The following would place the list above the input
          // const top = $results.position().top;
          // const height = $results.outerHeight();
          // const inputHeight = $input.outerHeight();
          // const newTop = top - height - inputHeight;

          // $results.css('top', newTop + 'px');
          const $parent = $results.parent();
          $results.data('savedOverflow', $parent.css('overflow'));
          $parent.css('overflow', 'visible');
        },
        close(event, ui) {
          const $input = $(event.target);
          const $results = $input.autocomplete('widget');
          const $parent = $results.parent();
          $parent.css('overflow', $results.data('savedOverflow'));
          $results.removeData('savedOverflow');
        },
        select(event, ui) {
          const $input = $(event.target);
          $input.val(ui.item.value);
          $input.blur();
        },
      })
      .on('focus, click', function() {
        const $this = $(this);
        if (!$this.autocomplete('widget').is(':visible')) {
          $this.autocomplete('search', $this.val());
        }
      });
  }
};

const updateInsuranceFee = function(elements) {

  const $scopeSelect = elements.$scopeSelect;
  const $brokerSelect = elements.$brokerSelect;
  const $insuranceAmount = elements.$insuranceAmount;
  const $insuranceRate = elements.$insuranceRate;
  const $insuranceFee = elements.$insuranceFee;

  const $scope = $scopeSelect.find('option:selected');
  if ($scope.length === 0) {
    return false;
  }
  const $broker = $brokerSelect.find('option:selected');
  if ($broker.length === 0) {
    return false;
  }
  const rates = $broker.data('data');
  const rateMeta = rates.find(rate => rate.geographicalScope === $scope.val());
  if (!rateMeta) {
    return false;
  }
  const rate = rateMeta.rate;

  $insuranceRate.find('.pme-input').val(rate);
  const $rateDisplay = $insuranceRate.find('.insurance-rate-display');
  $rateDisplay.data('value', rate);
  $rateDisplay.html((rate * 100.0).toLocaleString(lang) + ' %');

  $insuranceFee.html(
    new Intl.NumberFormat(
      lang, {
        style: 'currency',
        currency: $insuranceFee.data('currencyCode'),
      })
      .format(
        $insuranceAmount.val() * rate * (1.0 + $insuranceFee.data('taxRate'))
      )
  );
};

const enableScopeOptions = function($scopeSelect, $broker) {
  if ($broker.length === 0) {
    return;
  }
  const rates = $broker.data('data');
  $scopeSelect.find('option').each(function(idx) {
    const $option = $(this);
    $option.prop('disabled', rates.find(rate => rate.geographicalScope === $option.val()) === undefined);
  });
  $scopeSelect.trigger('change'); // update insurance fees
  SelectUtils.refreshWidget($scopeSelect);
};

const pmeFormInit = function(containerSel) {
  containerSel = PHPMyEdit.selector(containerSel);
  const container = PHPMyEdit.container(containerSel);
  const form = container.find('form[class^="pme-form"]');
  const submitSel = 'input.pme-save,input.pme-apply,input.pme-more';
  const submits = form.find(submitSel);

  if (submits.length > 0) {
    const rateDialog = container.find('select.broker').length > 0;
    const brokerDialog = container.find('input.broker').length > 0;

    let textInputs;
    let key;

    if (brokerDialog) {
      textInputs = {
        broker: container.find('input.broker'),
        brokerName: container.find('input.brokername'),
        brokerAddress: container.find('textarea.brokeraddress'),
      };
    } else if (rateDialog) {
      textInputs = {
        rate: container.find('input.rate'),
        policy: container.find('input.policy'),
      };
    } else {
      // need to disable all of these on blur in order to avoid
      // focus ping-pong
      textInputs = {
        insuredItem: container.find('input.insured-item'),
        manufacturer: container.find('input.manufacturer'),
        constructionYear: container.find('input.construction-year'),
        amount: container.find('input.amount'),
      };
    }

    const oldValues = {};
    for (key in textInputs) {
      oldValues[key] = textInputs[key].val();
    }

    const validate = function(control, button, lockCallback) {

      if (lockCallback === undefined) {
        lockCallback = function(lock) {};
      }

      const validateLock = function() {
        lockCallback(true);
      };

      const validateUnlock = function() {
        lockCallback(false);
      };

      let post = form.serialize();
      post += '&control=' + control;

      // until end of validation
      validateLock(true);

      Notification.hide(function() {
        $.post(generateUrl('insurance/validate/' + control), post)
          .fail(function(xhr, status, errorThrown) {
            Ajax.handleError(xhr, status, errorThrown, function() {
              for (const key in textInputs) {
                textInputs[key].val(oldValues[key]);
              }
              validateUnlock();
            });
          })
          .done(function(data) {
            if (!Ajax.validateResponse(
              data,
              Object.keys(textInputs),
              validateUnlock)) {
              for (const key in textInputs) {
                textInputs[key].val(oldValues[key]);
              }
              return;
            }

            Notification.messages(data.message);

            if (typeof textInputs[control] !== 'undefined') {
              textInputs[control].val(data[control]);
            }
            if (control === 'submit') {
              for (key in textInputs) {
                textInputs[key].val(data[key]);
              }
              if (typeof button !== 'undefined') {
                $(form).off('click', submitSel);
                button.trigger('click');
              } else {
                form.submit();
              }
            }
            for (key in textInputs) {
              oldValues[key] = textInputs[key].val();
            }

            validateUnlock();
          });
      });
    }; // validate end

    // Validate text inputs. We assume that select boxes work
    // out just fine.
    //
    // Mis-feature. Do client-side validation when modifying
    // individual inputs and a single server-side validation when
    // submitting the form

    // const blurLock = function(lock) {
    //   for (const key in textInputs) {
    //     textInputs[key].prop('disabled', lock);
    //   }
    //   submits.prop('disabled', lock);
    // };

    // for (key in textInputs) {
    //   textInputs[key]
    //     .off('blur')
    //     .on('blur', { control: key }, function(event) {

    //       event.preventDefault();

    //       validate(event.data.control, undefined, blurLock);

    //       return false;
    //     });
    // }

    // autocomplete some input things with precomputed values
    pmeAutocomplete(form.find('input.insured-item'));
    pmeAutocomplete(form.find('input.construction-year'));

    // restrict rate-selections based on what is supported by the
    // broker and update insurance-fee info fields on change.

    const $scopeSelect = form.find('select.scope-select');
    const $brokerSelect = form.find('select.broker-select');
    const $insuranceRate = form.find('td.pme-value.insurance-rate');
    const $insuranceFee = form.find('td.pme-value.insurance-fee .insurance-fee-display');
    const $insuranceAmount = form.find('td.pme-value.insurance-amount input');

    form.find('input.insurance-amount')
      .on('change', function(event) {
        updateInsuranceFee({
          $scopeSelect,
          $brokerSelect,
          $insuranceAmount,
          $insuranceRate,
          $insuranceFee,
        });
        return false;
      });

    enableScopeOptions($scopeSelect, $brokerSelect.find('option:selected'));

    $brokerSelect.on('change', function(event) {
      enableScopeOptions($scopeSelect, $(this).find('option:selected'));
      return false;
    });

    $scopeSelect.on('change', function(event) {
      updateInsuranceFee({
        $scopeSelect,
        $brokerSelect,
        $insuranceAmount,
        $insuranceRate,
        $insuranceFee,
      });
      return false;
    });

    // intercept form-submit until validated

    form
      .off('click', submitSel)
      .on('click', submitSel, function(event) {
        if ($(this).attr('name').indexOf('savedelete') < 0) {
          // alert('submit');
          event.preventDefault();
          validate('submit', $(this));
          return false;
        } else {
          return true;
        }
      });

  }

  container
    .off('click', '.instrument-insurance-bill a.bill')
    .on('click', '.instrument-insurance-bill a.bill', function(event) {
      const self = $(this);
      const post = self.data('post');
      const action = 'insurance/download';

      Page.busyIcon(true);

      fileDownload(
        action,
        post, {
          done(url) {
            console.log('ready');
            Page.busyIcon(false);
          },
          errorMessage(data, url) {
            return t(appName, 'Unable to export insurance overview.');
          },
          fail(data) {
            Page.busyIcon(false);
          },
        });
      return false;
    });
};

const documentReady = function() {

  PHPMyEdit.addTableLoadCallback('insurance-rates', {
    callback(selector, parameters, resizeCB) {
      pmeFormInit(selector);
      resizeCB();
    },
    context: globalState,
    parameters: [],
  });

  PHPMyEdit.addTableLoadCallback('insurance-brokers', {
    callback(selector, parameters, resizeCB) {
      pmeFormInit(selector);
      resizeCB();
    },
    context: globalState,
    parameters: [],
  });

  PHPMyEdit.addTableLoadCallback('instrument-insurance', {
    callback(selector, parameters, resizeCB) {
      pmeExportMenu(selector);

      SepaDebitMandate.insuranceReady(selector);

      pmeFormInit(selector);

      $(':button.musician-instrument-insurance').click(function(event) {
        Page.loadPage($(this).attr('name'));
        return false;
      });

      resizeCB();

    },
    context: globalState,
    parameters: [],
  });

  CAFEVDB.addReadyCallback(function() {
    const renderer = $(PHPMyEdit.defaultSelector).find('form.pme-form input[name="templateRenderer"]').val();
    if (renderer === Page.templateRenderer('instrument-insurance')
        || renderer === Page.templateRenderer('insuranc-rates')) {
      pmeFormInit(PHPMyEdit.defaultSelector);
    }
  });
};

export {
  documentReady,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
