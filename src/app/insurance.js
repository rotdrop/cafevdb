/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Notification from './notification.js';
import * as Ajax from './ajax.js';
import * as Page from './page.js';
import * as SepaDebitMandate from './sepa-debit-mandate.js';
import * as PHPMyEdit from './pme.js';

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
        brokername: container.find('input.brokername'),
        brokeraddress: container.find('textarea.brokeraddress')
      }
    } else if (rateDialog) {
      textInputs = {
        rate: container.find('input.rate'),
        // date: container.find('input.date'),
        policy: container.find('input.policy')
      }
    } else {
      // need to disable all of these on blur in order to avoid
      // focus ping-pong
      textInputs = {
        'insured-item': container.find('input.insured-item'),
        manufacturer: container.find('input.manufacturer'),
        'construction-year': container.find('input.construction-year'),
        amount: container.find('input.amount')
      };
    }

    const oldValues = {};
    for (key in textInputs) {
      oldValues[key] = textInputs[key].val();
    }

    const blurLock = function(lock) {
      for (const key in textInputs) {
        textInputs[key].prop('disabled', lock);
      }
      submits.prop('disabled', lock);
    };

    const validate = function(postAddOn, button, lockCallback) {

      if (typeof lockCallback == 'undefined') {
        lockCallback = function(lock) {};
      }

      const validateLock = function() {
        lockCallback(true);
      };

      const validateUnlock = function() {
        lockCallback(false);
      };

      let post = form.serialize();
      post += '&control='+postAddOn;

      // until end of validation
      validateLock(true);

      Notification.hide(function() {
        $.post(
          OC.filePath(appName, 'ajax/insurance', 'validate.php'),
          post,
          function(data) {
            if (!Ajax.validateResponse(
              data,
              Object.keys(textInputs),
              validateUnlock)) {
              for (const key in textInputs) {
                textInputs[key].val(oldValues[key]);
              }
            } else {
              if (data.data.message != '') {
                Notification.show(data.data.message);
                setTimeout(function() {
                  Notification.hide();
                }, 5000);
              }
              // alert('data: '+CAFEVDB.print_r(data.data, true));
              if (typeof textInputs[postAddOn] != 'undefined') {
                textInputs[postAddOn].val(data.data[postAddOn]);
              }
              if (postAddOn == 'submit') {
                for (key in textInputs) {
                  textInputs[key].val(data.data[key]);
                }
                if (typeof button != 'undefined') {
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
            }

            return false;
          });
      });
    };

    // Validate text inputs. We assume that select boxes work
    // out just fine.

    for (key in textInputs) {
      textInputs[key]
        .off('blur')
        .on('blur', { control: key }, function(event) {

          event.preventDefault();

          validate(event.data.control, undefined, blurLock);

          return false;
        });
    }

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
      const action = OC.filePath(appName, 'ajax/insurance', 'instrument-insurance-export.php');
      post.DownloadCookie = CAFEVDB.makeId();

      Page.busyIcon(true);

      $.fileDownload(action, {
        httpMethod: 'POST',
        data: post,
        cookieName: 'insurance_invoice_download',
        cookieValue: post['DownloadCookie'],
        cookiePath: oc_webroot,
        successCallback() {
          console.log('ready');
          Page.busyIcon(false);
        },
        failCallback(responseHtml, url, error) {
          OC.dialogs.alert(
            t(appName, 'Unable to export insurance overview:')
              + ' '
              + responseHtml,
            t(appName, 'Error'),
            function() { Page.busyIcon(false); },
            true, true);
        }
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
    parameters: []
  });

  PHPMyEdit.addTableLoadCallback('insurance-brokers', {
    callback(selector, parameters, resizeCB) {
      pmeFormInit(selector);
      resizeCB();
    },
    context: globalState,
    parameters: []
  });

  PHPMyEdit.addTableLoadCallback('instrument-insurance', {
    callback(selector, parameters, resizeCB) {
      CAFEVDB.exportMenu(selector);

      SepaDebitMandate.insuranceReady(selector);

      pmeFormInit(selector);

      $(':button.musician-instrument-insurance').click(function(event) {
        event.preventDefault();
        Page.loadPage($(this).attr('name'));
        return false;
      });

      resizeCB();

    },
    context: globalState,
    parameters: []
  });

  CAFEVDB.addReadyCallback(function() {
    const renderer = $(PHPMyEdit.defaultSelector).find('form.pme-form input[name="templateRenderer"]').val();
    if (renderer == Page.templateRenderer('instrument-insurance')
        || renderer == Page.templateRenderer('insuranc-rates')) {
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
