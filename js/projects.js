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

CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {
    'use strict';

    var Projects = function() {};

    Projects.nameExceptions = [];

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
    
    CAFEVDB.Projects = Projects;

})(window, jQuery, CAFEVDB);

$(document).ready(function(){

    if ($('input.pme-save').length || $('input.pme-more').length) { 

        var nameSelector =
            'input.pme-input-0-projectname,input.pme-input-1-projectname';
        var yearSelector = 'select.[name="PME_data_Jahr"]';

        var oldProjectYear = $(yearSelector+' :selected').text();
        var oldProjectName = $(nameSelector).val();

        $(yearSelector).change(function(event) {
            /* If the year is changed, then we attach the new year
             * setting to the name. We 2
             */
            alert('blah', 'blah');
        });
        
        $(nameSelector).blur(function(event) {
            event.preventDefault();

            /* Forward the request to the server via Ajax
             * technologies.
             */
            var post = $('form.pme-form').serialize();
            $.post(OC.filePath('cafevdb', 'ajax/projects', 'verifyName.php'), post,
                   function (data) {
                       if (data.status == 'success') {
                           $(nameSelector).val(data.data.projectName);
                           $(yearSelector).val(data.data.projectYear);
                       } else {
                           OC.Notification.show(data.data.message);
                           if ($(nameSelector).val() == '') {
                               $(nameSelector).val(oldProjectName);
                           }
                           if ($(yearSelector).val() == '') {
                               $(yearSelector).val(oldProjectYear);
                           }
                       }
                       $(yearSelector).trigger('chosen:updated');
                       return false;
                   });

            return false;
        });
        
        $('form.pme-form').submit(function(event) {
            alert('blah', 'blah');
            event.preventDefault();
            return false;
        });



        if (false) {
        $(nameSelector).blur(function(event) {
            projectName = $(this).val();
            if (!projectName || projectName == '') {
                OC.Notification.show(t('cafevdb', 'The project-name must not be empty.'));
                // add further checks, spaces, camelcase etc.            
            } else {
                OC.Notification.hide();
            }
        });
        
        $('form.pme-form').submit(function(event) {

            projectName = $(nameSelector).val();
            if (!projectName || projectName == '') {
                OC.Notification.show(t('cafevdb', 'The project-name must not be empty.'));
                // add further checks, spaces, camelcase etc.
                //            event.preventDefault();
                //            return false;
            }

            OC.Notification.hide();

            return true;
        });
        }
    }
});