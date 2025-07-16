/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import Vue from 'vue';
import { GET_VUE_COMPONENT } from '../event-bus-events.ts';
import { subscribe as asyncSubscribe } from './async-event-bus.ts'
import * as MountableComponents from '../mountable-component-names.ts';
import type { VueConstructor } from 'vue/types/vue';

const vueConstructors: Record<string, VueConstructor> = {};

export const provideMountableComponents = <T extends Vue>(vueApp: T) => {
  asyncSubscribe(GET_VUE_COMPONENT, async (event) => {
    if (!vueConstructors[event.name]) {
      let vueComponent: VueConstructor;
      switch (event.name) {
      case MountableComponents.DOKU_WIKI_WRAPPER:
        vueComponent = (await import('@rotdrop/nextcloud-app-dokuwiki/src/DokuWikiWrapper.vue')).default;
        break;
      case MountableComponents.INVOICE_ACTIONS_MENU:
        vueComponent = (await import('../components/InvoiceActionsMenu.vue')).default;
        break;
      case MountableComponents.LEGACY_QUERY_LOG:
        vueComponent = (await import('../components/LegacyQueryLog.vue')).default;
        break;
      case MountableComponents.PROJECT_ACTIONS_MENU:
        vueComponent = (await import('../components/ProjectActionsMenu.vue')).default;
        break;
      case MountableComponents.SEPA_BULK_TRANSACTION_ACTIONS_MENU:
        vueComponent = (await import('../components/SepaBulkTransactionActionsMenu.vue')).default;
        break;
      default:
        return;
      }
      // generate the constructor
      vueConstructors[event.name] = Vue.extend(vueComponent);
    }
    // obtain a new instance
    const instance = new vueConstructors[event.name]({
      parent: vueApp,
      propsData: event.propsData,
    });
    return instance;
  });
};
