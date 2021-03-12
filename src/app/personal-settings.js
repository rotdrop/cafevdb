/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, $ } from './globals.js';
import { setPersonalUrl } from './settings-urls.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as PHPMyEdit from './pme-selectors.js';

console.info('JQUERY ', $.fn.jquery);

const documentReady = function() {

  const container = $('.personal-settings');
  let msgElement = $('form.personal-settings .statusmessage');

  const chosenInit = function(container) {
    container.find('select.pagerows').each(function(index) {
      const self = $(this);
      // console.log("chosen pagerows", self);
      if (CAFEVDB.chosenActive(self)) {
        self.chosen('destroy');
      }
      self.chosen({
        disable_search: true,
        inherit_select_classes: true,
        width: '10ex',
      });
    });

    container.find('select.wysiwyg-editor').each(function(index) {
      const self = $(this);
      if (CAFEVDB.chosenActive(self)) {
        console.debug('destroy chosen', self);
        self.chosen('destroy');
      }
      console.info('call chosen', self);
      self.chosen('destroy');
      self.show();
      self.chosen({
        inherit_select_classes: true,
        disable_search: true,
        width: 'auto',
      });
    });

    container.find('select.debugmode').each(function(index) {
      const self = $(this);
      // console.log("chosen debugmode", self);
      if (CAFEVDB.chosenActive(self)) {
        self.chosen('destroy');
      }
      self.chosen({
        inherit_select_classes: true,
        disable_search: true,
        width: '100%',
      });
    });
  };

  container.on('cafevdb:content-update', function(event) {
    if (event.target === this) {
      chosenInit($(this));
      msgElement = $('form.personal-settings .statusmessage');
    }
  });

  // chosenInit(container);

  CAFEVDB.addReadyCallback(function() {
    chosenInit(container);
  });

  container.on('change', '.tooltips', function(event) {
    const self = $(this);
    CAFEVDB.toolTipsOnOff(self.prop('checked'));
    $.post(setPersonalUrl('tooltips'), { value: globalState.toolTipsEnabled })
      .done(function(data) {
        msgElement.html(data.message).show();
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        msgElement.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        // console.error(data);
      });
    $('.personal-settings input[type="checkbox"].tooltips').prop('checked', globalState.toolTipsEnabled);
    if (globalState.toolTipsEnabled) {
      $('#tooltipbutton').removeClass('tooltips-disabled').addClass('tooltips-enabled');
    } else {
      $('#tooltipbutton').removeClass('tooltips-enabled').addClass('tooltips-disabled');
    }
    return false;
  });

  container.on('change', '.filtervisibility', function(event) {
    const self = $(this);
    const checked = self.prop('checked');
    $.post(setPersonalUrl('filtervisibility'), { value: checked })
      .done(function(data) {
        msgElement.html(data.message).show();
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        msgElement.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        // console.error(data);
      });
    if (checked) {
      $('input.pme-search').trigger('click');
    } else {
      $('input.pme-hide').trigger('click');
    }
    $('.personal-settings input[type="checkbox"].filtervisibility').prop('checked', checked);
    return false;
  });

  container.on('change', '.directchange', function(event) {
    const self = $(this);
    const checked = self.prop('checked');
    $.post(setPersonalUrl('directchange'), { value: checked })
      .done(function(data) {
        msgElement.html(data.message).show();
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        msgElement.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        // console.error(data);
      });
    if (globalState.PHPMyEdit !== undefined) {
      globalState.PHPMyEdit.directChange = checked;
    }
    $('.personal-settings input[type="checkbox"].directchange').prop('checked', checked);
    return false;
  });

  container.on('change', '.showdisabled', function(event) {
    const self = $(this);
    const checked = self.prop('checked');
    $.post(setPersonalUrl('showdisabled'), { value: checked })
      .done(function(data) {
        msgElement.html(data.message).show();
        console.log(data);
        if (globalState.PHPMyEdit !== undefined) {
          const pmeForm = $('#content ' + PHPMyEdit.formSelector() + '.show-hide-disabled');
          console.log('form', pmeForm);
          pmeForm.each(function(index) {
            const form = $(this);
            const reload = form.find(PHPMyEdit.classSelector('input', 'reload')).first();
            if (reload.length > 0) {
              form.append('<input type="hidden"'
                          + ' name="' + PHPMyEdit.sys('sw') + '"'
                          + ' value="Clear"/>');
              reload.trigger('click');
            }
          });
        }
        return false;
      })
      .fail(function(xhr, status, errorThrown) {
        msgElement.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        // console.error(data);
      });
    if (globalState.PHPMyEdit !== undefined) {
      globalState.PHPMyEdit.showdisabled = checked;
    }
    $('.personal-settings input[type="checkbox"].showdisabled').prop('checked', checked);
    return false;
  });

  container.on('change', '.expertmode', function(event) {
    const self = $(this);
    const checked = self.prop('checked');
    $.post(setPersonalUrl('expertmode'), { value: checked })
      .done(function(data) {
        msgElement.html(data.message).show();
        console.log(data);
        if (globalState.PHPMyEdit !== undefined) {
          const pmeForm = $('#content ' + PHPMyEdit.formSelector());
          pmeForm.each(function(index) {
            const reload = $(this).find(PHPMyEdit.classSelector('input', 'reload')).first();
            reload.trigger('click');
          });
        }
      })
      .fail(function(xhr, status, errorThrown) {
        msgElement.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        // console.error(data);
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
    const $self = $(this);
    const value = $self.val();
    $.post(setPersonalUrl('pagerows'), { value })
      .done(function(data) {
        msgElement.html(data.message).show();
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        msgElement.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        // console.error(data);
      });
    $('.personal-settings select.pagerows').each(function(index) {
      if (this !== $self[0]) {
        CAFEVDB.selectValues(this, CAFEVDB.selectValues($self));
      }
    });
    return false;
  });

  container.on('change', '.debugmode', function(event) {
    const $self = $(this);
    const post = $self.serializeArray();
    console.log(post);
    $.post(setPersonalUrl('debugmode'), { value: post })
      .done(function(data) {
        msgElement.html(data.message).show();
        console.log(data);
        globalState.debugModes = data.value;
      })
      .fail(function(xhr, status, errorThrown) {
        msgElement.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        // console.error(data);
      });
    $('.personal-settings select.debugmode').each(function(index) {
      if (this !== $self[0]) {
        CAFEVDB.selectValues(this, CAFEVDB.selectValues($self));
      }
    });
    return false;
  });

  container.on('change', '.wysiwyg-editor', function(event) {
    const $self = $(this);
    const value = $self.val();
    $.post(setPersonalUrl('wysiwygEditor'), { value })
      .done(function(data) {
        msgElement.html(data.message).show();
        globalState.wysiwygEditor = value;
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        msgElement.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        // console.error(data);
      });
    $('.personal-settings select.wysiwyg-editor').each(function(index) {
      if (this !== $self[0]) {
        CAFEVDB.selectValues(this, CAFEVDB.selectValues($self));
      }
    });
    return false;
  });

  /****************************************************************************
   * Credits list
   *
   ***************************************************************************/

  const updateCredits = function() {
    const numItems = 5;
    const items = [];
    const numTotal = $('div.cafevdb.about div.product.credits.list ul li').length;
    for (let i = 0; i < numItems; ++i) {
      items.push(Math.round(Math.random() * (numTotal - 1)));
    }
    $('div.cafevdb.about div.product.credits.list ul li').each(function(index) {
      if (items.includes(index)) {
        $(this).removeClass('hidden');
      } else {
        $(this).addClass('hidden');
      }
    });
  };

  if (globalState.creditsTimer > 0) {
    clearInterval(globalState.creditsTimer);
  }

  globalState.creditsTimer = setInterval(function() {
    if ($('div.cafevdb.about div.product.credits.list:visible').length > 0) {
      console.info('Updating credits.');
      updateCredits();
    } else {
      console.log('Clearing credits timer.');
      clearInterval(globalState.creditsTimer);
    }
  }, 30000);

  /****************************************************************************
   *
   * Tooltips
   *
   ***************************************************************************/

  CAFEVDB.toolTipsInit('#personal-settings-container');

};

export default documentReady;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
