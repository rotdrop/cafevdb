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

    /**Generate a popup-dialog with a wiki-page. Not to much project
     * related, rather general. Page and page-title are assumed to be
     * attached to the "post"-object
     */
    Projects.wikiPopup = function(post) {
        if ($('#dokuwiki_popup').dialog('isOpen') == true) {
            $('#dokuwiki_popup').dialog('close').remove();
        }
        DWEmbed.wikiPopup({ wikiPage: post.wikiPage,
                            popupTitle: post.popupTitle,
                            modal: false },
                          function(dwDialog, dwDialogWidget) {
                              CAFEVDB.dialogToBackButton(dwDialogWidget);
                          });
    };

    /**Generate a popup-dialog for the events-listing for the given
     * project.
     * 
     * @param post Arguments object:
     * { Project: 'NAME', ProjectId: XX }
     */
    Projects.eventsPopup = function(post) {
        if ($('#events').dialog('isOpen') == true) {
            $('#events').dialog('close').remove();
        } else {
            $.post(OC.filePath('cafevdb', 'ajax/events', 'events.php'),
                   post, CAFEVDB.Events.UI.init, 'json');
        }
    };

    /**Generate a popup for the instrumentation numbers.
     * 
     * @param containerSel The ambient element of the container
     * (i.e. the base page, or the div holding the dialog this one was
     * initiated from.
     * 
     * @param past Arguments object:
     * { Project: 'NAME', ProjectId: XX }
     */
    Projects.instrumentationNumbersPopup = function(containerSel, post)
    {
        // Prepate the data-array for PHPMYEDIT.tableDialogOpen(). The
        // instrumentation numbers are somewhat nasty and require too
        // many options.

        var tableOptions = {
            AmbientContainerSelector: containerSel,
            DialogHolderCSSId: 'project-instruments-dialog', 
            Template: 'project-instruments',
            DisplayClass: 'ProjectInstruments',
            Table: 'BesetzungsZahlen',
            Transpose: 'transposed',
            InhibitTranspose: 'true',
            headervisibility: CAFEVDB.headervisibility,
            ProjectId: post.ProjectId,
            Project: post.Project, // this is the name
            // Now special options for the dialog popup
            InitialViewOperation: true,
            InitialName: 'PME_sys_operation',
            InitialValue: 'View',
            ReloadName: 'PME_sys_operation',
            ReloadValue: 'View',
            PME_sys_operation: 'View',
            ModalDialog: false,
            modified: false
        };
        PHPMYEDIT.tableDialogOpen(tableOptions);
    };

    /**Parse the user-selection from the project-actions menu.
     * 
     * Project-id and -name are contained in data-fields of the
     * select, other potentially needed data is contained in
     * data-fields in the options.
     */
    Projects.actions = function(select, containerSel) {
        var Projects = this;

        // determine the export format
        var selected = select.find('option:selected');
        var selectedValue = selected.val();

        var projectId = select.data('projectId');
        var projectName = select.data('projectName');
        var post = {
            ProjectId: projectId,
            Project: projectName
        };

        switch (selectedValue) {
            // The next 5 actions cannot reasonably loaded in a
            // popup-box.
          case 'brief-instrumentation':
          case 'detailed-instrumentation':
          case 'sepa-debit-mandates':
            post.Template = selectedValue;
            post.headervisibility = CAFEVDB.headervisibility;
            CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), $.param(post), 'post');
            break;
        case 'profit-and-loss':
        case 'project-files':
            var url  = OC.linkTo('files', 'index.php');
            var path = selected.data('project-files');
            CAFEVDB.formSubmit(url, $.param({dir: path}), 'get');
            break;

            // The following three can easily be opened in popup
            // dialogs which is more convenient as it does not destroy
            // the original view.
        case 'events':
            Projects.eventsPopup(post);
            break;
        case 'project-instruments':
            Projects.instrumentationNumbersPopup(containerSel, post);
            break;
        case 'project-wiki':
            post.wikiPage = selected.data('wikiPage');
            post.popupTitle = selected.data('wikiTitle');
            Projects.wikiPopup(post);
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

    Projects.actionMenu = function(containerSel) {
        containerSel = PHPMYEDIT.selector(containerSel);
        var container = PHPMYEDIT.container(containerSel);
        var projectActions = container.find('select.project-actions');

        // emulate per-project action pull down menu via chosen
        projectActions.chosen({ disable_search:true });
        projectActions.off('change');
        projectActions.change(function(event) {
            event.preventDefault();
            
            return Projects.actions($(this), containerSel);
        });  
        projectActions.off('chosen:showing_dropdown');
        projectActions.on('chosen:showing_dropdown', function (chosen) {
            container.find('ul.chosen-results li.active-result').tipsy({gravity:'w', fade:true});
        });
    };

    Projects.pmeFormInit = function(containerSel) {
        var containerSel = PHPMYEDIT.selector(containerSel);
        var container = PHPMYEDIT.container(containerSel);
        var form = container.find('form[class^="pme-form"]');
        var submitSel = 'input.pme-save,input.pme-apply,input.pme-more';

        if (form.find(submitSel).length > 0) {

            var nameSelector =
                'input.pme-input-0-projectname'+
                ','+
                'input.pme-input-1-projectname';
            var yearSelector = 'select[name="PME_data_Jahr"]';
            var attachSelector = '#project-name-yearattach';

            var name = container.find(nameSelector);
            var year = container.find(yearSelector);
            var attach = container.find(attachSelector);

            var oldProjectYear = $(form).find(yearSelector + ' :selected').text();
            var oldProjectName = name.val();

            /**Verify the user submitted name and year settings,
             * depending on whether the user has activated the name or
             * year control, or has clicked the submit button.
             */
            var verifyYearName = function (postAddOn, button) {
                /* Forward the request to the server via Ajax
                 * technologies.
                 */
                var post = form.serialize();
                post += '&control='+postAddOn;

                OC.Notification.hide(function () {
                    $.post(OC.filePath('cafevdb', 'ajax/projects', 'verifyName.php'),
                           post,
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
                                       if (typeof button !== 'undefined') {
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
                        if ($(this).attr('name').indexOf('savedelete') < 0) {
                            event.preventDefault();
                            verifyYearName('submit', $(this));
                            return false;
                        } else {
                            return true;
                        }
                    });
            
        }
        
    };

    ///Place an ajax call for public web-page management, create,
    ///delete, attach articles.
    ///
    /// @param post The data array with action and information.
    ///
    /// Supported post packages:
    ///
    /// { Action: delete,
    ///   ArticleId: XX,
    ///   ProjectId: XX }
    ///
    /// { Action: add,
    ///   ProjectId: XX,
    ///   ArticleId: XX }
    /// 
    /// For Action 'add' a negative ArticleId triggers the geneation
    /// of a new article, otherwise it is the id of an existing
    /// event-announcement to attach to this project.
    ///
    Projects.projectWebPageRequest = function(post, container) {

        OC.Notification.hide(function() {
            $.post(OC.filePath('cafevdb', 'ajax/projects', 'web-articles.php'),
                post,
                function (data) {
                    if (data.status == 'success') {
                        var rqData = data.data;
                        if (rqData.message != '') {
                            OC.Notification.showHtml(rqData.message);
                        }
                    } else if (data.status == 'error') {
                        var rqData = data.data;
                        OC.Notification.showHtml(rqData.message);
                        if (data.data.error == 'exception') {
                            OC.dialogs.alert(rqData.exception+rqData.trace,
                                t('cafevdb', 'Caught a PHP Exception'),
                                null, true);
                        }
                    }
                    var form = container.find('table.pme-navigation');
                    var submit = form.find('input.pme-more, input.pme-reload, input.pme-apply');
                    submit.first().trigger('click');
                    setTimeout(function() {
                        OC.Notification.hide();
                    }, 5000);
                    
                });
        });
    };

    /**Dispatch a UI-event and potentially add or delete a public
     * web-page. This is called as a beforeActive tab-event handler.
     *
     * @param event The event provided by jQuery tab widget.
     * 
     * @param ui An object with old and new panel und tabs
     * 
     * @param container The div which contains the current dialog.
     * 
     */
    Projects.projectWebPageTabHandler = function(event, ui, container) {
        var tabId = ui.newTab.attr('id');
        //alert('id' + tabId);
        switch (tabId) {
          case 'cmsarticle-tab-newpage':
            event.stopImmediatePropagation();
            var projectId = ui.oldPanel.data('projectid');
            // just do it ...
            Projects.projectWebPageRequest({ Action: 'add',
                                             ArticleId: -1,
                                             ProjectId: projectId },
                                           container);
            return false;
          case 'cmsarticle-tab-deletepage':
            event.stopImmediatePropagation();
            var articleId = ui.oldPanel.data('articleid');
            var projectId = ui.oldPanel.data('projectid');
            OC.dialogs.confirm(
                t('cafevdb', 'Really delete the displayed event announcement?'),
                t('cafevdb', 'Delete Web-Page with Id {ArticleId}?',
                  { ArticleId: articleId }),
                function(answer) {
                    if (!answer) {
                        return;
                    }
                    // do it ...
                    Projects.projectWebPageRequest({ Action: 'delete',
                                                     ArticleId: articleId,
                                                     ProjectId: projectId },
                                                   container);
                },
                true);
            return false;
          default:
            return true;
        }
    };

})(window, jQuery, CAFEVDB.Projects);

$(document).ready(function(){

    PHPMYEDIT.addTableLoadCallback('Projects', {
        callback: function(selector, resizeCB) {
            var Projects = this;
            var container = PHPMYEDIT.container(selector);
            var containerNode = container[0];
            Projects.actionMenu(selector);
            Projects.pmeFormInit(selector);

            var imagesReady = false;
            var imagePoller = function(callback) {
                if (!imagesReady) {
                    var poller = setInterval(function() {
                                     if (imagesReady) {
                                         clearInterval(poller);
                                         callback();
                                     }
                                 }, 100);
                } else {
                    callback();
                }
            };

            if (container.find('#file_upload_target').length > 0) {
                var idField = container.find('input[name="PME_data_Id"]');
                var recordId = -1;
                if (idField.length > 0) {
                    recordId = idField.val();
                }
                CAFEVDB.Photo.ready(recordId, function() {
                    imagesReady = true;
                });
            } else {
                container.find('div.photo, span.photo').imagesLoaded(function() {
                    imagesReady = true;
                });
            }

            var articleBox = container.find('#projectWebArticles');

            var displayFrames = articleBox.find('iframe.cmsarticleframe.display, iframe.cmsarticleframe.add');
            var numDisplayFrames = displayFrames.length;

            var changeFrames = articleBox.find('iframe.cmsarticleframe.change, iframe.cmsarticleframe.change');
            var numChangeFrames = changeFrames.length;

            // allFrames also contains some div + all available iframes
            var allDisplayFrames = articleBox.find('.cmsarticleframe.display');
            var allChangeFrames = articleBox.find('.cmsarticleframe.change');
            var allContainers = articleBox.find('.cmsarticlecontainer');

            var scrollbarAdjust = function() {
                var scrollBarWidth = containerNode.offsetWidth - containerNode.clientWidth;
                articleBox.css('margin-right', scrollBarWidth + 'px');
            }
            
            var forceSize = function(iframe) {
                var domFrame = iframe[0];
                var scrollHeight = domFrame.contentWindow.document.body.scrollHeight;
                var scrollWidth = domFrame.contentWindow.document.body.scrollWidth;
                iframe.css({ width: scrollWidth + 'px',
                             height: scrollHeight + 'px',
                             overflow: 'hidden' });
                imagePoller(function() {
                    resizeCB();
                    scrollbarAdjust();
                });
            };

            var displayArticleLoad = function(frame) {
                if (typeof frame != 'undefined') {
                    var self = frame;
                    var iframe = $(self);
                    var contents = iframe.contents();

                    // For the pretty-print version. We remove everything
                    // except the article itself
                    contents.find('div#header').remove();
                    contents.find('div#footer').remove();
                    contents.find('div.navi').remove();
                    contents.find('body').css({'min-width': 'unset',
                                               'width': 'unset'});
                    contents.find('#content').css({'width': 'auto',
                                                   'height': '100%'});
                    contents.find('div.item-text').css({ 'width': 'auto',
                                                         'margin-left': '50px',
                                                         'left': 'unset',
                                                         'position': 'unset' });

                    var scrollWidth = self.contentWindow.document.body.scrollWidth;
                    var scrollHeight = self.contentWindow.document.body.scrollHeight;
                    iframe.css({
                        width: scrollWidth + 'px',
                        height: scrollHeight + 'px'
                    });
                    
                    //alert('height: ' + iframe.height() + ' style ' + iframe.attr('style'));

                    --numDisplayFrames;
                }
                if (numDisplayFrames == 0) {
                    $('#cmsFrameLoader').fadeOut(function() {
                        articleBox.tabs({
                            active: 0,
                            heightStyle: 'auto',
                            activate: function(event, ui) {
                                
                            },
                            create: function(event, ui) {
                                articleBox.height('auto');
                                
                                var forcedWidth = articleBox.width();
                                var forcedHeight = articleBox.height() - $('#cmsarticletabs').outerHeight();
                                
                                allDisplayFrames.width(forcedWidth);
                                allDisplayFrames.height(forcedHeight);

                                imagePoller(function() {
                                    resizeCB();
                                    scrollbarAdjust();
                                });
                            },
                            beforeActivate: function(event, ui) {
                                return Projects.projectWebPageTabHandler(event, ui, container);
                            }
                        });
                    });
                } else if (numDisplayFrames < 0) {
                    // can happen, moving dialogs around causes
                    // reloads, at least with FF.

                    var forcedWidth = articleBox.width();
                    var forcedHeight = articleBox.height() - $('#cmsarticletabs').outerHeight();
                    
                    allDisplayFrames.width(forcedWidth);
                    allDisplayFrames.height(forcedHeight);

                    if (false) {
                        // In principle, this should not be necessary
                        // as the height of the articleBox should not change.
                        imagePoller(function() {
                            resizeCB();
                            scrollbarAdjust();
                        });
                    }
                }
            };

            var changeArticleLoad = function(frame) {
                if (typeof frame != 'undefined') {
                    var self = frame;
                    var iframe = $(self);
                    var contents = iframe.contents();

                    // in order to be prepared for automatic reloads
                    // caused by resize or redraw events we have to
                    // update the src-uri of the iframe.
                    // alert('src: '+ self.contentWindow.location.href);

                    var wrapper = contents.find('#rex-wrapper');
                    var website = contents.find('#rex-website');
                    var rexForm = wrapper.find('form#REX_FORM');

                    // set to auto and fix later for correct size and
                    // scrollbars when necessary.
                    container.css({
                        height: 'auto',
                        width: 'auto'
                    });

                    // The below lines style the edit window.
                    contents.find('#rex-navi-logout').remove();
                    contents.find('#rex-navi-main').remove();
                    contents.find('#rex-redaxo-link').remove();
                    contents.find('#rex-footer').remove();
                    contents.find('#rex-header').remove();
                    contents.find('#rex-title').remove();
                    contents.find('#rex-a256-searchbar').remove();
                    contents.find('body').css({
                        margin: 0,
                        'background-image': 'none'
                    });
                    contents.find('#rex-output').css({margin: 0});
                    contents.find('#rex-navi-path a').removeAttr('href');

                    wrapper.css({
                        padding: 0,
                        margin: 0,
                        float: 'left'
                    });
                    website.css({
                        width: '100%', // wrapper.css('width'),
                        'background-image': 'none'
                    });
                    contents.find('textarea').css({ 'max-width': '720px' });

                    var scrollWidth = self.contentWindow.document.body.scrollWidth;
                    var scrollHeight = self.contentWindow.document.body.scrollHeight;
                    iframe.css({
                        width: scrollWidth + 'px',
                        height: scrollHeight + 'px'
                    });
                
                    var articleContainer = iframe.parent();
                    articleContainer.css({
                        height: 'unset',
                        width: 'unset'
                    });

                    var editArea = rexForm.find('textarea');
                    if (editArea.length > 0) {
                        CAFEVDB.textareaResize(editArea);
                    
                        rexForm.off('resize', 'textarea');
                        rexForm.on('resize', 'textarea', function() {
                            forceSize(iframe);
                            return false;
                        });
                    }
                    
                    rexForm.off('resize', '.mceEditor');
                    rexForm.on('resize', '.mceEditor', function() {
                        forceSize(iframe);
                        return false;
                    });

                    --numChangeFrames;
                }
                //alert('Change Frames: ' + numChangeFrames);
                if (numChangeFrames == 0) {
                    $('#cmsFrameLoader').fadeOut(function() {
                        container.find('#projectWebArticles').tabs({
                            active: 0,
                            create: function(event, ui) {
                                articleBox.height('auto');
                                imagePoller(function() {
                                    resizeCB();
                                    scrollbarAdjust();
                                });
                            },
                            activate: function(event, ui) {
                                var iframe = ui.newPanel.find('iframe');
                                if (iframe.length == 1) {
                                    forceSize(iframe);
                                }
                            },
                            beforeActivate: function(event, ui) {
                                return Projects.projectWebPageTabHandler(event, ui, container);
                            }
                        });
                    });
                } else if (numChangeFrames < 0) {
                    // < 0 happens when inside the frame a reload
                    // is triggered, after the initial loading of all frames.
                    imagePoller(function() {
                        resizeCB();
                        scrollbarAdjust();
                    });
                }
            };

            if (allDisplayFrames.length > 0) {
                if (displayFrames.length > 0) {
                    displayFrames.on('load', function(event) {
                        displayArticleLoad(this);
                    });
                } else {
                    displayArticleLoad();
                }
            } else {
                if (changeFrames.length > 0) {
                    changeFrames.on('load', function(event) {
                        changeArticleLoad(this);
                    });
                } else {
                    changeArticleLoad();
                }    
            }

            container.find('div.photo, #cafevdb_inline_image_wrapper').on('click', 'img', function(event) {
                event.preventDefault();
                CAFEVDB.Photo.popup(this);
                return false;
            });

            var toolbox = container.find('fieldset.projectToolbox');
            if (toolbox.length > 0) {
                // If any of the 3 dialogs is already open, move it to top.
                var popup;
                if ((popup = $('#events')).dialog('isOpen') === true) {
                    popup.dialog('moveToTop');
                    popup.dialog('option', 'position', {
                        my: 'left top',
                        at: 'left+20px top+60px',
                        of: window
                    });
                }
                if ((popup = $('#event')).dialog('isOpen') === true) {
                    popup.dialog('moveToTop');
                    popup.dialog('option', 'position', {
                        my: 'left top',
                        at: 'left+40px top+40px',
                        of: window
                    });
                }
                if ((popup = $('#dokuwiki_popup')).dialog('isOpen') === true) {
                    popup.dialog('moveToTop');
                    popup.dialog('option', 'position', {
                        my: 'center top',
                        at: 'center top+20px',
                        of: window
                    });                    
                }
                if ((popup = $('#project-instruments-dialog')).dialog('isOpen') === true) {
                    popup.dialog('moveToTop');
                    popup.dialog('option', 'position', {
                        my: 'right top',
                        at: 'right-20px top+30px',
                        of: window
                    });                    
                }

                var projectId = toolbox.data('projectId');
                var projectName = toolbox.data('projectName');
                var post = {
                    ProjectId: projectId,
                    Project: projectName
                };
                toolbox.off('click', '**'); // safeguard
                toolbox.on('click', 'button.project-wiki',
                           function(event) {
                               self = $(this);
                               post.wikiPage = self.data('wikiPage');
                               post.popupTitle = self.data('wikiTitle');
                               Projects.wikiPopup(post);
                               return false;
                           });
                toolbox.on('click', 'button.events',
                           function(event) {
                               Projects.eventsPopup(post);
                               return false;
                           });
                toolbox.on('click', 'button.project-instruments',
                           function(event) {
                               Projects.instrumentationNumbersPopup(selector, post);
                               return false;
                           });
            };
            return false;
        },
        context: CAFEVDB.Projects,
        parameters: []
    });
    CAFEVDB.Projects.actionMenu();
    var dpyClass = $(PHPMYEDIT.defaultSelector).find('form.pme input[name="DisplayClass"]');
    if (dpyClass.length > 0 && dpyClass.val() === 'Projects') {
        CAFEVDB.Projects.pmeFormInit(PHPMYEDIT.defaultSelector);
    }
});

// Local Variables: ***
// js-indent-level: 4 ***
// js3-indent-level: 4 ***
// js3-label-indent-offset: -2 ***
// End: ***
