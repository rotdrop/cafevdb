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

$(document).ready(function() {

    $.widget("ui.dialog", $.ui.dialog, {
        _allowInteraction: function(event) {
            return !!$(event.target).closest(".mce-container").length || this._super( event );
        }
    });

    var content = $('#content');

    // Any pending form-submit which has not been caught otherwise is
    // here intercepted and redirected to the page-loader in order to
    // reduce load-time and to record usable history information.
    content.on('submit', 'form', function(event) {
        var form = $(this);
        var action = form.attr('action');
        if (action != '') {
            // not for us, external target.
            return true;
        }
        var post = form.serialize();
        //alert('post: '+post);
        CAFEVDB.Page.loadPage(post);
        return false;
    });

    // Any pending form-submit which has not been caught otherwise is
    // here intercepted and redirected to the page-loader in order to
    // reduce load-time and to record usable history information.
    content.on('click', ':submit', function(event) {
        var form = $(this.form);
        var post = form.serialize();
        var self = $(this);
        if (self.attr('name')) {
            var obj = {};
            obj[self.attr('name')] = self.val();
            post += '&' + $.param(obj);
        }
        //alert('post: '+post);
        CAFEVDB.Page.loadPage(post);
        return false;
    });

    // Intercept app-navigation events here and redirect to the page
    // loader
    content.on('click', 'ul#navigation-list li a', function(event) {
        var post = $(this).data('post');
        CAFEVDB.Page.loadPage(post);
        //alert('post: '+post);
        return false;
    });

    PHPMYEDIT.addTableLoadCallback('Musicians', {
        callback: function(selector, parameters, resizeCB) {

            if (parameters.reason == 'tabChange') {
                resizeCB();
                return;
            }

            var container = $(selector);
            CAFEVDB.exportMenu(selector);

            container.find('div.photo, #cafevdb_inline_image_wrapper').
                off('click', 'img.zoomable').
                on('click', 'img.zoomable', function(event) {
                event.preventDefault();
                CAFEVDB.Photo.popup(this);
                return false;
            });

            CAFEVDB.Musicians.ready(container);

            $(':button.musician-instrument-insurance').click(function(event) {
                event.preventDefault();
                var values = $(this).attr('name');

                CAFEVDB.Page.loadPage($(this).attr('name'));

                return false;
            });

            if (container.find('#contact_photo_upload').length > 0) {
                var idField = container.find('input[name="PME_data_Id"]');
                var recordId = -1;
                if (idField.length > 0) {
                    recordId = idField.val();
                }
                CAFEVDB.Photo.ready(recordId, 'Musiker', resizeCB);
            } else {
                container.find('div.photo, span.photo').imagesLoaded(resizeCB);
            }
        },
        context: CAFEVDB,
        parameters: []
    });

    PHPMYEDIT.addTableLoadCallback('Instruments', {
        callback: function(selector, parameters, resizeCB) {
            resizeCB();
        },
        context: CAFEVDB,
        parameters: []
    });

    CAFEVDB.addReadyCallback(function() {
        CAFEVDB.exportMenu();

        CAFEVDB.pmeTweaks();

        CAFEVDB.toolTipsInit();

        // Prevent drag&drop outside allowed areas.
        window.addEventListener("dragover", function(e){
            e = e || event;
            e.preventDefault();
        }, false);
        window.addEventListener("drop", function(e){
            e = e || event;
            e.preventDefault();
        }, false);

    });

    // The final callback stuff ...
    CAFEVDB.runReadyCallbacks();

});

// Local Variables: ***
// js-indent-level: 4 ***
// js3-indent-level: 4 ***
// js3-label-indent-offset: -2 ***
// End: ***
