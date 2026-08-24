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

import type { App, Component, VNode } from 'vue';

import { translate as t } from '@nextcloud/l10n';
import {
  // createApp,
  createVNode,
  render,
} from 'vue';
import { appName } from '../config.ts';
import { GET_VUE_COMPONENT } from '../event-bus-events.ts';
import * as MountableComponents from '../mountable-component-names.ts';
// import router from '../router/app-router.ts';
import { AppError } from '../toolkit/types/errors.ts';
// import { pinia } from '../vue-app.ts';
import { subscribe as asyncSubscribe } from './async-event-bus.ts';

const vueComponents: Record<string, Component> = {};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type ExposedComponentProperties = undefined|null|number|string|Record<number|string, unknown>|((...args: any) => any)|VNode|HTMLElement;

export type MountableComponent = {
  vNode?: VNode;
  element?: HTMLElement;
  parent?: HTMLElement;
  mount: (element: HTMLElement) => void;
  unmount: () => void;
  destroy: () => void;
  [x: string]: ExposedComponentProperties;
  [x: number]: ExposedComponentProperties;
};

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
    // const instance = createApp(vueComponents[event.name], event.propsData);
    // instance.use(router);
    // instance.use(pinia);
    // Object.assign(instance._context, vueApp);
    // instance._context = vueApp._context;
    const vNode = createVNode(vueComponents[event.name], event.propsData);
    if (vueApp._context) {
      vNode.appContext = vueApp._context;
    }
    console.info('MOUNTABLE COMPONENT APP', {
      vueApp,
      // instance,
      vNode,
    });
    // return instance;
    return new Proxy<MountableComponent>(
      {
        vNode,
        element: undefined,
        mount(element: HTMLElement) {
          this.element = element;
          this.parent = element.parentElement!;
          this.element.remove();
          render(vNode, this.parent);
        },
        unmount() {
          if (!this.element || !this.parent) {
            return;
          }
          render(null, this.parent);
          this.parent.appendChild(this.element);
          this.element = undefined;
          this.parent = undefined;
        },
        destroy() {
          this.vNode = undefined;
        },
      },
      {
        set(target, property, value, receiver) {
          if (!target.vNode) {
            return Reflect.set(target, property, value, receiver);
          }
          if (property in (vNode.component?.exposed ?? {})) {
            return Reflect.set(vNode.component!.exposeProxy!, property, value);
          }
          return Reflect.set(target, property, value, receiver);
        },
        get(target, property, receiver) {
          if (!target.vNode) {
            return Reflect.get(target, property, receiver);
          }
          if (property in (vNode.component?.exposed ?? {})) {
            return Reflect.get(vNode.component!.exposeProxy!, property);
          }
          return Reflect.get(target, property, receiver);
        },
      },
    );
  });
};
