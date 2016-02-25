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
     *
     * @param post Arguments object:
     * { ProjectName: 'NAME', ProjectId: XX }
     *
     * @param reopen If true, close any already dialog and re-open it
     * (the default). If false, only raise an existing dialog to top.
     */
    Projects.wikiPopup = function(post, reopen) {
        if (typeof reopen == 'undefined') {
            reopen = false;
        }
        var wikiDlg = $('#dokuwiki_popup');
        if (wikiDlg.dialog('isOpen') == true) {
            if (reopen === false) {
                wikiDlg.dialog('moveToTop');
                return;
            }
            wikiDlg.dialog('close').remove();
        }
        DWEmbed.wikiPopup({ wikiPage: post.wikiPage,
                            popupTitle: post.popupTitle,
                            modal: false },
                          function(dwDialog, dwDialogWidget) {
                              // open callback
                              dwDialog.dialog('option', 'appendTo', '#cafevdb-general');
                              // Custom shuffle button
                              CAFEVDB.dialogToBackButton(dwDialog);
                          },
                          function() {
                              // close callback
                              // Remove modal plane if appropriate
                              CAFEVDB.modalizer(false);
                          });
    };

    /**Generate a popup-dialog for the events-listing for the given
     * project.
     *
     * @param post Arguments object:
     * { ProjectName: 'NAME', ProjectId: XX }
     *
     * @param reopen If true, close any already dialog and re-open it
     * (the default). If false, only raise an existing dialog to top.
     */
    Projects.eventsPopup = function(post, reopen) {
        if (typeof reopen == 'undefined') {
            reopen = false;
        }
        var eventsDlg = $('#events');
        if (eventsDlg.dialog('isOpen') == true) {
            if (reopen === false) {
                eventsDlg.dialog('moveToTop');
                return;
            }
            eventsDlg.dialog('close').remove();
        }
        $.post(OC.filePath('cafevdb', 'ajax/events', 'events.php'),
               post, CAFEVDB.Events.UI.init, 'json');
    };

    /**Generate a popup-dialog for project related email.
     *
     * @param post Arguments object:
     * { ProjectName: 'NAME', ProjectId: XX }
     *
     * @param reopen If true, close any already dialog and re-open it
     * (the default). If false, only raise an existing dialog to top.
     */
    Projects.emailPopup = function(post, reopen) {
        if (typeof reopen == 'undefined') {
            reopen = false;
        }
        var emailDlg = $('#emailformdialog');
        if (emailDlg.dialog('isOpen') == true) {
            if (reopen === false) {
                emailDlg.dialog('moveToTop');
                return;
            }
            emailDlg.dialog('close').remove();
        }
        CAFEVDB.Email.emailFormPopup(post, false);
    };

    /**Generate a popup for the instrumentation numbers.
     *
     * @param containerSel The ambient element of the container
     * (i.e. the base page, or the div holding the dialog this one was
     * initiated from.
     *
     * @param past Arguments object:
     * { ProjectName: 'NAME', ProjectId: XX }
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
            ProjectId: post.ProjectId,
            ProjectName: post.ProjectName, // this is the name
            // Now special options for the dialog popup
            InitialViewOperation: true,
            InitialName: false, // 'PME_sys_operation',
            InitialValue: false, // 'View',
            ReloadName: false, // 'PME_sys_operation',
            ReloadValue: false, // 'View',
            PME_sys_operation: false, //'View',
            ModalDialog: false,
            modified: false
        };
        PHPMYEDIT.tableDialogOpen(tableOptions);
    };

    /**Generate a popup for the "project (over-)view.
     *
     * @param containerSel The ambient element of the container
     * (i.e. the base page, or the div holding the dialog this one was
     * initiated from.
     *
     * @param past Arguments object:
     * { ProjectName: 'NAME', ProjectId: XX }
     */
    Projects.projectViewPopup = function(containerSel, post)
    {
        // Prepate the data-array for PHPMYEDIT.tableDialogOpen(). The
        // instrumentation numbers are somewhat nasty and require too
        // many options.

        var tableOptions = {
            AmbientContainerSelector: containerSel,
            DialogHolderCSSId: 'project-overview',
            Template: 'projects',
            DisplayClass: 'Projects',
            // Now special options for the dialog popup
            InitialViewOperation: true,
            InitialName: 'PME_sys_operation',
            InitialValue: 'View',
            ReloadName: 'PME_sys_operation',
            ReloadValue: 'View',
            PME_sys_operation: 'View',
            PME_sys_rec: post.ProjectId,
            ModalDialog: true,
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
            ProjectName: projectName
        };

        var error = false;

        switch (selectedValue) {
            // project overview itself ...
          case 'project-infopage':
            Projects.projectViewPopup(containerSel, post);
            break;

            // The next 5 actions cannot reasonably loaded in a
            // popup-box.
          case 'detailed-instrumentation':
          case 'sepa-debit-mandates':
          case 'project-extra':
            post.Template = selectedValue;
            CAFEVDB.formSubmit('', $.param(post), 'post');
            break;
          case 'profit-and-loss':
          case 'project-files':
            var url  = OC.linkTo('files', 'index.php');
            var path = selected.data('projectFiles');
            CAFEVDB.formSubmit(url, $.param({dir: path}), 'get');
            break;

            // The following three can easily be opened in popup
            // dialogs which is more convenient as it does not destroy
            // the original view.
          case 'events':
            Projects.eventsPopup(post);
            break;
          case 'project-email':
            Projects.emailPopup(post);
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
                            +' "'+selectedValue+'"',
                             t('cafevdb', 'Unimplemented'));
            error = true;
            break;
        }

        // Cheating. In principle we mis-use this as a simple pull-down
        // menu, so let the text remain at its default value. Make sure to
        // also remove and re-attach the tool-tips, otherwise some of the
        // tips remain, because chosen() removes the element underneath.

        select.find('option').removeAttr('selected');
        $.fn.cafevTooltip.remove();

        select.trigger("chosen:updated");

        $('div.chosen-container').cafevTooltip({placement:'auto top'});
        $('li.active-result').cafevTooltip({placement:'auto right'});

        if (!error) {
            //alert('try to close snapper');
            CAFEVDB.snapperClose();
        }

        return false;
    };

    Projects.actionMenu = function(containerSel) {
        containerSel = PHPMYEDIT.selector(containerSel);
        var container = PHPMYEDIT.container(containerSel);
        var projectActions = container.find('select.project-actions');

        var chosenOptions = {
            placeholder_text_single:t('cafevdb', 'Select an Action'),
            inherit_select_classes:true,
            disable_search:true
        };

        // Install placeholder for proper sizing
        CAFEVDB.fixupNoChosenMenu(projectActions);
        var maxWidth = projectActions.maxOuterWidth(true);
        chosenOptions.width = maxWidth+'px';

        //alert('max: '+projectActions.maxOuterWidth(true));
        //alert('max: '+projectActions.maxWidth());
        projectActions.chosen(chosenOptions);
        if (CAFEVDB.chosenActive(projectActions)) {
            projectActions.find('option:first').html('');
            projectActions.trigger("chosen:updated");
        }

        projectActions.
            off('change').
            on('change', function(event) {
            event.preventDefault();
            return Projects.actions($(this), containerSel);
        });
        projectActions.
            off('chosen:showing_dropdown').
            on('chosen:showing_dropdown', function (event) {
            container.find('ul.chosen-results li.active-result').cafevTooltip({placement:'auto left'});
            return true;
        });
    };

    Projects.pmeFormInit = function(containerSel) {
        containerSel = PHPMYEDIT.selector(containerSel);
        var container = PHPMYEDIT.container(containerSel);
        var form = container.find('form[class^="pme-form"]');
        var submitSel = PHPMYEDIT.pmeClassSelectors('input',
                                                    ['save', 'apply', 'more']);

        if (form.find(submitSel).length > 0) {

            var nameSelector = 'input.projectname';
            var yearSelector = 'select[name="PME_data_Jahr"]';
            var attachSelector = 'select[name="PME_data_Art"]';

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
                               if (!CAFEVDB.ajaxErrorHandler(data, [
                                   'projectName', 'projectYear'
                                   ], function() {})) {
                                   if (name.val() == '') {
                                       name.val(oldProjectName);
                                   }
                                   if (year.val() == '') {
                                       year.val(oldProjectYear);
                                       year.trigger('chosen:updated');
                                   }
                               } else {
                                   var rqData = data.data;
                                   if (rqData.message != '') {
                                       OC.Notification.showTemporary(
                                           rqData.message,
                                           {
                                               'isHTML': true,
                                               'timeout': 30
                                           }
                                       );
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
                               }
                           });
                });
            }

            attach.off('change').change(function(event) {
                event.preventDefault();
                name.trigger('blur');
                return false;
            });

            year.off('change').change(function(event) {
                event.preventDefault();
                verifyYearName('year');
                return false;
            });

            name.off('blur').blur(function(event) {
                event.preventDefault();
                verifyYearName('name');
                return false;
            });

            // Attach a delegate handler to the form; this gives the
            // possibility to attach another delegate handler to the
            // container element.
            form.off('click', submitSel).
                on('click',
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
    ///   ProjectId: XX,
    ///   ArticleData: JSON }
    ///
    /// { Action: add,
    ///   ProjectId: XX,
    ///   ArticleId: XX,
    ///   ArticleData: JSON }
    ///
    /// { Action: link,
    ///   ProjectId: XX,
    ///   ArticleId: XX,
    ///   ArticleData: JSON }
    ///
    /// { Action: unlink,
    ///   ProjectId: XX,
    ///   ArticleId: XX,
    ///   ArticleData: JSON }
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
                    if (!CAFEVDB.ajaxErrorHandler(data, [])) {
                        // do nothing
                    } else if (data.data.message != '') {
                        OC.Notification.showHtml(data.data.message);
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
        var projectId;
        var articleId;
        var articleData;
        switch (tabId) {
          case 'cmsarticle-tab-newpage':
            event.stopImmediatePropagation();
            projectId = ui.newPanel.data('projectId');
            // just do it ...
            Projects.projectWebPageRequest({ Action: 'add',
                                             ArticleId: -1,
                                             ProjectId: projectId },
                                           container);
            return false;
          case 'cmsarticle-tab-unlinkpage':
            event.stopImmediatePropagation();
            articleId = ui.oldPanel.data('articleId');
            projectId = ui.oldPanel.data('projectId');
            articleData = ui.oldPanel.data('article');
            if (articleId == undefined) {
                // so what
                return false;
            }
            OC.dialogs.confirm(
                t('cafevdb', 'Really unlink the displayed event announcement?'),
                t('cafevdb', 'Unlink Web-Page with Id {ArticleId}?',
                  { ArticleId: articleId }),
                function(answer) {
                    if (!answer) {
                        return;
                    }
                    // do it ...
                    Projects.projectWebPageRequest({ Action: 'unlink',
                                                     ArticleId: articleId,
                                                     ArticleData: articleData,
                                                     ProjectId: projectId },
                                                   container);
                },
                true);
            return false;
          case 'cmsarticle-tab-deletepage':
            event.stopImmediatePropagation();
            articleId = ui.oldPanel.data('articleId');
            projectId = ui.oldPanel.data('projectId');
            articleData = ui.oldPanel.data('article');
            if (articleId == undefined) {
                // so what
                return false;
            }
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
                                                     ArticleData: articleData,
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
        callback: function(selector, parameters, resizeCB) {
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

            if (container.find('#project_flyer_upload').length > 0) {
                var idField = container.find('input[name="PME_data_Id"]');
                var recordId = -1;
                if (idField.length > 0) {
                    recordId = idField.val();
                }
                CAFEVDB.Photo.ready(recordId, 'Projekte', function() {
                    imagesReady = true;
                });
            } else {
                container.find('div.photo, span.photo').imagesLoaded(function() {
                    imagesReady = true;
                });
            }

            // Intercept app-navigation events here and redirect to the page
            // loader
            container.on('click', 'li.nav > a.nav', function(event) {
                var post = $(this).data('post');
                CAFEVDB.Page.loadPage(post);
                //alert('post: '+post);
                return false;
            });

            var articleBox = container.find('#projectWebArticles');

            var displayFrames = articleBox.find('iframe.cmsarticleframe.display, iframe.cmsarticleframe.add');
            var numDisplayFrames = displayFrames.length;

            var changeFrames = articleBox.find('iframe.cmsarticleframe.change, iframe.cmsarticleframe.change');
            var numChangeFrames = changeFrames.length;

            // allFrames also contains some div + all available iframes
            var allDisplayFrames = articleBox.find('.cmsarticleframe.display');
            var allChangeFrames = articleBox.find('.cmsarticleframe.change');
            var allContainers = articleBox.find('.cmsarticlecontainer');

            var articleSelect = container.find('#cmsarticleselect');
            articleSelect.chosen({width: 'auto',
                                  disable_search_threshold: 10,
                                  no_result_text: t('cafevdb','No values match')});
            articleSelect.on('chosen:showing_dropdown', function() {
                articleBox.css('overflow', 'visible');
                return true;
            });
            articleSelect.on('chosen:hiding_dropdown', function() {
                articleBox.css('overflow', 'hidden');
                return true;
            });

            articleSelect.on('change', function(event) {
                event.preventDefault();

                var projectId = articleSelect.data('projectId');
                var selected  = articleSelect.find('option:selected');
                var articleId = selected.val();
                var articleData = selected.data('article');
                // just do it ...
                Projects.projectWebPageRequest({ Action: 'link',
                                                 ArticleId: articleId,
                                                 ProjectId: projectId,
                                                 ArticleData: articleData },
                                               container);

                return false;
            });

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
                    var itemText = contents.find('div.item-text');
                    itemText.css({ 'width': '700px',
                                   //'min-width': '600px',
                                   'margin-left': '10px',
                                   'left': 'unset',
                                   'position': 'unset' });
                    itemText.children(':not(div.marginalie)').css('margin', '0px 10px 1em 300px');

                    var scrollWidth = self.contentWindow.document.body.scrollWidth;
                    var scrollHeight = self.contentWindow.document.body.scrollHeight;
                    iframe.css({
                        width: scrollWidth + 'px',
                        height: scrollHeight + 'px'
                    });

                    //alert('height: ' + iframe.height() + ' style ' + iframe.attr('style'));

                    --numDisplayFrames;
                }

                //alert('Display Frames: ' + numDisplayFrames);
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

                        rexForm.
                            off('resize', 'textarea').
                            on('resize', 'textarea', function() {
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
                                    //$('#cmsarticleselect_chosen').width('80%');
                                });
                            },
                            activate: function(event, ui) {
                                var iframe = ui.newPanel.find('iframe');
                                if (iframe.length == 1) {
                                    forceSize(iframe);
                                } else {
                                    resizeCB();
                                    scrollbarAdjust();
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
                //alert('all dpy frames: '+allDisplayFrames.length);
                if (displayFrames.length > 0) {
                    //alert('dpy frames: '+displayFrames.length);
                    displayFrames.on('load', function(event) {
                        displayArticleLoad(this);
                        //alert('Load');
                    });
                } else {
                    displayArticleLoad();
                }
            } else if (allChangeFrames.length > 0) {
                if (changeFrames.length > 0) {
                    changeFrames.on('load', function(event) {
                        changeArticleLoad(this);
                    });
                } else {
                    changeArticleLoad();
                }
            } else {
                // Just execute the resize callback:
                imagePoller(function() {
                    resizeCB();
                    scrollbarAdjust();
                });
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
                if ((popup = $('#emailformdialog')).dialog('isOpen') === true) {
                    popup.dialog('moveToTop');
                    popup.dialog('option', 'position', {
                        my: 'left top',
                        at: 'left+60px top+60px',
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
                    ProjectName: projectName
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
                toolbox.on('click', 'button.project-email',
                           function(event) {
                               Projects.emailPopup(post);
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

    CAFEVDB.addReadyCallback(function() {
        var container = PHPMYEDIT.container();
        if (!container.hasClass('projects')) {
            return;
        }
        CAFEVDB.Projects.actionMenu();
        CAFEVDB.Projects.pmeFormInit(PHPMYEDIT.defaultSelector);

        PHPMYEDIT.inner(container).
            off('click', 'input.show-disabled').
            on('click', 'input.show-disabled', function(event) {
            event.preventDefault();
            console.log('hide/show disabled projects');
            return PHPMYEDIT.pseudoSubmit($(this.form), $(this), container, false);
        });
    });
});

// Local Variables: ***
// js-indent-level: 4 ***
// js3-indent-level: 4 ***
// js3-label-indent-offset: -2 ***
// End: ***
