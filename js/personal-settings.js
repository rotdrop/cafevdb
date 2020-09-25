/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
    $('.personal-settings select.debugmode').chosen({
      disable_search:true,
      inherit_select_classes:true,
      width:'100%'
    });

    $('.personal-settings select.pagerows').chosen({
      disable_search:true,
      inherit_select_classes:true,
      width:'10ex'
    });

  });

  $('.personal-settings .tooltips').on('change', function(event) {
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
    $('.personal-settings input[type="checkbox"].tooltips').prop('checked', CAFEVDB.toolTipsEnabled);
    if (CAFEVDB.toolTipsEnabled) {
      $('#tooltipbutton').removeClass('tooltips-disabled').addClass('tooltips-enabled');
    } else {
      $('#tooltipbutton').removeClass('tooltips-enabled').addClass('tooltips-disabled');
    }
    return false;
  });

  $('.personal-settings .filtervisibility').on('change', function(event) {
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
    $('.personal-settings input[type="checkbox"].filtervisibility').prop('checked', checked)
    return false;
  });

  $('.personal-settings .directchange').on('change', function(event) {
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
    if (window.PHPMYEDIT !== undefined) {
      PHPMYEDIT.directChange = checked;
    }
    $('.personal-settings input[type="checkbox"].directchange').prop('checked', checked)
    return false;
  });

  $('.personal-settings .showdisabled').on('change', function(event) {
    var self = $(this);
    var checked = self.prop('checked')
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/showdisabled'),
           { 'value': checked })
    .done(function(data) {
      console.log(data);
      if (window.PHPMYEDIT !== undefined) {
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
      }
      return false;
    })
    .fail(function(data) {
      console.log(data);
    });
    if (window.PHPMYEDIT !== undefined) {
      PHPMYEDIT.showdisabled = checked;
    }
    $('.personal-settings input[type="checkbox"].showdisabled').prop('checked', checked);
    return false;
  });

  $('.personal-settings .expertmode').on('change', function(event) {
    var self = $(this);
    var checked = self.prop('checked');
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/expertmode'),
           { 'value': checked })
    .done(function(data) {
      console.log(data);
      if (window.PHPMYEDIT !== undefined) {
        const pme = PHPMYEDIT;
        const pmeForm = $('#content '+pme.formSelector());
        pmeForm.each(function(index) {
          const reload = $(this).find(pme.pmeClassSelector('input', 'reload')).first();
          reload.trigger('click');
        });
      }
    })
    .fail(function(data) {
      console.log(data);
    });
    if (checked) {
      $('.expertmode-container').removeClass('hidden');
    } else {
      $('.expertmode-container').addClass('hidden');
    }
    $('.personal-settings input[type="checkbox"].expertmode').prop('checked', checked);
    $('select.debug-mode').prop('disabled', false).trigger('chosen:updated');
    $.fn.cafevTooltip.remove(); // remove any left-over items.
    return false;
  });

  $('.personal-settings .pagerows').on('change', function(event) {
    const self = $(this);
    const value = self.val();
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/pagerows'),
           { 'value': value })
    .done(function(data) {
      console.log(data);
    })
    .fail(function(data) {
      console.log(data);
    });
    $('.personal-settings select.pagerows').val(value);
    return false;
  });

  $('.personal-settings .debugmode').on('change', function(event) {
    const self = $(this);
    const post = self.serializeArray();
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
    // TODO cross update options.
    return false;
  });

  $('.personal-settings .wysiwyg').on('change', function(event) {
    const self = $(this);
    const value = self.val();
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/wysiwyg'),
           { 'value': value })
    .done(function(data) {
      console.log(data);
    })
    .fail(function(data) {
      console.log(data);
    });
    $('.personal-settings select.wysiwyg').val(value);
    return false;
  });

  ///////////////////////////////////////////////////////////////////////
  //
  // chosen stuff
  //

  $('.personal-settings .pagerows').chosen({
    disable_search:true,
    inherit_select_classes:true,
    width:'10ex'
  });

  $('.personal-settings .wysiwyg-editor').chosen({
    inherit_select_classes:true,
    disable_search:true
  });

  $('.personal-settings .debugmode').chosen({
    inherit_select_classes:true,
    disable_search:true,
    width:'100%'
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Credits list
  //
  ///////////////////////////////////////////////////////////////////////////

  const updateCredits = function()
  {
    const numItems = 5;
    var items = [];
    const numTotal = $('div.cafevdb.about div.product.credits.list ul li').length;
    for (var i = 0; i < numItems; ++i) {
      items.push(Math.round(Math.random()*(numTotal-1)));
    }
    $('div.cafevdb.about div.product.credits.list ul li').each(function(index) {
      if (items.includes(index)) {
        $(this).removeClass('hidden');
      } else {
        $(this).addClass('hidden');
      }
    });
  };

  if (CAFEVDB.creditsTimer > 0) {
    clearInterval(CAFEVDB.creditsTimer);
  }

  CAFEVDB.creditsTimer = setInterval(function() {
                           if ($('div.cafevdb.about div.product.credits.list:visible').length > 0) {
                             console.log("Updating credits.");
                             updateCredits()
                           } else {
                             console.log("Clearing credits timer.");
                             clearInterval(CAFEVDB.creditsTimer);
                           }
                         }, 30000);

  ///////////////////////////////////////////////////////////////////////////
  //
  // Tooltips
  //
  ///////////////////////////////////////////////////////////////////////////

  console.log('PS', $('#personal-settings-container').length);
  CAFEVDB.toolTipsInit('#personal-settings-container');

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
