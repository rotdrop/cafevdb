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

$(document).ready(function(){

    PHPMYEDIT.addTableLoadCallback('Musicians', {
        callback: function(selector, resizeCB) {
            var container = $(selector);
            CAFEVDB.exportMenu(selector);
            container.find('input.pme-email').addClass('formsubmit');
            container.find('input.pme-bulkcommit').addClass('formsubmit');
            container.find('div.photo, #cafevdb_inline_image_wrapper').
		on('click', 'img', function(event) {
                    event.preventDefault();
                    CAFEVDB.Photo.popup(this);
                    return false;
		});

            container.find('input.register-musician').off('click').
                on('click', function(event) {

                    var form = container.find('form.pme-form');
                    var projectId = form.find('input[name="ProjectId"]').val();
                    var projectName = form.find('input[name="Project"]').val();
                    var musicianId = $(this).data('musician-id');

 		    $.post(OC.filePath('cafevdb', 'ajax/instrumentation', 'add-musicians.php'),
			   {
			       'ProjectId': projectId,
			       'ProjectName': projectName,
			       'MusicianId': musicianId,
			   }, function(data) {
			       if (!CAFEVDB.ajaxErrorHandler(data, [
				   'musicians'
			       ])) {
				   return false;
			       }
			       var musician = data.data.musicians[0];
			       //alert('data: '+CAFEVDB.print_r(musician, true));
			       CAFEVDB.Instrumentation.personalRecordDialog(
				   musician.instrumentationId,
				   {
				       ProjectId: projectId,
				       ProjectName: projectName,
				       InitialValue: 'Change'
				   });
			   }
			  );

                    return false;
		});

            $(':button.musician-instrument-insurance').click(function(event) {
                event.preventDefault();
                var values = $(this).attr('name');
                
                CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), values, 'post');
                
                return false;
            });

            if (container.find('#file_upload_target').length > 0) {
                var idField = $(selector).find('input[name="PME_data_Id"]');
                var recordId = -1;
                if (idField.length > 0) {
                    recordId = idField.val();
                }
                CAFEVDB.Photo.ready(recordId, resizeCB);
            } else {
                container.find('div.photo, span.photo').imagesLoaded(resizeCB);
            }
        },
        context: CAFEVDB,
        parameters: []
    });
    
    PHPMYEDIT.addTableLoadCallback('EmailHistory', {
        callback: function(selector, resizeCB) {
            //CAFEVDB.exportMenu(selector);
            //$(selector).find('input.pme-email').addClass('formsubmit');
            //CAFEVDB.SepaDebitMandate.popupInit(selector);
            //this.ready(selector);
            resizeCB();
        },
        context: CAFEVDB.Email,
        parameters: []
    });

    PHPMYEDIT.addTableLoadCallback('InstrumentInsurance', {
        callback: function(selector, resizeCB) {
            CAFEVDB.exportMenu(selector);
            $(selector).find('input.pme-email').addClass('formsubmit');
            //CAFEVDB.SepaDebitMandate.popupInit(selector);
            //this.ready(selector);

            $(':button.musician-instrument-insurance').click(function(event) {
                event.preventDefault();
                var values = $(this).attr('name');
                
                CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), values, 'post');
                
                return false;
            });

            resizeCB();

        },
        context: CAFEVDB,
        parameters: []
    });

    CAFEVDB.pmeTweaks();

    CAFEVDB.tipsy();
    
    if (CAFEVDB.toolTips) {
        $.fn.tipsy.enable();
    } else {
        $.fn.tipsy.disable();
    }
    CAFEVDB.broadcastHeaderVisibility();

    // Prevent drag&drop outside allowed areas.
    window.addEventListener("dragover",function(e){
        e = e || event;
        e.preventDefault();
    },false);
    window.addEventListener("drop",function(e){
        e = e || event;
        e.preventDefault();
    },false);
    
});

// Local Variables: ***
// js-indent-level: 4 ***
// js3-indent-level: 4 ***
// js3-label-indent-offset: -2 ***
// End: ***
