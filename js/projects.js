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
    }
    
})(window, jQuery, CAFEVDB.Projects);

$(document).ready(function(){

    if ($('input.pme-save[name$="savechange"]').length ||
        $('input.pme-more[name$="morechange"]').length ||
        $('input.pme-save[name$="saveadd"]').length ||
        $('input.pme-more[name$="moreadd"]').length ||
        $('input.pme-save[name$="savecopy"]').length) { 

        var nameSelector =
            'input.pme-input-0-projectname,input.pme-input-1-projectname';
        var yearSelector = 'select[name="PME_data_Jahr"]';
        var attachSelector = '#project-name-yearattach';

        var oldProjectYear = $(yearSelector+' :selected').text();
        var oldProjectName = $(nameSelector).val();

        /**Verify the user submitted name and year settings, depending
         * on whether the user has activated the name or year control,
         * or has clicked the submit button.
         */
        var verifyYearName = function (postAddOn, button = null) {
            /* Forward the request to the server via Ajax
             * technologies.
             */

            var post = $('form.pme-form').serialize();
            post += '&control='+postAddOn;

	    //$('#notification').empty();
	    //$('#notification').css("display","none");
            OC.Notification.hide(function () {
                $.post(OC.filePath('cafevdb', 'ajax/projects', 'verifyName.php'), post,
                       function (data) {
                           if (data.status == 'success') {
                               var rqData = data.data;
                               if (rqData.message != '') {
                                   OC.Notification.show(rqData.message);
                               }
                               $(nameSelector).val(rqData.projectName);
                               $(yearSelector).val(rqData.projectYear);
                               $(yearSelector).trigger('chosen:updated');
                               oldProjectYear = rqData.projectYear;
                               oldProjectName = rqData.projectName;
                               if (postAddOn == 'submit') {
                                   if (button !== null) {
                                       button.off('click');
                                       button.trigger('click');
                                   } else {
                                       $('form.pme-form').submit();
                                   }
                               }
                           } else if (data.status == 'error') {
                               var rqData = data.data;
                               OC.Notification.show(rqData.message);
                               if ($(nameSelector).val() == '') {
                                   $(nameSelector).val(oldProjectName);
                               }
                               if ($(yearSelector).val() == '') {
                                   $(yearSelector).val(oldProjectYear);
                                   $(yearSelector).trigger('chosen:updated');
                               }
                               if (data.data.error == 'exception') {
                                   OC.dialogs.alert(rqData.exception+rqData.trace,
                                                    t('cafevdb', 'Caught a PHP Exception'),
                                                    null, true);
                               }
                           }
                           return false;
                       });
            });
        }
        
        $(attachSelector).click(function(event) {
            $(nameSelector).trigger('blur');
        });

        $(yearSelector).change(function(event) {
            event.preventDefault();

            verifyYearName('year');

            return false;
        });

        $(nameSelector).blur(function(event) {
            event.preventDefault();

            verifyYearName('name');

            return false;
        });
        
        $('input.pme-save,input.pme-more').click(function(event) {
            event.preventDefault();

            verifyYearName('submit', $(this));

            return false;
        });

    }
});