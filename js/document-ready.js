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


$(document).ready(function(){

    PHPMYEDIT.addTableLoadCallback('BriefInstrumentation',
                                   {
                                       callback: function(selector) {
                                           CAFEVDB.exportMenu(selector);
                                           $(selector).find('input.pme-email').addClass('formsubmit');
                                           CAFEVDB.SepaDebitMandate.popupInit(selector);
                                       },
                                       context: CAFEVDB,
                                       parameters: []
                                   });

    PHPMYEDIT.addTableLoadCallback('DetailedInstrumentation',
                                   {
                                       callback: function(selector) {
                                           CAFEVDB.exportMenu(selector);
                                           $(selector).find('input.pme-email').addClass('formsubmit');
                                       },
                                       context: CAFEVDB,
                                       parameters: []
                                   });

    PHPMYEDIT.addTableLoadCallback('Musicians',
                                   {
                                       callback: function(selector, resizeCB) {
                                           var container = $(selector);
                                           CAFEVDB.exportMenu(selector);
                                           container.find('input.pme-email').addClass('formsubmit');
                                           container.find('input.pme-bulkcommit').addClass('formsubmit');
                                           if (container.find('#file_upload_target').length > 0) {
                                               var idField = $(selector).find('input[name="PME_data_Id"]');
                                               var recordId = -1;
                                               if (idField.length > 0) {
                                                   recordId = idField.val();
                                               }
                                               CAFEVDB.Photo.ready(recordId, resizeCB);
                                           }
                                           container.find('span.photo').click(function(event) {
                                               event.preventDefault();
                                               CAFEVDB.Photo.popup(this);
                                               return false;
                                           });
                                       },
                                       context: CAFEVDB,
                                       parameters: []
                                   });

    $(PHPMYEDIT.defaultSelector+' input.pme-email').addClass('formsubmit');
    $(PHPMYEDIT.defaultSelector+' input.pme-bulkcommit').addClass('formsubmit');

    CAFEVDB.tipsy();
    
    if (CAFEVDB.toolTips) {
        $.fn.tipsy.enable();
    } else {
        $.fn.tipsy.disable();
    }
    CAFEVDB.broadcastHeaderVisibility();

});
