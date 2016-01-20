/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

    if (false) {
        // should somehow depend on debug mode.
        $(document).on('ajaxError', function(event, xhr, settings, error) {
            OC.dialogs.alert(t('cafevdb', 'Unhandled internal AJAX error:')+
                             '<br/>'+
                             t('cafevdb', 'Error')+': '+error+
                             '<br/>'+
                             t('cafevdb', 'URL')+': '+settings.url,
                             t('cafevdb', 'Error'),
                             undefined, true, true);
            return false;
        });
    }

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

    // too long, should probably go to another file.
    PHPMYEDIT.addTableLoadCallback('ProjectExtra', {
        callback: function(selector, parameters, _resizeCB) {

            var resizeCB = function() {
                console.log('resize');
                _resizeCB();
            };

            if (parameters.reason != 'dialogOpen') {
                resizeCB();
                return;
            }

            var container = $(selector);

            var tableTab = container.find('select.tab');
            var newTab = container.find('input.new-tab');
            newTab.prop('disabled', !!tableTab.find(':selected').val());
            container.on('change', 'select.tab', function(event) {
                newTab.prop('disabled', !!tableTab.find(':selected').val());
                return false;
            });

            var typeSelect = container.find('select.field-type');
            var handleFieldType = function(select) {
                var singleClass = 'single-value-field';
                var multiClass = 'multi-value-field';
                var typeOption = select.find(':selected');
                var group = typeOption.data('groupId');
                console.log('group: '+group);
                if (group === 2) {
                    container.find('tr.field-type').addClass(multiClass);
                } else {
                    container.find('tr.field-type').removeClass(multiClass);
                }
                if (group === 0) {
                    container.find('tr.field-type').addClass(singleClass);
                } else {
                    container.find('tr.field-type').removeClass(singleClass);
                }
            };
            handleFieldType(typeSelect);

            container.on('change', 'select.field-type', function(event) {
                handleFieldType($(this));
                resizeCB();
                return false;
            });

            container.on('change', '.allowed-values', function(event) {
                var self = $(this);
                var allowed = self.val();
                var dflt = container.find('select.default-multi-value');
                var oldDflt = dflt.find(':selected').val();
                var postData = {
                    request: 'AllowedValuesOptions',
                    value: {
                        values: allowed,
                        selected: oldDflt
                    }
                };
                $.post(OC.filePath('cafevdb', 'ajax/projects', 'extra-fields.php'),
                       postData,
                       function (data) {
                           if (!CAFEVDB.ajaxErrorHandler(data, [ 'AllowedValuesOptions' ])) {
                               return;
                           }
                           var options = data.data.AllowedValuesOptions;
                           var value   = data.data.AllowedValues; // sanitized
                           console.log('typeInfo: '+CAFEVDB.print_r(options, true));
                           self.val(value);
                           dflt.html(options);
                           dflt.trigger('chosen:updated');
                           //resizeCB();
                       });
                return false;
            });

            // When a reader-group is removed, we also deselect it from the
            // writers. This -- of course -- only works if initially
            // the readers and writers list is in a sane state ;)
            container.on('change', 'select.readers', function(event) {
                console.log('readers change');
                var self = $(this);

                var changed = false;
                var writers = container.find('select.writers');
                self.find('option').not(':selected').each(function() {
                    var writer = writers.find('option[value="'+this.value+'"]');
                    if (writer.prop('selected')) {
                        writer.prop('selected', false);
                        changed = true;
                    }
                });
                if (changed) {
                    writers.trigger('chosen:updated');
                }
                return false;
            });

            // When a writer-group is added, then add it to the
            // readers as well ;)
            container.on('change', 'select.writers', function(event) {
                console.log('writers change');
                var self = $(this);

                var changed = false;
                var readers = container.find('select.readers');
                self.find('option:selected').each(function() {
                    var reader = readers.find('option[value="'+this.value+'"]');
                    if (!reader.prop('selected')) {
                        reader.prop('selected', true);
                        changed = true;
                    }
                });
                if (changed) {
                    readers.trigger('chosen:updated');
                }
                return false;
            });

            var tableContainerId = PHPMYEDIT.pmeIdSelector('table-container');
            container.on('chosen:showing_dropdown', tableContainerId+' select', function(event) {
                console.log('chosen:showing_dropdown');
                var widget = container.cafevDialog('widget');
                var tableContainer = container.find(tableContainerId);
                widget.css('overflow', 'visible');
                container.css('overflow', 'visible');
                tableContainer.css('overflow', 'visible');
                return false;
            });

            container.on('chosen:hiding_dropdown', tableContainerId+' select', function(event) {
                console.log('chosen:hiding_dropdown');
                var widget = container.cafevDialog('widget');
                var tableContainer = container.find(tableContainerId);
                tableContainer.css('overflow', '');
                container.css('overflow', '');
                widget.css('overflow', '');
                return false;
            });

            container.on('chosen:update', 'select.writers, select.readers', function(event) {
                resizeCB();
                return false;
            });

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
