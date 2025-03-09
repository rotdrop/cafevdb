/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { globalState, appName, $ } from './globals.js';
import generateAppUrl from './generate-url.js';
import textareaResize from './textarea-resize.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Page from './page.js';
import { templateRenderer } from './template-renderer.js';
import pageBusyIcon from './busy-icon.js';
import * as Dialogs from './dialogs.js';
import * as Notification from './notification.js';
import * as Email from './email.js';
import {
  data as pmeData,
  sys as pmeSys,
  classSelector as pmeClassSelector,
  formSelector as pmeFormSelector,
  inputSelector as pmeInputSelector,
  token as pmeToken,
  idSelector as pmeIdSelector,
} from './pme-selectors.js';
import * as PHPMyEdit from './pme.js';
import * as SelectUtils from './select-utils.js';
import setBusyIndicators from './busy-indicators.js';
import iFrameResize from './iframe-resize.js';
import {
  emit as asyncEmit,
  subscribe as asyncSubscribe,
  getEmitResult,
} from '../services/async-event-bus.ts';
import * as BusEvents from '../event-bus-events.ts';
import { PROJECT_ACTIONS_MENU } from '../mountable-component-names.ts';

// eslint-disable-next-line no-unused-vars
// import iFrameResize from 'iframe-resizer';
// eslint-disable-next-line
// import iFrameContentScript from '!!raw-loader!iframe-resizer/js/iframeResizer.contentWindow.js';

require('projects.scss');

asyncSubscribe(BusEvents.PROJECT_POPUP, async (event) => {
  console.info('EVENT', event);
  asyncEmit(BusEvents.PUSH_BUSY_STATE);
  await projectViewPopup(PHPMyEdit.selector(), event);
  asyncEmit(BusEvents.POP_BUSY_STATE);
});

asyncSubscribe(BusEvents.PROJECT_INSTRUMENTATION_NUMBERS_POPUP, async (event) => {
  console.info('EVENT', event);
  asyncEmit(BusEvents.PUSH_BUSY_STATE);
  await instrumentationNumbersPopup(PHPMyEdit.selector(), event);
  asyncEmit(BusEvents.POP_BUSY_STATE);
});

asyncSubscribe(BusEvents.PROJECT_PARTICIPANT_FIELDS_POPUP, async (event) => {
  console.info('EVENT', event);
  asyncEmit(BusEvents.PUSH_BUSY_STATE);
  await participantFieldsPopup(PHPMyEdit.selector(), event);
  asyncEmit(BusEvents.POP_BUSY_STATE);
});

asyncSubscribe(BusEvents.EMAIL_POPUP, async (event) => {
  console.info('EVENT', event);
  asyncEmit(BusEvents.PUSH_BUSY_STATE);
  const post = {
    projectId: event.projectId,
    projectName: event.projectName,
    ...(event.post || {}),
  };
  await emailPopup(post, event.reopen);
  asyncEmit(BusEvents.POP_BUSY_STATE);
});

/**
 * Generate a popup-dialog for project related email.
 *
 * @param {object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 *
 * @param {boolean} reopen If true, close any already dialog and re-open it
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
  pageBusyIcon(true);
  Email.emailFormPopup(post, false, undefined, () => pageBusyIcon(false));
};

/**
 * Generate a popup for the instrumentation numbers.
 *
 * @param {string} containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param {object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 */
const instrumentationNumbersPopup = async function(containerSel, post) {
  // Prepare the data-array for PHPMyEdit.tableDialogOpen(). The
  // instrumentation numbers are somewhat nasty and require too
  // many options.

  const template = 'project-instrumentation-numbers';
  const tableOptions = {
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: template + '-dialog',
    template,
    templateRenderer: templateRenderer(template),
    Table: 'BesetzungsZahlen',
    Transpose: 'transposed',
    InhibitTranspose: 'true',
    projectId: post.projectId,
    projectName: post.projectName,
    // Now special options for the dialog popup
    initialViewOperation: true,
    initialName: false, // 'PME_sys_operation',
    initialValue: false, // 'View',
    reloadName: false, // 'PME_sys_operation',
    reloadValue: false, // 'View',
    [pmeSys('operation')]: false, // 'View',
    modalDialog: false,
    modified: false,
  };
  await PHPMyEdit.tableDialogOpen(tableOptions);
};

/**
 * Generate a popup for the participant-fields setup
 *
 * @param {string} containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param {object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 */
const participantFieldsPopup = async function(containerSel, post) {
  // Prepate the data-array for PHPMyEdit.tableDialogOpen(). The
  // instrumentation numbers are somewhat nasty and require too
  // many options.

  const template = 'project-participant-fields';
  const tableOptions = {
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: template + '-dialog',
    template,
    templateRenderer: templateRenderer(template),
    Table: 'ProjectParticipantFields',
    projectId: post.projectId,
    projectName: post.projectName,
    // Now special options for the dialog popup
    initialViewOperation: true,
    initialName: false, // 'PME_sys_operation',
    initialValue: false, // 'View',
    reloadName: false, // 'PME_sys_operation',
    reloadValue: false, // 'View',
    // [pmeSys('operation')]: false, // 'View',
    modalDialog: false,
    modified: false,
  };
  await PHPMyEdit.tableDialogOpen(tableOptions);
};

/**
 * Generate a popup for the "project (over-)view.
 *
 * @param {string} containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param {object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 */
const projectViewPopup = async function(containerSel, post) {
  // Prepate the data-array for PHPMyEdit.tableDialogOpen(). The
  // instrumentation numbers are somewhat nasty and require too
  // many options.

  const template = 'projects';
  const tableOptions = {
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: 'project-overview',
    template,
    templateRenderer: templateRenderer(template),
    // Now special options for the dialog popup
    initialViewOperation: true,
    initialName: pmeSys('operation'),
    initialValue: 'View',
    reloadName: pmeSys('operation'),
    reloadValue: 'View',
    // [pmeSys('operation')]: 'View',
    [pmeSys('rec')]: { id: post.projectId },
    modalDialog: true,
    modified: false,
  };
  await PHPMyEdit.tableDialogOpen(tableOptions);
};

const actionMenu = async function(containerSel) {
  console.info('PROJECT CONTAINER SELECTOR', containerSel);
  containerSel = PHPMyEdit.selector(containerSel);
  const $container = PHPMyEdit.container(containerSel);

  const generateVueMenu = async ($actionMenu) => {
    const projectId = $actionMenu.data('projectId');
    const projectName = $actionMenu.data('projectName');
    const vueMenu = await getEmitResult(
      asyncEmit(BusEvents.GET_VUE_COMPONENT, {
        name: PROJECT_ACTIONS_MENU,
        propsData: {
          projectId,
          projectName,
          enableOverviewItem: $container.find(pmeFormSelector).hasClass(pmeToken('list')),
        },
      }),
    );
    console.info('AFTER CREATE NEW MENU', vueMenu);
    $actionMenu.data('vueMenu', vueMenu);
    $actionMenu.removeClass('dropdown-container').empty().html('<div></div>');
    return await vueMenu.$mount($actionMenu.children(':first')[0]);
  };
  // $container.find('.project-actions.dropdown-container').each(function() { generateVueMenu($(this); });

  $container
    .off('click', '.project-actions')
    .on('click', '.project-actions', async function(event) {

      $.fn.cafevTooltip.hide();

      const $actionMenu = $(this);
      if ($actionMenu.data('vueMenu')) {
        // the menu already exists, just let it do its work
        return;
      }

      // otherwise intercept the event and mount the menu
      event.preventDefault();
      event.stopImmediatePropagation();

      const vueMenu = await generateVueMenu($actionMenu);
      const projectId = $actionMenu.data('projectId');

      asyncEmit(BusEvents.PROJECT_ACTIONS, {
        open: false,
        projectId: -projectId,
      });
      vueMenu.openMenu();

      return false;
    });

  $container
    .off('pme:contextmenu', 'tr.' + pmeToken('row'))
    .on('pme:contextmenu', 'tr.' + pmeToken('row'), async function(event, originalEvent, databaseIdentifier) {
      console.info('CONTEXTMENU EVENT', $(this), event, originalEvent, databaseIdentifier);

      const $row = $(this);
      const $form = $row.closest(pmeFormSelector);
      const $actionMenuContainer = $form.is('.' + pmeToken('list')) ? $row : $row.closest(pmeFormSelector);
      const $actionMenu = $actionMenuContainer.find('.project-actions').first();

      if ($actionMenu.length === 0) {
        return;
      }

      originalEvent.preventDefault();
      originalEvent.stopImmediatePropagation();

      const vueMenu = $actionMenu.data('vueMenu') || await generateVueMenu($actionMenu);
      const projectId = $actionMenu.data('projectId');

      if (vueMenu.isOpen()) {
        vueMenu.closeMenu();
      } else {
        asyncEmit(BusEvents.PROJECT_ACTIONS, {
          open: false,
          projectId: -projectId,
        });
        vueMenu.openMenu(
          originalEvent.originalEvent.clientX,
          originalEvent.originalEvent.clientY,
        );
      }

      return false;
    });
};

const pmeFormInit = function(containerSel) {
  containerSel = PHPMyEdit.selector(containerSel);
  const $container = PHPMyEdit.container(containerSel);
  const $form = $container.find(pmeFormSelector);
  const submitSel = PHPMyEdit.classSelectors(
    'input',
    ['save', 'apply', 'more']);

  if ($form.find(submitSel).length > 0) {
    const nameSelector = 'input.projectname';
    const yearSelector = 'select[name="' + pmeData('year') + '"]';
    const typeSelector = 'select[name="' + pmeData('type') + '"]';
    const mailingListSelector = 'input[type="radio"][name^="' + pmeData('mailing_list_id') + '"]';

    const $name = $container.find(nameSelector);
    const $year = $container.find(yearSelector);
    const $projectType = $container.find(typeSelector);
    const $registrationStart = $container.find(pmeInputSelector + '.registration-start-date');

    let oldProjectYear = SelectUtils.selectedOptions($year).text();
    let oldProjectName = $name.val();
    let oldProjectType = $projectType.val();

    // make sure the some legacy stuff is also up to date
    const $persistentCgiProjectName = $container.find('input[name="projectName"]');
    if ($persistentCgiProjectName.val() !== oldProjectName) {
      $persistentCgiProjectName.val(oldProjectName);
    }

    /**
     * Verify the user submitted name and year settings,
     * depending on whether the user has activated the name or
     * year control, or has clicked the submit button.
     *
     * @param {object} postAddOn TBD.
     *
     * @param {object} button TBD.
     */
    const verifyYearName = function(postAddOn, button) {

      if ($container.data('project-validating')) {
        return;
      }
      $container.data('project-validating', true);

      /* Forward the request to the server via Ajax
       * technologies.
       */
      let post = $form.serialize();
      post += '&control=' + postAddOn;

      const cleanup = function() {
        if ($name.val() === '') {
          $name.val(oldProjectName);
        }
        if ($year.val() === '') {
          $year.val(oldProjectYear);
          $year.trigger('chosen:updated');
        }
        $container.data('project-validating', false);
      };
      Notification.hide();
      $.post(generateAppUrl('validate/projects/name'), post)
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
          Notification.messages(rqData.message);
          $name.val(rqData.projectName);
          $year.val(rqData.projectYear);
          $year.trigger('chosen:updated');
          oldProjectYear = rqData.projectYear;
          oldProjectName = rqData.projectName;
          if (postAddOn === 'submit') {
            if (typeof button !== 'undefined') {
              $form.off('click', submitSel);
              button.trigger('click');
            } else {
              $form.submit();
            }
          }
          $container.data('project-validating', false);
        });
    };

    $projectType
      .off('change')
      .on('change', function(event) {
        const value = $(this).val();
        $container.find(mailingListSelector).prop('disabled', value === 'template');
        $form
          .removeClass('project-type-' + oldProjectType)
          .addClass('project-type-' + value);
        oldProjectType = value;
        if ($name.val() !== '') {
          $name.trigger('blur');
        }
        return false;
      });

    $year
      .off('change')
      .on('change', function(event) {
        verifyYearName('year');
        return false;
      });

    $name
      .off('blur')
      .on('blur', function(event) {
        verifyYearName('name');
        return false;
      });

    $registrationStart.on('blur, change', function(event) {
      const $this = $(this);
      const empty = $.trim($this.val()) === '';

      const $deadlineLock = $container.find(pmeIdSelector('project-registration-deadline'));

      $deadlineLock.prop('checked', true).trigger('change');
      $deadlineLock.prop('disabled', empty);
    });

    // Attach a delegate handler to the form; this gives the
    // possibility to attach another delegate handler to the
    // container element.
    $form
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
  } // has form submit controls

  const updateLinkShareControls = function($control, data) {
    Notification.messages(data.message);
    const $controlContainer = $control.closest(pmeClassSelector('', 'cell-wrapper'));
    const empty = !data.share;
    const tooltip = [data.folder, data.share].filter((x) => !!x).join('<br/>');
    const $anchor = $controlContainer.find('a.url');
    $controlContainer.toggleClass('empty', empty).toggleClass('has-content', !empty);
    $anchor.attr('href', data.share).find('.content').html(data.share);
    $anchor.cafevTooltip('dispose').attr('title', tooltip).cafevTooltip();
    if (data.expires) {
      const date = new Date(data.expires.date.substring(0, 10));
      console.info('EXPIRES', date, date.toLocaleDateString());
      const $expires = $controlContainer.find('.share-expiration-date');
      $expires.val(date.toLocaleDateString());
      $expires.datepicker('setDate', date);
    }
  };

  const $downloadShareContainer = $form.find('.download-share');

  $downloadShareContainer.find('.operation.button.create-share-link')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      const projectId = $form.find('input[name="projectId"]').val();
      setBusyIndicators(true, $container, false);
      $.post(
        generateAppUrl('projects/' + projectId + '/share/downloads'))
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, function() {
            setBusyIndicators(false, $container, false);
          });
        })
        .done(function(data, textStatus, request) {
          updateLinkShareControls($this, data);
          PHPMyEdit.submitOuterForm($container.closest(pmeFormSelector));
          setBusyIndicators(false, $container, false);
        });
      return false;
    });

  $downloadShareContainer.find('.operation.button.delete-share-link')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      const projectId = $form.find('input[name="projectId"]').val();
      setBusyIndicators(true, $container, true);
      $.ajax({
        url: generateAppUrl('projects/' + projectId + '/share/downloads'),
        type: 'DELETE',
      })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, function() {
            setBusyIndicators(false, $container, true);
          });
        })
        .done(function(data, textStatus, request) {
          updateLinkShareControls($this, data);
          PHPMyEdit.submitOuterForm($container.closest(pmeFormSelector));
          setBusyIndicators(false, $container, true);
        });
      return false;
    });

  $downloadShareContainer
    .off('blur', 'input.share-expiration-date')
    .on('blur', 'input.share-expiration-date', function(event) {
      const $this = $(this);
      const projectId = $form.find('input[name="projectId"]').val();
      setBusyIndicators(true, $container, true);
      console.info('DATE', $this.val(), $this.datepicker('getDate'));
      $.ajax({
        url: generateAppUrl('projects/' + projectId + '/share/downloads'),
        type: 'PATCH',
        data: {
          expirationDate: $this.val(),
        },
      })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, function() {
            setBusyIndicators(false, $container, true);
          });
        })
        .done(function(data, textStatus, request) {
          updateLinkShareControls($this, data);
          PHPMyEdit.submitOuterForm($container.closest(pmeFormSelector));
          setBusyIndicators(false, $container, true);
        });
      return false;
    });

  $downloadShareContainer.find('.operation.button.copy-to-clipboard')
    .off('click')
    .on('click', function(event) {
      const $controlContainer = $(this).closest(pmeClassSelector('', 'cell-wrapper'));
      const url = $controlContainer.find('a.url.external').attr('href');
      navigator.clipboard.writeText(url).then(function() {
        Notification.showTemporary(t(appName, 'Share-link has been copied to the clipboard.'));
      }, function(reason) {
        Notification.showTemporary(t(appName, 'Failed copying share-link to the clipboard: {reason}.', { reason }));
      });
    });

  $form.find('.mailing-list-dropdown .list-action').on('click', function(event) {
    const $this = $(this);
    const operation = $this.data('operation');
    if (!operation) {
      return;
    }
    const projectId = $form.find('input[name="projectId"]').val();

    const post = function(force) {
      $.post(
        generateAppUrl('projects/mailing-lists/' + operation), {
          operation,
          projectId,
          force,
        })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown);
        })
        .done(function(data, textStatus, request) {
          if (data.status === 'unconfirmed') {
            Dialogs.confirm(
              data.feedback,
              t(appName, 'Confirmation Required'),
              function(answer) {
                if (answer) {
                  post(true);
                } else {
                  Notification.showTemporary(t(appName, 'Unconfirmed, doing nothing.'));
                }
              },
              true);
          } else {
            Notification.messages(data.message);
            if (data.status !== 'unchanged') {
              const $listDisplay = $form.find('.list-id.display');
              const oldStatus = $listDisplay.data('status');
              $listDisplay.data('status', data.status);
              $listDisplay.removeClass('status-' + oldStatus).addClass('status-' + data.status);
              const $listActions = $form.find('.list-id.actions');
              $listDisplay.find('input.mailing-list').val(data.list_id);
              $listDisplay.find('.list-label').html(data.fqdn_listname);
              $listDisplay.find('.list-status').html(data.l10nStatus);
              $listActions.data('status', data.status);
              $listActions.removeClass('status-' + oldStatus).addClass('status-' + data.status);
            }
          }
        });
    };
    post(false);
  });
};

/**
 * Place an ajax call for public web-page management, create,
 * delete, attach articles.
 *
 *  @param {object} post The data array with action and information.
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

  Notification.hide();
  $.post(generateAppUrl('projects/webpages/' + post.action), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
    })
    .done(function(data) {
      if (post.action === 'ping') {
        return;
      }
      const $form = container.find('table.pme-navigation');
      const $submit = $form.find('input.pme-more, input.pme-reload, input.pme-apply');
      $submit.first().trigger('click', {
        postOpen(dialogHolder) {
          Notification.messages(data.message);
          dialogHolder.dialog('moveToTop');
        },
      });
    });
};

/**
 * Dispatch a UI-event and potentially add or delete a public
 * web-page. This is called as a beforeActive tab-event handler.
 *
 * @param {object} event The event provided by jQuery tab widget.
 *
 * @param {object} ui An object with old and new panel und tabs
 *
 * @param {object} container The div which contains the current dialog.
 *
 * @returns {boolean}
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
      t(appName, 'Unlink Web-Page with Id {articleId}?', { articleId }),
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
      t(appName, 'Delete Web-Page with Id {articleId}?', { articleId }),
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

const articleSelectOnChange = function(event, container) {
  const $this = $(this);

  const projectId = $this.data('projectId');
  const selected = SelectUtils.selectedOptions($this);
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
};

const attachArticleSelectHandlers = function(containerContext) {
  const container = containerContext.container;
  const articleBox = containerContext.articleBox;
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
    return articleSelectOnChange.call(this, event, container);
  });
};

const scrollbarAdjust = function(containerContext) {
  const containerNode = containerContext.container[0];
  const scrollBarWidth = containerNode.offsetWidth - containerNode.clientWidth;
  containerContext.articleBox.css('margin-right', scrollBarWidth + 'px');
};

/**
 * Force only the height of the give iframe.
 *
 * @param {jQuery} containerContext TBD.
 *
 * @param {jQuery} iframe TBD.
 */
const forceHeight = function(containerContext, iframe) {
  const domFrame = iframe[0];
  const scrollHeight = domFrame.contentWindow.document.body.scrollHeight;
  iframe.css({
    height: scrollHeight + 'px',
  });
  containerContext.resizeCB();
  scrollbarAdjust(containerContext);
};

const displayArticleLoad = function(containerContext, iframe) {
  if (typeof iframe !== 'undefined') {
    const $iframe = $(iframe);
    const contents = $iframe.contents();

    // For the pretty-print version. We remove everything except the
    // article itself. Note that this is very specific for the
    // Camerata web-pages, in their current form and would have to be
    // customized for other web-pages.
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

    const scrollWidth = iframe.contentWindow.document.body.scrollWidth;
    const scrollHeight = iframe.contentWindow.document.body.scrollHeight;
    $iframe.css({
      width: scrollWidth + 'px',
      height: scrollHeight + 'px',
    });

    // alert('height: ' + iframe.height() + ' style ' + iframe.attr('style'));

    --containerContext.numDisplayFrames;
  }

  const articleBox = containerContext.articleBox;
  if (containerContext.numDisplayFrames === 0) {
    $('#cmsFrameLoader').fadeOut(function() {
      articleBox.tabs({
        active: 0,
        heightStyle: 'auto',
        activate(event, ui) {
          // nothing
        },
        create(event, ui) {
          containerContext.articleBox.height('auto');

          const forcedWidth = articleBox.width();
          const forcedHeight = articleBox.height() - $('#cmsarticletabs').outerHeight();

          containerContext.allDisplayFrames.width(forcedWidth).height(forcedHeight);

          containerContext.resizeCB();
          scrollbarAdjust(containerContext);
        },
        beforeActivate(event, ui) {
          return projectWebPageTabHandler(event, ui, containerContext.container);
        },
      });
    });
  } else if (containerContext.numDisplayFrames < 0) {
    // can happen, moving dialogs around causes
    // reloads, at least with FF.

    const forcedWidth = articleBox.width();
    const forcedHeight = articleBox.height() - $('#cmsarticletabs').outerHeight();

    containerContext.allDisplayFrames.width(forcedWidth).height(forcedHeight);
  }
};

const changeArticleLoad = function(containerContext, iframe) {
  const container = containerContext.container;

  if (typeof iframe !== 'undefined') {
    const $iframe = $(iframe);
    const contents = $iframe.contents();

    // in order to be prepared for automatic reloads
    // caused by resize or redraw events we have to
    // update the src-uri of the iframe.
    // alert('src: '+ self.contentWindow.location.href);

    const website = contents.find('#rex-start-of-page');
    const wrapper = contents.find('#rex-js-page-container');
    const mainPage = contents.find('#rex-js-page-main');

    const rexForm = wrapper.find('form#REX_FORM');

    // set to auto and fix later for correct size and
    // scrollbars when necessary.
    container.css({
      width: 'auto',
      height: 'auto',
    });

    // The below lines style the edit window.
    contents.find('#rex-js-nav-top').remove();
    contents.find('#rex-js-nav-main').remove();
    mainPage.find('header.rex-page-header').remove();
    mainPage.find('.col-lg-8').removeClass('col-lg-8').addClass('col-lg-12');
    contents.find('#rex-js-structure-breadcrumb').remove();
    contents.find('#rex-js-main-sidebar').remove();
    website.find('.rex-global-footer').remove();
    wrapper.find('.rex-page-main').css({ 'padding-top': 0 });

    wrapper.css({
      padding: 0,
      margin: 0,
      // float: 'left',
    });
    website.css({
      width: '100%', // wrapper.css('width'),
      // 'background-image': 'none',
    });
    // contents.find('textarea').css({ 'max-width': '720px' });

    // the width is set via calc using max-width and a desired width property.
    const scrollHeight = iframe.contentWindow.document.body.scrollHeight;
    $iframe.css({
      height: scrollHeight + 'px',
    });

    const editArea = rexForm.find('textarea');
    if (editArea.length > 0) {
      textareaResize(editArea);

      rexForm
        .off('resize', 'textarea')
        .on('resize', 'textarea', function() {
          forceHeight(containerContext, $iframe);
          return false;
        });
    }

    rexForm.off('resize', '.mceEditor');
    rexForm.on('resize', '.mceEditor', function() {
      forceHeight(containerContext, $iframe);
      return false;
    });

    // contents.find('head').prepend('<script type="text/javascript">' + iFrameContentScript + '</script>');
    iFrameResize($iframe);

    --containerContext.numChangeFrames;
  }

  const articleBox = containerContext.articleBox;
  if (containerContext.numChangeFrames === 0) {
    $('#cmsFrameLoader').fadeOut(function() {
      container.find('#projectWebArticles').tabs({
        active: 0,
        create(event, ui) {
          articleBox.height('auto');
          containerContext.resizeCB();
          scrollbarAdjust(containerContext);
        },
        activate(event, ui) {
          const $iframe = ui.newPanel.find('iframe');
          if ($iframe.length === 1) {
            forceHeight(containerContext, $iframe);
          } else {
            containerContext.resizeCB();
            scrollbarAdjust(containerContext);
          }
        },
        beforeActivate(event, ui) {
          return projectWebPageTabHandler(event, ui, container);
        },
      });
      $('#projectWebArticles').css({ opacity: 1.0 });
    });
  } else if (containerContext.numChangeFrames < 0) {
    // < 0 happens when inside the frame a reload
    // is triggered, after the initial loading of all frames.
    containerContext.resizeCB();
    scrollbarAdjust(containerContext);
    $('#projectWebArticles').css({ opacity: 1.0 });
  }
};

const tableLoadCallback = function(selector, parameters, resizeCB) {

  if (parameters.reason === 'dialogClose') {
    if (parameters.closedBy !== undefined && parameters.closedBy === pmeSys('savedelete')) {
      const templateRenderer = $(parameters.tableOptions.ambientContainerSelector).find('input[name="templateRenderer"]').val();
      if (templateRenderer !== templateRenderer('projects')) {
        // we have to reload the default page as the underlying page
        // most likely depends on the now deleted project
        window.location.replace(generateAppUrl('') + '?history=discard');
        PHPMyEdit.halt();
      }
    }
    return;
  }

  const container = PHPMyEdit.container(selector);
  actionMenu(selector);
  pmeFormInit(selector);

  const articleBox = container.find('#projectWebArticles');
  const displayFrames = articleBox.find('iframe.cmsarticleframe.display, iframe.cmsarticleframe.add');
  const changeFrames = articleBox.find('iframe.cmsarticleframe.change, iframe.cmsarticleframe.change');
  const allDisplayFrames = articleBox.find('.cmsarticleframe.display');
  const allChangeFrames = articleBox.find('.cmsarticleframe.change');

  const containerContext = {
    container,
    resizeCB,
    articleBox,
    displayFrames,
    numDisplayFrames: displayFrames.length,
    allDisplayFrames,
    changeFrames,
    numChangeFrames: changeFrames.length,
    allChangeFrames,
  };

  if (allDisplayFrames.length > 0) {
    if (displayFrames.length > 0) {
      displayFrames.each(function(index) {
        const $this = $(this);
        if ($this.data(appName + 'LoadEvent') === 1) {
          console.warn('DISPLAY FRAME LOAD EVENT LOST; TOO LATE');
          displayArticleLoad(containerContext, this);
        } else {
          const iframeLoadDeferred = $.Deferred()
            .done(function() {
              console.info('IFRAME LOAD CAUGHT IN TIME');
            })
            .fail(function() {
              console.warn('IFRAME LOAD LOST, PROBABLY TOO LATE');
              $this.trigger('load');
            });
          $this.on('load', function(event) {
            iframeLoadDeferred.resolve();
            displayArticleLoad(containerContext, this);
          });
          const timeout = 10;
          setTimeout(function() {
            iframeLoadDeferred.reject();
          }, timeout * 1000);
        }
      });
    } else {
      displayArticleLoad(containerContext);
    }
  } else if (allChangeFrames.length > 0) {
    if (changeFrames.length > 0) {
      $('#projectWebArticles').css({ opacity: 0.0 });
      changeFrames.each(function(index) {
        const $this = $(this);
        if ($this.data(appName + 'LoadEvent') === 1) {
          console.warn('DISPLAY FRAME LOAD EVENT LOST; TOO LATE');
          changeArticleLoad(containerContext, this);
        } else {
          const iframeLoadDeferred = $.Deferred()
            .done(function() {
              console.info('IFRAME LOAD CAUGHT IN TIME');
            })
            .fail(function() {
              console.warn('IFRAME LOAD LOST, PROBABLY TOO LATE');
              $this.trigger('load');
            });
          $this.on('load', function(event) {
            iframeLoadDeferred.resolve();
            changeArticleLoad(containerContext, this);
          });
          const timeout = 10;
          setTimeout(function() {
            iframeLoadDeferred.reject();
          }, timeout * 1000);
        }
      });
    } else {
      changeArticleLoad(containerContext);
    }
  } else {
    // Just execute the resize callback:
    resizeCB();
    scrollbarAdjust(containerContext);
  }

  // Intercept app-navigation events here and redirect to the page
  // loader
  container.on('click', 'li.nav > a.nav', function(event) {
    const post = $(this).data('post');
    Page.loadPage(post);
    // alert('post: '+post);
    return false;
  });

  attachArticleSelectHandlers(containerContext);

  const linkPopups = {
    'projects--participant-fields': participantFieldsPopup,
    'projects--instrumentation': instrumentationNumbersPopup,
    'projects--instrumentation-voices': instrumentationNumbersPopup,
  };

  for (const [css, popup] of Object.entries(linkPopups)) {
    const element = container.find('td.pme-value.' + css + ' a.nav');
    element
      .off('click')
      .on('click', function(event) {
        const data = $(this).data('json');
        popup(selector, data);
        return false;
      });
  }

  // Instrumentation and instrumentation numbers on add/copy/change
  // pages. The idea is to provide enough but not too much excess
  // voices to select.

  /**
   * Update the instrument-voices select with data from an Ajax call
   *
   * @param {object} additionalVoices Array of additional voices to add
   * in the form { INSTRUMENT: VOICE }.
   */
  const updateInstrumentVoices = function(additionalVoices) {
    const $instrumentsSelect = container.find('select.projects--instrumentation');
    const $instrumentationVoicesSelect = container.find('select.projects--instrumentation-voices');

    const cleanup = function() {};
    const instrumentsName = $instrumentsSelect.attr('name');
    const voicesName = $instrumentationVoicesSelect.attr('name');
    let post = $.param({
      instruments: instrumentsName,
      voices: voicesName,
    })
          + '&' + $instrumentsSelect.serialize()
          + '&' + $instrumentationVoicesSelect.serialize();
    for (const [instrument, voice] of Object.entries(additionalVoices || {})) {
      post += '&' + $.param({ [voicesName]: instrument + ':' + voice });
    }
    $.post(generateAppUrl('projects/change-instrumentation'), post)
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown);
        cleanup();
      })
      .done(function(rqData) {
        if (!Ajax.validateResponse(rqData, ['voices'])) {
          cleanup();
        }
        $instrumentationVoicesSelect
          .empty()
          .append(rqData.voices);
        $instrumentationVoicesSelect
          .prop('disabled', !rqData.voices)
          .trigger('chosen:updated');
        Notification.messages(rqData.message);
      });
  };

  const inputVoicesHandler = function(event, input) {
    const $this = $(input);

    const $instrumentationVoicesSelect = container.find('select.projects--instrumentation-voices');
    const selectCombo = $instrumentationVoicesSelect.parent();
    selectCombo.show();
    $this.closest('.container').hide();

    SelectUtils.locked($instrumentationVoicesSelect, false);

    if ($this.val() !== '') {
      const dataHolder = $this.closest('.container').find('input.data');
      const instrument = dataHolder.data('instrument');
      const voice = parseInt($this.val());
      updateInstrumentVoices({ [instrument]: voice });
    }

    return false;
  };

  container.on('blur', 'div.instrument-voice.request.container input.instrument-voice.input', function(event) {
    return inputVoicesHandler(event, this);
  });

  container.on('click', 'div.instrument-voice.request.container input.instrument-voice.confirm', function(event) {
    const instrument = $(this).data('instrument');
    return inputVoicesHandler(event, $(this).parent().find('input.input.instrument-' + instrument));
  });

  container.on('change', 'select.projects--instrumentation, select.projects--instrumentation-voices', function(event) {
    const $instrumentationVoicesSelect = container.find('select.projects--instrumentation-voices');

    // intercept request to enter voices-number manually
    const selectedVoices = SelectUtils.selected($instrumentationVoicesSelect);
    for (const voiceItem of selectedVoices) {
      const [instrument, voice] = voiceItem.split(':');
      if (voice === '?') {
        const inputVoices = container.find('.pme-value div.instrument-voice.request.container');
        const selectCombo = $instrumentationVoicesSelect.parent();
        const inputCombo = inputVoices.filter('div.instrument-' + instrument);
        SelectUtils.locked($instrumentationVoicesSelect, true);
        selectCombo.hide();
        inputCombo.show();
        const index = selectedVoices.findIndex((v) => voiceItem === v);
        if (index > -1) {
          selectedVoices.splice(index, 1);
        }
        SelectUtils.selected($instrumentationVoicesSelect, selectedVoices);
        return false;
      }
    }

    updateInstrumentVoices();

    return false; // select handler
  });

  return false; // table load callback
};

const documentReady = function() {

  PHPMyEdit.addTableLoadCallback('projects', {
    callback: tableLoadCallback,
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
  projectViewPopup,
  instrumentationNumbersPopup,
};
