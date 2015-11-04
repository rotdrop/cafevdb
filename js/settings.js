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

  var adminSettings = $('#adminsettingstabs').length > 0;

  if (adminSettings) {

    $("#appsettings_popup").tabs({ selected: 0});
    $("#appsettings_popup").on("tabsselect", function (event, ui) {
      $('div.statusmessage').hide();
      $('span.statusmessage').hide();
    });

    $("#appsettings_popup").on("tabsshow", function (event, ui) {
      if (ui.index == 3) {
        $('#smtpsecure').chosen({ disable_search_threshold: 10 });
        $('#imapsecure').chosen({ disable_search_threshold: 10 });
      } else {
        //$('#smtpsecure').chosen().remove();
        //$('#imapsecure').chosen().remove();
      }
    });
  }

  $('#expertmode').change(function(event) {
    event.preventDefault();
    var post = $("#expertmode").serialize();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'expertmode.php') , post, function(data) {return;});
    if ($('#expertmode').attr('checked')) {
      $('#expertbutton').show();
      $('#expertbutton').css('float', 'left');
      $('select.debug-mode').prop('disabled', false);
    } else {
      $('#expertbutton').hide();
      $('select.debug-mode').prop('disabled', true);
    }
    $('select.debug-mode').trigger('chosen:updated');
    return false;
  });

  $('#tooltips').change(function(event) {
    event.preventDefault();
    var self = $(this);
    var post = self.serialize();
    CAFEVDB.toolTipsOnOff(self.attr('checked'));
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'tooltips.php'),
           post, function(data) {});
    return false;
  });

  $('#filtervisibility').change(function(event) {
    event.preventDefault();
    var post = $("#filtervisibility").serialize();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'filtervisibility.php') , post, function(data) {return;});
    if ($('#filtervisibility').attr('checked')) {
      $('input.pme-search').trigger('click');
    } else {
      $('input.pme-hide').trigger('click');
    }
    return false;
  });

  $('select.table-pagerows').chosen({
    disable_search:true,
    inherit_select_classes:true,
    width:'10ex'
  });
  $('select.table-pagerows').change(function(event) {
    event.preventDefault();
    var select = $(this);
    $('#cafevdb #msg').hide();

    $.post(OC.filePath('cafevdb', 'ajax/settings', 'pagerows.php'),
           select.serialize(),
           function (data) {
             if (data.status == 'success') {
               $('#cafevdb #msg').html(data.data.message);
             } else {
               $('#cafevdb #msg').html(t('cafevdb','Error:')+' '+data.data.message);
             }
             $('#cafevdb #msg').show();
             return false;
           }, 'json');

    return false;
  });

  $('select.debug-mode').chosen({
    inherit_select_classes:true,
    disable_search:true
  });
  $('select.debug-mode').change(function(event) {
    event.preventDefault();
    var select = $(this);
    $('#cafevdb #msg').hide();

    $.post(OC.filePath('cafevdb', 'ajax/settings', 'debugmode.php'),
           { debugModes: $('select.debug-mode').val() },
           function(data) {
             if (data.status == 'success') {
               $('#cafevdb #msg').html(data.data.message);
               CAFEVDB.debugModes = data.data.value;
             } else {
               $('#cafevdb #msg').html(t('cafevdb','Error:')+' '+data.data.message);
             }
             $('#cafevdb #msg').show();
             return false;
           }, 'json');

    return false;
  });

  $('select.wysiwyg-editor').chosen({ disable_search:true });
  $('select.wysiwyg-editor').change(function (event) {
    event.preventDefault();
    var select = $(this);
    $('#cafevdb #msg').hide();

    $.post(OC.filePath('cafevdb', 'ajax/settings', 'wysiwyg-editor.php'),
           select.serialize(),
           function (data) {
             if (data.status == 'success') {
               $('#cafevdb #msg').html(data.data.message);
               CAFEVDB.wysiwygEditor = data.data.value;
             } else {
               $('#cafevdb #msg').html(t('cafevdb','Error:')+' '+data.data.message);
             }
             $('#cafevdb #msg').show();
             return false;
           }, 'json');

    return false;
  });

  // 'show password' checkbox
  var tmp = $('#userkey #encryptionkey').val();
  $('#userkey #encryptionkey').showPassword();
  $('#userkey #encryptionkey').val(tmp);

  tmp = $('#userkey #password').val();
  $('#userkey #password').showPassword();
  $('#userkey #password').val(tmp);

  $("#userkey #button").click(function() {
    // We allow empty keys, meaning no encryption
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    if ($('#userkey #password').val() != '' && (true || $('#userkey #encryptionkey').val() != '')) {
      // Serialize the data
      var post = $("#userkey").serialize();
      // Ajax foo
      $.post(OC.filePath('cafevdb', 'ajax/settings', 'encryptionkey.php'),
             post, function(data) {
               if(data.status == "success") {
                 $('#userkey input[name="dbkey1"]').val('');
                 $('#userkey input[name="userkey"]').val('');
                 $('#userkey input[name="userkey-clone"]').val('');
                 $('#userkey #changed').show();
               } else{
                 $('#userkey #error').html(data.data.message);
                 $('#userkey #error').show();
               }
             },'json');
      return false;
    } else {
      $('#userkey #error').show();
      return false;
    }

  });

  $('#exampletext').blur(function(event) {
    event.preventDefault();
    var post = $("#exampletext").serialize();
    $('#cafevdb #msg').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'exampletext.php'),
           post,
           function(data) {
             if (data.status == 'success') {
               $('#cafevdb #msg').html(data.data.message);
             } else {
               $('#cafevdb #msg').html(t('cafevdb','Error:')+' '+data.data.message);
             }
             $('#cafevdb #msg').show();
             return;
           });
    return false;
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Application settings stuff
  //
  ///////////////////////////////////////////////////////////////////////////

  // Encryption-key
  // 'show password' checkbox
  var tmp =   $('#systemkey #key').val();
  $('#systemkey #key').showPassword();
  $('#systemkey #key').val(tmp);

  tmp = $('#systemkey #oldkey').val();
  $('#systemkey #oldkey').showPassword();
  $('#systemkey #oldkey').val(tmp);

  $("#keychangebutton").click(function() {
    // We allow empty keys, meaning no encryption
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    if ($('#systemkey #oldkey').val() != $('#systemkey #key').val()) {
      // Serialize the data
      var post = $("#systemkey").serialize();

      // disable form elements until we got an answer
      $('#appsettings_popup fieldset').attr('disabled', 'disabled');
      $("#appsettings_popup").tabs("disable");
      $('#systemkey #standby').show();

      // Ajax foo
      OC.Notification.show(t('cafevdb', 'Please standby, the operation will take some time!'));
      $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'), post, function(data) {
        // re-enable all forms
        $('#appsettings_popup fieldset').removeAttr('disabled');
        $("#appsettings_popup").tabs("enable");
        $('#systemkey #standby').hide();

        OC.Notification.hide();

        if (data.status == "success") {
          $('#systemkey #changed').show();
            if ($('#systemkey #key').val() == '') {
              $('#systemkey #insecure').show();
            }
          $('#systemkey input[name="oldkey"]').val('');
          $('#systemkey input[name="systemkey"]').val('');
          $('#systemkey input[name="systemkey-clone"]').val('');
          if ($('#systemkey input[name="systemkey-clone"]').is(':visible')) {
            $('#systemkey-show').removeAttr('checked');
            $('#systemkey-show').click();
            $('#systemkey-show').removeAttr('checked');
          }
        } else {
          $('#systemkey #error').html(data.data.message);
          $('#systemkey #error').show();
        }
      });
      return false;
    } else {
      $('#systemkey #equal').show();
      if ($('#systemkey #oldkey').val() == '') {
        $('#systemkey #insecure').show();
      }
      return false;
    }
  });

  $('form#systemkey #keygenerate').click(function(event) {
    event.preventDefault();

    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $('#eventsettings #msg').empty();
    if ($('form#systemkey #key').is(':visible')) {
      $('#systemkey-show').click();
    }
    // Ajax foo
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
               // Make sure both inputs have the same value
               $('form#systemkey input[name="systemkey-clone"]').val(data.data.message);
               $('form#systemkey input[name="systemkey"]').val(data.data.message);
             } else {
               $('#eventsettings #msg').html(data.data.message);
               $('#eventsettings #msg').show();
             }
             return false;
           });

    return false;
  });

  $('#keydistributebutton').click(function() {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             $('#keydistribute #msg').html(data.data.message);
             $('#keydistribute #msg').show();
           });
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // name of orchestra
  //
  ///////////////////////////////////////////////////////////////////////////

  $('#admingeneral').submit(function () { return false; });

  $('#admingeneral :input').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#admingeneral #msg').html(data.data.message);
	       $('#admingeneral #msg').show();

               if (data.data.value == '') {
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
             }
             return false;
	   }, 'json');
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // data-base
  //
  ///////////////////////////////////////////////////////////////////////////

  $('#dbgeneral :input').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#dbsettings #msg').html(data.data.message);
	       $('#dbsettings #msg').show();
             }
             return false;
	   }, 'json');
  });

  // DB-Password
  // 'show password' checkbox
  var tmp = $('fieldset.cafevdb_dbpassword #cafevdb_dbpassword').val();
  $('fieldset.cafevdb_dbpassword #cafevdb_dbpassword').showPassword();
  $('fieldset.cafevdb_dbpassword #cafevdb_dbpassword').val(tmp);
  $("fieldset.cafevdb_dbpassword #button").click(function() {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    if ($('fieldset.cafevdb_dbpassword #password').val() != '') {
      // Serialize the data
      var post = $("fieldset.cafevdb_dbpassword").serialize();
      // Ajax foo
      $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
             post, function(data) {
               if(data.status == "success") {
                 //$('#cafevdb_dbpassword input[name="dbpass1"]').val('');
                 $('fieldset.cafevdb_dbpassword input[name="password"]').val('');
                 $('fieldset.cafevdb_dbpassword input[name="password-clone"]').val('');
               }
               $('fieldset.cafevdb_dbpassword #dbteststatus').html(data.data.message);
               $('fieldset.cafevdb_dbpassword #dbteststatus').show();
             });
      return false;
    } else {
      $('fieldset.cafevdb_dbpassword #error').show();
      return false;
    }
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Sharing, share-owner
  //
  ///////////////////////////////////////////////////////////////////////////

  $('#shareowner #shareowner-force').click(function(event) {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $('#eventsettings #msg').empty();

    if (!$(this).is(':checked') &&
        $('#shareowner #user-saved').val() != '') {
      $('#shareowner #user').val($('#shareowner #user-saved').val());
      $('#shareowner #user').attr('disabled','disabled');
    } else {
      $('#shareowner #user').removeAttr('disabled');
    }
  })

  $('#shareowner #check').click(function(event) {
    event.preventDefault();

    var post = $('#shareowner').serializeArray();

    if ($('#shareowner #user').is(':disabled')) {
      var type = new Object();
      type['name']  = 'shareowner';
      type['value'] = $('#shareowner #user-saved').val();
      post.push(type);
    }

    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           post,
           function(data) {
             if (data.status == 'success') {
               $('#shareowner #user').attr('disabled',true);
               $('#shareowner #user-saved').val($('#shareowner #user').val());
               if ($('#shareowner #user').val() != '') {
                 $('div.personalblock.sharing').find('fieldset').each(function(i, elm) {
                   $(elm).removeAttr('disabled');
                 });
               } else {
                 $('#calendars,#sharedfolderform').find('fieldset').each(function(i, elm) {
                   $(elm).attr('disabled','disabled');
                 });
               }
             }
	     $('#eventsettings #msg').html(data.data.message);
	     $('#eventsettings #msg').show();
	   });
  })


  // Share-owner´s password
  // 'show password' checkbox
  $('fieldset.sharingpassword #sharingpassword').showPassword();
  $('fieldset.sharingpassword #change').click(function(event) {
    event.preventDefault();

    // We allow empty keys, meaning no encryption
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $('#eventsettings #msg').empty();

    // Generate the request by hand
    var post = Array();

    var input1 = $('fieldset.sharingpassword input[name="sharingpassword-clone"]');
    var input2 = $('fieldset.sharingpassword input[name="sharingpassword"]');

    // Check both inputs fors consistency, because we are fiddling
    // with an auto-generated password below
    var pass1 = input1.val();
    var pass2 = input2.val();

    if (pass1 != pass2) {
      var type = new Object();
      type['name']  = 'error';
      type['value'] = 'Visible and invisible passwords do not match.';
      post.push(type);
    } else if (pass1 == '') {
      var type = new Object();
      type['name']  = 'error';
      type['value'] = 'Password is empty.';
      post.push(type);
    } else {
      var type = new Object();
      type['name']  = 'sharingpassword';
      type['value'] = pass1;
      post.push(type);
    }
    // Ajax foo
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           post,
           function(data) {
             if (data.status == "success") {
               input1.val('');
               input2.val('');
               $('#sharingpassword-show').removeAttr('checked');
               $('#sharingpassword-show').click();
               $('#sharingpassword-show').removeAttr('checked');
             }
             $('#eventsettings #msg').html(data.data.message);
             $('#eventsettings #msg').show();
             return false;
           });
    return false;
  });

  $('fieldset.sharingpassword #generate').click(function(event) {
    event.preventDefault();

    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $('#eventsettings #msg').empty();
    if ($('fieldset.sharingpassword #sharingpassword').is(':visible')) {
      $('#sharingpassword-show').click();
    }
    // Ajax foo
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
               // Make sure both inputs have the same value
               $('fieldset.sharingpassword input[name="sharingpassword-clone"]').val(data.data.message);
               $('fieldset.sharingpassword input[name="sharingpassword"]').val(data.data.message);
             } else {
               $('#eventsettings #msg').html(data.data.message);
               $('#eventsettings #msg').show();
             }
             return false;
           });

    return false;
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Events, calendars
  //
  ///////////////////////////////////////////////////////////////////////////

  $('#eventsettings #calendars :input').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
	     $('#eventsettings #msg').html(data.data.message);
	     $('#eventsettings #msg').show();
	   });
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Contacts, addressbooks (actually only one ;)
  //
  ///////////////////////////////////////////////////////////////////////////

  $('#eventsettings #contacts :input').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
	     $('#eventsettings #msg').html(data.data.message);
	     $('#eventsettings #msg').show();
	   });
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Sharing, share-folder
  //
  ///////////////////////////////////////////////////////////////////////////

  $('#sharedfolderform').submit(function () { return false; });

  $('#sharedfolder-force').blur(function(event) {
    event.preventDefault();
    return false;
  });

  $('#sharedfolder-force').click(function(event) {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $('#eventsettings #msg').empty();

    if (!$(this).is(':checked') &&
        $('#sharedfolder-saved').val() != '') {
      $('#sharedfolder').val($('#sharedfolder-saved').val());
      $('#sharedfolder').attr('disabled','disabled');
    } else {
      $('#sharedfolder').removeAttr('disabled');
    }
  });

  $('#sharedfoldercheck').click(function(event) {
    event.preventDefault();

    var post = $('#sharedfolderform').serializeArray();

    if ($('#sharedfolder').is(':disabled')) {
      // Fake.
      var type = new Object();
      type['name']  = 'sharedfolder';
      type['value'] = $('#sharedfolder-saved').val();
      post.push(type);
    }

    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           post,
           function(data) {
             if (data.status == 'success') {
               $('#sharedfolder').attr('disabled',true);
               $('#sharedfolder-saved').val($('#sharedfolder').val());
             }
	     $('#eventsettings #msg').html(data.data.message);
	     $('#eventsettings #msg').show();
	   });
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Sharing, project-folder
  //
  ///////////////////////////////////////////////////////////////////////////

  $('#projectsfolderform').submit(function () { return false; });

  $('#projectsfolder-force').blur(function(event) {
    event.preventDefault();
    return false;
  });

  $('#projectsfolder-force').click(function(event) {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $('#eventsettings #msg').empty();

    if (!$(this).is(':checked') &&
        $('#projectsfoldersaved').val() != '') {
      $('#projectsfolder').val($('#projectsfoldersaved').val());
      $('#projectsfolder').attr('disabled','disabled');
    } else {
      $('#projectsfolder').removeAttr('disabled');
    }
  });

  $('#projectsfoldercheck').click(function(event) {
    event.preventDefault();

    var post = $('#projectsfolderform').serializeArray();

    if ($('#projectsfolder').is(':disabled')) {
      // Fake.
      var type = new Object();
      type['name']  = 'projectsfolder';
      type['value'] = $('#projectsfoldersaved').val();
      post.push(type);
    }

    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           post,
           function(data) {
             if (data.status == 'success') {
               $('#projectsfoldersaved').attr('value', data.data.data);
               $('#projectsfolder').attr('value', data.data.data);
               $('#projectsbalanceprojectsfolder').text(data.data.data);
               if (data.data.data != '') {
                 $('#projectsfolder').attr('disabled', true);
                 $('#projectsfolder-force').prop('checked', false);
                 $('#projectsbalancefolderform fieldset').removeAttr('disabled');
               } else {
                 $('#projectsbalancefolderform fieldset').attr('disabled',true);
               }
             }
	     $('#eventsettings #msg').html(data.data.message);
	     $('#eventsettings #msg').show();
	   });
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Sharing, project balance folder (for financial balance sheets)
  //
  ///////////////////////////////////////////////////////////////////////////

  $('#projectsbalancefolderform').submit(function () { return false; });

  $('#projectsbalancefolder-force').blur(function(event) {
    event.preventDefault();
    return false;
  });

  $('#projectsbalancefolder-force').click(function(event) {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $('#eventsettings #msg').empty();

    if (!$(this).is(':checked') &&
        $('#projectsbalancefoldersaved').val() != '') {
      $('#projectsbalancefolder').val($('#projectsbalancefoldersaved').val());
      $('#projectsbalancefolder').attr('disabled','disabled');
    } else {
      $('#projectsbalancefolder').removeAttr('disabled');
    }
  });

  $('#projectsbalancefoldercheck').click(function(event) {
    event.preventDefault();

    var post = $('#projectsbalancefolderform').serializeArray();

    if ($('#projectsbalancefolder').is(':disabled')) {
      // Fake.
      var type = new Object();
      type['name']  = 'projectsbalancefolder';
      type['value'] = $('#projectsbalancefoldersaved').val();
      post.push(type);
    }

    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           post,
           function(data) {
             if (data.status == 'success') {
               $('#projectsbalancefoldersaved').attr('value', data.data.data);
               $('#projectsbalancefolder').attr('value', data.data.data);
               $('#projectsbalancefolder').attr('disabled', true);
               $('#projectsbalancefolder-force').prop('checked', false);
             }
	     $('#eventsettings #msg').html(data.data.message);
	     $('#eventsettings #msg').show();
	   });
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // email
  //
  ///////////////////////////////////////////////////////////////////////////

  $('#emailuser').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return true;
             } else {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  // Email-Password
  // 'show password' checkbox
  var tmp = $('fieldset.emailpassword #emailpassword').val();
  $('fieldset.emailpassword #emailpassword').showPassword();
  $('fieldset.emailpassword #emailpassword').val(tmp);
  $("fieldset.emailpassword #button").click(function() {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    if ($('fieldset.emailpassword #emailpassword').val() != '') {
      // Serialize the data
      var post = $("fieldset.emailpassword").serialize();
      // Ajax foo
      $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
             post, function(data) {
               if(data.status == "success") {
                 //$('fieldset.emailpassword input[name="emailpass1"]').val('');
                 $('fieldset.emailpassword input[name="password"]').val('');
                 $('fieldset.emailpassword input[name="password-clone"]').val('');
	         $('#emailsettings #msg').html(data.data.message);
	         $('#emailsettings #msg').show();
               } else {
	         $('#emailsettings #msg').html(data.data.message);
	         $('#emailsettings #msg').show();
               }
             });
      return false;
    } else {
      $('fieldset.emailpassword #error').show();
      return false;
    }
  });

  $('[id$=noauth]:checkbox').change(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    var post = $(this);
    if (!$(this).attr('checked')) {
      post = new Array();
      var tmp = new Object();
      tmp['name']  = $(this).attr('name');
      tmp['value'] = 0;
      post.push(tmp);
    }
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           post,
           function(data) {
             if (data.status == "success") {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
             } else {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
             }
             return false;
	   }, 'json');
    return false;
  });

  $('#emaildistributebutton').click(function() {
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             $('#emaildistribute span.statusmessage').html(data.data.message);
             $('#emaildistribute span.statusmessage').show();
           });
  });

  $('[id$=secure]:input').change(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
	     $('#emailsettings #msg').html(data.data.message);
	     $('#emailsettings #msg').show();
             if (data.status == "success") {
               var proto = data.data.proto;
               $('#'+proto+'port').val(data.data.port);
               return true;
             }
             return false;
	   }, 'json');
    return false;
  });

  $('#smtpport,#imapport').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return true;
             } else {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  $('#smtpserver,#imapserver').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return true;
             } else {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  $('#emailfromname').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return true;
             } else {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  $('#emailfromaddress').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return true;
             } else {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  $('#emailtestbutton').click(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return true;
             } else {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  })

  $('#emailtestmode').change(function(event) {
    event.preventDefault();
    var post = $(this);
    if (!$(this).is(':checked')) {
      post = new Array();
      var tmp = new Object();
      tmp['name']  = $(this).attr('name');
      tmp['value'] = 'off';
      post.push(tmp);
    }
    $('#emailsettings #msg').empty();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           post, function(data) {
      if (data.status == "success") {
	$('#emailsettings #msg').html(data.data.message);
	$('#emailsettings #msg').show();
        if ($('#emailtestmode').is(':checked')) {
          $('#emailtestaddress').removeAttr('disabled');
        } else {
          $('#emailtestaddress').attr('disabled',true);
        }
        return true;
      } else {
	$('#emailsettings #msg').html(data.data.message);
      }
      return false;
    });
    return false;
  });

  $('#emailtestaddress').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return true;
             } else {
	       $('#emailsettings #msg').html(data.data.message);
	       $('#emailsettings #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // street address settings
  //
  ///////////////////////////////////////////////////////////////////////////

  $('input[class^="streetAddress"], input.phoneNumber').blur(function(event) {
    var self = $(this);
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           self,
           function(data) {
             if (data.status == "success") {
               self.val(data.data.value);
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               return true;
             } else {
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  })

  $('select.streetAddressCountry').
    chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      width: '30%'
    }).
    on('change', function(event) {
    var self = $(this);
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           self,
           function(data) {
             if (data.status == "success") {
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               return true;
             } else {
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // special members
  //
  ///////////////////////////////////////////////////////////////////////////

  $('input.specialMemberTables').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               return true;
             } else {
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  })


  $('select.executive-board-ids').
    chosen({
      disable_search_threshold: 10,
      allow_single_deselect: true,
      width: '30%'
    }).
    on('change', function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               return true;
             } else {
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // bank account settings
  //
  ///////////////////////////////////////////////////////////////////////////

  $('input[class^="bankAccount"]').blur(function(event) {
    event.preventDefault();
    var element = this;
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
               if (data.data.value) {
                 $(element).val(data.data.value);
               }
               if (data.data.iban) {
                 $('input.bankAccountIBAN').val(data.data.iban);
               }
               if (data.data.blz) {
                 $('input.bankAccountBLZ').val(data.data.blz);
               }
               if (data.data.bic) {
                 $('input.bankAccountBIC').val(data.data.bic);
               }
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               if ($('#orchestra #suggestion').html() !== '') {
	         $('#orchestra #suggestion').show();
               }
               return true;
             } else {
               if (data.data.suggestion !== '') {
	         $('#orchestra #suggestion').html(data.data.suggestion);
               }
	       $('#orchestra #msg').html(data.data.message);
	       $('#orchestra #msg').show();
               if ($('#orchestra #suggestion').html() !== '') {
	         $('#orchestra #suggestion').show();
               }
               return false;
             }
	   }, 'json');
    return false;
  })

  ///////////////////////////////////////////////////////////////////////////
  //
  // development settings, mostly link stuff
  //
  ///////////////////////////////////////////////////////////////////////////

  $('input.devlink').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#develsettings #msg').html(data.data.message);
	       $('#develsettings #msg').show();
               return true;
             } else {
	       $('#develsettings #msg').html(data.data.message);
	       $('#develsettings #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  $('input.devlinktest').click(function (event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('#develsettings #msg').html(data.data.message);
	       $('#develsettings #msg').show();
               window.open(data.data.target, data.data.link);
               return true;
             } else {
	       $('#develsettings #msg').html(data.data.message);
	       $('#develsettings #msg').show();
               return false;
             }
	   }, 'json');
    return false;
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // CMS stuff
  //
  ///////////////////////////////////////////////////////////////////////////

  $('input.redaxo').blur(function(event) {
    event.preventDefault();
    $('div.statusmessage').hide();
    $('span.statusmessage').hide();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'redaxo-settings.php'),
           $(this),
           function(data) {
             if (data.status == "success") {
	       $('.statusmessage').html(data.data.message);
	       $('.statusmessage').show();
               return true;
             } else {
	       $('.statusmessage').html(data.data.message);
	       $('.statusmessage').show();
               return false;
             }
	   }, 'json');
    return false;
  })

  ///////////////////////////////////////////////////////////////////////////
  //
  // Credits list
  //
  ///////////////////////////////////////////////////////////////////////////

  var loadCredits = function() {
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'creditslist.php'),
           {},
           function(data) {
             if (!CAFEVDB.ajaxErrorHandler(data, [ 'contents' ])) {
               return false;
             }
             $('div.cafevdb.about div.product.credits.list').html(data.data.contents);
             return false;
           });
  };
  if (CAFEVDB.creditsTimer > 0) {
    clearInterval(CAFEVDB.creditsTimer);
  }
  CAFEVDB.creditsTimer = setInterval(function() {
                           if ($('div.cafevdb.about div.product.credits.list').length > 0) {
                             loadCredits()
                           } else {
                             clearInterval(CAFEVDB.creditsTimer);
                           }
                         }, 30000);


  ///////////////////////////////////////////////////////////////////////////
  //
  // Tooltips
  //
  ///////////////////////////////////////////////////////////////////////////

  CAFEVDB.toolTipsInit('#appsettings_popup');

});

// Local Variables: ***
// js3-indent-level: 2 ***
// End: ***
