/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DualListboxComponentProps, DualListboxControls } from '../types/dual-listbox.ts';
import type { MountableComponent } from './mountable-components.ts';

import { awaitEmit, subscribe } from '@rotdrop/async-nextcloud-event-bus';
import { GET_VUE_COMPONENT, LEGACY_PAGE_CLEANUP } from '../event-bus-events.ts';
import { DUAL_LISTBOX } from '../mountable-component-names.ts';

type Component = MountableComponent<typeof DUAL_LISTBOX>&DualListboxControls;
const instances = new Set<DualListboxService>();
const bySelect = new WeakMap<HTMLSelectElement, DualListboxService>();
let removalObserver: MutationObserver|undefined;

export class DualListboxService {
  readonly ready: Promise<void>;
  private readonly host = document.createElement('div');
  private readonly mountPoint = document.createElement('span');
  private readonly originalDisplay: string;
  private readonly observer: MutationObserver;
  private component?: Component;
  private destroyed = false;
  private wasConnected: boolean;

  private constructor(readonly select: HTMLSelectElement) {
    this.originalDisplay = select.style.display;
    this.wasConnected = select.isConnected;
    this.host.className = 'dual-listbox-control';
    this.host.append(this.mountPoint);
    select.insertAdjacentElement('afterend', this.host);
    select.addEventListener('change', this.refresh);
    this.observer = new MutationObserver(this.refresh);
    this.observer.observe(select, {
      attributes: true,
      childList: true,
      characterData: true,
      subtree: true,
    });
    bySelect.set(select, this);
    instances.add(this);
    if (!removalObserver) {
      removalObserver = new MutationObserver(() => {
        for (const instance of instances) {
          if (instance.wasConnected && (!instance.select.isConnected || !instance.host.isConnected)) {
            instance.destroy();
          }
          instance.wasConnected ||= instance.select.isConnected;
        }
      });
      removalObserver.observe(document.documentElement, { childList: true, subtree: true });
    }
    this.ready = this.mountComponent().catch((error: unknown) => {
      this.destroy();
      throw error;
    });
  }

  static mount(select: HTMLSelectElement) {
    return bySelect.get(select) ?? new DualListboxService(select);
  }

  private get readonly() {
    return this.select.hasAttribute('readonly')
      || this.select.classList.contains('readonly')
      || ('readOnly' in this.select && !!this.select.readOnly);
  }

  private props(): DualListboxComponentProps {
    return {
      options: Array.from(this.select.options, (option) => ({
        value: option.value,
        label: option.label,
        title: option.title,
        disabled: option.disabled || (option.parentElement instanceof HTMLOptGroupElement && option.parentElement.disabled),
      })),
      modelValue: Array.from(this.select.selectedOptions, (option) => option.value),
      disabled: this.select.disabled,
      readonly: this.readonly,
      inputId: this.select.id ? `${this.select.id}-dual-listbox` : undefined,
      onChange: this.handleChange,
    };
  }

  private async mountComponent() {
    const component = await awaitEmit(GET_VUE_COMPONENT, {
      name: DUAL_LISTBOX,
      propsData: this.props(),
    }) as Component;
    if (this.destroyed) {
      component.destroy();
      return;
    }
    this.component = component;
    component.mount(this.mountPoint);
    this.select.style.display = 'none';
    this.refresh();
  }

  private readonly handleChange = (values: string[]) => {
    if (this.select.disabled || this.readonly) {
      this.refresh();
      return;
    }
    const selected = new Set(values);
    let changed = false;
    for (const option of this.select.options) {
      if (!option.disabled && !(option.parentElement instanceof HTMLOptGroupElement && option.parentElement.disabled)) {
        const next = selected.has(option.value);
        changed ||= option.selected !== next;
        option.selected = next;
      }
    }
    this.refresh();
    if (changed) {
      this.select.dispatchEvent(new Event('change', { bubbles: true }));
    }
  };

  readonly refresh = () => {
    if (!this.destroyed) {
      this.component?.setState(this.props());
    }
  };

  destroy() {
    if (this.destroyed) {
      return;
    }
    this.destroyed = true;
    this.observer.disconnect();
    this.select.removeEventListener('change', this.refresh);
    this.component?.unmount();
    this.component?.destroy();
    this.component = undefined;
    this.host.remove();
    this.select.style.display = this.originalDisplay;
    instances.delete(this);
    bySelect.delete(this.select);
    if (!instances.size) {
      removalObserver?.disconnect();
      removalObserver = undefined;
    }
  }
}

export const getMountedDualListbox = (select: HTMLSelectElement) => bySelect.get(select);

subscribe(LEGACY_PAGE_CLEANUP, (event) => {
  for (const instance of instances) {
    if (!event || event.containers.some((container) => container.contains(instance.select))) {
      instance.destroy();
    }
  }
});
