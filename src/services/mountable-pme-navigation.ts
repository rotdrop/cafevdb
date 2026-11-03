/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
  PmeNavigationSelectComponentProps,
  PmeNavigationSelectControls,
} from '../types/pme-navigation-select.ts';
import type { MountableComponent } from './mountable-components.ts';

import { awaitEmit, subscribe } from '@rotdrop/async-nextcloud-event-bus';
import { GET_VUE_COMPONENT, LEGACY_PAGE_CLEANUP } from '../event-bus-events.ts';
import { PME_NAVIGATION_SELECT } from '../mountable-component-names.ts';

export interface PmeNavigationOptions {
  /** Search becomes available when there are more options than this. */
  searchThreshold?: number;
}

type Component = MountableComponent<typeof PME_NAVIGATION_SELECT>&PmeNavigationSelectControls;

const instances = new Set<PmeNavigationService>();
const bySelect = new WeakMap<HTMLSelectElement, PmeNavigationService>();
let removalObserver: MutationObserver|undefined;

/**
 * Replaces a legacy PME "button + select" combo (goto, rows per page) by a
 * single button-like select. The native select stays authoritative for the
 * submitted value and still receives a bubbling change event on selection.
 */
export class PmeNavigationService {
  readonly element: HTMLSpanElement;
  readonly ready: Promise<void>;
  private readonly mountPoint: HTMLSpanElement;
  private readonly hiddenElements: Array<[HTMLElement, string]>;
  private component?: Component;
  private destroyed = false;
  private wasConnected: boolean;

  private constructor(
    readonly select: HTMLSelectElement,
    readonly button: HTMLInputElement|HTMLButtonElement|null,
    private readonly options: PmeNavigationOptions,
  ) {
    this.wasConnected = select.isConnected;
    this.hiddenElements = [select, button].filter((element): element is HTMLSelectElement|HTMLInputElement => !!element)
      .map((element) => [element, element.style.display]);
    this.element = document.createElement('span');
    this.element.className = 'pme-navigation-select-control';
    this.mountPoint = document.createElement('span');
    this.element.append(this.mountPoint);
    select.insertAdjacentElement('afterend', this.element);
    select.addEventListener('change', this.handleNativeChange);
    bySelect.set(select, this);
    instances.add(this);
    if (!removalObserver) {
      removalObserver = new MutationObserver(() => {
        for (const instance of instances) {
          if (instance.wasConnected && (!instance.select.isConnected || !instance.element.isConnected)) {
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

  static create(select: HTMLSelectElement, options: PmeNavigationOptions = {}) {
    const button = select.parentElement?.querySelector<HTMLInputElement|HTMLButtonElement>(
      ':scope > input:is([type=submit], [type=button]), :scope > button',
    ) ?? null;
    return bySelect.get(select) ?? new PmeNavigationService(select, button, options);
  }

  get caption() {
    return (this.button instanceof HTMLInputElement ? this.button.value : this.button?.textContent?.trim())
      || this.select.getAttribute('aria-label') || this.select.title || this.select.name;
  }

  private props(): PmeNavigationSelectComponentProps {
    return {
      caption: this.caption,
      options: Array.from(this.select.options, (option) => ({
        value: option.value,
        label: option.label || option.textContent || option.value,
        disabled: option.disabled,
      })),
      modelValue: this.select.selectedIndex >= 0 ? this.select.value : null,
      inputId: this.select.id ? `${this.select.id}-navigation-input` : undefined,
      placeholder: this.select.dataset.placeholder?.trim() || undefined,
      disabled: this.select.disabled,
      searchThreshold: this.options.searchThreshold,
      onChange: this.handleComponentChange,
    };
  }

  private async mountComponent() {
    const component = await awaitEmit(GET_VUE_COMPONENT, {
      name: PME_NAVIGATION_SELECT,
      propsData: this.props(),
    }) as Component;
    if (this.destroyed) {
      component.destroy();
      return;
    }
    this.component = component;
    component.mount(this.mountPoint);
    for (const [element] of this.hiddenElements) {
      element.style.display = 'none';
    }
    this.refresh();
  }

  private readonly handleComponentChange = (value: string) => {
    if (this.select.disabled || this.select.value === value) {
      this.refresh();
      return;
    }
    this.select.value = value;
    this.refresh();
    this.select.dispatchEvent(new Event('change', { bubbles: true }));
  };

  private readonly handleNativeChange = () => this.refresh();

  refresh() {
    if (!this.destroyed) {
      this.component?.setState(this.props());
    }
  }

  open() {
    this.component?.open();
  }

  close() {
    this.component?.close();
  }

  focus() {
    this.component?.focus();
  }

  destroy() {
    if (this.destroyed) {
      return;
    }
    this.destroyed = true;
    instances.delete(this);
    bySelect.delete(this.select);
    this.select.removeEventListener('change', this.handleNativeChange);
    this.component?.unmount();
    this.component?.destroy();
    this.component = undefined;
    this.element.remove();
    for (const [element, display] of this.hiddenElements) {
      element.style.display = display;
    }
    if (instances.size === 0) {
      removalObserver?.disconnect();
      removalObserver = undefined;
    }
  }
}

export const getMountedPmeNavigation = (select: HTMLSelectElement) => bySelect.get(select);

/**
 * Mount the navigation selects matched by a selector inside a container.
 *
 * @param container Element to search.
 * @param selector Selector for the native selects.
 * @param options Options applied to every matching select.
 */
export const mountPmeNavigation = async (container: ParentNode, selector: string, options: PmeNavigationOptions = {}) => {
  const created: PmeNavigationService[] = [];
  const services = Array.from(container.querySelectorAll<HTMLSelectElement>(selector), (select) => {
    const existing = getMountedPmeNavigation(select);
    const service = existing ?? PmeNavigationService.create(select, options);
    if (!existing) {
      created.push(service);
    }
    return service;
  });
  try {
    await Promise.all(services.map((service) => service.ready));
    return services;
  } catch (error) {
    created.forEach((service) => service.destroy());
    throw error;
  }
};

/**
 * Re-read captions, options and values, e.g. after legacy code changed the
 * caption button or the native select.
 *
 * @param container Element to search for mounted navigation selects.
 */
export const refreshPmeNavigation = (container: ParentNode) => {
  for (const select of Array.from(container.querySelectorAll('select'))) {
    getMountedPmeNavigation(select)?.refresh();
  }
};

subscribe(LEGACY_PAGE_CLEANUP, () => {
  for (const instance of instances) {
    instance.destroy();
  }
});
