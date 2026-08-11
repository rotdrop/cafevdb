/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { App, Component } from 'vue';

import { translate as t } from '@nextcloud/l10n';
import { createApp } from 'vue';
import { appName } from '../config.ts';
import { GET_VUE_COMPONENT } from '../event-bus-events.ts';
import * as MountableComponents from '../mountable-component-names.ts';
import { AppError } from '../toolkit/types/errors.ts';
import { subscribe as asyncSubscribe } from './async-event-bus.ts';

const vueComponents: Record<string, Component> = {};

export const provideMountableComponents = <T extends App>(vueApp: T) => {
  asyncSubscribe(GET_VUE_COMPONENT, async (event) => {
    if (!vueComponents[event.name as string]) {
      let vueComponent: Component;
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
        case MountableComponents.PROJECT_PAYMENT_ACTIONS_MENU:
          vueComponent = (await import('../components/ProjectPaymentActionsMenu.vue')).default;
          break;
        case MountableComponents.SEPA_BULK_TRANSACTION_ACTIONS_MENU:
          vueComponent = (await import('../components/SepaBulkTransactionActionsMenu.vue')).default;
          break;
        default:
          throw new AppError(event, t(appName, 'Unknown mountable component: "{name}".', event));
      }
      vueComponents[event.name] = vueComponent;
      vueApp.component(event.name, vueComponent);
    }
    // obtain a new instance
    const instance = createApp(vueComponents[event.name], event.propsData);
    Object.assign(instance._context, vueApp);
    return instance;
  });
};
