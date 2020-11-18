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

$(function() {

  const container = $('.personal-settings');
  var msgElement = $('form.personal-settings .statusmessage');

  const chosenInit = function(container) {
    container.find('select.pagerows').each(function(index) {
      const self = $(this);
      //console.log("chosen pagerows", self);
      if (CAFEVDB.chosenActive(self)) {
        self.chosen('destroy');
      }
      self.chosen({
        disable_search:true,
        inherit_select_classes:true,
        width:'10ex'
      });
    });

    container.find('select.wysiwyg-editor').each(function(index) {
      const self = $(this);
      if (CAFEVDB.chosenActive(self)) {
        self.chosen('destroy');
      }
      self.chosen({
        inherit_select_classes:true,
        disable_search:true
      });
    });

    container.find('select.debugmode').each(function(index) {
      const self = $(this);
      //console.log("chosen debugmode", self);
      if (CAFEVDB.chosenActive(self)) {
        self.chosen('destroy');
      }
      self.chosen({
        inherit_select_classes:true,
        disable_search:true,
        width:'100%'
      });
    });
  };

  container.on('cafevdb:content-update', function(event) {
    if (event.target == this) {
      chosenInit($(this));
      msgElement = $('form.personal-settings .statusmessage');
    }
  });

  //chosenInit(container);

  CAFEVDB.addReadyCallback(function() {
    chosenInit(container);
  });

  container.on('change', '.tooltips', function(event) {
    var self = $(this);
    CAFEVDB.toolTipsOnOff(self.prop('checked'));
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/tooltips'),
           { 'value': CAFEVDB.toolTipsEnabled })
      .done(function(data) {
        msgElement.html(data.message).show();
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
        console.error(data);
      });
    $('.personal-settings input[type="checkbox"].tooltips').prop('checked', CAFEVDB.toolTipsEnabled);
    if (CAFEVDB.toolTipsEnabled) {
      $('#tooltipbutton').removeClass('tooltips-disabled').addClass('tooltips-enabled');
    } else {
      $('#tooltipbutton').removeClass('tooltips-enabled').addClass('tooltips-disabled');
    }
    return false;
  });

  container.on('change', '.filtervisibility', function(event) {
    const self = $(this);
    const checked = self.prop('checked');
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/filtervisibility'),
           { 'value': checked })
    .done(function(data) {
      msgElement.html(data.message).show();
      console.log(data);
    })
    .fail(function(xhr, status, errorThrown) {
      msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
      console.error(data);
    });
    if (checked) {
      $('input.pme-search').trigger('click');
    } else {
      $('input.pme-hide').trigger('click');
    }
    $('.personal-settings input[type="checkbox"].filtervisibility').prop('checked', checked)
    return false;
  });

  container.on('change', '.directchange', function(event) {
    var self = $(this);
    var checked = self.prop('checked')
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/directchange'),
           { 'value': checked })
    .done(function(data) {
      msgElement.html(data.message).show();
      console.log(data);
    })
    .fail(function(xhr, status, errorThrown) {
      msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
      console.error(data);
    });
    if (window.PHPMYEDIT !== undefined) {
      PHPMYEDIT.directChange = checked;
    }
    $('.personal-settings input[type="checkbox"].directchange').prop('checked', checked)
    return false;
  });

  container.on('change', '.showdisabled', function(event) {
    var self = $(this);
    var checked = self.prop('checked')
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/showdisabled'),
           { 'value': checked })
    .done(function(data) {
      msgElement.html(data.message).show();
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
    .fail(function(xhr, status, errorThrown) {
      msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
      console.error(data);
    });
    if (window.PHPMYEDIT !== undefined) {
      PHPMYEDIT.showdisabled = checked;
    }
    $('.personal-settings input[type="checkbox"].showdisabled').prop('checked', checked);
    return false;
  });

  container.on('change', '.expertmode', function(event) {
    var self = $(this);
    var checked = self.prop('checked');
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/expertmode'),
           { 'value': checked })
    .done(function(data) {
      msgElement.html(data.message).show();
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
    .fail(function(xhr, status, errorThrown) {
      msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
      console.error(data);
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

  container.on('change', '.pagerows', function(event) {
    const self = $(this);
    const value = self.val();
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/pagerows'),
           { 'value': value })
    .done(function(data) {
      msgElement.html(data.message).show();
      console.log(data);
    })
    .fail(function(xhr, status, errorThrown) {
      msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
      console.error(data);
    });
    $('.personal-settings select.pagerows').val(value);
    return false;
  });

  container.on('change', '.debugmode', function(event) {
    const self = $(this);
    const post = self.serializeArray();
    console.log(post);
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/debugmode'),
           { 'value': post })
    .done(function(data) {
      msgElement.html(data.message).show();
      console.log(data);
      CAFEVDB.debugModes = data.value;
    })
    .fail(function(xhr, status, errorThrown) {
      msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
      console.error(data);
    });
    // TODO cross update options.
    return false;
  });

  container.on('change', '.wysiwyg-editor', function(event) {
    const self = $(this);
    const value = self.val();
    $.post(OC.generateUrl('/apps/cafevdb/settings/personal/set/wysiwygEditor'),
           { 'value': value })
    .done(function(data) {
      msgElement.html(data.message).show();
      CAFEVDB.wysiwygEditor = value;
      console.log(data);
    })
    .fail(function(xhr, status, errorThrown) {
      msgElement.html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)).show();
      console.error(data);
    });
    $('.personal-settings select.wysiwyg-editor').val(value);
    return false;
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
                             console.info("Updating credits.");
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

  CAFEVDB.toolTipsInit('#personal-settings-container');

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
