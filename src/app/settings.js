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

import { appName, $ } from './globals.js';
import * as Ajax from './ajax.js';
import * as Notification from './notification.js';
import * as Dialogs from './dialogs.js';
import * as FileUpload from './file-upload.js';
import generateUrl from './generate-url.js';
import { simpleSetHandler, simpleSetValueHandler } from './simple-set-value.js';
import { toolTipsInit } from './cafevdb.js';
import { setPersonalUrl, setAppUrl, getUrl } from './settings-urls.js';
import fileDownload from './file-download.js';

require('../legacy/nextcloud/jquery/showpassword.js');
require('jquery-file-download');
require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');
require('jquery-ui/ui/widgets/accordion');
require('jquery-ui/ui/widgets/tabs');

require('settings.scss');
require('about.scss');

/**
 * Permanent DOM element holding the dynamically injected settings
 * forms.
 */
const containerSelector = 'div.app-admin-settings';
const tabsSelector = '#personal-settings-container';

/**
 * Initialize handlers etc. Contents of container may be replaced by
 * AJAX calls. This function initializes all dynamic
 * elements. Everything attached to the container is initialized in
 * the $(document).ready() callback.
 *
 * @param {jQUery} container Should be a permanent DOM element.
 *
 */
const afterLoad = function(container) {

  container = container || $(containerSelector);
  const tabsHolder = $(tabsSelector);

  if (!container.is(':parent')) {
    // nothing to do, empty container
    return;
  }

  tabsHolder.tabs({ selected: 0 });

  // Work around showPassword erasing the value and returns the
  // text input clone.
  const showPassword = function(element) {
    const tmp = element.val();
    let showElement;
    element.showPassword(function(args) {
      showElement = args.clone;
    });
    element.val(tmp);
    return showElement;
  };

  // 'show password' checkbox
  const encryptionKey = $('#userkey #encryptionkey');
  const loginPassword = $('#userkey #password');
  showPassword(encryptionKey);
  showPassword(loginPassword);

  $('#userkey #button').click(function() {
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
      setPersonalUrl('encryptionkey'), {
        value: {
          encryptionkey: encryptionKey.val(),
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
      })
      .fail(function(xhr, status, errorThrown) {
        const failData = Ajax.handleError(xhr, status, errorThrown);
        $('#userkey .info').html(failData.message);
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
      adminGeneral.find(':input'), 'blur', msg, {
        success(element, data, value, msg) {
          if (value === '') {
            $('div.personalblock.admin,div.personalblock.sharing').find('fieldset').each(function(i, elm) {
              $(elm).prop('disabled', true);
            });
          } else {
            $('div.personalblock.admin').find('fieldset').each(function(i, elm) {
              $(elm).removeAttr('disabled');
            });
            if ($('#shareowner #user-saved').val() !== '') {
              $('div.personalblock.sharing').find('fieldset').each(function(i, elm) {
                $(elm).removeAttr('disabled');
              });
            } else {
              $('#shareownerform').find('fieldset').each(function(i, elm) {
                $(elm).removeAttr('disabled');
              });
            }
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
              container.find('.statusmessage.general').html(data.message).show();
            }
          })
          .fail(function(xhr, status, errorThrown) {
            $(tabsSelector + ' fieldset').prop('disabled', false);
            $(tabsSelector).tabs('enable');
            container.find('.statusmessage.standby').hide();

            Notification.hide();

            $('.statusmessage.error').show();
            const msg = Ajax.failMessage(xhr, status, errorThrown);
            if (msg) {
              container.find('.statusmessage.general').html(msg).show();
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

    $('form#systemkey #keygenerate').on('click', function(event) {
      $('.statusmessage').hide();

      // show the visible password text input
      if ($('form#systemkey #key').is(':visible')) {
        $('#systemkey-show').click();
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
          if (data.message !== '') {
            msg.html(data.message).show();
          }
        });
      return false;
    });

    $('#keydistributebutton').on('click', function(even) {
      const msg = form.find('fieldset.keydistribute .statusmessage');
      form.find('.statusmessage').hide();
      const name = $(this).attr('name');
      $.post(setAppUrl(name))
        .fail(function(xhr, status, errorThrown) {
          const failData = Ajax.handleError(xhr, status, errorThrown);
          msg.html(failData.message).show();
        })
        .done(function(data) {
          msg.html(data.message).show();
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
    const dbPassword = $('fieldset.cafevdb_dbpassword #cafevdb-dbpassword');
    showPassword(dbPassword);

    // test password
    simpleSetValueHandler(
      $('fieldset.cafevdb_dbpassword #button'), 'click', $('fieldset.cafevdb_dbpassword .statusmessage'), {
        success(element, data, value) {
          // $('fieldset.cafevdb_dbpassword input[name="dbpassword"]').val('');
          // $('fieldset.cafevdb_dbpassword input[name="dbpassword-clone"]').val('');
        },
        getValue(element, msg) {
          const val = { name: dbPassword.attr('name'), value: dbPassword.val() };
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

  {
    const container = $('#shareowner');
    const msg = $('#shareownerform .statusmessage');
    const shareOwner = container.find('#user');
    const shareOwnerSaved = container.find('#user-saved');
    const shareOwnerForce = container.find('#shareowner-force');
    const shareOwnerCheck = container.find('#check');

    shareOwnerForce.on('change', function(event) {
      msg.hide();
      if (!$(this).is(':checked') && shareOwnerSaved.val() !== '') {
        shareOwner.val(shareOwnerSaved.val());
        shareOwner.prop('disabled', true);
      } else {
        shareOwner.prop('disabled', false);
      }
      return false;
    });

    shareOwner.on('blur', function(event) {
      shareOwnerCheck.prop('disabled', shareOwner.val() === '');
      return false;
    });

    simpleSetValueHandler(
      shareOwnerCheck, 'click', msg, {
        sucess(element, data, value, msg) { // done
          shareOwner.prop('disabled', true);
          shareOwnerSaved.val(shareOwner.val());
          if (shareOwner.val() !== '') {
            $('div.personalblock.sharing').find('fieldset').each(function(i, elm) {
              $(elm).removeAttr('disabled');
            });
          } else {
            $('#calendars,#sharedfolderform').find('fieldset').each(function(i, elm) {
              $(elm).prop('disabled', true);
            });
          }
        },
        getValue(element, msg) { // getValue
          return {
            name: 'shareowner',
            value: {
              shareowner: shareOwner.val(),
              'shareowner-saved': shareOwnerSaved.val(),
              'shareowner-force': shareOwnerForce.is(':checked'),
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
        success(element, data, value, msg) { // done
          // Why should we want to empty this except for security reasons?
          // password.val('');
          // passwordClone.val('');
        },
        getValue(element, msg) {
          let val = { name: password.attr('name'), value: password.val() };
          if (val.value === '') {
            msg.html(t(appName, 'Password field must not be empty')).show();
            val = undefined;
          }
          return val;
        },
      });

    container.find('#generate').on('click', function(event) {
      $('.statusmessage').hide();
      msg.hide();

      // show the visible password input
      if (password.is(':visible')) {
        $('#shareownerpassword-show').click();
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
        success($self, data, value, msgElement) {
          if (data.value.name && data.value.name !== value) {
            $self.val(data.value.name);
          }
        },
      }
    );

    container.find('#sharedfolder-form').submit(function() { return false; }); // @@TODO ???

    const sharedFolder = function(cssBase, callback) {
      const css = cssBase;
      const cssSaved = cssBase + '-saved';
      const cssForce = cssBase + '-force';
      const cssCheck = cssBase + '-check';
      const sharedObject = container.find('#' + css);
      const sharedObjectSaved = container.find('#' + cssSaved);
      const sharedObjectForce = container.find('#' + cssForce);
      const sharedObjectCheck = container.find('#' + cssCheck);

      sharedObjectForce.blur(function(event) { // @@TODO ???
        return false;
      });

      sharedObjectForce.click(function(event) {
        msg.hide();
        if (!sharedObjectForce.is(':checked') && sharedObjectSaved.val() !== '') {
          sharedObject.val(sharedObjectSaved.val());
          sharedObject.prop('disabled', true);
        } else {
          sharedObject.prop('disabled', false);
        }
      });

      simpleSetValueHandler(
        sharedObjectCheck, 'click', msg, {
          success(element, data, value, msg) { // done
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
          getValue(element, msg) { // getValue
            return {
              name: css,
              value: {
                [css]: sharedObject.val(),
                [cssSaved]: sharedObjectSaved.val(),
                [cssForce]: sharedObjectForce.is(':checked'),
              },
            };
          },
        });
    };

    /**************************************************************************
     *
     * Sharing, share-folder, projects-folder, projects balance folder
     *
     *************************************************************************/

    sharedFolder('sharedfolder', function(element, css, data, value, msg) {
      $('div#sharing-settings span.sharedfolder').html(value[css]); // update display
      const $folderView = $('#sharedfolder-fieldset').find('a.sharedfolder-view');
      $folderView.attr('href', data.folderLink || '');
      if (data.folderLink) {
        $folderView.removeClass('hidden');
      } else {
        $folderView.addClass('hidden');
      }
    });
    sharedFolder('projectsfolder', function(element, css, data, value, msg) {
      $('#projectsbalancefolder-fieldset').prop('disabled', value[css] === '');
      $('#projectparticipantsfolder-fieldset').prop('disabled', value[css] === '');
      $('div#sharing-settings span.projectsfolder').html(value[css]); // update
    });
    sharedFolder('projectparticipantsfolder');
    sharedFolder('projectsbalancefolder');
    sharedFolder('documenttemplatesfolder', function(element, css, data, value, msg) {
      $('fieldset.document-template input').prop('disabled', value[css] === '');
    });
    sharedFolder('postboxfolder');

  } // shared objects

  /****************************************************************************
   *
   * email
   *
   ***************************************************************************/

  {
    const emailContainer = $('div#email-settings');

    $('div#email-settings').accordion({
      heightStyle: 'content',
    });

    {
      const container = emailContainer.find('fieldset.emailuser');

      $('#emailuser').blur(function(event) {
        const name = $(this).attr('name');
        const value = $(this).val();
        $.post(
          setAppUrl(name), { value })
          .fail(function(xhr, status, errorThrown) {
            Notification.messages(Ajax.failMessage(xhr, status, errorThrown));
          })
          .done(function(data) {
            Notification.messages(data.message);
          });
        return false;
      });

      // Email-Password
      // 'show password' checkbox
      const password = container.find('#emailpassword');
      showPassword(password);
      const passwordChange = container.find('#button');

      passwordChange.on('click', function() {
        const value = password.val();
        const name = password.attr('name');
        if (value !== '') {
          $.post(
            setAppUrl(name), { value })
            .fail(function(xhr, status, errorThrown) {
              Notification.messages(Ajax.failMessage(xhr, status, errorThrown));
            })
            .done(function(data) {
              Notification.messages(data.message);
            });
        } else {
          Notification.messages(t(appName, 'Password field must not be empty'));
        }
        return false;
      });
    } // fieldset emailuser

    { // eslint-disable-line
      // const container = form.find('#emaildistribute');

      $('#emaildistributebutton').click(function() {
        const name = $(this).attr('name');
        $.post(
          setAppUrl(name))
          .fail(function(xhr, status, errorThrown) {
            Notification.messages(Ajax.failMessage(xhr, status, errorThrown));
          })
          .done(function(data) {
            Notification.messages(data.message);
          });
        return false;
      });
    }

    {
      const container = emailContainer.find('form.serversettings');

      $('[id$=security]:input').change(function(event) {
        const name = $(this).attr('name');
        const value = $(this).val();
        $.post(
          setAppUrl(name), { value })
          .fail(function(xhr, status, errorThrown) {
            Notification.messages(Ajax.failMessage(xhr, status, errorThrown));
          })
          .done(function(data) {
            if (data.port) {
              $('#' + data.proto + 'port').val(data.port);
            }
            Notification.messages(data.message);
          });
        return false;
      });

      container.find('#smtpport,#imapport,#smtpserver,#imapserver').blur(function(event) {
        const $self = $(this);
        const name = $(this).attr('name');
        const value = $(this).val();
        $.post(setAppUrl(name), { value })
          .fail(function(xhr, status, errorThrown) {
            Notification.messages(Ajax.failMessage(xhr, status, errorThrown));
          })
          .done(function(data) {
            if (data[name]) {
              $self.val(data[name]);
            }
            Notification.messages(data.message);
          });
        return false;
      });
    }

    {
      const container = emailContainer.find('form.emailidentity');
      console.log('************', container);

      container.find('#emailfromname,#emailfromaddress').on('blur', function(event) {
        const name = $(this).attr('name');
        const value = $(this).val();
        $.post(
          setAppUrl(name), { value })
          .fail(function(xhr, status, errorThrown) {
            Notification.messages(Ajax.failMessage(xhr, status, errorThrown))
          })
          .done(function(data) {
            Notification.messages(data.message);
          });
        return false;
      });
    }

    {
      const container = emailContainer.find('form.emailtest');
      const emailTestAddress = container.find('#emailtestaddress');

      simpleSetHandler(container.find('#emailtestbutton'), 'click');
      simpleSetValueHandler(emailTestAddress, 'blur');
      simpleSetValueHandler(
        container.find('#emailtestmode'), 'change', undefined, {
          success(element, data) {
            if (element.is(':checked')) {
              emailTestAddress.prop('disabled', false);
            } else {
              emailTestAddress.prop('disabled', true);
            }
          },
        });
    }

    {
      // mailing list REST stuff
      const container = emailContainer.find('form.mailing-list');

      const password = container.find('#mailingListRestPassword');
      showPassword(password);

      $('#mailingListServer, #mailingListRestUser, #mailingListRestPassword').blur(function(event) {
        const name = $(this).attr('name');
        const value = $(this).val();
        $.post(
          setAppUrl(name), { value })
          .fail(function(xhr, status, errorThrown) {
            Notification.messages(Ajax.failMessage(xhr, status, errorThrown));
          })
          .done(function(data) {
            Notification.messages(data.message);
          });
        return false;
      });

    }

    $('form#orchestra').accordion({
      heightStyle: 'content',
    });

  }

  {
    /**************************************************************************
     *
     * street address settings
     *
     *************************************************************************/

    const msg = $('#orchestra #msg');

    simpleSetValueHandler($('input[class^="streetAddress"]'), 'blur', msg);

    simpleSetValueHandler(
      $('input.phoneNumber'),
      'blur',
      msg, {
        success(element, data, value, msgElement) {
          console.info(data);
          element.val(data.number);
        },
      });

    const streetAddressCountry = $('select.streetAddressCountry');
    streetAddressCountry.chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      width: '30%',
    });
    simpleSetValueHandler(streetAddressCountry, 'change', msg);

    /**************************************************************************
     *
     * special members
     *
     *************************************************************************/

    // Set special members projects with create/rename/delete feedback
    const specialMemberProjects = $('input[type="text"].specialMemberProjects');

    const projectsData = specialMemberProjects.data('projects');
    console.info('PROJECTS', projectsData);
    let autocompleteProjects = projectsData
      ? specialMemberProjects.data('projects').map(v => v.name)
      : [];

    specialMemberProjects.autocomplete({
      source: autocompleteProjects,
      position: { my: 'left bottom', at: 'left top' },
      minLength: 0,
    });

    specialMemberProjects.on('focus', function(event) {
      const $self = $(this);
      if ($self.val() === '') {
        $self.autocomplete('search', '');
      }
    });

    simpleSetValueHandler(specialMemberProjects, 'blur', msg, {
      success($self, data, value, msgElement) {
        const name = $self.attr('name');
        $('input[name="' + name + 'Create"]').prop('disabled', data.projectId > -1);
        if (data.newName) {
          $self.val(data.newName);
        }
        if (data.suggestions) {
          autocompleteProjects = data.suggestions.map(v => v.name);
          specialMemberProjects.autocomplete('option', 'source', autocompleteProjects);
        }
        if (data.feedback) {
          const feedbackOptions = ['Create', 'Rename', 'Delete'];
          for (const option of feedbackOptions) {
            if (data.feedback[option]) {
              Dialogs.confirm(
                data.feedback[option].message,
                data.feedback[option].title,
                function(decision) {
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
                          autocompleteProjects = data.suggestions.map(v => v.name);
                          specialMemberProjects.autocomplete('option', 'source', autocompleteProjects);
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

    const specialMemberProjectsCreate = $('input[type="button"].specialMemberProjects');
    simpleSetValueHandler(
      specialMemberProjectsCreate, 'click', msg, {
        success($self, data, value, msgElement) {},
        getValue($self, msgElement) {
          const name = $self.attr('name');
          return {
            name,
            value: {
              newProjectName: $self.next().val(),
              projectId: $self.data('projectId'),
              projectName: $self.data('projectName'),
            },
          };
        },
      });

    const executiveBoardIds = $('select.executive-board-ids');
    executiveBoardIds.chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      inherit_select_classes: true,
      width: '100%',
    });
    simpleSetValueHandler(executiveBoardIds, 'change', msg);

    /**************************************************************************
     *
     * bank account settings
     *
     *************************************************************************/

    const bankAccountInputs = $('input[class^="bankAccount"]');

    bankAccountInputs.autocomplete({
      source: [],
      minLength: 0,
    });

    const bankAccountProperties = [
      'bankAccountIBAN',
      'bankAccountBLZ',
      'bankAccountBIC',
    ];

    simpleSetValueHandler(
      bankAccountInputs,
      'blur',
      msg,
      {
        success(element, data, value, msg) { // done
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
          console.info('BK DATA', data);
          for (const property of bankAccountProperties) {
            if (data[property]) {
              console.info('SET BK PROP', data[property], $('input.' + property));
              $('input.' + property).val(data[property]);
            }
          }
        },
        fail: Ajax.handleError,
      },
    );
  }

  {
    /**************************************************************************
     *
     * document template uploads
     *
     *************************************************************************/

    const $fieldset = $('fieldset.document-template');
    const $uploaders = $fieldset.find('input.upload-placeholder, input.upload-replace');
    const $cloudSelectors = $fieldset.find('input.select-cloud');
    const $deleters = $fieldset.find('input.delete');
    const $autofillers = $fieldset.find('input.auto-fill-test');

    if ($('#documenttemplatesfolder').val() === '' || $('#sharedfolder').val() === '') {
      $fieldset.find('input').prop('disabled', true);
    }

    const moveInPlace = function(file, $container) {
      const destinationPath =
            '/' + $('#sharedfolder').val()
            + '/' + $('#documenttemplatesfolder').val()
            + '/' + file.original_name;

      $.post(
        generateUrl('upload/move'), {
          stashedFile: file.name,
          destinationPath,
        })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['message', 'fileName', 'downloadLink'])) {
            return;
          }
          Notification.messages(data.message);
          const fileName = data.fileName;
          const downloadLink = data.downloadLink;
          $.post(
            setAppUrl($container.data('documentTemplate')), { value: fileName })
            .fail(function(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown);
            })
            .done(function(data) {
              if (!Ajax.validateResponse(data, ['message'])) {
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
              console.info(data);
            });
        });
    };

    simpleSetHandler($deleters, 'click', undefined, {
      success($self, data, msgElement) {
        $self.prop('disabled', true);
        $self.nextAll('input.upload-placeholder').val('').show();
        $self.nextAll('a.downloadlink')
          .attr('href', '')
          .html('')
          .hide();
        $self.nextAll('.auto-fill-test').hide().prop('disabled', true);
      },
    });

    $autofillers.on('click', function(event) {
      const $self = $(this);

      fileDownload(
        'settings/app/get/auto-fill-test', {
          documentTemplate: $self.data('template'),
        }, {
          errorMessage(data, url) {
            return t(appName, 'Unable to download auto-fill result.');
          },
        });

      return false;
    });

    $uploaders.on('click', function(event) {
      const $this = $(this);
      const $container = $this.parent();

      FileUpload.init({
        url: generateUrl('upload/stash'),
        doneCallback(file, index, container) {
          console.info('FILE', file, container);
          moveInPlace(file, $container);
        },
        stopCallback: null,
        dropZone: $container,
        containerSelector: '.document-template-upload-wrapper',
        inputSelector: 'input[type="file"]',
        multiple: false,
      });

      $('.document-template-upload-wrapper input[type="file"]').trigger('click');
      return false;
    });

    $cloudSelectors.on('click', function(event) {
      const $this = $(this);
      const $container = $this.closest('.template-upload');

      Dialogs.filePicker(
        $this.data('placeholder'),
        function(path) {
          if (!path) {
            Dialogs.alert(t(appName, 'Empty response from file selection!'), t(appName, 'Error'));
            return;
          }
          $.post(generateUrl('upload/stash'), { cloudPaths: [path] })
            .fail(function(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown);
            })
            .done(function(files) {
              console.info('FILES', files);
              if (!Array.isArray(files) || files.length !== 1) {
                Dialogs.alert(
                  t(appName, 'Unable to copy selected file {file}.', { file: paths[0] }),
                  t(appName, 'Error'));
              }
              moveInPlace(files[0], $container);
            });
        },
        false, // multi-select
        '', // sub-directory
        [] // options
      );
    });
  }

  {
    /**************************************************************************
     *
     * translations via extra tables in DB
     *
     *************************************************************************/

    const translationKeys = $('select.translation-phrases');
    const locales = $('select.translation-locales');
    const translationKey = $('.translation-key');
    const translationText = $('textarea.translation-translation');
    const hideTranslated = $('#cafevdb-hide-translated');
    const downloadPoTemplates = $('#' + appName + '-translations-download-pot');
    const deleteRecorded = $('#' + appName + '-translations-erase-all');
    const msg = $('.translation.msg');

    let key;
    let language;
    let translations;
    let translation;

    const updateControls = function() {
      key = translationKeys.find('option:selected');
      language = locales.val();
      translation = '';
      translations = {};

      translationKey.html(key.text());

      if (language && key.length === 1) {
        translations = key.data('translations');
        translation = translations[language] || '';
      }
      translationText.val(translation);
    };

    const showHideTranslated = function() {
      const hide = hideTranslated.prop('checked');
      translationKeys.find('option').each(function(idx, option) {
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
      translationKeys.trigger('chosen:updated');
      translationKeys.trigger('change');
      console.info('update options');
    };

    translationKeys.chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      width: '30%',
    });

    locales.chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      width: '10%',
    });

    translationKeys.on('change', function(event) {
      updateControls();
      return false;
    });

    locales.on('change', function(event) {
      updateControls();
      showHideTranslated();
      return false;
    });

    hideTranslated.on('change', function(event) {
      showHideTranslated();
      return false;
    });

    simpleSetValueHandler(
      translationText, 'blur', msg, {
        success(element, data, value, msg) { // done
          // no need to do any extra stuff?
        },
        getValue(element, msg) {
          let val;
          if (language && key.length === 1) {
            // save it in order to restore, maybe we want to have an
            // "OK" button in order not to accidentally damage
            // existing translations.
            translation = translationText.val();
            translations[language] = translation;
            key.data('translations', translations);
            val = {
              name: 'translation',
              value: {
                key: key.text(),
                language,
                translation: translationText.val(),
              },
            };
          }
          return val;
        },
      });

    simpleSetHandler(deleteRecorded, 'click', msg, {
      success($self, data, msgElement) {
        translationKeys.html('');
        translationKeys.trigger('chosen:updated');
        translationKeys.trigger('change');
      },
    });

    downloadPoTemplates.on('click', function(event) {

      fileDownload(
        'settings/app/get/translation-templates',
        [], {
          errorMessage(data, url) {
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
      setup() { devLinkTests.prop('disabled', true); },
      cleanup() { devLinkTests.prop('disabled', false); },
    });

    devLinkTests.on('click', function(event) {
      const target = $(this).attr('name');
      $.post(
        getUrl(target))
        .fail(function(xhr, status, errorThrown) {
          msg.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        })
        .done(function(data) {
          console.info('Open dev-link', data.value.link, data.value.target);
          window.open(data.value.link, data.value.target);
        });
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

  container.removeClass('hidden');// show(); // fadeIn()...
};

const documentReady = function(container) {

  if (container === undefined) {
    console.debug('default container');
    container = $(containerSelector);
  }

  container.on('tabsbeforeactivate', container.is(tabsSelector) ? null : tabsSelector, function(event, ui) {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
  });

  container.on('tabsactivate', container.is(tabsSelector) ? null : tabsSelector, function(event, ui) {
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
  });

  container.on('cafevdb:content-update', function(event) {
    console.debug('Settings content-update');
    if (event.target === this) {
      console.debug('Settings trigger PS content-update');
      if (!container.hasClass('personal-settings')) {
        $('.personal-settings').trigger('cafevdb:content-update');
      }
      afterLoad($(this));
    } else {
      console.debug('Settings ignore update on ', $(this));
    }
  });

  afterLoad(container);
};

export default documentReady;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
