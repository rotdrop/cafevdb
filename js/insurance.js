/*global  */
/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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
CAFEVDB.Insurances = CAFEVDB.Insurances || {};

(function(window, $, Insurances, undefined) {
    'use strict';
  
    Insurances.pmeFormInit = function(containerSel) {
        containerSel = PHPMYEDIT.selector(containerSel);
        var container = PHPMYEDIT.container(containerSel);
        var form = container.find('form[class^="pme-form"]');
        var submitSel = 'input.pme-save,input.pme-apply,input.pme-more';
        var submits = form.find(submitSel);

        if (submits.length > 0) {
            var rateDialog = container.find('select.broker').length > 0;
            var brokerDialog = container.find('input.broker').length > 0;

            var textInputs;
            var key;

            if (brokerDialog) {
                textInputs = {
                    'broker': container.find('input.broker'),
                    'brokername': container.find('input.brokername'),
                    'brokeraddress': container.find('textarea.brokeraddress')
                }
            } else if (rateDialog) {
                textInputs = {
                    'rate': container.find('input.rate'),
                    //'date': container.find('input.date'),
                    'policy': container.find('input.policy')
                }
            } else {
                // need to disable all of these on blur in order to avoid
                // focus ping-pong
                textInputs = {
                    'insured-item': container.find('input.insured-item'),
                    'manufacturer': container.find('input.manufacturer'),
                    'construction-year': container.find('input.construction-year'),
                    'amount': container.find('input.amount')
                };
            }

            var oldValues = {};
            for (key in textInputs) {
                oldValues[key] = textInputs[key].val();
            }
            
            var blurLock = function(lock) {
                for (var key in textInputs) {
                    textInputs[key].prop('disabled', lock);
                }
                submits.prop('disabled', lock);
            };

            var validate = function(postAddOn, button, lockCallback) {

                if (typeof lockCallback == 'undefined') {
                    lockCallback = function(lock) {};
                }

                var validateLock = function() {
                    lockCallback(true);
                };
                
                var validateUnlock = function() {
                    lockCallback(false);
                };
                
                var post = form.serialize();
                post += '&control='+postAddOn;

                // until end of validation
                validateLock(true);      

                OC.Notification.hide(function() {
                    $.post(OC.filePath('cafevdb', 'ajax/insurance', 'validate.php'),
                           post,
                           function(data) {
                               var key;
                               if (!CAFEVDB.ajaxErrorHandler(data,
                                                             Object.keys(textInputs),
                                                             validateUnlock)) {
                                   for (key in textInputs) {
                                       textInputs[key].val(oldValues[key]);
                                   }
                               } else {
                                   if (data.data.message != '') {
                                       OC.Notification.show(data.data.message);
                                       setTimeout(function() {
                                           OC.Notification.hide();
                                       }, 5000);
                                   }
                                   //alert('data: '+CAFEVDB.print_r(data.data, true));
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
                           })
                });
            };

            // Validate text inputs. We assume that select boxes work
            // out just fine.

            for (key in textInputs) {
                textInputs[key].
                    off('blur').
                    on('blur', { control: key }, function(event) {

                    event.preventDefault();

                    validate(event.data.control, undefined, blurLock);

                    return false;
                });
            }

            // intercept form-submit until validated

            form.
                off('click', submitSel).
                on('click', submitSel, function(event) {
                if ($(this).attr('name').indexOf('savedelete') < 0) {
                    //alert('submit');
                    event.preventDefault();
                    validate('submit', $(this));
                    return false;
                } else {
                    return true;
                }
            });

        }        
    };

})(window, jQuery, CAFEVDB.Insurances);

$(document).ready(function(){

    PHPMYEDIT.addTableLoadCallback('InsuranceRates', {
        callback: function(selector, resizeCB) {
            CAFEVDB.Insurances.pmeFormInit(selector);
            resizeCB();
        },
        context: CAFEVDB.Insurances,
        parameters: []
    });

    PHPMYEDIT.addTableLoadCallback('InsuranceBrokers', {
        callback: function(selector, resizeCB) {
            CAFEVDB.Insurances.pmeFormInit(selector);
            resizeCB();
        },
        context: CAFEVDB.Insurances,
        parameters: []
    });

    PHPMYEDIT.addTableLoadCallback('InstrumentInsurance', {
        callback: function(selector, resizeCB) {
            CAFEVDB.exportMenu(selector);

            CAFEVDB.SepaDebitMandate.insuranceReady(selector);

            CAFEVDB.Insurances.pmeFormInit(selector);

            $(':button.musician-instrument-insurance').click(function(event) {
                event.preventDefault();
                var values = $(this).attr('name');
                
                CAFEVDB.Page.loadPage($(this).attr('name'));
                
                return false;
            });

            resizeCB();

        },
        context: CAFEVDB.Insurances,
        parameters: []
    });

    CAFEVDB.addReadyCallback(function() {
        var dpyClass = $(PHPMYEDIT.defaultSelector).find('form.pme-form input[name="DisplayClass"]');
        if (dpyClass.length > 0 &&
            (dpyClass.val() == 'InstrumentInsurance' || dpyClass.val() == 'InsurancRates')) {
          CAFEVDB.Insurances.pmeFormInit(PHPMYEDIT.defaultSelector);
        }
    });
});

// Local Variables: ***
// js-indent-level: 4 ***
// js3-indent-level: 4 ***
// js3-label-indent-offset: -2 ***
// End: ***
