/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName, $ } from './globals.js';
import generateUrl from './generate-url.js';
import textareaResize from './textarea-resize.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Page from './page.js';
import * as Dialogs from './dialogs.js';
import * as Photo from './inlineimage.js';
import * as Notification from './notification.js';
import * as Events from './events.js';
import * as Email from './email.js';
import { data as pmeData, sys as pmeSys } from './pme-selectors.js';
import * as PHPMyEdit from './pme.js';
import * as ncRouter from '@nextcloud/router';
import * as DialogUtils from './dialog-utils.js';
import { wikiPopup as dokuWikiPopup } from 'dokuwikiembedded/src/doku-wiki-popup';

require('projects.css');

// /**
//  * Strip any digit from the end of name and attach the four digit
//  * year to the end of name.
//  *
//  * @param {String} name TBD.
//  *
//  * @param {int} year TBD.
//  *
//  * @returns {String}
//  */
// const attachYear = function(name, year) {
//   name = name.replace(/\d+$/, '');
//   return name + year;
// };

// /**
//  *Check whether exactly four digits are attached to the end of
//  * name and return those as a four digit year. If not exactly four
//  * digits are attached to the end of name return false.
//  *
//  * @param {String} name TBD.
//  *
//  * @returns {bool}
//  */
// const extractYear = function(name) {
//   const year = name.match(/[^\d](\d{4})$/);
//   return year !== null ? year[1] : false;
// };

/**
 * Generate a popup-dialog with a wiki-page. Not to much project
 * related, rather general. Page and page-title are assumed to be
 * attached to the "post"-object
 *
 * @param {Object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 *
 * @param {bool} reopen If true, close any already dialog and re-open it
 * (the default). If false, only raise an existing dialog to top.
 */
const wikiPopup = function(post, reopen) {
  if (typeof reopen === 'undefined') {
    reopen = false;
  }
  const wikiDlg = $('#dokuwiki_popup');
  if (wikiDlg.dialog('isOpen') === true) {
    if (reopen === false) {
      wikiDlg.dialog('moveToTop');
      return;
    }
    wikiDlg.dialog('close').remove();
  }
  dokuWikiPopup(
    {
      wikiPage: post.wikiPage,
      popupTitle: post.popupTitle,
      cssClass: appName,
      modal: false,
    },
    function(dwDialog, dwDialogWidget) {
      // open callback
      dwDialog.dialog('option', 'appendTo', '#cafevdb-general');
      // Custom shuffle button
      DialogUtils.toBackButton(dwDialog);
    },
    function() {
      // close callback
      // Remove modal plane if appropriate
      CAFEVDB.modalizer(false);
    });
};

/**
 * Generate a popup-dialog for the events-listing for the given
 * project.
 *
 * @param {Object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 *
 * @param {bool} reopen If true, close any already dialog and re-open it
 * (the default). If false, only raise an existing dialog to top.
 */
const eventsPopup = function(post, reopen) {
  console.info('POST', post);
  if (typeof reopen === 'undefined') {
    reopen = false;
  }
  const eventsDlg = $('#events');
  if (eventsDlg.dialog('isOpen') === true) {
    if (reopen === false) {
      eventsDlg.dialog('moveToTop');
      return;
    }
    eventsDlg.dialog('close').remove();
  }
  $.post(
    generateUrl('projects/events/dialog'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
    })
    .done(Events.init);
};

/**
 * Generate a popup-dialog for project related email.
 *
 * @param {Object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 *
 * @param {bool} reopen If true, close any already dialog and re-open it
 * (the default). If false, only raise an existing dialog to top.
 */
const emailPopup = function(post, reopen) {
  if (typeof reopen === 'undefined') {
    reopen = false;
  }
  const emailDlg = $('#emailformdialog');
  if (emailDlg.dialog('isOpen') === true) {
    if (reopen === false) {
      emailDlg.dialog('moveToTop');
      return;
    }
    emailDlg.dialog('close').remove();
  }
  Email.emailFormPopup(post, false);
};

/**
 * Generate a popup for the instrumentation numbers.
 *
 * @param {String} containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param {Object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 */
const instrumentationNumbersPopup = function(containerSel, post) {
  // Prepate the data-array for PHPMyEdit.tableDialogOpen(). The
  // instrumentation numbers are somewhat nasty and require too
  // many options.

  const template = 'project-instrumentation-numbers';
  const tableOptions = {
    AmbientContainerSelector: containerSel,
    DialogHolderCSSId: template + '-dialog',
    template,
    templateRenderer: Page.templateRenderer(template),
    Table: 'BesetzungsZahlen',
    Transpose: 'transposed',
    InhibitTranspose: 'true',
    projectId: post.projectId,
    projectName: post.projectName,
    // Now special options for the dialog popup
    InitialViewOperation: true,
    InitialName: false, // 'PME_sys_operation',
    InitialValue: false, // 'View',
    ReloadName: false, // 'PME_sys_operation',
    ReloadValue: false, // 'View',
    [pmeSys('operation')]: false, // 'View',
    ModalDialog: false,
    modified: false,
  };
  PHPMyEdit.tableDialogOpen(tableOptions);
};

/**
 * Generate a popup for the participant-fields setup
 *
 * @param {String} containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param {Object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 */
const extraFieldsPopup = function(containerSel, post) {
  // Prepate the data-array for PHPMyEdit.tableDialogOpen(). The
  // instrumentation numbers are somewhat nasty and require too
  // many options.

  const template = 'project-participant-fields';
  const tableOptions = {
    AmbientContainerSelector: containerSel,
    DialogHolderCSSId: template + '-dialog',
    template,
    templateRenderer: Page.templateRenderer(template),
    Table: 'ProjectParticipantFields',
    projectId: post.projectId,
    projectName: post.projectName,
    // Now special options for the dialog popup
    InitialViewOperation: true,
    InitialName: false, // 'PME_sys_operation',
    InitialValue: false, // 'View',
    ReloadName: false, // 'PME_sys_operation',
    ReloadValue: false, // 'View',
    [pmeSys('operation')]: false, // 'View',
    ModalDialog: false,
    modified: false,
  };
  PHPMyEdit.tableDialogOpen(tableOptions);
};

/**
 * Generate a popup for the "project (over-)view.
 *
 * @param {String} containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param {Object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 */
const projectViewPopup = function(containerSel, post) {
  // Prepate the data-array for PHPMyEdit.tableDialogOpen(). The
  // instrumentation numbers are somewhat nasty and require too
  // many options.

  const template = 'projects';
  const tableOptions = {
    AmbientContainerSelector: containerSel,
    DialogHolderCSSId: 'project-overview',
    template,
    templateRenderer: Page.templateRenderer(template),
    // Now special options for the dialog popup
    InitialViewOperation: true,
    InitialName: pmeSys('operation'),
    InitialValue: 'View',
    ReloadName: pmeSys('operation'),
    ReloadValue: 'View',
    [pmeSys('operation')]: 'View',
    [pmeSys('rec')]: post.projectId,
    ModalDialog: true,
    modified: false,
  };
  PHPMyEdit.tableDialogOpen(tableOptions);
};

/**
 * Parse the user-selection from the project-actions menu.
 *
 * Project-id and -name are contained in data-fields of the
 * select, other potentially needed data is contained in
 * data-fields in the options.
 *
 * @param {jQuery} select TBD.
 *
 * @param {String|jQuery} containerSel TBD.
 *
 * @returns {bool}
 */
const actions = function(select, containerSel) {

  // determine the export format
  const selected = select.find('option:selected');
  const selectedValue = selected.val();

  const projectId = select.data('projectId');
  const projectName = select.data('projectName');
  const post = {
    projectId,
    projectName,
  };

  let error = false;

  switch (selectedValue) {
  case 'project-infopage': // project overview itself ...
    projectViewPopup(containerSel, post);
    break;

    // The next 5 actions cannot reasonably loaded in a
    // popup-box.
  case 'project-participants':
  case 'sepa-debit-mandates':
  case 'project-participant-fields':
    // @TODO: this really could be a popup
    post.template = selectedValue;
    CAFEVDB.formSubmit('', $.param(post), 'post');
    break;
  case 'profit-and-loss':
  case 'project-files': {
    const url = ncRouter.linkTo('files', 'index.php');
    const path = selected.data('projectFiles');
    CAFEVDB.formSubmit(url, $.param({ dir: path }), 'get');
    break;
  }
  // The following three can easily be opened in popup
  // dialogs which is more convenient as it does not destroy
  // the original view.
  case 'events':
    eventsPopup(post);
    break;
  case 'project-email':
    emailPopup(post);
    break;
  case 'project-instrumentation-numbers':
    instrumentationNumbersPopup(containerSel, post);
    break;
  case 'project-wiki':
    post.wikiPage = selected.data('wikiPage');
    post.popupTitle = selected.data('wikiTitle');
    wikiPopup(post);
    break;
  default:
    OC.dialogs.alert(
      t(appName, 'Unknown operation:')
        + ' "' + selectedValue + '"',
      t(appName, 'Unimplemented'));
    error = true;
    break;
  }

  // Cheating. In principle we mis-use this as a simple pull-down
  // menu, so let the text remain at its default value. Make sure to
  // also remove and re-attach the tool-tips, otherwise some of the
  // tips remain, because chosen() removes the element underneath.

  console.info('remove selected');
  select.find('option').prop('selected', false);

  console.info('update chosen', select.find('option:selected'));
  select.trigger('chosen:updated');

  $('div.chosen-container').cafevTooltip({ placement: 'auto top' });

  if (!globalState.toolTipsEnabled) {
    $.fn.cafevTooltip.disable();
  }
  $.fn.cafevTooltip.remove();

  if (!error) {
    // alert('try to close snapper');
    CAFEVDB.snapperClose();
  }

  return false;
};

const actionMenu = function(containerSel) {
  containerSel = PHPMyEdit.selector(containerSel);
  const container = PHPMyEdit.container(containerSel);
  const projectActions = container.find('select.project-actions');

  const chosenOptions = {
    placeholder_text_single: t(appName, 'Select an Action'),
    inherit_select_classes: true,
    disable_search: true,
  };

  // Install placeholder for proper sizing
  CAFEVDB.fixupNoChosenMenu(projectActions);
  const maxWidth = projectActions.maxOuterWidth(true);
  chosenOptions.width = maxWidth + 'px';

  // alert('max: '+projectActions.maxOuterWidth(true));
  // alert('max: '+projectActions.maxWidth());
  projectActions.chosen(chosenOptions);
  if (CAFEVDB.chosenActive(projectActions)) {
    projectActions.find('option:first').html('');
    projectActions.trigger('chosen:updated');
  }

  projectActions
    .off('change')
    .on('change', function(event) {
      event.preventDefault();
      return actions($(this), containerSel);
    });
};

const pmeFormInit = function(containerSel) {
  containerSel = PHPMyEdit.selector(containerSel);
  const container = PHPMyEdit.container(containerSel);
  const form = container.find('form[class^="pme-form"]');
  const submitSel = PHPMyEdit.classSelectors(
    'input',
    ['save', 'apply', 'more']);

  if (form.find(submitSel).length > 0) {

    const nameSelector = 'input.projectname';
    const yearSelector = 'select[name="' + pmeData('year') + '"]';
    const typeSelector = 'select[name="' + pmeData('temporal_type') + '"]';

    const name = container.find(nameSelector);
    const year = container.find(yearSelector);
    const projectType = container.find(typeSelector);

    let oldProjectYear = $(form).find(yearSelector + ' :selected').text();
    let oldprojectName = name.val();

    /**
     * Verify the user submitted name and year settings,
     * depending on whether the user has activated the name or
     * year control, or has clicked the submit button.
     *
     * @param {Object} postAddOn TBD.
     *
     * @param {Object} button TBD.
     */
    const verifyYearName = function(postAddOn, button) {
      /* Forward the request to the server via Ajax
       * technologies.
       */
      let post = form.serialize();
      post += '&control=' + postAddOn;

      Notification.hide(function() {
        const cleanup = function() {
          if (name.val() === '') {
            name.val(oldprojectName);
          }
          if (year.val() === '') {
            year.val(oldProjectYear);
            year.trigger('chosen:updated');
          }
        };
        $.post(generateUrl('validate/projects/name'), post)
          .fail(function(xhr, status, errorThrown) {
            Ajax.handleError(xhr, status, errorThrown);
            cleanup();
          })
          .done(function(rqData) {
            if (!Ajax.validateResponse(rqData, [
              'projectName', 'projectYear',
            ])) {
              cleanup();
            }
            if (rqData.message !== '') {
              Notification.showTemporary(
                rqData.message, { isHTML: true, timeout: 300 }
              );
            }
            name.val(rqData.projectName);
            year.val(rqData.projectYear);
            year.trigger('chosen:updated');
            oldProjectYear = rqData.projectYear;
            oldprojectName = rqData.projectName;
            if (postAddOn === 'submit') {
              if (typeof button !== 'undefined') {
                $(form).off('click', submitSel);
                button.trigger('click');
              } else {
                form.submit();
              }
            }
          });
      });
    };

    projectType.off('change').on('change', function(event) {
      if (name.val() !== '') {
        name.trigger('blur');
      }
      return false;
    });

    year.off('change').on('change', function(event) {
      verifyYearName('year');
      return false;
    });

    name.off('blur').on('blur', function(event) {
      verifyYearName('name');
      return false;
    });

    // Attach a delegate handler to the form; this gives the
    // possibility to attach another delegate handler to the
    // container element.
    form
      .off('click', submitSel)
      .on('click', submitSel, function(event) {
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

/**
 * Place an ajax call for public web-page management, create,
 * delete, attach articles.
 *
 *  @param {Object} post The data array with action and information.
 *
 *  Supported post packages:
 *
 *  { action: delete,
 *    articleId: XX,
 *    projectId: XX,
 *    articleData: JSON }
 *
 *  { action: add,
 *    projectId: XX,
 *    articleId: XX,
 *    articleData: JSON }
 *
 *  { action: link,
 *    projectId: XX,
 *    articleId: XX,
 *    articleData: JSON }
 *
 *  { action: unlink,
 *    projectId: XX,
 *    articleId: XX,
 *    articleData: JSON }
 *
 *  For Action 'add' a negative ArticleId triggers the geneation
 *  of a new article, otherwise it is the id of an existing
 *  event-announcement to attach to this project.
 *
 * @param {jQuery} container TBD.
 */
const projectWebPageRequest = function(post, container) {

  $.post(generateUrl('projects/webpages/' + post.action), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
    })
    .done(function(data) {
      const form = container.find('table.pme-navigation');
      const submit = form.find('input.pme-more, input.pme-reload, input.pme-apply');
      submit.first().trigger('click', {
        postOpen() {
          Notification.messages(data.message);
        },
      });
    });
};

/**
 * Dispatch a UI-event and potentially add or delete a public
 * web-page. This is called as a beforeActive tab-event handler.
 *
 * @param {Object} event The event provided by jQuery tab widget.
 *
 * @param {Object} ui An object with old and new panel und tabs
 *
 * @param {Object} container The div which contains the current dialog.
 *
 * @returns {bool}
 */
const projectWebPageTabHandler = function(event, ui, container) {
  const tabId = ui.newTab.attr('id');
  // alert('id' + tabId);
  let projectId;
  let articleId;
  let articleData;
  switch (tabId) {
  case 'cmsarticle-tab-newpage':
    event.stopImmediatePropagation();
    projectId = ui.newPanel.data('projectId');
    // just do it ...
    projectWebPageRequest({
      action: 'add',
      articleId: -1,
      projectId,
    }, container);
    return false;
  case 'cmsarticle-tab-unlinkpage':
    event.stopImmediatePropagation();
    articleId = ui.oldPanel.data('articleId');
    projectId = ui.oldPanel.data('projectId');
    articleData = ui.oldPanel.data('article');
    if (articleId === undefined) {
      // so what
      return false;
    }
    Dialogs.confirm(
      t(appName, 'Really unlink the displayed event announcement?'),
      t(appName, 'Unlink Web-Page with Id {ArticleId}?', { articleId }),
      function(answer) {
        if (!answer) {
          return;
        }
        // do it ...
        projectWebPageRequest({
          action: 'unlink',
          articleId,
          articleData,
          projectId,
        }, container);
      },
      true);
    return false;
  case 'cmsarticle-tab-deletepage':
    event.stopImmediatePropagation();
    articleId = ui.oldPanel.data('articleId');
    projectId = ui.oldPanel.data('projectId');
    articleData = ui.oldPanel.data('article');
    if (articleId === undefined) {
      // so what
      return false;
    }
    Dialogs.confirm(
      t(appName, 'Really delete the displayed event announcement?'),
      t(appName, 'Delete Web-Page with Id {ArticleId}?', { articleId }),
      function(answer) {
        if (!answer) {
          return;
        }
        // do it ...
        projectWebPageRequest({
          action: 'delete',
          articleId,
          articleData,
          projectId,
        }, container);
      },
      true);
    return false;
  default:
    return true;
  }
};

const documentReady = function() {

  PHPMyEdit.addTableLoadCallback('projects', {
    callback(selector, parameters, resizeCB) {
      const container = PHPMyEdit.container(selector);
      const containerNode = container[0];
      actionMenu(selector);
      pmeFormInit(selector);
      let imagesReady = false;
      const imagePoller = function(callback) {
        if (!imagesReady) {
          const poller = setInterval(function() {
            if (imagesReady) {
              clearInterval(poller);
              callback();
            }
          }, 100);
        } else {
          callback();
        }
      };

      const posterContainer = container.find('.project-poster');
      if (posterContainer.length > 0) {
        let readyCountDown = posterContainer.length;
        posterContainer.each(function(index) {
          Photo.ready($(this), function() {
            imagesReady = --readyCountDown <= 0;
          });
        });
      } else {
        container.find('div.photo, span.photo').imagesLoaded(function() {
          imagesReady = true;
        });
      }

      // Intercept app-navigation events here and redirect to the page
      // loader
      container.on('click', 'li.nav > a.nav', function(event) {
        const post = $(this).data('post');
        Page.loadPage(post);
        // alert('post: '+post);
        return false;
      });

      const articleBox = container.find('#projectWebArticles');

      const displayFrames = articleBox.find('iframe.cmsarticleframe.display, iframe.cmsarticleframe.add');
      let numDisplayFrames = displayFrames.length; // count-down variable

      const changeFrames = articleBox.find('iframe.cmsarticleframe.change, iframe.cmsarticleframe.change');
      let numChangeFrames = changeFrames.length;

      // allFrames also contains some div + all available iframes
      const allDisplayFrames = articleBox.find('.cmsarticleframe.display');
      const allChangeFrames = articleBox.find('.cmsarticleframe.change');
      // const allContainers = articleBox.find('.cmsarticlecontainer');

      const articleSelect = container.find('#cmsarticleselect');
      articleSelect.chosen({
        width: 'auto',
        disable_search_threshold: 10,
        no_results_text: t(appName, 'No values match'),
      });
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

        const projectId = articleSelect.data('projectId');
        const selected = articleSelect.find('option:selected');
        const articleId = selected.val();
        const articleData = selected.data('article');
        // just do it ...
        projectWebPageRequest({
          action: 'link',
          articleId,
          projectId,
          articleData,
        }, container);

        return false;
      });

      const scrollbarAdjust = function() {
        const scrollBarWidth = containerNode.offsetWidth - containerNode.clientWidth;
        articleBox.css('margin-right', scrollBarWidth + 'px');
      };

      const forceSize = function(iframe) {
        const domFrame = iframe[0];
        const scrollHeight = domFrame.contentWindow.document.body.scrollHeight;
        const scrollWidth = domFrame.contentWindow.document.body.scrollWidth;
        iframe.css({
          width: scrollWidth + 'px',
          height: scrollHeight + 'px',
          overflow: 'hidden',
        });
        imagePoller(function() {
          resizeCB();
          scrollbarAdjust();
        });
      };

      const displayArticleLoad = function(frame) {
        if (typeof frame !== 'undefined') {
          const self = frame;
          const iframe = $(self);
          const contents = iframe.contents();

          // For the pretty-print version. We remove everything
          // except the article itself
          contents.find('div#header').remove();
          contents.find('div#footer').remove();
          contents.find('div.navi').remove();
          contents.find('body').css({
            'min-width': 'unset',
            width: 'unset',
          });
          contents.find('#content').css({
            width: 'auto',
            height: '100%',
          });
          const itemText = contents.find('div.item-text');
          itemText.css({
            width: '700px',
            // 'min-width': '600px',
            'margin-left': '10px',
            left: 'unset',
            position: 'unset',
          });
          itemText.children(':not(div.marginalie)').css('margin', '0px 10px 1em 300px');

          const scrollWidth = self.contentWindow.document.body.scrollWidth;
          const scrollHeight = self.contentWindow.document.body.scrollHeight;
          iframe.css({
            width: scrollWidth + 'px',
            height: scrollHeight + 'px',
          });

          // alert('height: ' + iframe.height() + ' style ' + iframe.attr('style'));

          --numDisplayFrames;
        }

        // alert('Display Frames: ' + numDisplayFrames);
        if (numDisplayFrames === 0) {
          $('#cmsFrameLoader').fadeOut(function() {
            articleBox.tabs({
              active: 0,
              heightStyle: 'auto',
              activate(event, ui) {
                // nothing
              },
              create(event, ui) {
                articleBox.height('auto');

                const forcedWidth = articleBox.width();
                const forcedHeight = articleBox.height() - $('#cmsarticletabs').outerHeight();

                allDisplayFrames.width(forcedWidth);
                allDisplayFrames.height(forcedHeight);

                imagePoller(function() {
                  resizeCB();
                  scrollbarAdjust();
                });
              },
              beforeActivate(event, ui) {
                return projectWebPageTabHandler(event, ui, container);
              },
            });
          });
        } else if (numDisplayFrames < 0) {
          // can happen, moving dialogs around causes
          // reloads, at least with FF.

          const forcedWidth = articleBox.width();
          const forcedHeight = articleBox.height() - $('#cmsarticletabs').outerHeight();

          allDisplayFrames.width(forcedWidth);
          allDisplayFrames.height(forcedHeight);

          // if (false) {
          //   // In principle, this should not be necessary
          //   // as the height of the articleBox should not change.
          //   imagePoller(function() {
          //     resizeCB();
          //     scrollbarAdjust();
          //   });
          // }
        }
      };

      const changeArticleLoad = function(frame) {
        if (typeof frame !== 'undefined') {
          const self = frame;
          const iframe = $(self);
          const contents = iframe.contents();

          // in order to be prepared for automatic reloads
          // caused by resize or redraw events we have to
          // update the src-uri of the iframe.
          // alert('src: '+ self.contentWindow.location.href);

          const wrapper = contents.find('#rex-wrapper');
          const website = contents.find('#rex-website');
          const rexForm = wrapper.find('form#REX_FORM');

          // set to auto and fix later for correct size and
          // scrollbars when necessary.
          container.css({
            height: 'auto',
            width: 'auto',
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
            'background-image': 'none',
          });
          contents.find('#rex-output').css({ margin: 0 });
          contents.find('#rex-navi-path a').removeAttr('href');

          wrapper.css({
            padding: 0,
            margin: 0,
            float: 'left',
          });
          website.css({
            width: '100%', // wrapper.css('width'),
            'background-image': 'none',
          });
          contents.find('textarea').css({ 'max-width': '720px' });

          const scrollWidth = self.contentWindow.document.body.scrollWidth;
          const scrollHeight = self.contentWindow.document.body.scrollHeight;
          iframe.css({
            width: scrollWidth + 'px',
            height: scrollHeight + 'px',
          });

          const articleContainer = iframe.parent();
          articleContainer.css({
            height: 'unset',
            width: 'unset',
          });

          const editArea = rexForm.find('textarea');
          if (editArea.length > 0) {
            textareaResize(editArea);

            rexForm
              .off('resize', 'textarea')
              .on('resize', 'textarea', function() {
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
        // alert('Change Frames: ' + numChangeFrames);
        if (numChangeFrames === 0) {
          $('#cmsFrameLoader').fadeOut(function() {
            container.find('#projectWebArticles').tabs({
              active: 0,
              create(event, ui) {
                articleBox.height('auto');
                imagePoller(function() {
                  resizeCB();
                  scrollbarAdjust();
                  // $('#cmsarticleselect_chosen').width('80%');
                });
              },
              activate(event, ui) {
                const iframe = ui.newPanel.find('iframe');
                if (iframe.length === 1) {
                  forceSize(iframe);
                } else {
                  resizeCB();
                  scrollbarAdjust();
                }
              },
              beforeActivate(event, ui) {
                return projectWebPageTabHandler(event, ui, container);
              },
            });
            $('#projectWebArticles').css({ opacity: 1.0 });
          });
        } else if (numChangeFrames < 0) {
          // < 0 happens when inside the frame a reload
          // is triggered, after the initial loading of all frames.
          imagePoller(function() {
            resizeCB();
            scrollbarAdjust();
            $('#projectWebArticles').css({ opacity: 1.0 });
          });
        }
      };

      if (allDisplayFrames.length > 0) {
        // alert('all dpy frames: '+allDisplayFrames.length);
        if (displayFrames.length > 0) {
          // alert('dpy frames: '+displayFrames.length);
          displayFrames.on('load', function(event) {
            displayArticleLoad(this);
            // alert('Load');
          });
        } else {
          displayArticleLoad();
        }
      } else if (allChangeFrames.length > 0) {
        if (changeFrames.length > 0) {
          $('#projectWebArticles').css({ opacity: 0.0 });
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

      container.find('div.photo, .cafevdb_inline_image_wrapper').on('click', 'img', function(event) {
        event.preventDefault();
        Photo.popup(this);
        return false;
      });

      const toolbox = container.find('fieldset.projectToolbox');
      if (toolbox.length > 0) {
        // If any of the 3 dialogs is already open, move it to top.
        let popup;
        if ((popup = $('#events')).dialog('isOpen') === true) {
          popup.dialog('moveToTop');
          popup.dialog('option', 'position', {
            my: 'left top',
            at: 'left+20px top+60px',
            of: window,
          });
        }
        if ((popup = $('#event')).dialog('isOpen') === true) {
          popup.dialog('moveToTop');
          popup.dialog('option', 'position', {
            my: 'left top',
            at: 'left+40px top+40px',
            of: window,
          });
        }
        if ((popup = $('#emailformdialog')).dialog('isOpen') === true) {
          popup.dialog('moveToTop');
          popup.dialog('option', 'position', {
            my: 'left top',
            at: 'left+60px top+60px',
            of: window,
          });
        }
        if ((popup = $('#dokuwiki_popup')).dialog('isOpen') === true) {
          popup.dialog('moveToTop');
          popup.dialog('option', 'position', {
            my: 'center top',
            at: 'center top+20px',
            of: window,
          });
        }
        if ((popup = $('#project-instrumentation-numbers-dialog')).dialog('isOpen') === true) {
          popup.dialog('moveToTop');
          popup.dialog('option', 'position', {
            my: 'right top',
            at: 'right-20px top+30px',
            of: window,
          });
        }

        const projectId = toolbox.data('projectId');
        const projectName = toolbox.data('projectName');
        const post = {
          projectId,
          projectName,
        };
        toolbox.off('click', '**'); // safeguard
        toolbox.on(
          'click', 'button.project-wiki',
          function(event) {
            const self = $(this);
            post.wikiPage = self.data('wikiPage');
            post.popupTitle = self.data('wikiTitle');
            wikiPopup(post);
            return false;
          });
        toolbox.on(
          'click', 'button.events',
          function(event) {
            eventsPopup(post);
            return false;
          });
        toolbox.on(
          'click', 'button.project-email',
          function(event) {
            emailPopup(post);
            return false;
          });
        toolbox.on(
          'click', 'button.project-instrumentation-numbers',
          function(event) {
            instrumentationNumbersPopup(selector, post);
            return false;
          });
      }

      const linkPopups = {
        projectextra: extraFieldsPopup,
        projectinstrumentation: instrumentationNumbersPopup,
      };

      for (const [css, popup] of Object.entries(linkPopups)) {
        const element = container.find('td.pme-value.' + css + ' a.nav');
        element
          .off('click')
          .on('click', function(event) {
            const data = $(this).data('json');
            console.info('DATA', data, selector);
            popup(selector, data);
            return false;
          });
      }

      return false; // table load callback
    },
    context: globalState,
    parameters: [],
  });

  CAFEVDB.addReadyCallback(function() {
    const container = PHPMyEdit.container();
    if (!container.hasClass('projects')) {
      return;
    }
    actionMenu();
    pmeFormInit(PHPMyEdit.defaultSelector);
  });
};

export {
  documentReady,
  eventsPopup,
  projectViewPopup,
  instrumentationNumbersPopup,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
