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

  CAFEVDB.addReadyCallback(function() {

    $('#app-settings-content select.debug-mode').chosen({
      disable_search:true,
      inherit_select_classes:true,
      width:'100%'
    });

    $('#app-settings-content select.table-pagerows').chosen({
      disable_search:true,
      inherit_select_classes:true,
      width:'10ex'
    });

  });

  var appNav = $('#app-navigation');

  appNav.on('click keydown', '#app-settings-header', function(event) {
    if ($('#app-settings').hasClass('open')) {
      $('#app-settings').switchClass('open', '');
    } else {
      $('#app-settings').switchClass('', 'open');
    }
    $('#app-settings-header').cafevTooltip('hide');
    CAFEVDB.unfocus('#app-settings-header');
    return false;
  });

  appNav.on('change', '#app-settings-tooltips', function(event) {
    event.preventDefault();
    var self = $(this);
    var post = self.serialize();
    CAFEVDB.toolTipsOnOff(self.prop('checked'));
    $('#tooltips').prop('checked', CAFEVDB.toolTipsEnabled);
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'tooltips.php'),
           post, function(data) {});
    return false;
  });

  appNav.on('change', '#app-settings-filtervisibility', function(event) {
    event.preventDefault();
    var self = $(this);
    var post = self.serialize();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'filtervisibility.php'),
           post,
           function(data) {
             return;
           });
    var checked = self.prop('checked');
    if (checked) {
      $('input.pme-search').trigger('click');
    } else {
      $('input.pme-hide').trigger('click');
    }
    $('#filtervisibility').prop('checked', checked)
    return false;
  });

  appNav.on('change', '#app-settings-directchange', function(event) {
    event.preventDefault();
    var self = $(this);
    var post = self.serialize();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'directchange.php'),
           post,
           function(data) {
             return;
           });
    var checked = self.prop('checked')
    PHPMYEDIT.directChange = checked;
    $('#directchange').prop('checked', checked);
    return false;
  });

  appNav.on('change', '#app-settings-showdisabled', function(event) {
    event.preventDefault();
    var self = $(this);
    var post = self.serialize();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'showdisabled.php'),
           post,
           function(data) {
             var pme = PHPMYEDIT;
             var pmeForm = $('#content '+pme.formSelector()+'.show-hide-disabled');
             console.log('form',pmeForm);
             pmeForm.each(function(index) {
                       var form = $(this);
                       var reload = form.find(pme.pmeClassSelector('input', 'reload')).first();
                       if (reload.length > 0) {
                         form.append('<input type="hidden"'
                                    + ' name="'+pme.pmeSys('sw')+'"'
                                    + ' value="Clear"/>');
                         reload.trigger('click');
                       }
                     });
             return;
           });
    var checked = self.prop('checked')
    PHPMYEDIT.showdisabled = checked;
    $('#showdisabled').prop('checked', checked);
    return false;
  });

  appNav.on('change', '#app-settings-expertmode', function(event) {
    event.preventDefault();
    var self = $(this);
    var post = self.serialize();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'expertmode.php'),
           post,
           function(data) {
             var pme = PHPMYEDIT;
             var pmeForm = $('#content '+pme.formSelector());
             pmeForm.each(function(index) {
               var reload = $(this).find(pme.pmeClassSelector('input', 'reload')).first();
               reload.trigger('click');
             });
           });
    var checked = self.prop('checked');
    if (checked) {
      $('#app-settings-content li.expertmode').removeClass('hidden');
      $('#app-settings-content select.debug-mode').trigger('chosen:updated');
    } else {
      $('#app-settings-content li.expertmode').addClass('hidden');
    }
    $('#expertmode').prop('checked', checked);
    $('select.debug-mode').trigger('chosen:updated');
    return false;
  });

  appNav.on('change', '#app-settings-table-pagerows', function(event) {
    event.preventDefault();
    var self = $(this);
    var post = self.serialize();
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'pagerows.php'),
           post, function (data) {});

    return false;
  });

  appNav.on('click', '#app-settings-further-settings', function(event) {
    event.stopImmediatePropagation();

    $("#appsettings").tabs({ selected: 0});

    OC.appSettings({
      appid:'cafevdb',
      loadJS:true,
      cache:false,
      scriptName:'settings.php'
    });

    return false;
  });

  appNav.on('click', '#app-settings-expert-operations', function(event) {
    event.stopImmediatePropagation();

    OC.appSettings({
      appid:'cafevdb',
      loadJS:'expertmode.js',
      cache:false,
      scriptName:'expert.php'
    });
  });

  appNav.on('change', 'select#app-settings-debugmode', function(event) {
    event.preventDefault();
    var select = $(this);
    $.post(OC.filePath('cafevdb', 'ajax/settings', 'debugmode.php'),
           { debugModes: select.val() },
           function(data) {
             if (data.status == 'success') {
               CAFEVDB.debugModes = data.data.value;
             }
             return false;
           }, 'json');

    return false;
  });

});
