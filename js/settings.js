/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

//OCP.Loader.loadScript('cafevdb', 'personal-settings.js');
//OCP.Loader.loadStyle('cafevdb', 'settings.css');

var CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {
  'use strict';

  var Settings = function() {};

  /**Permanent DOM element holding the dynamically injected settings
   * forms.
   */
  Settings.containerSelector = 'div.app-admin-settings';
  Settings.tabsSelector = '#personal-settings-container';

  Settings.documentReady = function(container) {
    if (container === undefined) {
      console.log('default container');
      container = $(Settings.containerSelector);
    }

    container.on("tabsselect", Settings.tabsContainer, function (event, ui) {
      $('div.statusmessage').hide();
      $('span.statusmessage').hide();
    });

    container.on("tabsshow", Settings.tabsContainer, function (event, ui) {
      if (ui.index == 3) {
	$('#smtpsecure').chosen({ disable_search_threshold: 10 });
	$('#imapsecure').chosen({ disable_search_threshold: 10 });
      } else {
	//$('#smtpsecure').chosen().remove();
	//$('#imapsecure').chosen().remove();
      }
    });

    container.on('cafevdb:content-update', function(event) {
      console.log('S content-update');
      if (event.target == this) {
        console.log('S trigger PS content-update');
	if (!container.hasClass('personal-settings')) {
	  $('.personal-settings').trigger('cafevdb:content-update');
	}
	Settings.afterLoad($(this));
      } else {
        console.log('S ignore update on ', $(this));
      }
    });

    Settings.afterLoad(container);
  };

  /**Initialize handlers etc. Contents of container may be replaced by
   * AJAX calls. This function initializes all dynamic
   * elements. Everything attached to the container is initialized in
   * the $(document).ready() callback.
   *
   * @param container Should be a permanent DOM element.
   *
   */
  Settings.afterLoad = function(container) {

    container = container || $(Settings.containerSelector);
    const tabsHolder = $(Settings.tabsSelector);

    tabsHolder.tabs({ selected: 0});

    // Work around showPassword erasing the value and returns the
    // text input clone.
    const showPassword = function(element) {
      const tmp = element.val();
      var showElement;
      element.showPassword(function(args) {
        showElement = args.clone;
      });
      element.val(tmp);
      return showElement;
    };

    // AJAX call with a simple value
    const simpleSetValueHandler = function(element, eventType, msgElement, callback, getValue) {
      element.on(eventType, function(event) {
        msgElement.hide();
        $('.statusmessage').hide();
        const self = $(this);
        var name;
        var value;
        if (getValue !== undefined && (value = getValue(element, msgElement)) !== undefined) {
          name = value.name;
          value = value.value;
        } else {
          name = self.attr('name');
          value = element.is(':checkbox') ? element.is(':checked') : self.val();
        }
        if (value !== undefined) {
          $.post(
	    OC.generateUrl('/apps/cafevdb/settings/app/set/' + name),
            { 'value': value })
	  .fail(function(xhr, status, errorThrown) {
            msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
          })
          .done(function(data) {
            if (callback !== undefined) {
              callback(element, data, value, msgElement);
            }
            msgElement.html(data.message).show();
          });
        }
        return false;
      });
    };

    // AJAX call without a value
    const simpleSetHandler = function(element, eventType, msgElement) {
      element.on(eventType, function(event) {
        msgElement.hide();
        const name = $(this).attr('name');
        $.post(
	  OC.generateUrl('/apps/cafevdb/settings/app/set/' + name))
	.fail(function(xhr, status, errorThrown) {
          msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
        })
        .done(function(data) {
          msgElement.html(data.message).show();
        });
        return false;
      });
    };

    // 'show password' checkbox
    const encryptionKey = $('#userkey #encryptionkey');
    const loginPassword = $('#userkey #password');
    showPassword(encryptionKey);
    showPassword(loginPassword);

    $("#userkey #button").click(function() {
      // We allow empty keys, meaning no encryption
      $('div.statusmessage').hide();
      $('span.statusmessage').hide();
      if (loginPassword.val() == '') {
        $('#userkey .info').html(t('cafevdb', 'You must type in your login password.'));
        $('#userkey .info').show();
        $('#userkey .error').show();
        return false;
      }
      $.post(
	OC.generateUrl('/apps/cafevdb/settings/personal/set/encryptionkey'),
        { 'value': {'encryptionkey': encryptionKey.val(),
                    'loginpassword': loginPassword.val()
                   }
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
          $('#userkey .info').html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown));
          $('#userkey .info').show();
          $('#userkey .error').show();
        });
      return false;
    });

    ///////////////////////////////////////////////////////////////////////////
    //
    // Application settings stuff
    //
    ///////////////////////////////////////////////////////////////////////////

    // name of orchestra

    {
      const adminGeneral = $('#admingeneral');
      const msg = adminGeneral.find('.msg');

      simpleSetValueHandler(
        adminGeneral.find(':input'), 'blur', msg,
        function(element, data, value, msg) {
	  if (value == '') {
            $('div.personalblock.admin,div.personalblock.sharing').find('fieldset').each(function(i, elm) {
              $(elm).attr('disabled','disabled');
            });
	  } else {
            $('div.personalblock.admin').find('fieldset').each(function(i, elm) {
              $(elm).removeAttr('disabled');
            });
            if ($('#shareowner #user-saved').val() != '') {
              $('div.personalblock.sharing').find('fieldset').each(function(i, elm) {
                $(elm).removeAttr('disabled');
              });
            } else {
              $('#shareownerform').find('fieldset').each(function(i, elm) {
                $(elm).removeAttr('disabled');
              });
            }
	  }
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
      const oldKeyInputClone = showPassword(oldKeyInput)

      $("#keychangebutton").on('click', function() {
        // We allow empty keys, meaning no encryption
        form.find('.statusmessage').hide();
        if (oldKeyInput.val() != keyInput.val()) {

	  // disable form elements until we got an answer
	  $(Settings.tabsSelector + ' fieldset').prop('disabled', true);
	  $(Settings.tabsSelector).tabs("disable");
	  container.find('.statusmessage.standby').show();

	  CAFEVDB.Notification.show(t('cafevdb', 'Please standby, the operation will take some time!'));

	  $.post(
	    OC.generateUrl('/apps/cafevdb/settings/personal/set/systemkey'),
            { 'value': { 'systemkey': keyInput.val(),
                         'oldkey': oldKeyInput.val() }
            })
          .done(function(data) {
            // re-enable all forms
            $(Settings.tabsSelector + ' fieldset').prop('disabled', false);
            $(Settings.tabsSelector).tabs("enable");
            container.find('.statusmessage.standby').hide();

            CAFEVDB.Notification.hide();

            if (keyInput.val() == '') {
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
            $(Settings.tabsSelector + ' fieldset').prop('disabled', false);
            $(Settings.tabsSelector).tabs("enable");
            container.find('.statusmessage.standby').hide();

            CAFEVDB.Notification.hide();

            $('.statusmessage.error').show();
            const msg = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown);
            if (msg) {
              container.find('.statusmessage.general').html(msg).show();
            }
          });
        } else {
          container.find('.statusmessage.equal').show();
	  if (oldKeyInput.val() == '') {
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
	  OC.generateUrl('/apps/cafevdb/settings/get/passwordgenerate'))
        .fail(function(xhr, status, errorThrown) {
          msg.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
        })
        .done(function(data) {
	  // Make sure both inputs have the same value
          keyInput.val(data.value);
	  keyInputClone.val(data.value);
          if (data.message != '') {
            msg.html(data.message).show();
          }
        });
        return false;
      });

      $('#keydistributebutton').on('click', function(even) {
        const msg = form.find('fieldset.keydistribute .statusmessage');
        form.find('.statusmessage').hide();
        const name = $(this).attr('name');
        $.post(
	  OC.generateUrl('/apps/cafevdb/settings/app/set/' + name))
	.fail(function(xhr, status, errorThrown) {
          msg.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
        })
        .done(function(data) {
          msg.html(data.message).show();
        });
        return false;
      });
    }

    ///////////////////////////////////////////////////////////////////////////
    //
    // data-base
    //
    ///////////////////////////////////////////////////////////////////////////

    simpleSetValueHandler($('#dbgeneral :input'), 'blur', $('#dbsettings #msg'));

    // DB-Password
    // 'show password' checkbox
    const dbPassword = $('fieldset.cafevdb_dbpassword #cafevdb-dbpassword')
    showPassword(dbPassword);

    // test password
    simpleSetValueHandler(
      $("fieldset.cafevdb_dbpassword #button"), 'click', $('fieldset.cafevdb_dbpassword .statusmessage'),
      function(element, data, value) {
        //$('fieldset.cafevdb_dbpassword input[name="dbpassword"]').val('');
        //$('fieldset.cafevdb_dbpassword input[name="dbpassword-clone"]').val('');
      },
      function(element, msg) {
        var val = { 'name': dbPassword.attr('name'), 'value': dbPassword.val() };
        if (val.value == '') {
          msg.html(t('cafevdb', 'Password field must not be empty')).show();
          val = undefined;
        }
        return val;
      });

    ///////////////////////////////////////////////////////////////////////////
    //
    // Sharing, share-owner
    //
    ///////////////////////////////////////////////////////////////////////////

    {
      const container = $('#shareowner');
      const msg = $('#shareownerform .statusmessage');
      const shareOwner = container.find('#user');
      const shareOwnerSaved = container.find('#user-saved');
      const shareOwnerForce = container.find('#shareowner-force');
      const shareOwnerCheck = container.find('#check');

      shareOwnerForce.on('change', function(event) {
        msg.hide();
        if (!$(this).is(':checked') && shareOwnerSaved.val() != '') {
	  shareOwner.val(shareOwnerSaved.val());
	  shareOwner.prop('disabled', true);
        } else {
          shareOwner.prop('disabled', false);
        }
        return false;
      })

      shareOwner.on('blur', function(event) {
        shareOwnerCheck.prop("disabled", shareOwner.val() == '');
        return false;
      });

      simpleSetValueHandler(
        shareOwnerCheck, 'click', msg,
        function(element, data, value, msg) { // done
	  shareOwner.attr('disabled', true);
	  shareOwnerSaved.val(shareOwner.val());
	  if (shareOwner.val() != '') {
            $('div.personalblock.sharing').find('fieldset').each(function(i, elm) {
              $(elm).removeAttr('disabled');
            });
	  } else {
            $('#calendars,#sharedfolderform').find('fieldset').each(function(i, elm) {
              $(elm).attr('disabled','disabled');
            });
	  }
        },
        function(element, msg) { // getValue
          return { 'name': 'shareowner',
                   'value': { 'shareowner': shareOwner.val(),
                              'shareowner-saved': shareOwnerSaved.val(),
                              'shareowner-force': shareOwnerForce.is(':checked') ? true : false }
                 };
        });
    } // fieldset block

    // Share-owner´s password
    {
      let container = $('fieldset.sharingpassword');
      const password = container.find('#sharingpassword');
      const change = container.find('#change');
      const msg = $('#shareownerform .statusmessage');

      const passwordClone = showPassword(password);

      simpleSetValueHandler(
        change, 'click', msg,
        function(element, data, value, msg) { // done
          // Why should we want to empty this except for security reasons?
          //password.val('');
          //passwordClone.val('');
        },
        function(element, msg) {
          var val = { 'name': password.attr('name'), 'value': password.val() };
          if (val.value == '') {
            msg.html(t('cafevdb', 'Password field must not be empty')).show();
            val = undefined;
          }
          return val;
        });

      container.find('#generate').on('click', function(event) {
        $('.statusmessage').hide();
        msg.hide();

        // show the visible password input
        if (password.is(':visible')) {
	  $('#sharingpassword-show').click();
        }

        $.post(
	  OC.generateUrl('/apps/cafevdb/settings/get/passwordgenerate'))
        .fail(function(xhr, status, errorThrown) {
          msg.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
        })
        .done(function(data) {
          // TODO check integrity of return etc.
          password.val(data.value);
          passwordClone.val(data.value);
          if (data.message != '') {
            msg.html(data.message).show();
          }
        });
        return false;
      });
    }

    { // shared objects
      const container = $('#sharing-settings');
      const msg = container.find('.statusmessage.sharing-settings');

      ///////////////////////////////////////////////////////////////////////////
      //
      // Events, calendars, contacts
      //
      ///////////////////////////////////////////////////////////////////////////

      simpleSetValueHandler(container.find('#calendars :input, #contacts :input'), 'blur', msg);

      ///////////////////////////////////////////

      const sharedFolder =
      function(cssBase, callback) {
        const form = container.find('#' + cssBase + '-form');
        const css = cssBase;
        const cssSaved = cssBase + '-saved';
        const cssForce = cssBase + '-force';
        const cssCheck = cssBase + '-check';
        const sharedObject = container.find('#' + css);
        const sharedObjectSaved = container.find('#' + cssSaved);
        const sharedObjectForce = container.find('#' + cssForce);
        const sharedObjectCheck = container.find('#' + cssCheck);

        form.submit(function () { return false; }); // @@TODO ???

        sharedObjectForce.blur(function(event) { //@@TODO ???
          return false;
        });

        sharedObjectForce.click(function(event) {
          msg.hide();
          if (!sharedObjectForce.is(':checked') && sharedObjectSaved.val() != '') {
	    sharedObject.val(sharedObjectSaved.val());
	    sharedObject.prop('disabled', true);
          } else {
	    sharedObject.prop('disabled', false);
          }
        });

        simpleSetValueHandler(
          sharedObjectCheck, 'click', msg,
          function(element, data, value, msg) { // done
            // value is just the thing submitted to the AJAX call
	    sharedObject.val(data.value);
	    sharedObjectSaved.val(data.value);
            if (value != '') {
	      sharedObject.prop('disabled', true);
              sharedObjectForce.prop('checked', false);
            }
            if (callback !== undefined) {
              callback(element, data, value, msg);
            }
          },
          function(element, msg) { // getValue
            return { 'name': css,
                     'value': { [css]: sharedObject.val(),
                                [cssSaved]: sharedObjectSaved.val(),
                                [cssForce]: sharedObjectForce.is(':checked') ? true : false }
                   };
          });
      };

      ///////////////////////////////////////////////////////////////////////////
      //
      // Sharing, share-folder, projects-folder, projects balance folder
      //
      ///////////////////////////////////////////////////////////////////////////

      sharedFolder('sharedfolder');
      sharedFolder('projectsfolder', function(element, data, value, msg) {
        $('#projectsbalancefolderform fieldset').prop('disabled', value == '');
      });
      sharedFolder('projectsbalancefolder');

    } // shared objects

    ///////////////////////////////////////////////////////////////////////////
    //
    // email
    //
    ///////////////////////////////////////////////////////////////////////////
    {
      const form = $('#emailsettings');
      const msg = form.find('.statusmessage');

      {
        const container = form.find('fieldset.emailuser');
        //const msg = container.find('.statusmessage');

        $('#emailuser').blur(function(event) {
          msg.hide();
          const name = $(this).attr('name');
          const value = $(this).val();
          $.post(
	    OC.generateUrl('/apps/cafevdb/settings/app/set/' + name),
            { 'value': value })
	  .fail(function(xhr, status, errorThrown) {
            msg.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
          })
          .done(function(data) {
            msg.html(data.message).show();
          });
          return false;
        });

        // Email-Password
        // 'show password' checkbox
        const password = container.find('#emailpassword');
        const passwordClone = showPassword(password);
        const passwordChange = container.find('#button');

        passwordChange.on('click', function() {
          msg.hide();
          const value = password.val();
          const name = password.attr('name');
          if (value != '') {
            $.post(
	      OC.generateUrl('/apps/cafevdb/settings/app/set/' + name),
              { 'value': value })
	    .fail(function(xhr, status, errorThrown) {
              msg.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
            })
            .done(function(data) {
              msg.html(data.message).show();
            });
          } else {
            msg.html(t('cafevdb', 'Password field must not be empty')).show();
          }
          return false;
        });
      } // fieldset emailuser

      {
        const container = form.find('#emaildistribute');
        //const msg = container.find('.statusmessage');

        $('#emaildistributebutton').click(function() {
          msg.hide();
          const name = $(this).attr('name');
          $.post(
	    OC.generateUrl('/apps/cafevdb/settings/app/set/' + name))
	  .fail(function(xhr, status, errorThrown) {
            msg.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
          })
          .done(function(data) {
            msg.html(data.message).show();
          });
          return false;
        });
      }

      {
        const container = form.find('fieldset.serversettings');
        //const msg = container.find('.statusmessage');

        $('[id$=secure]:input').change(function(event) {
          msg.hide();
          const name = $(this).attr('name');
          const value = $(this).val();
          $.post(
	    OC.generateUrl('/apps/cafevdb/settings/app/set/' + name),
            { 'value': value })
	  .fail(function(xhr, status, errorThrown) {
            msg.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
          })
          .done(function(data) {
	    $('#'+data.proto+'port').val(data.port);
            msg.html(data.message).show();
          });
          return false;
        });

        container.find('#smtpport,#imapport,#smtpserver,#imapserver').blur(function(event) {
          msg.hide();
          const name = $(this).attr('name');
          const value = $(this).val();
          $.post(
	    OC.generateUrl('/apps/cafevdb/settings/app/set/' + name),
            { 'value': value })
	  .fail(function(xhr, status, errorThrown) {
            msg.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
          })
          .done(function(data) {
            msg.html(data.message).show();
          });
          return false;
        });
      }

      {
        const container = form.find('fieldset.emailidentity');
        console.log('************', container);

        container.find('#emailfromname','#emailfromaddress').on('blur', function(event) {
          msg.hide();
          const name = $(this).attr('name');
          const value = $(this).val();
          $.post(
	    OC.generateUrl('/apps/cafevdb/settings/app/set/' + name),
            { 'value': value })
	  .fail(function(xhr, status, errorThrown) {
            msg.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
          })
          .done(function(data) {
            msg.html(data.message).show();
          });
          return false;
        });
      }

      {
        const container = form.find('fieldset.emailtest');
        const emailTestAddress = container.find('#emailtestaddress');
        console.log('***************', emailTestAddress);

        simpleSetHandler(container.find('#emailtestbutton'), 'click', msg);
        simpleSetValueHandler(emailTestAddress, 'blur', msg);
        simpleSetValueHandler(
          container.find('#emailtestmode'), 'change', msg,
          function(element, data) {
	    if (element.is(':checked')) {
	      emailTestAddress.prop('disabled', false);
	    } else {
	      emailTestAddress.prop('disabled',true);
	    }
          });
      }
    }

    {
      ///////////////////////////////////////////////////////////////////////////
      //
      // street address settings
      //
      ///////////////////////////////////////////////////////////////////////////

      const msg = $('#orchestra #msg');

      simpleSetValueHandler(
        $('input[class^="streetAddress"], input.phoneNumber'), 'blur', msg);

      const streetAddressCountry = $('select.streetAddressCountry');
      streetAddressCountry.chosen({
	disable_search_threshold: 10,
	allow_single_deselect: true,
	width: '30%'
      });
      simpleSetValueHandler(streetAddressCountry, 'change', msg);

      ///////////////////////////////////////////////////////////////////////////
      //
      // special members
      //
      ///////////////////////////////////////////////////////////////////////////

      simpleSetValueHandler($('input.specialMemberTables'), 'blur', msg);

      const executiveBoardIds = $('select.executive-board-ids');
      executiveBoardIds.chosen({
	disable_search_threshold: 10,
	allow_single_deselect: true,
	inherit_select_classes:true,
	width: '30%'
      });
      simpleSetValueHandler(executiveBoardIds, 'change', msg);

      ///////////////////////////////////////////////////////////////////////////
      //
      // bank account settings
      //
      ///////////////////////////////////////////////////////////////////////////

      simpleSetValueHandler(
        $('input[class^="bankAccount"]'),
        'blur',
        msg,
        function(element, data, value, msg) { // done
          data = data.value;
	  if (data.suggestion) {
	    $('#orchestra #suggestion').html(data.suggestion).show();
          } else {
	    $('#orchestra #suggestion').empt().hide();
          }
          if (data.value) {
            element.val(data.value);
          }
	  if (data.iban) {
            $('input.bankAccountIBAN').val(data.iban);
	  }
	  if (data.blz) {
            $('input.bankAccountBLZ').val(data.blz);
	  }
	  if (data.bic) {
            $('input.bankAccountBIC').val(data.data.bic);
	  }
        });
    }

    ///////////////////////////////////////////////////////////////////////////
    //
    // development settings, mostly link stuff
    //
    ///////////////////////////////////////////////////////////////////////////

    {
      const msg = $('#develsettings #msg');

      simpleSetValueHandler($('input.devlink'), 'blur', msg);
      simpleSetValueHandler(
        $('input.devlinktest'), 'click', msg,
        function (element, data, value, msg) {
	  window.open(data.value.target, data.value.link);
        });
    }

    ///////////////////////////////////////////////////////////////////////////
    //
    // CMS stuff
    //
    ///////////////////////////////////////////////////////////////////////////

    simpleSetValueHandler($('input.redaxo'), 'blur', $('form#cmssettings .statusmessage'));

    ///////////////////////////////////////////////////////////////////////////
    //
    // Tooltips
    //
    ///////////////////////////////////////////////////////////////////////////

    CAFEVDB.toolTipsInit(container);

  };

  CAFEVDB.Settings = Settings;

})(window, jQuery, CAFEVDB);


$(document).ready(function() {
  CAFEVDB.Settings.documentReady();
});

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
