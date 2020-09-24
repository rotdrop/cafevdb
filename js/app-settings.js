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

// @@TODO these are rather personal settings

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
    CAFEVDB.toolTipsOnOff(self.prop('checked'));
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/tooltips'),
           { 'value': CAFEVDB.toolTipsEnabled })
    .done(function(data) {
      console.log(data);
    })
    .fail(function(data) {
      console.log(data);
    });
    $('#tooltips').prop('checked', CAFEVDB.toolTipsEnabled);
    return false;
  });

  appNav.on('change', '#app-settings-filtervisibility', function(event) {
    event.preventDefault();
    const self = $(this);
    const checked = self.prop('checked');
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/filtervisibility'),
           { 'value': checked })
    .done(function(data) {
      console.log(data);
    })
    .fail(function(data) {
      console.log(data);
    });
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
    var checked = self.prop('checked')
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/directchange'),
           { 'value': checked })
    .done(function(data) {
      console.log(data);
    })
    .fail(function(data) {
      console.log(data);
    });
    PHPMYEDIT.directChange = checked;
    $('#directchange').prop('checked', checked);
    return false;
  });

  appNav.on('change', '#app-settings-showdisabled', function(event) {
    event.preventDefault();
    var self = $(this);
    var checked = self.prop('checked')
    var post = self.serialize();
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/showdisabled'),
           { 'value': checked })
    .done(function(data) {
      console.log(data);
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
    })
    .fail(function(data) {
      console.log(data);
    });
    PHPMYEDIT.showdisabled = checked;
    $('#showdisabled').prop('checked', checked);
    return false;
  });

  appNav.on('change', '#app-settings-expertmode', function(event) {
    event.preventDefault();
    var self = $(this);
    var checked = self.prop('checked');
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/expertmode'),
           { 'value': checked })
    .done(function(data) {
      console.log(data);
      var pme = PHPMYEDIT;
      var pmeForm = $('#content '+pme.formSelector());
      pmeForm.each(function(index) {
        var reload = $(this).find(pme.pmeClassSelector('input', 'reload')).first();
        reload.trigger('click');
      });
    })
    .fail(function(data) {
      console.log(data);
    });
    if (checked) {
      $('#app-settings-content li.expertmode').removeClass('hidden');
    } else {
      $('#app-settings-content li.expertmode').addClass('hidden');
    }
    $('#expertmode').prop('checked', checked);
    $('select.debug-mode').prop('disabled', false).trigger('chosen:updated');
    return false;
  });

  appNav.on('change', '#app-settings-table-pagerows', function(event) {
    event.preventDefault();
    var self = $(this);
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/pagerows'),
           { 'value': self.val() })
    .done(function(data) {
      console.log(data);
    })
    .fail(function(data) {
      console.log(data);
    });
    return false;
  });

  appNav.on('click', '#app-settings-further-settings', function(event) {
    event.stopImmediatePropagation();

    OC.appSettings({
      appid:'cafevdb',
      loadJS:true,
      cache:false,
      scriptName:'../../settings/user/cafevdb'
    });

    $("#personal-settngs-container").tabs({ selected: 0});

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
    const self = $(this);
    var post = self.serializeArray();
    console.log(post);
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/debugmode'),
           { 'value': post })
    .done(function(data) {
      console.log(data);
      CAFEVDB.debugModes = data.value;
    })
    .fail(function(data) {
      console.log(data);
    });
    return false;
  });

});
