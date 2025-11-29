/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.js';
import { appName, appPrefix } from '../config.ts';
import * as Ajax from './ajax.ts';
import * as Notification from './notification.ts';
import * as Dialogs from './dialogs.ts';
import * as FileUpload from './file-upload.ts';
import * as SelectUtils from './select-utils.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import { simpleSetHandler, simpleSetValueHandler, type GetValueResult } from './simple-set-value.ts';
import { toolTipsInit } from './cafevdb.ts';
import { setPersonalUrl, setAppUrl, getUrl } from './settings-urls.ts';
import fileDownload from './file-download.ts';
import { makePlaceholder as selectPlaceholder } from './select-utils.ts';
import * as WysiwygEditor from './wysiwyg-editor.ts';
import personalSettingsAfterLoad, { updateCreditsTimer } from './personal-settings.js';
import { showInfo, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs';
import { translate as t } from '@nextcloud/l10n';
import * as ConfigConstants from '../../build/ts-types/php-modules/Settings/ConfigConstants.ts';
import * as DTO from '../../build/ts-types/php-modules/Controller/DTO.ts';
import { EnumPersonalSettingsKey } from '../../build/ts-types/php-modules/Controller.ts';

require('../legacy/nextcloud/jquery/showpassword.js');
require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');
require('jquery-ui/ui/widgets/accordion');
require('jquery-ui/ui/widgets/tabs');

require('personal-settings.scss');
require('about.scss');

/* eslint-disable @typescript-eslint/no-explicit-any */

/**
 * Permanent DOM element holding the dynamically injected settings
 * forms.
 */
const containerSelector = 'div.app-admin-settings';
const tabsSelector = '#personal-settings-container';

let timeStampTimer: NodeJS.Timeout;

const localeUpdateOne = function(element: HTMLElement|JQuery) {
  const $element = $(element);
  if (!$element.is(':visible')) {
    // avoid update if the element currently is hidden
    return;
  }
  return $.post(getUrl('locale-info'), { scope: $element.data('scope') })
  // .fail(function(xhr, status, errorThrown) { ignore }
    .done(function(data) {
      $element.find('.locale.time').replaceWith($(data.contents).find('.locale.time'));
    });
};

const localeUpdater = async function($localeInfo: JQuery) {
  if (!$localeInfo.filter(':visible').length) {
    clearTimeout(timeStampTimer);
    return;
  }
  const promises: JQuery.jqXHR<any>[] = [];
  for (const element of $localeInfo) {
    promises.push(localeUpdateOne(element)!);
  }
  for (const promise of promises) {
    await promise;
  }
  clearTimeout(timeStampTimer); // just in case, should not be necessary
  timeStampTimer = setTimeout(() => localeUpdater($localeInfo), 1000 + Math.floor(Math.random() * 29000.0));
};

const updateLocaleTimeStamps = function($container: JQuery) {
  const $localeInfo = $container.find('.locale.information');
  localeUpdater($localeInfo);
};

/**
 * Initialize handlers etc. Contents of container may be replaced by
 * AJAX calls. This function initializes all dynamic
 * elements. Everything attached to the container is initialized in
 * the $(document).ready() callback.
 *
 * @param container Should be a permanent DOM element.
 */
const afterLoad = function(container?: JQuery) {

  container = container || $(containerSelector);
  const tabsHolder = $(tabsSelector);

  if (!container.is(':parent')) {
    // nothing to do, empty container
    return;
  }

  // @ts-expect-error 2339
  tabsHolder.tabs({ selected: 0 });

  // Work around showPassword erasing the value and returns the
  // text input clone.
  const showPassword = function(element: JQuery) {
    const tmp = element.val();
    let showElement: JQuery;
    // @ts-expect-error 2339
    element.showPassword(function(args: { input: JQuery, checkbox: JQuery, clone: JQuery }) {
      showElement = args.clone;
    });
    element.val(tmp || '');
    return showElement!;
  };

  // 'show password' checkbox
  const encryptionKey = $(`#userkey #${EnumPersonalSettingsKey.ENCRYPTION_KEY}`);
  const loginPassword = $('#userkey #password');
  showPassword(encryptionKey);
  showPassword(loginPassword);

  $('#userkey input.button').on('click', function() {
    // We allow empty keys, meaning no encryption
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    if (loginPassword.val() === '') {
      $('#userkey .info').html(t(appName, 'You must type in your login password.'));
      $('#userkey .info').show();
      $('#userkey .error').show();
      return false;
    }
    $.post(
      setPersonalUrl(EnumPersonalSettingsKey.ENCRYPTION_KEY), {
        value: {
          [EnumPersonalSettingsKey.ENCRYPTION_KEY]: encryptionKey.val(),
          loginpassword: loginPassword.val(),
        },
      })
      .done(function(data) {
        console.log(data);
        $('#userkey input[name="dbkey1"]').val('');
        $('#userkey input[name="userkey"]').val('');
        $('#userkey input[name="userkey-clone"]').val('');
        $('#userkey .info').html(data.message);
        $('#userkey .info').show();
        $('#userkey .changed').show();
        showInfo(t(appName, 'Encryption key changed, reloading settings form.'));
        const url = generateAppUrl('settings/personal/form');
        $.get(url)
          .done((data) => {
            $('#personal-settings-container').replaceWith(data);
            console.info('CALL AFTER LOAD');
            afterLoad($('#personal-settings-container'));
            personalSettingsAfterLoad();
          })
          .fail((xhr, textStatus, errorThrown) => {
            Ajax.handleError(xhr, textStatus, errorThrown);
          });
      })
      .fail(async (xhr, status, errorThrown) => {
        const failData = await Ajax.handleError(xhr, status, errorThrown);
        $('#userkey .info').html(failData.messages.join(' '));
        $('#userkey .info').show();
        $('#userkey .error').show();
      });
    return false;
  });

  /****************************************************************************
   *
   * Application settings stuff
   *
   ***************************************************************************/

  // name of orchestra

  {
    const adminGeneral = $('#admingeneral');
    const msg = adminGeneral.find('.msg');

    simpleSetValueHandler(
      adminGeneral.find('input[name="orchestra"]') as JQuery<HTMLInputElement>,
      'blur',
      msg, {
        success(_element, _data, value, _msg) {
          if (value === '') {
            $('div.personalblock.admin,div.personalblock.sharing').find('fieldset').each(function() {
              $(this).prop('disabled', true);
            });
          } else {
            $('div.personalblock.admin').find('fieldset').each(function() {
              $(this).removeAttr('disabled');
            });
            if ($(`#${ConfigConstants.SHARE_OWNER_KEY} #user-saved`).val() !== '') {
              $('div.personalblock.sharing').find('fieldset').each(function() {
                $(this).removeAttr('disabled');
              });
            } else {
              $(`#${ConfigConstants.SHARE_OWNER_KEY}form`).find('fieldset').each(function() {
                $(this).removeAttr('disabled');
              });
            }
          }
        },
      });

    simpleSetValueHandler(
      adminGeneral.find<HTMLSelectElement>(`select[name="${ConfigConstants.ORCHESTRA_LOCALE_KEY}"]`), 'change', msg, {
        success(_element, data: DTO.OrchestraLocaleResponse, _value, _msg) {
          if (data.localeInfo) {
            const $localeInfo = adminGeneral.find('.locale.information');
            $localeInfo.children().remove();
            $localeInfo.append($(data.localeInfo as string).children());
          }
        },
      });
  }

  {
    const form = $('#systemkey');
    const container = form.find('fieldset.systemkey');
    const msg = container.find('.statusmessage.general');

    // Encryption-key
    const keyInput = container.find('#key');
    const oldKeyInput = container.find('#oldkey');

    const keyInputClone = showPassword(keyInput);
    const oldKeyInputClone = showPassword(oldKeyInput);

    $('#keychangebutton').on('click', function() {
      // We allow empty keys, meaning no encryption
      form.find('.statusmessage').hide();
      if (oldKeyInput.val() !== keyInput.val()) {

        // disable form elements until we got an answer
        $(tabsSelector + ' fieldset').prop('disabled', true);
        $(tabsSelector).tabs('disable');
        container.find('.statusmessage.standby').show();

        Notification.show(t(appName, 'Please standby, the operation will take some time!'));

        $.post(
          setAppUrl('systemkey'),
          {
            value: {
              systemkey: keyInput.val(),
              oldkey: oldKeyInput.val(),
            },
          })
          .done(function(data) {
            // re-enable all forms
            $(tabsSelector + ' fieldset').prop('disabled', false);
            $(tabsSelector).tabs('enable');
            container.find('.statusmessage.standby').hide();

            Notification.hide();

            if (keyInput.val() === '') {
              container.find('.statusmessage.insecure').show();
            }
            keyInput.val('');
            keyInputClone.val('');
            oldKeyInput.val('');
            oldKeyInputClone.val('');
            if (keyInputClone.is(':visible')) {
              $('#systemkey-show').trigger('change');
            }
            $('.statusmessage.changed').show();
            if (data.message) {
              Notification.messages(data.message);
              container.find('.statusmessage.general').html(data.message).show();
            }
          })
          .fail(async (xhr, status, errorThrown) => {
            const failData = await Ajax.handleError(xhr, status, errorThrown);
            $(tabsSelector + ' fieldset').prop('disabled', false);
            $(tabsSelector).tabs('enable');
            container.find('.statusmessage.standby').hide();

            Notification.hide();

            $('.statusmessage.error').show();
            if (failData.message) {
              container.find('.statusmessage.general').html(failData.message).show();
            }
          });
      } else {
        container.find('.statusmessage.equal').show();
        if (oldKeyInput.val() === '') {
          container.find('.statusmessage.insecure').show();
        }
      }
      return false;
    });

    $('form#systemkey #keygenerate').on('click', function(_event) {
      $('.statusmessage').hide();

      // show the visible password text input
      if ($('form#systemkey #key').is(':visible')) {
        $('#systemkey-show').trigger('click');
      }

      $.post(
        getUrl('passwordgenerate'))
        .fail(function(xhr, status, errorThrown) {
          msg.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        })
        .done(function(data) {
          // Make sure both inputs have the same value
          keyInput.val(data.value);
          keyInputClone.val(data.value);
          Notification.messages(data.message);
        });
      return false;
    });

    $('#keydistributebutton').on('click', function(_event) {
      const msg = form.find('fieldset.keydistribute .statusmessage');
      form.find('.statusmessage').hide();
      const name = $(this).attr('name') as string;
      $.post(setAppUrl(name))
        .fail(async (xhr, status, errorThrown) => {
          const failData = await Ajax.handleError(xhr, status, errorThrown);
          msg.html(failData.messages.join(' ')).show();
        })
        .done(function(data) {
          Notification.messages(data.message);
        });
      return false;
    });
  }

  /****************************************************************************
   *
   * data-base
   *
   ***************************************************************************/

  {
    simpleSetValueHandler($('#dbgeneral :input'), 'blur', $('#dbsettings #msg'));

    // DB-Password
    // 'show password' checkbox
    const $dbPassword = $(`fieldset.${appName}_dbpassword #${appPrefix(ConfigConstants.APP_DB_PASSWORD)}`);
    showPassword($dbPassword);

    // test password
    simpleSetValueHandler(
      $(`fieldset.${appName}_${ConfigConstants.APP_DB_PASSWORD} input.button`), 'click', $(`fieldset.${appName}_${ConfigConstants.APP_DB_PASSWORD} .statusmessage`), {
        success(_element, _data, _value) {
          // $(`fieldset.${appName}_dbpassword input[name="dbpassword"]`).val('');
          // $(`fieldset.${appName}_dbpassword input[name="dbpassword-clone"]`).val('');
        },
        getValue(_element, msg) {
          const val = { name: $dbPassword.attr('name')!, value: $dbPassword.val() };
          if (val.value === '') {
            msg.html(t(appName, 'Empty password, trying to use configured credentials.')).show();
          }
          return val;
        },
      });
  }

  /****************************************************************************
   *
   * Sharing, share-owner
   *
   ***************************************************************************/

  const enableFieldSets = function() {
    const $shareOwnerSet = $(`#${ConfigConstants.SHARE_OWNER_KEY}`).find<HTMLInputElement>('input#user').val() !== '';

    $('#calendars, #contacts').find('fieldset').each(function() {
      $(this).prop('disabled', !$shareOwnerSet);
    });
    const $sharedFolder = $(`input#${ConfigConstants.SHARED_FOLDER}`).val() !== '';
    const $projectsFolder = $(`input#${ConfigConstants.PROJECTS_FOLDER}`).val() !== '';
    const $financeFolder = $(`input#${ConfigConstants.FINANCE_FOLDER}`).val() !== '';
    $(`#${ConfigConstants.SHARED_FOLDER}-form`).find('fieldset').each(function(_i, element) {
      const $element = $(element);
      const disabled = (!$shareOwnerSet
                      || ($element.hasClass(`needs-${ConfigConstants.SHARED_FOLDER}`) && !$sharedFolder)
                      || ($element.hasClass(`needs-${ConfigConstants.PROJECTS_FOLDER}`) && !$projectsFolder)
                      || ($element.hasClass(`needs-${ConfigConstants.FINANCE_FOLDER}`) && !$financeFolder));
      $element.prop('disabled', disabled);
    });
  };

  {
    const container = $(`#${ConfigConstants.SHARE_OWNER_KEY}`);
    const msg = $('#shareownerform .statusmessage');
    const $shareOwner = container.find<HTMLInputElement>('#user');
    const $shareOwnerSaved = container.find<HTMLInputElement>('#user-saved');
    const $shareOwnerForce = container.find<HTMLInputElement>(`#${ConfigConstants.SHARE_OWNER_KEY}-force`);
    const $shareOwnerCheck = container.find('#check');

    $shareOwnerForce.on('change', function(this: HTMLInputElement, _event) {
      msg.hide();
      if (!$(this).is(':checked') && $shareOwnerSaved.val() !== '') {
        $shareOwner.val($shareOwnerSaved.val()!);
        $shareOwner.prop('disabled', true);
      } else {
        $shareOwner.prop('disabled', false);
      }
      return false;
    });

    $shareOwner.on('blur', function(_event) {
      $shareOwnerCheck.prop('disabled', $shareOwner.val() === '');
      return false;
    });

    simpleSetValueHandler(
      $shareOwnerCheck, 'click', msg, {
        success(_element, _data, _value, _msg) { // done
          $shareOwnerSaved.val($shareOwner.val()!);
          $shareOwner.prop('disabled', true);
          enableFieldSets();
        },
        getValue(_element, _msg) { // getValue
          return {
            name: ConfigConstants.SHARE_OWNER_KEY,
            value: {
              [ConfigConstants.SHARE_OWNER_KEY]: $shareOwner.val(),
              [`${ConfigConstants.SHARE_OWNER_KEY}-saved`]: $shareOwnerSaved.val(),
              [`${ConfigConstants.SHARE_OWNER_KEY}-force`]: $shareOwnerForce.is(':checked'),
            },
          };
        },
      });
  } // fieldset block

  // Share-owner´s password
  {
    const container = $('fieldset.shareownerpassword');
    const password = container.find('#shareownerpassword');
    const change = container.find('#change');
    const msg = $('#shareownerform .statusmessage');

    const passwordClone = showPassword(password);

    simpleSetValueHandler(
      change, 'click', msg, {
        success(_element, _data, _value, _msg) { // done
          // Why should we want to empty this except for security reasons?
          // password.val('');
          // passwordClone.val('');
        },
        getValue(_element, msg) {
          const val = { name: password.attr('name')!, value: password.val() };
          if (val.value === '') {
            msg.html(t(appName, 'Password field must not be empty')).show();
            return;
          }
          return val;
        },
      });

    container.find('#generate').on('click', function(_event) {
      $('.statusmessage').hide();
      msg.hide();

      // show the visible password input
      if (password.is(':visible')) {
        $('#shareownerpassword-show').trigger('click');
      }

      $.post(
        getUrl('passwordgenerate'))
        .fail(function(xhr, status, errorThrown) {
          msg.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        })
        .done(function(data) {
          // TODO check integrity of return etc.
          password.val(data.value);
          passwordClone.val(data.value);
          if (data.message !== '') {
            msg.html(data.message).show();
          }
        });
      return false;
    });
  }

  { // shared objects
    const container = $('#sharing-settings');
    const msg = container.find('.statusmessage.sharing-settings');

    /**************************************************************************
     *
     * Events, calendars, contacts
     *
     *************************************************************************/

    $('div#sharing-settings').accordion({
      heightStyle: 'content',
    });

    simpleSetValueHandler(
      container.find('#calendars :input, #contacts :input'),
      'blur',
      msg,
      {
        success($self, data: DTO.NameIdValueResponse, value: string, _msgElement) {
          if (data.value.name && data.value.name !== value) {
            $self.val(data.value.name);
          }
        },
      },
    );

    container.find(`#${ConfigConstants.SHARED_FOLDER}-form`).on('submit', function() { return false; });

    type CSSBase = typeof ConfigConstants.DEDICATED_FOLDERS[number];
    type CSSSaved<T extends CSSBase> = `${T}${'-saved'}`;
    type CSSForce<T extends CSSBase> = `${T}${'-force'}`;
    type CSSCheck<T extends CSSBase> = `${T}${'-check'}`;

    type Value<T extends CSSBase> = {
      [key in T|CSSSaved<T>|CSSForce<T>]: key extends T|CSSSaved<T> ? string : boolean;
    };

    const sharedFolder = <CSS extends CSSBase>(
      cssBase: CSS,
      callback?: (
        $element: JQuery,
        css: CSS,
        data: DTO.FolderValueResponse,
        value: Value<CSS>,
        $msg: JQuery,
      ) => undefined,
    ) => {
      const css = cssBase;
      const cssSaved: CSSSaved<CSS> = `${css}-saved` as const;
      const cssForce: CSSForce<CSS> = `${css}-force` as const;
      const cssCheck: CSSCheck<CSS> = `${css}-check` as const;
      const sharedObject = container.find('#' + css);
      const sharedObjectSaved = container.find('#' + cssSaved);
      const sharedObjectForce = container.find('#' + cssForce);
      const sharedObjectCheck = container.find('#' + cssCheck);

      sharedObjectForce.on('blur', function(_event) { // @@TODO ???
        return false;
      });

      sharedObjectForce.on('click', function(_event) {
        msg.hide();
        if (!sharedObjectForce.is(':checked') && sharedObjectSaved.val() !== '') {
          sharedObject.val(sharedObjectSaved.val()!);
          sharedObject.prop('disabled', true);
        } else {
          sharedObject.prop('disabled', false);
        }
      });

      simpleSetValueHandler(
        sharedObjectCheck, 'click', msg, {
          success(element, data: DTO.FolderValueResponse, value: Value<CSS>, msg) { // done
            // value is just the thing submitted to the AJAX call
            sharedObject.val(data.value);
            sharedObjectSaved.val(data.value);
            if (value[css] !== '') {
              sharedObject.prop('disabled', true);
              sharedObjectForce.prop('checked', false);
            }
            if (callback !== undefined) {
              callback(element, css, data, value, msg);
            }
          },
          getValue(_element, _msg) { // getValue
            return {
              name: css,
              value: {
                [css]: sharedObject.val() as string,
                [cssSaved]: sharedObjectSaved.val() as string,
                [cssForce]: sharedObjectForce.is(':checked'),
              } as Value<CSS>,
            };
          },
        });
    };

    sharedFolder(ConfigConstants.SHARED_FOLDER, function(_element, css, data, value, _msg) {
      $(`div#sharing-settings span.${ConfigConstants.SHARED_FOLDER}`).html(value[css]); // update display
      const $folderView = $(`#${ConfigConstants.SHARED_FOLDER}-fieldset`).find(`a.${ConfigConstants.SHARED_FOLDER}-view`);
      $folderView.attr('href', data.folderLink || '');
      if (data.folderLink) {
        $folderView.removeClass('hidden');
      } else {
        $folderView.addClass('hidden');
      }
      enableFieldSets();
    });
    sharedFolder(ConfigConstants.FINANCE_FOLDER, function(_element, css, _data, value, _msg) {
      // const emptyProjectsFolder = $(`div#sharing-settings input[name="${ConfigConstants.PROJECTS_FOLDER}"]`).val() === '';
      $(`div#sharing-settings span.${ConfigConstants.FINANCE_FOLDER}`).html(value[css] as string); // update
      enableFieldSets();
    });
    sharedFolder(ConfigConstants.PROJECTS_FOLDER, function(_element, css, _data, value, _msg) {
      // const emptyFinanceFolder = $(`div#sharing-settings input[name="${ConfigConstants.FINANCE_FOLDER}"]`).val() === '';
      $(`div#sharing-settings span.${ConfigConstants.PROJECTS_FOLDER}`).html(value[css]); // update
      enableFieldSets();
    });
    sharedFolder(ConfigConstants.PROJECT_PARTICIPANTS_FOLDER);
    sharedFolder(ConfigConstants.PROJECT_POSTERS_FOLDER);
    sharedFolder(ConfigConstants.PROJECT_PUBLIC_DOWNLOADS_FOLDER);
    sharedFolder(ConfigConstants.TRANSACTIONS_FOLDER);
    sharedFolder(ConfigConstants.BALANCES_FOLDER);
    sharedFolder(ConfigConstants.DOCUMENT_TEMPLATES_FOLDER, function(_element, css, _data, value, _msg) {
      $('fieldset.document-template input').prop('disabled', value[css] === '');
    });
    sharedFolder(ConfigConstants.POSTBOX_FOLDER, function(_element, _css, data, _value, _msg) {
      if (data.url) {
        $(`div.${ConfigConstants.POSTBOX_FOLDER}-sharelink`).html(data.url);
      }
    });
    sharedFolder(ConfigConstants.OUTBOX_FOLDER);

    simpleSetValueHandler(container.find('#taxExcemptionNoticeTemplate'), 'blur', msg);

    const $cloudUserForm = container.find('form.cloud-user');

    const $importClubMembersFieldSet = $cloudUserForm.find('fieldset.user-sql');
    const $importClubMembersAsCloudUsers = $importClubMembersFieldSet.find<HTMLInputElement>('input[name="importClubMembersAsCloudUsers"]');
    const $recreateViewsButton = $importClubMembersFieldSet.find('input[name="userSqlBackendRecreateViews"]');
    const $shownIfImport = $importClubMembersFieldSet.find('.show-if-user-sql-backend');
    const $enabledIfImport = $cloudUserForm.find('.enable-if-user-sql-backend');

    const $personalizedViewsFieldSet = $cloudUserForm.find('fieldset.personalized-views');
    const $musicianPersonalizedViews = $personalizedViewsFieldSet.find('input[name="musicianPersonalizedViews"]') as JQuery<HTMLInputElement>;
    const $recreatePersonalizedViewsButton = $personalizedViewsFieldSet.find('input[name="musicianPersonalizedViewsRecreateViews"]') as JQuery<HTMLInputElement>;
    const $enabledIfPersonalizedViews = $cloudUserForm.find('.enable-if-personalized-views');

    const $cloudUserHints = $cloudUserForm.find('div.cloud-user.hints');

    const updateHints = function(hints?: Array<string>) {
      $cloudUserHints.empty();
      if (!Array.isArray(hints) || hints.length === 0) {
        $cloudUserHints.closest('fieldset').hide();
        return;
      }
      $cloudUserHints.closest('fieldset').toggleClass('hidden', !$importClubMembersAsCloudUsers.is(':checked'));
      for (const hint of hints) {
        $cloudUserHints.append('<div class="cloud-user hint">' + hint + '</div>');
      }
    };

    const updateOtherOnImportChange = <T extends HTMLElement>($element: JQuery<T>) => {
      const isChecked = $element.prop('checked');
      console.info('UPDATE OTHER', {
        isChecked,
        $cloudUserHints,
        $enabledIfImport,
        $shownIfImport,
        $importClubMembersFieldSet,
      });
      $cloudUserHints.closest('fieldset').toggleClass('hidden', !isChecked);
      $enabledIfImport.prop('disabled', !isChecked).find('*').prop('disabled', !isChecked);
      $shownIfImport.toggleClass('hidden', !isChecked);
      $importClubMembersFieldSet.toggleClass('club-member-users-enabled', isChecked);
      $importClubMembersFieldSet.toggleClass('club-member-users-disabled', !isChecked);
    };

    const updateOtherOnPersonalizedViewsChange = function($element: JQuery) {
      const isChecked = $element.prop('checked');
      $enabledIfPersonalizedViews.prop('disabled', !isChecked).find('*').prop('disabled', !isChecked);
    };

    simpleSetValueHandler(
      $importClubMembersAsCloudUsers,
      'change',
      undefined, {
        setup() {
          console.info('SETUP');
          const $this = $(this) as JQuery;
          $this.addClass('busy');
          updateOtherOnImportChange($this);
        },
        cleanup() {
          console.info('CLEANUP');
          const $this = $(this) as JQuery;
          updateOtherOnImportChange($this);
          $this.removeClass('busy');
        },
        success(_$element, data: DTO.SimpleSetValueResponse) {
          console.info('SUCCESS');
          console.info('DATA', data);
          updateHints(data.hints);
        },
        fail(xhr, textStatus, errorThrown) {
          console.info('FAIL');
          const $this = $(this) as JQuery;
          Ajax.handleError(xhr, textStatus, errorThrown);
          // revert on failure
          $this.prop('checked', !$this.is(':checked'));
          updateOtherOnImportChange($this);
        },
      });

    const $cloudUserViewsDatabase = $importClubMembersFieldSet.find('input[name="cloudUserViewsDatabase"]') as JQuery<HTMLInputElement>;
    const $cloudUserViewsDatabaseCheckbox = $importClubMembersFieldSet.find('input.checkbox.user-sql.separate-database') as JQuery<HTMLInputElement>;

    $cloudUserViewsDatabaseCheckbox
      .on('change', function(_event) {
        const $this = $cloudUserViewsDatabaseCheckbox;
        const $databaseValue = $cloudUserViewsDatabase.val()!;
        if ($this.prop('checked')) {
          if ($databaseValue !== ($this.data('savedDatabaseValue') || '')) {
            $cloudUserViewsDatabase.val($this.data('savedDatabaseValue'));
            $cloudUserViewsDatabase.trigger('blur');
          }
        } else {
          $this.data('savedDatabaseValue', $databaseValue);
          if ($databaseValue !== '') {
            $cloudUserViewsDatabase.val('');
            $cloudUserViewsDatabase.trigger('blur');
          }
        }
        return false;
      });

    simpleSetValueHandler($cloudUserViewsDatabase, 'blur', undefined, {
      success(_$element, data: DTO.SimpleSetValueResponse) {
        updateHints(data.hints);
      },
    });

    simpleSetValueHandler($recreateViewsButton, 'click', undefined, {
      setup() { $recreateViewsButton.addClass('busy'); },
      success(_$element, data: DTO.SimpleSetValueResponse) {
        updateHints(data.hints);
      },
      cleanup() { $recreateViewsButton.removeClass('busy'); },
    });

    simpleSetValueHandler(
      $musicianPersonalizedViews, 'change', undefined, {
        setup() {
          const $this = $(this) as JQuery;
          $this.addClass('busy');
          // updateOtherOnPersonalizedViewsChange($this);
        },
        cleanup() {
          const $this = $(this) as JQuery;
          updateOtherOnPersonalizedViewsChange($this);
          $this.removeClass('busy');
        },
        success(_$element, data: DTO.SimpleSetValueResponse) {
          console.info('DATA', data);
          updateHints(data.hints);
        },
        fail(xhr, textStatus, errorThrown) {
          const $this = $(this) as JQuery;
          Ajax.handleError(xhr, textStatus, errorThrown);
          // revert on failure
          $this.prop('checked', !$this.is(':checked'));
          updateOtherOnPersonalizedViewsChange($this);
        },
      });

    simpleSetValueHandler($recreatePersonalizedViewsButton, 'click', undefined, {
      setup() { $recreatePersonalizedViewsButton.addClass('busy'); },
      success(_$element, data: DTO.SimpleSetValueResponse) {
        updateHints(data.hints);
      },
      cleanup() { $recreatePersonalizedViewsButton.removeClass('busy'); },
    });

  } // shared objects

  /****************************************************************************
   *
   * email
   *
   ***************************************************************************/

  {
    const $emailContainer = $('div#email-settings');

    $('div#email-settings').accordion({
      heightStyle: 'content',
    });

    {
      const configKey = ConfigConstants.EMAIL_USER;
      // Email-User
      const $container = $emailContainer.find('fieldset.' + configKey);
      const $input = $container.find<HTMLInputElement>('input#' + configKey);

      simpleSetValueHandler($input, 'blur', undefined,
        {
          success($element, data: DTO.SimpleSetValueResponse) {
            if (data.value) {
              $element.val(data.value);
            }
          },
        },
      );
    }

    {
      const configKey = ConfigConstants.EMAIL_PASSWORD;
      const $container = $emailContainer.find('fieldset.' + configKey);

      // 'show password' checkbox
      const $input = $container.find<HTMLInputElement>('#' + configKey);
      showPassword($input);
      const $passwordChange = $container.find<HTMLInputElement>('input.button');

      $passwordChange.on('click', function() {
        const value = $input.val()!;
        const name = $input.attr('name')!;
        if (value !== '') {
          $.post(
            setAppUrl(name), { value })
            .fail(function(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown);
            })
            .done(function(data: DTO.SimpleSetValueResponse) {
              Notification.messages(data.messages);
            });
        } else {
          Notification.messages([t(appName, 'Password field must not be empty.')], { timeout: TOAST_PERMANENT_TIMEOUT });
        }
        return false;
      });
    } // fieldset emailpassword

    {
      const $container = $emailContainer.find('form.serversettings');
      const $serverSelects = $container.find<HTMLSelectElement>(
        [ConfigConstants.SMTP_SECURITY, ConfigConstants.IMAP_SECURITY].map(x => 'select#' + x).join(','),
      );
      const $serverInputs = $container.find<HTMLInputElement>(
        [
          ConfigConstants.SMTP_PORT,
          ConfigConstants.SMTP_SERVER,
          ConfigConstants.IMAP_PORT,
          ConfigConstants.IMAP_SERVER,
        ].map(x => 'input#' + x).join(','),
      );

      $serverSelects.on('change', function(this: HTMLSelectElement, _event) {
        const name = $(this).attr('name')!;
        const value = $(this).val();
        $.post(
          setAppUrl(name), { value })
          .fail(function(xhr, status, errorThrown) {
            Ajax.handleError(xhr, status, errorThrown);
          })
          .done(function(data) {
            console.info('SECURITY RESPONSE', { data });
            if (data.port) {
              $('#' + data.proto + 'port').val(data.port);
            }
            Notification.messages(data.messages);
          });
        return false;
      });

      simpleSetValueHandler(
        $serverInputs,
        'blur',
        undefined, {
          success($element, data: DTO.SimpleSetValueResponse) {
            console.info('EMAIL SERVER VALUE', { data });
            if (data.value) {
              $element.val(data.value);
            }
          },
        },
      );
    }

    {
      const $container = $emailContainer.find('form.bulk-email-settings');
      console.log('************', $container);

      const blurInputsSelector = [
        'input#' + ConfigConstants.ANNOUNCEMENTS_MAILING_LIST_KEY,
        'input#' + ConfigConstants.EMAIL_FROM_NAME_KEY,
        'input#' + ConfigConstants.EMAIL_FROM_ADDRESS_KEY,
        'input#' + ConfigConstants.EMAIL_FROM_DOMAIN_KEY,
        'input#' + ConfigConstants.BULK_EMAIL_SUBJECT_TAG,
        'input.' + ConfigConstants.ATTACHMENT_LINK_SIZE_LIMIT,
        'input.' + ConfigConstants.ATTACHMENT_LINK_EXPIRATION_LIMIT,
      ].join(',');
      const $blurInputs = $container.find<HTMLInputElement>(blurInputsSelector);

      simpleSetValueHandler(
        $blurInputs,
        'blur',
        undefined, {
          success($element, data: DTO.SimpleSetValueResponse) {
            console.info('BULK EMAIL VALUE', { data });
            if (data.value && $element.val() !== data.value) {
              $element.val(data.value);
            }
          },
        },
      );

      const $checkboxInputs = $container.find<HTMLInputElement>(
        [
          'input#' + ConfigConstants.CLOUD_ATTACHMENT_ALWAYS_LINK,
          'input#' + ConfigConstants.PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY,
          'input#' + ConfigConstants.PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS,
        ].join(','),
      );

      simpleSetValueHandler($checkboxInputs, 'change');

      simpleSetHandler($container.find('#' + ConfigConstants.ANNOUNCEMENTS_MAILING_LIST_KEY + 'Autoconf'), 'click');
    }

    {
      const configKey = ConfigConstants.BULK_EMAIL_PRIVACY_NOTICE;
      const $container = $emailContainer.find('form.' + configKey);
      console.log('************', $container);

      const $bulkEmailPrivacyNotice = $container.find<HTMLTextAreaElement>('textarea[name="' + configKey + '"]');
      console.info('PRIV NOTICE', $bulkEmailPrivacyNotice);
      WysiwygEditor.addEditor($bulkEmailPrivacyNotice);

      simpleSetValueHandler(
        $bulkEmailPrivacyNotice,
        'blur',
        undefined, {
          success($element, data: DTO.SimpleSetValueResponse) {
            console.info('BULK EMAIL PRIVACY NOTICE', { data });
            if (data.value && $element.val() !== data.value) {
              $element.val(data.value);
            }
          },
        },
      );
    }

    {
      const $container = $emailContainer.find('form.emailtest');
      const $emailTestAddress = $container.find('#' + ConfigConstants.EMAIL_TEST_ADDRESS_KEY);

      simpleSetHandler($container.find('#emailtestbutton'), 'click');
      simpleSetValueHandler($emailTestAddress, 'blur');
      simpleSetValueHandler(
        $container.find('#' + ConfigConstants.EMAIL_TEST_MODE), 'change', undefined, {
          success(_element, _data) {
            // if (element.is(':checked')) {
            //   emailTestAddress.prop('disabled', false);
            // } else {
            //   emailTestAddress.prop('disabled', true);
            // }
          },
        });
    }

    {
      // mailing list REST stuff
      const $container = $emailContainer.find('form.mailing-list');

      const inputValues = [
        ...Object.values(ConfigConstants.MAILING_LIST_CONFIG),
        ...Object.values(ConfigConstants.MAILING_LIST_REST_CONFIG),
      ];
      const $inputs = $container.find<HTMLInputElement>(inputValues.map(x => '#' + x).join(','));

      const $password = $container.find('#' + ConfigConstants.MAILING_LIST_REST_CONFIG.password);
      showPassword($password);

      simpleSetValueHandler($inputs, 'blur');
    }

    $('form#orchestra').accordion({
      heightStyle: 'content',
    });

  }

  {
    /**************************************************************************
     *
     * street address and other address settings
     *
     *************************************************************************/

    const msg = $('#orchestra #msg');

    simpleSetValueHandler($('input[class^="' + ConfigConstants.STREET_ADDRESS_PREFIX + '"], input[class^="register"]'), 'blur', msg);

    simpleSetValueHandler(
      $('input.phoneNumber') as JQuery<HTMLInputElement>,
      'blur',
      msg, {
        success(element, data: DTO.PhoneNumberResponse, _value, _msgElement) {
          element.val(data.number);
        },
      });

    const $streetAddressCountry = $('select.' + ConfigConstants.STREET_ADDRESS_COUNTRY);
    $streetAddressCountry.chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      width: '30%',
    });
    simpleSetValueHandler($streetAddressCountry, 'change', msg);

    /**************************************************************************
     *
     * special members
     *
     *************************************************************************/

    // Set special members projects with create/rename/delete feedback
    const $specialMemberProjects = $('input[type="text"].specialMemberProjects') as JQuery<HTMLInputElement>;

    const projectsData = $specialMemberProjects.data('projects');
    let autocompleteProjects = projectsData
      ? $specialMemberProjects.data('projects').map((v: { name: string }) => v.name)
      : [];

    $specialMemberProjects.autocomplete({
      source: autocompleteProjects,
      position: { my: 'left bottom', at: 'left top' },
      minLength: 0,
    });

    $specialMemberProjects.on('focus', function(_event) {
      const $self = $(this);
      if ($self.val() === '') {
        $self.autocomplete('search', '');
      }
    });

    simpleSetValueHandler(
      $specialMemberProjects,
      'blur',
      msg, {
        success($self, data: DTO.SpecialProjectsResponse, _value, _msgElement) {
          const name = $self.attr('name');
          $('input[name="' + name + 'Create"]').prop('disabled', data.projectId > -1);
          if (data.newName) {
            $self.val(data.newName);
          }
          if (data.suggestions) {
            autocompleteProjects = data.suggestions.map((v: { name: string }) => v.name);
            $specialMemberProjects.autocomplete('option', 'source', autocompleteProjects);
          }
          if (data.feedback) {
            const feedbackOptions = ['Create', 'Rename', 'Delete'];
            for (const option of feedbackOptions) {
              if (data.feedback[option]) {
                Dialogs.confirm(
                  data.feedback[option].message,
                  data.feedback[option].title,
                  function(decision: boolean) {
                    data.feedback = decision;
                    if (decision === true) {
                      $.post(
                        setAppUrl(name + option), {
                          value: {
                            project: data.project,
                            projectId: data.projectId,
                            newName: data.newName,
                          },
                        })
                        .fail(function(xhr, status, errorThrown) {
                          Ajax.handleError(xhr, status, errorThrown);
                        })
                        .done(function(data) {
                          if (data.message) {
                            data.message = Notification.messages(data.message, { timeout: 15 });
                            msg.html(data.message.join('; ')).show();
                          }
                          if (data.suggestions) {
                            autocompleteProjects = data.suggestions.map((v: { name: string }) => v.name);
                            $specialMemberProjects.autocomplete('option', 'source', autocompleteProjects);
                          }
                          if (data.projectid) {
                            $('input[name="' + name + 'Create"]').prop('disabled', data.projectId > -1);
                          }
                        });
                    }
                  },
                  true);
              }
            }
          }
        },
      });

    type SpecialProjectCreatePayload = {
      newProjectName: string,
      projectId: number,
      projectName: string,
    };

    const $specialMemberProjectsCreate = $('input[type="button"].specialMemberProjects') as JQuery<HTMLInputElement>;
    simpleSetValueHandler(
      $specialMemberProjectsCreate, 'click', msg, {
        success(_$self, _data: DTO.SpecialProjectsResponse, _value: SpecialProjectCreatePayload, _msgElement) {},
        getValue($self, _msgElement) {
          const name = $self.attr('name')!;
          return {
            name,
            value: {
              newProjectName: $self.next().val() as string,
              projectId: $self.data('projectId')!,
              projectName: $self.data('projectName')!,
            },
          };
        },
      });

    const executiveBoardIds = $('select.executive-board-ids') as JQuery<HTMLSelectElement>;
    // ts-expect-error 2339
    executiveBoardIds.chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      inherit_select_classes: true,
      title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
      width: '100%',
    });
    selectPlaceholder(executiveBoardIds);
    simpleSetValueHandler(executiveBoardIds, 'change', msg);
    simpleSetValueHandler($('input.executive-board-ids'), 'blur', msg);

    /**************************************************************************
     *
     * bank account settings
     *
     *************************************************************************/

    const $bankAccountInputs = $('input[class^="bankAccount"]') as JQuery<HTMLInputElement>;

    $bankAccountInputs.autocomplete({
      source: [],
      minLength: 0,
    });

    const bankAccountProperties = [
      ConfigConstants.BANK_ACCOUNT_IBAN,
      ConfigConstants.BANK_ACCOUNT_BLZ,
      ConfigConstants.BANK_ACCOUNT_BIC,
      ConfigConstants.BANK_ACCOUNT_BANK_NAME,
    ] as const;

    simpleSetValueHandler(
      $bankAccountInputs,
      'blur',
      msg,
      {
        success(element, data, value, _msg) { // done
          if (data.suggestions && data.suggestions.length > 0) {
            // TODO: make the autocomplete option(s) more visible
            element.autocomplete('option', 'source', data.suggestions);
            element.autocomplete('option', 'minLength', 0);
            element.autocomplete('search', value);
          } else {
            console.debug('NO SUGGESTIONS', data.suggestion);
            element.autocomplete('option', 'source', []);
          }
          if (data.value) {
            element.val(data.value);
          }
          for (const property of bankAccountProperties) {
            if (data[property]) {
              $('input.' + property).val(data[property]);
            }
          }
        },
        fail: Ajax.handleError,
      },
    );

    simpleSetValueHandler($('select[name="' + ConfigConstants.BANK_ACCOUNT_BANK_HOLIDAYS + '"]'), 'change', msg);
  }

  {
    /**************************************************************************
     *
     * document template uploads
     *
     *************************************************************************/

    const $fieldset = $('fieldset.document-template');
    const $uploaders = $fieldset.find('input.upload-placeholder, input.upload-replace') as JQuery<HTMLInputElement>;
    const $cloudSelectors = $fieldset.find('input.select-cloud') as JQuery<HTMLInputElement>;
    const $deleters = $fieldset.find('input.delete') as JQuery<HTMLInputElement>;
    const $autofillers = $fieldset.find('input.auto-fill-test') as JQuery<HTMLInputElement>;
    const $autofillersdata = $fieldset.find('input.auto-fill-test-data') as JQuery<HTMLInputElement>;

    if ($('#documenttemplatesfolder').val() === '' || $(`#${ConfigConstants.SHARED_FOLDER}`).val() === '') {
      $fieldset.find('input').prop('disabled', true);
    }

    /**
     * @param file TBD.
     *
     * @param $container TBD.
     *
     * @param $trigger TBD.
     *
     * @todo This is the only place which actually uses upload/move,
     * it should be replace by the things the other parts of the code
     * use.
     */
    const moveIntoPlace = function(file: FileUpload.UploadFile, $container: JQuery, $trigger: JQuery) {
      const subFolderId = $container.data('documentTemplateSubFolder') || '';
      const destinationPath =
            '/' + $(`#${ConfigConstants.SHARED_FOLDER}`).val()
            + '/' + $('#documenttemplatesfolder').val()
            + (subFolderId === '' ? '' : '/' + $('#' + subFolderId).val())
            + '/' + file.original_name;

      $.post(
        generateAppUrl('upload/move'), {
          stashedFile: file.tmp_name,
          destinationPath,
          originalFileName: file.original_name,
          uploadMode: 'copy',
        })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown);
          $trigger.removeClass('busy');
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['message', 'fileName', 'downloadLink'])) {
            $trigger.removeClass('busy');
            return;
          }
          Notification.messages(data.message);
          const fileName = data.fileName;
          const downloadLink = data.downloadLink;
          $.post(
            setAppUrl($container.data('documentTemplate')), { value: fileName })
            .fail(function(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown);
              $trigger.removeClass('busy');
            })
            .done(function(data) {
              if (!Ajax.validateResponse(data, ['message'])) {
                $trigger.removeClass('busy');
                return;
              }
              Notification.messages(data.message);
              $container.find('.upload-placeholder').val(fileName).hide();
              $container.find('.downloadlink')
                .attr('href', downloadLink)
                .attr('download', fileName)
                .html(fileName)
                .show();
              $container.find('.auto-fill-test').prop('disabled', false).show();
              $container.find('.delete').prop('disabled', false);
              $trigger.removeClass('busy');
            });
        });
    };

    simpleSetHandler($deleters, 'click', undefined, {
      success($self, _data, _$msgElement) {
        $self.prop('disabled', true);
        $self.nextAll('input.upload-placeholder').val('').show();
        $self.nextAll('a.downloadlink')
          .attr('href', '')
          .html('')
          .hide();
        $self.nextAll('.auto-fill-test').hide().prop('disabled', true);
      },
    });

    $autofillers.on('click', function(_event) {
      const $self = $(this);

      $self.addClass('busy');

      fileDownload(
        'settings/app/get/auto-fill-test', {
          documentTemplate: $self.data('template'),
          format: $self.data('format'),
        }, {
          always() {
            $self.removeClass('busy');
          },
          errorMessage() {
            return t(appName, 'Unable to download auto-fill result.');
          },
        });

      return false;
    });

    // get the test-data set as JSON for offline testing
    $autofillersdata.on('click', function(_event) {
      const $self = $(this);

      $self.addClass('busy');

      fileDownload(
        'settings/app/get/auto-fill-test-data', {
          documentTemplate: $self.data('template'),
        }, {
          always() {
            $self.removeClass('busy');
          },
          errorMessage() {
            return t(appName, 'Unable to download auto-fill result.');
          },
        });

      return false;
    });

    $uploaders.on('click', function(_event) {
      const $this = $(this);
      const $container = $this.parent();

      $this.addClass('busy');

      FileUpload.init({
        url: generateAppUrl('upload/stash'),
        doneCallback(file) {
          moveIntoPlace(file, $container, $this);
        },
        stopCallback: undefined,
        failCallback(_event: any, _data: any) { $this.removeClass('busy'); },
        dropZone: $container,
        containerSelector: '.document-template-upload-wrapper',
        inputSelector: 'input[type="file"]',
        multiple: false,
      });

      $('.document-template-upload-wrapper input[type="file"]').trigger('click');
      return false;
    });

    $cloudSelectors.on('click', function(_event) {
      const $this = $(this);
      const $container = $this.closest('.template-upload');

      Dialogs.filePicker({
        title: $this.data('placeholder'),
        callback(path: string|string[]) {

          $this.addClass('busy');

          if (!path) {
            Dialogs.alert(t(appName, 'Empty response from file selection!'), t(appName, 'Error'));
            $this.removeClass('busy');
            return;
          }
          $.post(generateAppUrl('upload/stash'), { cloudPaths: [path] })
            .fail(function(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown);
              $this.removeClass('busy');
            })
            .done(function(files) {
              if (!Array.isArray(files) || files.length !== 1) {
                Dialogs.alert(
                  t(appName, 'Unable to copy selected file {file}.', { file: files }),
                  t(appName, 'Error'),
                  function() {
                    $this.removeClass('busy');
                  });
                return;
              }
              moveIntoPlace(files[0], $container, $this);
            });
        },
      });
    });
  }

  {
    /**************************************************************************
     *
     * translations via extra tables in DB
     *
     *************************************************************************/

    const $translationKeys = $('select.translation-phrases') as JQuery<HTMLSelectElement>;
    const $locales = $('select.translation-locales');
    const $translationKey = $('.translation-key');
    const $translationText = $('textarea.translation-translation');
    const $hideTranslated = $('#' + appPrefix('hide-translated'));
    const $downloadPoTemplates = $('#' + appName + '-translations-download-pot');
    const $deleteRecorded = $('#' + appName + '-translations-erase-all');
    const $msg = $('.translation.msg');

    let $key: JQuery;
    let language: string;
    let translations: { [key: string]: string };
    let translation: string;

    const updateControls = function() {
      $key = SelectUtils.selectedOptions($translationKeys);
      language = $locales.val() as string;
      translation = '';
      translations = {};

      $translationKey.html($key.text());

      if (language && $key.length === 1) {
        translations = $key.data('translations');
        translation = translations[language] || '';
      }
      $translationText.val(translation);
    };

    const showHideTranslated = function() {
      const hide = $hideTranslated.prop('checked');
      $translationKeys.find('option').each(function() {
        const $option = $(this);
        if (!hide || !language) {
          $option.show();
        } else {
          const translations = $option.data('translations');
          if (translations[language]) {
            $option.hide();
            if ($option.prop('selected')) {
              $option.prop('selected', false);
            }
          } else {
            $option.show();
          }
        }
      });
      SelectUtils.refreshWidget($translationKeys);
      $translationKeys.trigger('change');
    };

    $translationKeys.chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      width: '30%',
    });

    $locales.chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      width: '10%',
    });

    $translationKeys.on('change', function(_event) {
      updateControls();
      return false;
    });

    $locales.on('change', function(_event) {
      updateControls();
      showHideTranslated();
      return false;
    });

    $hideTranslated.on('change', function(_event) {
      showHideTranslated();
      return false;
    });

    simpleSetValueHandler(
      $translationText, 'blur', $msg, {
        success(_element, _data, _value, _$msg) { // done
          // no need to do any extra stuff?
        },
        getValue(_element, _msg) {
          if (language && $key.length === 1) {
            // save it in order to restore, maybe we want to have an
            // "OK" button in order not to accidentally damage
            // existing translations.
            translation = $translationText.val() as string;
            translations[language] = translation;
            $key.data('translations', translations);
            const val: GetValueResult = {
              name: 'translation',
              value: {
                key: $key.text(),
                language,
                translation: $translationText.val(),
              },
            };
            return val;
          }
        },
      });

    simpleSetHandler($deleteRecorded, 'click', $msg, {
      success(_$self, _data, _msgElement) {
        SelectUtils.replaceOptions($translationKeys, '');
        $translationKeys.trigger('change');
      },
    });

    $downloadPoTemplates.on('click', function(_event) {

      fileDownload(
        'settings/app/get/translation-templates',
        [], {
          errorMessage(_url: string, _data: any) {
            return t(appName, 'Unable to download translation templates.');
          },
        });
      return false;
    });

    updateControls();

  }

  /****************************************************************************
   *
   * development settings, mostly link stuff
   *
   ***************************************************************************/

  {
    const msg = $('#develsettings #msg');
    const devLinkTests = $('input.devlinktest');

    simpleSetValueHandler($('input.devlink'), 'blur', msg, {
      setup() {
        devLinkTests.prop('disabled', true);
      },
      success($self, _data, value: string, _msgElement) {
        const $testLink = $self.parent().find('a.devlinktest');
        $testLink.attr('href', value);
      },
      cleanup() {
        devLinkTests.prop('disabled', false);
      },
    });
  }

  /****************************************************************************
   *
   * CMS stuff
   *
   ***************************************************************************/

  simpleSetValueHandler($('input.redaxo'), 'blur', $('form#cmssettings .statusmessage'));

  simpleSetValueHandler($('select.redaxo'), 'change', $('form#cmssettings .statusmessage'));

  /****************************************************************************
   *
   * Tooltips
   *
   ***************************************************************************/

  toolTipsInit(container);

  container.removeClass('hidden'); // show(); // fadeIn()...

  updateLocaleTimeStamps(tabsHolder);
};

const documentReady = function(container?: JQuery|undefined) {

  if (container === undefined) {
    console.debug('default container');
    container = $(containerSelector);
  }

  container.on('tabsbeforeactivate', container.is(tabsSelector) ? null : tabsSelector, function(_event, _ui) {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
  });

  container.on('tabsactivate', container.is(tabsSelector) ? null : tabsSelector, function(_event, ui) {
    updateCreditsTimer();

    updateLocaleTimeStamps($(this));

    if (ui.newPanel[0].id === 'tabs-5') {
      $('#smtpsecure').chosen({ disable_search_threshold: 10 });
      $('#imapsecure').chosen({ disable_search_threshold: 10 });
    } else if (ui.newPanel[0].id === 'tabs-4') {
      $('div#sharing-settings').accordion('refresh');
    } else if (ui.newPanel[0].id === 'tabs-3') {
      $('form#orchestra').accordion('refresh');
    } else {
      // $('#smtpsecure').chosen().remove();
      // $('#imapsecure').chosen().remove();
    }

    $.fn.cafevTooltip.remove(); // remove pending tooltips ...
  });

  container.on(appName + ':content-update', function(event) {
    console.debug('Settings content-update');
    if (event.target === this) {
      console.debug('Settings trigger PS content-update');
      if (!container.hasClass('personal-settings')) {
        $('.personal-settings').trigger(appName + ':content-update');
      }
      afterLoad($(this));
    } else {
      console.debug('Settings ignore update on ', $(this));
    }
  });

  afterLoad(container);
};

export default documentReady;
