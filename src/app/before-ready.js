/* Orchestra member, musicion and project management application.
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

import { globalState, appName } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Dialogs from './dialogs.js';
import * as Page from './page.js';
import * as Photo from './inlineimage.js';
import * as ProjectExtra from './project-extra.js';
import * as DebitNotes from './debit-notes.js';
import * as Musicians from './musicians.js';
import * as PHPMyEdit from './pme.js';
import pmeTweaks from './pme-tweaks.js';

const documentReady = function() {

  if (globalState.expertMode) {
    $('body').addClass('cafevdb-expert-mode');
  }

  // ???? needed ????
  $.widget('ui.dialog', $.ui.dialog, {
    _allowInteraction: function(event) {
      return !!$(event.target).closest('.mce-container').length || this._super( event );
    }
  });

  if (false) {
    // should somehow depend on debug mode.
    $(document).on('ajaxError', function(event, xhr, settings, error) {
      Dialogs.alert(
        t(appName, 'Unhandled internal AJAX error:')
          + '<br/>'
          + t(appName, 'Error') + ': ' + error
          + '<br/>'
          + t(appName, 'URL') + ': ' + settings.url,
        t(appName, 'Error'),
        undefined, true, true);
      return false;
    });
  }

  const content = $('#content');

  content.on('cafevdb:content-update', function(event) {
    $.fn.cafevTooltip.remove(); // remove any left-over items.
  });

  content.on('chosen:showing_dropdown', 'select', function(event, params)   {
    const container = params.chosen.container;
    const results = params.chosen.search_results;
    const menuItems = results.find('li');
    menuItems.cafevTooltip({placement: 'right'});
    if (!globalState.toolTipsEnabled) {
      menuItems.cafevTooltip('disable');
    }
    container.cafevTooltip('hide');
    container.cafevTooltip('disable');
    // $.fn.cafevTooltip.remove(); // remove any left-over items.
  });
  content.on('chosen:hiding_dropdown', 'select', function(event, params)   {
    const container = params.chosen.container;
    const results = params.chosen.search_results;
    const menuItems = results.find('li');
    if (globalState.toolTipsEnabled) {
      menuItems.cafevTooltip('disable');
      container.cafevTooltip('enable');
      // params.chosen.container.cafevTooltip('show');
    }
  });

  // Any pending form-submit which has not been caught otherwise is
  // here intercepted and redirected to the page-loader in order to
  // reduce load-time and to record usable history information.
  content.on('submit', 'form', function(event) {
    console.info('Catchall form submit', event);
    const form = $(this);
    const action = form.attr('action');
    if (action != '') {
      // not for us, external target.
      return true;
    }
    const post = form.serialize();
    // alert('post: '+post);
    Page.loadPage(post);
    return false;
  });

  // Any pending form-submit which has not been caught otherwise is
  // here intercepted and redirected to the page-loader in order to
  // reduce load-time and to record usable history information.
  content.on('click', ':submit', function(event) {
    console.info('Catchall form submit input', event);
    const self = $(this);
    const form = $(this.form);
    const action = self.attr('formaction');
    if (action != '') {
      return true; // not for us
    }
    const post = form.serialize();
    if (self.attr('name')) {
      const obj = {};
      obj[self.attr('name')] = self.val();
      post += '&' + $.param(obj);
    }
    // alert('post: '+post);
    Page.loadPage(post);
    return false;
  });

  // Intercept app-navigation events here and redirect to the page
  // loader
  content.on('click', 'ul#navigation-list li a', function(event) {
    const target = $(event.target);
    if (target.is('.nav-heading a')) {
      // don't recurse on nav-heading which just should close the sidebar.
      return true;
    }
    const post = $(this).data('post');
    Page.loadPage(post);
    // alert('post: '+post);
    return false;
  });

  const musiciansCallback = {
    callback: function(selector, parameters, resizeCB) {

      if (parameters.reason === 'tabChange') {
        resizeCB();
        return;
      }

      const container = $(selector);
      CAFEVDB.exportMenu(selector);

      container.find('div.photo, #cafevdb_inline_image_wrapper').
        off('click', 'img.zoomable').
        on('click', 'img.zoomable', function(event) {
          event.preventDefault();
          Photo.popup(this);
          return false;
        });

      Musicians.ready(container);

      $(':button.musician-instrument-insurance').click(function(event) {
        Page.loadPage($(this).attr('name'));
        return false;
      });

      if (container.find('#contact_photo_upload').length > 0) {
        const idField = container.find('input[name="PME_data_id"]');
        let recordId = -1;
        if (idField.length > 0) {
          recordId = idField.val();
        }
        console.info('Run photo.ready');
        Photo.ready(recordId, 'MusicianPhoto', resizeCB);
      } else {
        console.info('Call imagesLoaded');
        container.find('div.photo, span.photo').imagesLoaded(resizeCB);
      }
    },
    context: CAFEVDB,
    parameters: [],
  };

  PHPMyEdit.addTableLoadCallback('all-musicians', musiciansCallback);
  PHPMyEdit.addTableLoadCallback('add-musicians', musiciansCallback);

  PHPMyEdit.addTableLoadCallback('project-extra-fields', {
    callback: function(selector, parameters, resizeCB) {

      if (parameters.reason != 'dialogOpen') {
        resizeCB();
        return;
      }

      ProjectExtra.ready(selector, resizeCB);
    },
    context: CAFEVDB,
    parameters: []
  });


  PHPMyEdit.addTableLoadCallback('instruments', {
    callback(selector, parameters, resizeCB) {
      resizeCB();
    },
    context: CAFEVDB,
    parameters: []
  });

  PHPMyEdit.addTableLoadCallback('instrument-families', {
    callback(selector, parameters, resizeCB) {
      resizeCB();
    },
    context: CAFEVDB,
    parameters: []
  });

  PHPMyEdit.addTableLoadCallback('project-payments', {
    callback(selector, parameters, resizeCB) {
      resizeCB();
    },
    context: CAFEVDB,
    parameters: []
  });

  PHPMyEdit.addTableLoadCallback('debit-notes', {
    callback(selector, parameters, resizeCB) {

      if (parameters.reason != 'dialogOpen') {
        resizeCB();
        return;
      }

      DebitNotes.ready(selector, resizeCB);
    },
    context: CAFEVDB,
    parameters: []
  });

  CAFEVDB.addReadyCallback(function() {
    CAFEVDB.exportMenu();

    pmeTweaks();

    CAFEVDB.toolTipsInit();

    // Prevent drag&drop outside allowed areas.
    window.addEventListener('dragover', function(e) {
      e = e || event;
      e.preventDefault();
      console.info('Prevented dragover event');
    }, false);
    window.addEventListener('drop', function(e) {
      e = e || event;
      e.preventDefault();
      console.info('Prevented drop event');
    }, false);

  });

};

export default documentReady;

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
