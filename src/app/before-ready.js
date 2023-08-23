/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, $, appName } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
// import * as Dialogs from './dialogs.js';
import * as Page from './page.js';
import * as ProjectExtra from './project-participant-fields.js';
import * as SepaBulkTransactions from './sepa-bulk-transactions.js';
import * as SepaDebitMandate from './sepa-debit-mandate.js';
import * as Musicians from './musicians.js';
import * as Projects from './projects.js';
import * as ProjectPayments from './project-payments.js';
import * as PHPMyEdit from './pme.js';
import * as Dialogs from './dialogs.js';
import { tweaks as pmeTweaks /* , unTweak as pmeUnTweak */ } from './pme-tweaks.js';
import pmeExportMenu from './pme-export.js';
import stopEnterSubmit from './stop-enter-submit.js';
import actionMenuHandlers from './action-menu.js';

const documentReady = function() {

  document.onkeypress = stopEnterSubmit;

  Dialogs.attachDialogHandlers();

  // @TODO move to global state context
  window.oldWidth = -1;
  window.oldHeight = -1;
  $(window).on('resize', function(event) {
    const win = this;
    if (!win.resizeTimeout) {
      const delay = 50;
      const width = (win.innerWidth > 0) ? win.innerWidth : screen.width;
      const height = (win.innerHeight > 0) ? win.innerHeight : screen.height;
      if (win.oldWidth !== width || win.oldHeight !== height) {
        console.debug('cafevdb size change', width, win.oldWidth, height, win.oldHeight);
        win.resizeTimeout = setTimeout(
          function() {
            win.resizeTimeout = null;
            $('.resize-target, .ui-dialog-content').trigger('resize');
          }, delay);
        win.oldHeight = height;
        win.oldWidth = width;
      }
    }
    return false;
  });

  /****************************************************************************
   *
   * Add handlers as delegates. Note however that the snapper is
   * attached to #app-content below #content, so it is not possible to
   * prevent the snapper events. If we want to change this we have to
   * insert another div-container inside #app-content.
   *
   */
  const content = $('#content.app-' + appName);
  // const appInnerContent = $('#app-inner-content');

  actionMenuHandlers(content);

  // Display the overview-page for the given project.
  content.on(
    'click', 'ul#navigation-list li.nav-projectlabel-control a',
    function(event) {
      event.stopImmediatePropagation();
      const data = $(this).data('json');
      Projects.projectViewPopup(PHPMyEdit.selector(), data);
      return false;
    });

  // Display the instrumentation numbers in a dialog widget if in project-mode
  content.on(
    'click', 'ul#navigation-list li.nav-project-instrumentation-numbers-control a',
    function(event) {
      const $this = $(this);
      const jsonData = $this.data('json');
      if (parseInt(jsonData.projectId) > 0) {
        event.stopImmediatePropagation(); // this is vital
        const data = $(this).data('json');
        Projects.instrumentationNumbersPopup(PHPMyEdit.selector(), data);
        return false;
      }
    });

  CAFEVDB.addReadyCallback(function() {
    $('input.alertdata.cafevdb-page').each(function(index) {
      const title = $(this).attr('name');
      const text = $(this).attr('value');
      Dialogs.alert(text, title, undefined, true, true);
    });

  });

  // fire an event when this has been finished
  console.debug('trigger loaded');
  $(document).trigger('cafevdb:donecafevdbjs');

  if (globalState.expertMode) {
    $('body').addClass('cafevdb-expert-mode');
  }

  if (globalState.financeMode) {
    $('body').addClass('cafevdb-finance-mode');
  }

  // ???? needed ????
  $.widget('ui.dialog', $.ui.dialog, {
    _allowInteraction(event) {
      return !!$(event.target).closest('.mce-container').length || this._super(event);
    },
  });

  // // should somehow depend on debug mode.
  // $(document).on('ajaxError', function(event, xhr, settings, error) {
  //   Dialogs.alert(
  //     t(appName, 'Unhandled internal AJAX error:')
  //       + '<br/>'
  //       + t(appName, 'Error') + ': ' + error
  //       + '<br/>'
  //       + t(appName, 'URL') + ': ' + settings.url,
  //     t(appName, 'Error'),
  //     undefined, true, true);
  //   return false;
  // });

  content.on('cafevdb:content-update', function(event) {
    $.fn.cafevTooltip.remove(); // remove any left-over items.
  });

  content.on('chosen:showing_dropdown chosen:update_results', 'select', function(event, params) {
    const container = params.chosen.container;
    const results = params.chosen.search_results;
    const menuItems = results.find('li');
    menuItems.cafevTooltip({ placement: 'right' });
    if (!globalState.toolTipsEnabled) {
      menuItems.cafevTooltip('disable');
    }
    container.cafevTooltip('hide');
    container.cafevTooltip('disable');
    // $.fn.cafevTooltip.remove(); // remove any left-over items.
  });

  content.on('chosen:hiding_dropdown', 'select', function(event, params) {
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
    const $form = $(this);
    const action = $form.attr('action');
    if (action !== '') {
      // not for us, external target.
      return true;
    }
    console.info('Catchall form submit', event);
    const post = $form.serialize();
    // alert('post: '+post);
    Page.loadPage(post);
    return false;
  });

  // Any pending form-submit which has not been caught otherwise is
  // here intercepted and redirected to the page-loader in order to
  // reduce load-time and to record usable history information.
  content.on('click', ':submit', function(event) {
    const $self = $(this);
    const $form = $(this.form);
    const action = $self.attr('formaction');
    if (action !== '') {
      return true; // not for us
    }
    console.info('Catchall form submit input', event);
    let post = $form.serialize();
    if ($self.attr('name')) {
      post += '&' + $.param($self);
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
    callback(selector, parameters, resizeCB) {

      if (parameters.reason === 'tabChange') {
        resizeCB();
        return;
      }

      if (parameters.reason === 'dialogClose') {
        resizeCB();
        return;
      }

      const container = $(selector);
      pmeExportMenu(selector);
      SepaDebitMandate.popupInit(selector);
      container.find('#sepa-bank-accounts-show-deleted').on('change', function(event) {
        const $sepaTable = container.find('td.pme-value.sepa-bank-accounts table');
        if ($(this).prop('checked')) {
          $sepaTable.addClass('show-deleted').removeClass('hide-deleted');
        } else {
          $sepaTable.removeClass('show-deleted').addClass('hide-deleted');
        }
        resizeCB();
        return false;
      });

      Musicians.ready(container);

      container.find('.cloud-avatar').imagesLoaded(resizeCB);
    },
    context: CAFEVDB,
    parameters: [],
  };

  PHPMyEdit.addTableLoadCallback('all-musicians', musiciansCallback);
  PHPMyEdit.addTableLoadCallback('add-musicians', musiciansCallback);

  PHPMyEdit.addTableLoadCallback('project-participant-fields', {
    callback(selector, parameters, resizeCB) {

      if (parameters.reason !== 'dialogOpen') {
        resizeCB();
        return;
      }

      ProjectExtra.ready(selector, resizeCB);
    },
    context: CAFEVDB,
    parameters: [],
  });

  PHPMyEdit.addTableLoadCallback('instruments', {
    callback(selector, parameters, resizeCB) {
      resizeCB();
    },
    context: CAFEVDB,
    parameters: [],
  });

  PHPMyEdit.addTableLoadCallback('instrument-families', {
    callback(selector, parameters, resizeCB) {
      resizeCB();
    },
    context: CAFEVDB,
    parameters: [],
  });

  PHPMyEdit.addTableLoadCallback('project-payments', {
    callback(selector, parameters, resizeCB) {

      switch (parameters.reason) {
      case 'dialogOpen':
      case 'dialogClose':
        ProjectPayments.ready(selector, parameters, resizeCB);
        break;
      default:
        ProjectPayments.backgroundDecryption(selector);
        resizeCB();
        break;
      }
    },
    context: CAFEVDB,
    parameters: [],
  });

  PHPMyEdit.addTableLoadCallback('sepa-bulk-transactions', {
    callback(selector, parameters, resizeCB) {

      if (parameters.reason !== 'dialogOpen') {
        SepaBulkTransactions.backgroundDecryption(selector);
        resizeCB();
        return;
      }

      SepaBulkTransactions.ready(selector, resizeCB);
    },
    context: CAFEVDB,
    parameters: [],
  });

  CAFEVDB.addReadyCallback(function() {
    pmeExportMenu();

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
