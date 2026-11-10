/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { MountableComponent } from './mountable-components.ts';

import { awaitEmit, subscribe } from '@rotdrop/async-nextcloud-event-bus';
import { GET_VUE_COMPONENT, LEGACY_PAGE_CLEANUP } from '../event-bus-events.ts';
import { PME_EXPORT_MENU } from '../mountable-component-names.ts';

type Component = MountableComponent<typeof PME_EXPORT_MENU>;
const instances = new Set<PmeExportService>();
const byButton = new WeakMap<HTMLButtonElement, PmeExportService>();

/** Mounts the Vue export menu in place of its server-rendered button. */
export class PmeExportService {
  readonly element = document.createElement('span');
  readonly ready: Promise<void>;
  private readonly mountPoint = document.createElement('span');
  private readonly originalDisplay: string;
  private readonly observer: MutationObserver;
  private component?: Component;
  private destroyed = false;
  private wasConnected: boolean;

  private constructor(readonly button: HTMLButtonElement, private readonly form: HTMLFormElement) {
    this.originalDisplay = button.style.display;
    this.wasConnected = button.isConnected;
    this.element.className = 'pme-export-menu-control';
    this.element.append(this.mountPoint);
    button.insertAdjacentElement('afterend', this.element);
    byButton.set(button, this);
    instances.add(this);
    this.observer = new MutationObserver(() => {
      if (this.wasConnected && (!button.isConnected || !this.element.isConnected)) {
        this.destroy();
      } else {
        this.wasConnected ||= button.isConnected;
      }
    });
    this.observer.observe(document.documentElement, { childList: true, subtree: true });
    this.ready = this.mount().catch((error: unknown) => {
      this.destroy();
      throw error;
    });
  }

  static create(button: HTMLButtonElement) {
    const existing = byButton.get(button);
    if (existing) {
      return existing;
    }
    if (!button.form) {
      throw new Error('The PME export menu requires an associated form.');
    }
    return new PmeExportService(button, button.form);
  }

  private async mount() {
    const component = await awaitEmit(GET_VUE_COMPONENT, {
      name: PME_EXPORT_MENU,
      propsData: { form: this.form, disabled: this.button.disabled },
    }) as Component;
    if (this.destroyed) {
      component.destroy();
      return;
    }
    this.component = component;
    component.mount(this.mountPoint);
    this.button.style.display = 'none';
  }

  destroy() {
    if (this.destroyed) {
      return;
    }
    this.destroyed = true;
    this.observer.disconnect();
    instances.delete(this);
    byButton.delete(this.button);
    this.component?.unmount();
    this.component?.destroy();
    this.element.remove();
    this.button.style.display = this.originalDisplay;
  }
}

subscribe(LEGACY_PAGE_CLEANUP, (event) => {
  for (const instance of instances) {
    if (!event || event.container?.contains(instance.button)) {
      instance.destroy();
    }
  }
});
