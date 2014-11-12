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

        if (form.find(submitSel).length > 0) {
            var rateDialog = false;

            // for the insurance rates
            var broker;
            var rate;

            // for the insured items
            var musicianId;
            var billToParty;
            var brokerSelect;
            var scopeSelect;
            var insuredItem;
            var accessorySelect;
            var manufacturer;
            var constructionYear;
            var insuranceAmount;

            var blurInputs;
            var oldValues;

            // for the insurance rates
            broker = container.find('input.broker');
            rate   = container.find('input.rate');

            if (broker.length > 0) {
                rateDialog = true;

                oldValues = [ broker.val(), rate.val() ];
                blurInputs = [ broker, rate ];
            } else {
                // for the insured items

                musicianId = container.find('input.musician-id');
                billToParty = container.find('input.bill-to-party');
                brokerSelect = container.find('select.broker-select');
                scopeSelect = container.find('select.scope-select');
                insuredItem = container.find('input.insured-item');
                accessorySelect = container.find('select.accessory');
                manufacturer = container.find('input.manufacturer');
                constructionYear = container.find('input.construction-year');
                insuranceAmount = container.find('input.amount');

                if (false) {
                    // doesn't make too much sense.
                    //alert('hello'+constructionYear.length);
                    constructionYear.datepicker({
                        changeMonth: false,
                        constrainInput: true,
                        gotoCurrent: true,
                        monthNames: [ '', '', '', '', '', '', '', '', '', '', '', '' ],
                        changeYear: true,
                        showButtonPanel: true,
                        dateFormat: 'yy',
                        yearRange: '-300:+0',
                        stepMonths: 12,
                        minDate: null,
                        beforeShow: function(input) {
                            $(input).unbind('blur');
                        },
                        onSelect: function(dateText, inst) {
                            //$(this).blur(self.validate);
                            $(this).focus();
                            $(this).blur();
                        }
                    }).focus(function() {
                        var thisCalendar = $(this);
                        $('.ui-datepicker-calendar').detach();
		        $('.ui-datepicker-close').click(function() {
                            var year = $("#ui-datepicker-div .ui-datepicker-year :selected").val();
                            thisCalendar.datepicker('setDate', new Date(year, 1, 1));
		        });
                    });
                }

                // need to disable all of these on blur in order to avoid
                // focus ping-pong
                oldValues = [
                    insuredItem.val(), manufacturer.val(), constructionYear.val(), insuranceAmount.val()
                ];
                blurInputs = [
                    insuredItem, manufacturer, constructionYear, insuranceAmount
                ];
            }

            var blurLock = function(lock) {
                var idx;

                for (idx = 0; idx < blurInputs.length; ++idx) {
                    blurInputs[idx].prop('disabled', lock);
                }
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
                               var idx;
                               if (!CAFEVDB.ajaxErrorHandler(data, [], validateUnlock)) {
                                   for (idx = 0; idx < blurInputs.length; ++idx) {
                                       blurInputs[idx].val(oldValues[idx]);
                                   }
                               } else {
                                   if (data.data.message != '') {
                                       OC.Notification.show(data.data.message);
                                       setTimeout(function() {
                                           OC.Notification.hide();
                                       }, 5000);
                                   }
                                   //alert('data: '+CAFEVDB.print_r(data.data, true));
                                   if (rateDialog) {
                                       broker.val(data.data.broker);
                                       rate.val(data.data.rate);
                                   }
                                   if (postAddOn == 'submit') {
                                       if (typeof button != 'undefined') {
                                           $(form).off('click', submitSel);
                                           button.trigger('click');
                                       } else {
                                           form.submit();
                                       }
                                   }
                                   for (idx = 0; idx < blurInputs.length; ++idx) {
                                       oldValues[idx] = blurInputs[idx].val()
                                   }

                                   validateUnlock();
                               }

                               return false;
                           })
                });
            };

            // validate brokers and rates

            broker.
                off('blur').
                on('blur', function(event) {
                event.preventDefault();
                
                validate('broker', undefined, blurLock);

                return false;
            });

            rate.
                off('blur').
                on('blur', function(event) {
                event.preventDefault();

                validate('rate', undefined, blurLock);
                
                return false;                
            });

            // validate new input for insured items

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
