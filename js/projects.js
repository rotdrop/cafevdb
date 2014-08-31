/**Orchestra member, musicion and project management application.
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
CAFEVDB.Projects = CAFEVDB.Projects || {};

(function(window, $, Projects, undefined) {
    'use strict';

    /**Strip any digit from the end of name and attach the four digit
     * year to the end of name.
     */
    Projects.attachYear = function(name, year) {
        name = name.replace(/\d+$/,'');
        return name+year;
    };

    /**Check whether exactly four digits are attached to the end of
     * name and return those as a four digit year. If not exactly four
     * digits are attached to the end of name return false.
     */
    Projects.extractYear = function(name) {
        var year = name.match(/[^\d](\d{4})$/);
        return year !== null ? year[1] : false;
    };
    
    Projects.actions = function(select) {
        
        // determine the export format
        var selected = select.find('option:selected').val();
        var values = select.attr('name');
        var optionValues = selected.split('?');

        selected = optionValues[0];

        switch (selected) {
        case 'events':
            if ($('#events').dialog('isOpen') == true) {
                $('#events').dialog('close').remove();
            } else {
                // We store the values in the name attribute as serialized
                // string.
                $.post(OC.filePath('cafevdb', 'ajax/events', 'events.php'),
                       values, CAFEVDB.Events.UI.init, 'json');
            }
            break;
        case 'brief-instrumentation':
        case 'detailed-instrumentation':
        case 'project-instruments':
        case 'sepa-debit-mandates':
            values += '&Template='+selected;
            values += '&headervisibility='+CAFEVDB.headervisibility;

            CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), values, 'post');
            break;
        case 'profit-and-loss':
        case 'project-files':
            var url    = OC.linkTo('files', 'index.php');
            var values = 'dir='+optionValues[1];

            CAFEVDB.formSubmit(url, values, 'get');
            break;
        case 'project-wiki':
            var url    = OC.linkTo('dokuwikiembed', 'index.php');
            var values = 'wikiPath='+CAFEVDB.urlencode('/doku.php?id=')+optionValues[1];
            CAFEVDB.formSubmit(url, values, 'post');
            break;
        default:
            OC.dialogs.alert(t('cafevdb', 'Unknown operation:')
                             +' "'+selected+'"',
                             t('cafevdb', 'Unimplemented'));
        }
        
        // Cheating. In principle we mis-use this as a simple pull-down
        // menu, so let the text remain at its default value. Make sure to
        // also remove and re-attach the tool-tips, otherwise some of the
        // tips remain, because chosen() removes the element underneath.
        
        select.find('option').removeAttr('selected');
        $('.tipsy').remove();

        select.trigger("chosen:updated");

        $('div.chosen-container').tipsy({gravity:'sw', fade:true});
        $('li.active-result').tipsy({gravity:'w', fade:true});

        return false;
    };

    Projects.actionMenu = function(containerSel = '#cafevdb-page-body') {
        var container = $(containerSel);
        var projectActions = container.find('select.project-actions');

        // emulate per-project action pull down menu via chosen
        projectActions.chosen({ disable_search:true });
        projectActions.off('change');
        projectActions.change(function(event) {
            event.preventDefault();
            
            return Projects.actions($(this));
        });  
        projectActions.off('chosen:showing_dropdown');
        projectActions.on('chosen:showing_dropdown', function (chosen) {
            container.find('ul.chosen-results li.active-result').tipsy({gravity:'w', fade:true});
        });
    };

    Projects.pmeFormInit = function(containerSel = '#cafevdb-page-body') {
        var container = $(containerSel);
        var form = $(containerSel).find('form[class^="pme-form"]');
        var submitSel = 'input.pme-save,input.pme-apply,input.pme-more';

        if (form.find(submitSel).length > 0) {

            var nameSelector =
                'input.pme-input-0-projectname'+
                ','+
                'input.pme-input-1-projectname';
            var yearSelector = 'select[name="PME_data_Jahr"]';
            var attachSelector = '#project-name-yearattach';

            var name = $(nameSelector);
            var year = $(yearSelector);
            var attach = $(attachSelector);

            var oldProjectYear = $(form).find(yearSelector + ' :selected').text();
            var oldProjectName = name.val();

            /**Verify the user submitted name and year settings,
             * depending on whether the user has activated the name or
             * year control, or has clicked the submit button.
             */
            var verifyYearName = function (postAddOn, button = null) {
                /* Forward the request to the server via Ajax
                 * technologies.
                 */

                var post = form.serialize();
                post += '&control='+postAddOn;

                OC.Notification.hide(function () {
                    $.post(OC.filePath('cafevdb', 'ajax/projects', 'verifyName.php'), post,
                           function (data) {
                               if (data.status == 'success') {
                                   var rqData = data.data;
                                   if (rqData.message != '') {
                                       OC.Notification.showHtml(rqData.message);
                                   }
                                   name.val(rqData.projectName);
                                   year.val(rqData.projectYear);
                                   year.trigger('chosen:updated');
                                   oldProjectYear = rqData.projectYear;
                                   oldProjectName = rqData.projectName;
                                   if (postAddOn == 'submit') {
                                       if (button !== null) {
                                           $(form).off('click', submitSel);
                                           button.trigger('click');
                                       } else {
                                           form.submit();
                                       }
                                   }
                               } else if (data.status == 'error') {
                                   rqData = data.data;
                                   OC.Notification.showHtml(rqData.message);
                                   if (name.val() == '') {
                                       name.val(oldProjectName);
                                   }
                                   if (year.val() == '') {
                                       year.val(oldProjectYear);
                                       year.trigger('chosen:updated');
                                   }
                                   if (data.data.error == 'exception') {
                                       OC.dialogs.alert(rqData.exception+rqData.trace,
                                                        t('cafevdb', 'Caught a PHP Exception'),
                                                        null, true);
                                   }
                               }
                           });
                });
            }
        
            attach.off('click');
            attach.click(function(event) {
                name.trigger('blur');
            });

            year.off('change');
            year.change(function(event) {
                event.preventDefault();
                
                verifyYearName('year');
                
                return false;
            });

            name.off('blur');
            name.blur(function(event) {
                event.preventDefault();
                
                verifyYearName('name');
                
                return false;
            });
        
            // Attach a delegate handler to the form; this gives the
            // possibility to attach another delegate handler to the
            // container element.
            form.off('click', submitSel);
            form.on('click',
                    submitSel,
                    function(event) {
                        event.preventDefault();
                        
                        verifyYearName('submit', $(this));
                        
                        return false;
                    });
            
        }
        
    };

})(window, jQuery, CAFEVDB.Projects);

$(document).ready(function(){
    CAFEVDB.Projects.actionMenu();
    CAFEVDB.Projects.pmeFormInit();
});
