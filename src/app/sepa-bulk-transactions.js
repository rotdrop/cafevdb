/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2021, 2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.js';
import * as CAFEVDB from './cafevdb.js';
import { templateRenderer } from './template-renderer.js';
import * as PHPMyEdit from './pme.js';
import {
  token as pmeToken,
  formSelector as pmeFormSelector,
  sys as pmeSys,
  classSelector as pmeClassSelector,
} from './pme-selectors.js';
import {
  lazyDecrypt,
  reject as rejectDecryptionPromise,
  promise as decryptionPromise,
} from './lazy-decryption.js';
import {
  emit as asyncEmit,
  getEmitResult,
  subscribe as asyncSubscribe,
} from '../services/async-event-bus.ts';
import { SEPA_BULK_TRANSACTION_ACTIONS_MENU } from '../mountable-component-names.ts';
import * as BusEvents from '../event-bus-events.ts';

require('sepa-bulk-transactions.scss');

const template = 'sepa-bulk-transactions';

asyncSubscribe(BusEvents.SEPA_BULK_TRANSACTION_POPUP, async (event) => {
  asyncEmit(BusEvents.PUSH_BUSY_STATE);
  await overviewPopup(PHPMyEdit.selector(), event);
  asyncEmit(BusEvents.POP_BUSY_STATE);
});

const backgroundDecryption = function(container) {
  const $container = PHPMyEdit.container(container);
  rejectDecryptionPromise();
  console.time('DECRYPTION PROMISE');
  decryptionPromise.done((maxJobs) => {
    console.timeEnd('DECRYPTION PROMISE');
    console.debug('MAX DECRYPTION JOBS HANDLED', maxJobs);
  });
  lazyDecrypt($container);
};

const overviewPopup = async function(containerSel, data) {
  const tableOptions = {
    ambientContainerSelector: containerSel,
    template,
    templateRenderer: templateRenderer(template),
    // Now special options for the dialog popup
    initialViewOperation: true,
    initialName: pmeSys('operation'),
    initialValue: 'View',
    reloadName: pmeSys('operation'),
    reloadValue: 'View',
    // [pmeSys('operation')]: 'View',
    [pmeSys('rec')]: { id: data.bulkTransactionId },
    [pmeSys('groupby_rec')]: {
      id: data.bulkTransactionId,
      // eslint-disable-next-line camelcase
      CompositePayments__master_key_: '0;' + data.bulkTransactionId,
    },
    [pmeSys('mrec_rec')]: {
      id: data.bulkTransactionId,
      // eslint-disable-next-line camelcase
      CompositePayments__master_key_: '0;' + data.bulkTransactionId,
    },
    projectId: data.projectId,
    projectName: data.projectName,
    modalDialog: true,
    modified: false,
  };
  await PHPMyEdit.tableDialogOpen(tableOptions);
};

const actionMenu = async function($container) {

  const generateVueMenu = async ($actionMenu) => {
    const propsData = { ...$actionMenu.data('actionMenu') };
    propsData.enableOverviewItem = $container.find(pmeFormSelector).hasClass(pmeToken('list'));
    const vueMenu = await getEmitResult(
      asyncEmit(BusEvents.GET_VUE_COMPONENT, {
        name: SEPA_BULK_TRANSACTION_ACTIONS_MENU,
        propsData,
      }),
    );
    const vueComponents = $container.data('vueComponents') || [];
    if (vueComponents.length === 0) {
      $container.data('vueComponents', vueComponents);
    }
    vueComponents.push(vueMenu);

    $actionMenu.data('vueMenu', vueMenu);
    await vueMenu.$mount($actionMenu.find('.vue-mount-point')[0]);
    return vueMenu;
  };

  const actionTriggerSelector = `.vue-action-menu-placeholder.${template} button.vue-mount-point`;
  $container
    .off('click', actionTriggerSelector)
    .on('click', actionTriggerSelector, async function(event) {

      $.fn.cafevTooltip.hide();

      const $actionMenu = $(this).parent();
      if ($actionMenu.data('vueMenu')) {
        // the menu already exists, just let it do its work
        return;
      }

      // otherwise intercept the event and mount the menu
      event.preventDefault();
      event.stopImmediatePropagation();

      const vueMenu = await generateVueMenu($actionMenu);
      const bulkTransactionId = $actionMenu.data('actionMenu').bulkTransactionId;

      asyncEmit(BusEvents.SEPA_BULK_TRANSACTION_ACTIONS, {
        open: false,
        bulkTransactionId: -bulkTransactionId,
      });
      vueMenu.openMenu();

      return false;
    });

  $container
    .off('pme:contextmenu', 'tr.' + pmeToken('row'))
    .on('pme:contextmenu', 'tr.' + pmeToken('row'), async function(event, originalEvent, databaseIdentifier) {
      console.debug('CONTEXTMENU EVENT', $(this), event, originalEvent, databaseIdentifier);

      const $row = $(this);
      const $form = $row.closest(pmeFormSelector);
      let $actionMenuContainer;
      if ($form.is('.' + pmeToken('list'))) {
        $actionMenuContainer = $row.hasClass('following') ? $row.prevAll('.first').first() : $row;
      } else {
        $actionMenuContainer = $row.closest(pmeFormSelector);
      }
      const $actionMenu = $actionMenuContainer.find('.vue-action-menu-placeholder.' + template).first();

      if ($actionMenu.length === 0) {
        return;
      }

      originalEvent.preventDefault();
      originalEvent.stopImmediatePropagation();

      const vueMenu = $actionMenu.data('vueMenu') || await generateVueMenu($actionMenu);
      const bulkTransactionId = $actionMenu.data('actionMenu').bulkTransactionId;

      if (vueMenu.isOpen()) {
        vueMenu.closeMenu();
      } else {
        asyncEmit(BusEvents.SEPA_BULK_TRANSACTION_ACTIONS, {
          open: false,
          bulkTransactionId: -bulkTransactionId,
        });
        vueMenu.openMenu(
          originalEvent.originalEvent.clientX,
          originalEvent.originalEvent.clientY,
        );
      }

      return false;
    });
};

const ready = function(container, resizeCB) {

  // sanitize
  const $container = PHPMyEdit.container(container);

  backgroundDecryption($container);

  const listMode = $container.find(pmeClassSelector('form', 'list')).length > 0;

  $container
    .on('contextmenu', 'table.pme-main tr.bulk-transaction.first td', function(event) {
      // @TODO: be less greedy, anable actions context menu in places
      // where it makes sense. For this we have to look at the target
      // and the pme operation mode.
      if (!listMode || event.ctrlKey) {
        return; // let the user see the normal context menu
      }
      const $row = $(this).closest('tr.bulk-transaction.first');
      if ($row.length === 0) {
        return;
      }
      event.stopImmediatePropagation();
      $row.toggleClass('following-hidden');
      $row.find('input.expanded-marker').val($row.hasClass('following-hidden') ? 0 : 1);
      return false;
    });

  actionMenu($container);

  resizeCB();
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {

    const $container = PHPMyEdit.container();

    if (!$container.hasClass('sepa-bulk-transactions')) {
      return;
    }

    ready($container, function() {});
  });

};

export {
  documentReady,
  backgroundDecryption,
  ready,
};
