/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013, 2016, 2020, 2021, 2022, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState } from './globals.ts';
import $ from './jquery.ts';
import * as CAFEVDB from './cafevdb.ts';
import * as Notification from './notification.ts';
import * as Ajax from './ajax.ts';
import * as Page from './page.ts';
import { templateRenderer } from './template-renderer.ts';
import * as SepaDebitMandate from './sepa-debit-mandate.js';
import * as PHPMyEdit from './pme.ts';
import * as SelectUtils from './select-utils.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import fileDownload from './file-download.ts';
import pmeExportMenu from './pme-export.ts';
import pmeAutocomplete from './pme-autocomplete.ts';
import {
  formSelector as pmeFormSelector,
  inputClassSelector as pmeInputClassSelector,
  classSelectors as pmeClassSelectors,
  valueSelector as pmeValueSelector,
} from './pme-selectors.ts';
import { type EnumGeographicalScope } from '../../build/ts-types/php-modules/Database/Doctrine/DBAL/Types.ts';

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

require('instrument-insurances.scss');

const lang = $('html').attr('lang');

type UpdateInsuranceFeeArg = {
  $scopeSelect: JQuery<HTMLSelectElement>,
  $brokerSelect: JQuery<HTMLSelectElement>,
  $insuranceAmount: JQuery<HTMLInputElement>,
  $insuranceRate: JQuery<HTMLInputElement>,
  $insuranceFee: JQuery<HTMLInputElement>,
};

type RateMeta = {
  geographicalScope: EnumGeographicalScope;
  rate: number;
  dueDate: string;
  policyNumer: string;
};

const updateInsuranceFee = ({
  $scopeSelect,
  $brokerSelect,
  $insuranceAmount,
  $insuranceRate,
  $insuranceFee,
}: UpdateInsuranceFeeArg) => {

  const scope = SelectUtils.selected($scopeSelect);
  if (!scope) {
    return false;
  }
  const $broker = SelectUtils.selectedOptions($brokerSelect);
  if ($broker.length === 0) {
    return false;
  }
  const rates = $broker.data('data') as RateMeta[];
  const rateMeta = rates.find(rate => rate.geographicalScope === scope);
  if (!rateMeta) {
    return false;
  }
  const rate = rateMeta.rate;

  $insuranceRate.find(pmeInputClassSelector()).val(rate);
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
        +$insuranceAmount.val()! * rate * (1.0 + $insuranceFee.data('taxRate')),
      ),
  );
};

const enableScopeOptions = (
  $scopeSelect: JQuery<HTMLSelectElement>,
  $broker: JQuery<HTMLOptionElement>,
) => {
  if ($broker.length === 0 || $broker.val() === '') {
    return;
  }
  const rates = $broker.data('data') as RateMeta[];
  if (rates === undefined) {
    console.error('RATES UNDEFINED', $broker.data());
    return;
  }
  $scopeSelect.find('option').each(function() {
    const $option = $(this);
    $option.prop('disabled', rates.find(rate => rate.geographicalScope === $option.val()) === undefined);
  });
  $scopeSelect.trigger('change'); // update insurance fees
  SelectUtils.refreshWidget($scopeSelect);
};

const pmeFormInit = (containerSel: string) => {
  containerSel = PHPMyEdit.selector(containerSel);
  const $container = PHPMyEdit.container(containerSel);
  const $form = $container.find(pmeFormSelector);

  const submitSel = pmeClassSelectors('input', ['save', 'apply', 'more']);
  const $submits = $form.find(submitSel);

  if ($submits.length > 0) {

    const rateDialog = $container.find('select.broker').length > 0;
    const brokerDialog = $container.find('input.broker').length > 0;

    let textInputs: Record<string, JQuery<HTMLInputElement>|JQuery<HTMLTextAreaElement> >;

    if (brokerDialog) {
      textInputs = {
        $broker: $container.find('input.broker') as JQuery<HTMLInputElement>,
        $brokerName: $container.find('input.brokername') as JQuery<HTMLInputElement>,
        $brokerAddress: $container.find('textarea.brokeraddress') as JQuery<HTMLTextAreaElement>,
      };
    } else if (rateDialog) {
      textInputs = {
        $rate: $container.find('input.rate') as JQuery<HTMLInputElement>,
        $policy: $container.find('input.policy') as JQuery<HTMLInputElement>,
      };
    } else {
      // need to disable all of these on blur in order to avoid
      // focus ping-pong
      textInputs = {
        $insuredItem: $container.find('input.insured-item') as JQuery<HTMLInputElement>,
        $manufacturer: $container.find('input.manufacturer') as JQuery<HTMLInputElement>,
        $constructionYear: $container.find('input.construction-year') as JQuery<HTMLInputElement>,
        $amount: $container.find('input.amount') as JQuery<HTMLInputElement>,
      };
    }

    const oldValues = {};
    for (const key in textInputs) {
      oldValues[key] = textInputs[key].val();
    }

    const validate = function(control: string, $button?: JQuery, lockCallback: (lock: boolean) => void = () => {}) {

      const validateLock = function() {
        lockCallback(true);
      };

      const validateUnlock = function() {
        lockCallback(false);
      };

      let post = $form.serialize();
      post += '&control=' + control;

      // until end of validation
      validateLock();

      Notification.hide(function() {
        $.post(generateAppUrl('insurance/validate/' + control), post)
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
              for (const key in textInputs) {
                textInputs[key].val(data[key]);
              }
              if (typeof $button !== 'undefined') {
                $form.off('click', submitSel);
                $button.trigger('click');
              } else {
                $form.trigger('submit');
              }
            }
            for (const key in textInputs) {
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
    pmeAutocomplete($form.find('input.insured-item'));
    pmeAutocomplete($form.find('input.construction-year'));

    // restrict rate-selections based on what is supported by the
    // broker and update insurance-fee info fields on change.

    const $scopeSelect = $form.find('select.scope-select') as JQuery<HTMLSelectElement>;
    const $brokerSelect = $form.find('select.broker-select') as JQuery<HTMLSelectElement>;
    const $insuranceRate = $form.find(pmeValueSelector + '.insurance-rate') as JQuery<HTMLInputElement>;
    const $insuranceFee = $form.find(pmeValueSelector + '.insurance-fee .insurance-fee-display') as JQuery<HTMLInputElement>;
    const $insuranceAmount = $form.find(pmeValueSelector + '.insurance-amount input') as JQuery<HTMLInputElement>;

    $form.find('input.insurance-amount')
      .on('change', function() {
        updateInsuranceFee({
          $scopeSelect,
          $brokerSelect,
          $insuranceAmount,
          $insuranceRate,
          $insuranceFee,
        });
        return false;
      });

    enableScopeOptions($scopeSelect, SelectUtils.selectedOptions($brokerSelect));

    $brokerSelect.on('change', function() {
      enableScopeOptions($scopeSelect, SelectUtils.selectedOptions($brokerSelect));
      return false;
    });

    $scopeSelect.on('change', function() {
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

    $form
      .off('click', submitSel)
      .on('click', submitSel, function(this: HTMLElement, event) {
        if ($(this).attr('name')!.indexOf('savedelete') < 0) {
          // alert('submit');
          event.preventDefault();
          validate('submit', $(this));
          return false;
        } else {
          return true;
        }
      });

  } // found submit inputs

  $container
    .off('click', 'a.download-link.ajax-download')
    .on('click', 'a.download-link.ajax-download', function(this: HTMLElement) {
      const $this = $(this);
      const post = $this.data('post');
      fileDownload($this.attr('href')!, post);
      return false;
    });
};

const documentReady = function() {

  PHPMyEdit.addTableLoadCallback('insurance-rates', {
    callback(selector, parameters, resizeCB) {
      if (parameters.reason !== 'dialogClose') {
        pmeFormInit(selector);
      }
      resizeCB();
    },
    context: globalState,
    parameters: [],
  });

  PHPMyEdit.addTableLoadCallback('insurance-brokers', {
    callback(selector, parameters, resizeCB) {
      if (parameters.reason !== 'dialogClose') {
        pmeFormInit(selector);
      }
      resizeCB();
    },
    context: globalState,
    parameters: [],
  });

  PHPMyEdit.addTableLoadCallback('instrument-insurance', {
    callback(selector, parameters, resizeCB) {
      if (parameters.reason !== 'dialogClose') {
        pmeExportMenu(selector);

        SepaDebitMandate.insuranceReady(selector);

        pmeFormInit(selector);

        $(':button.musician-instrument-insurance').on('click', function() {
          Page.loadPage($(this).attr('name')!);
          return false;
        });
      }
      resizeCB();
    },
    context: globalState,
    parameters: [],
  });

  CAFEVDB.addReadyCallback(async () => {
    const renderer = $(PHPMyEdit.defaultSelector).find(pmeFormSelector + ' input[name="templateRenderer"]').val();
    if (renderer === templateRenderer('instrument-insurance')
        || renderer === templateRenderer('insurance-rates')) {
      pmeFormInit(PHPMyEdit.defaultSelector);
    }
  });
};

export {
  documentReady,
};
