/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { VueSelectComponentProps, VueSelectControls, VueSelectValue } from '../types/vue-select.ts';
import type { MountableComponent } from './mountable-components.ts';

import { awaitEmit, subscribe } from '@rotdrop/async-nextcloud-event-bus';
import { appName } from '../config.ts';
import { GET_VUE_COMPONENT, LEGACY_PAGE_CLEANUP } from '../event-bus-events.ts';
import { VUE_SELECT } from '../mountable-component-names.ts';
import { readSelectOptions } from './select-options.ts';

export interface ChosenAdapterOptions extends Chosen.Options {
  onOpen?: () => void;
  onClose?: () => void;
  onUpdate?: () => void;
  onDestroy?: () => void;
}

const instances = new Set<ChosenService>();
const bySelect = new WeakMap<HTMLSelectElement, ChosenService>();
let removalObserver: MutationObserver|undefined;

/** A native select remains the source of truth for values and form submission. */
export class ChosenService {
  readonly element: HTMLDivElement;
  readonly ready: Promise<void>;
  private readonly mountPoint: HTMLSpanElement;
  private readonly originalHidden: string|null;
  private readonly originalRequired: boolean;
  private required: boolean;
  private component?: MountableComponent<typeof VUE_SELECT>&VueSelectControls;
  private destroyed = false;
  private locked = false;
  private pendingOpen = false;
  private opened = false;
  private readonly form: HTMLFormElement|null;
  private readonly labels: HTMLLabelElement[];
  private wasConnected: boolean;

  private constructor(
    readonly select: HTMLSelectElement,
    private readonly options: ChosenAdapterOptions,
  ) {
    this.originalHidden = select.getAttribute('hidden');
    this.originalRequired = select.required;
    this.required = select.required;
    this.form = select.form;
    this.labels = Array.from(select.labels ?? []);
    this.wasConnected = select.isConnected;
    this.element = document.createElement('div');
    this.element.className = `${appName}-vue-select-control`;
    this.element.classList.add(select.multiple ? `${appName}-vue-select-control--multiple` : `${appName}-vue-select-control--single`);
    if (options.inherit_select_classes) {
      select.classList.forEach((name) => this.element.classList.add(name));
    }
    if (select.id) {
      this.element.id = `${select.id}-vue-select-control`;
    }
    this.element.style.display = 'inline-block';
    this.element.style.verticalAlign = 'middle';
    this.element.style.width = options.width ?? `${select.offsetWidth || 160}px`;
    this.mountPoint = document.createElement('span');
    this.element.append(this.mountPoint);
    select.insertAdjacentElement('afterend', this.element);
    select.addEventListener('change', this.handleNativeChange);
    this.form?.addEventListener('reset', this.handleReset);
    for (const label of this.labels) {
      label.addEventListener('click', this.handleLabelClick);
    }
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

  static create(select: HTMLSelectElement, options: ChosenAdapterOptions = {}) {
    return bySelect.get(select) ?? new ChosenService(select, options);
  }

  private props(): VueSelectComponentProps {
    const { options, groups } = readSelectOptions(this.select);
    return {
      options,
      groups,
      modelValue: this.getValue(),
      label: this.select.getAttribute('aria-label')
        || Array.from(this.select.labels ?? [], (label) => label.textContent?.trim()).filter(Boolean).join(' ')
        || this.select.dataset.placeholder || this.select.title || this.select.name || this.select.id,
      inputId: this.select.id ? `${this.select.id}-vue-select-input` : undefined,
      placeholder: this.select.dataset.placeholder || '',
      multiple: this.select.multiple,
      disabled: this.isDisabled,
      readonly: this.isLocked,
      required: this.required,
      hideSelected: true,
      removeOnBackspace: false,
      nativeOrder: true,
      blankPlaceholder: true,
      retainMissingValues: false,
      searchValues: false,
      labelOutside: false,
      multiplePlaceholder: false,
      allowSingleDeselect: this.options.allow_single_deselect ?? false,
      disableSearch: this.options.disable_search ?? false,
      disableSearchThreshold: this.options.disable_search_threshold ?? 0,
      noResultsText: this.options.no_results_text,
      dropup: this.options.dropup ?? (this.select.classList.contains(`${appName}-vue-select--up`)
        ? true
        : this.select.classList.contains(`${appName}-vue-select--auto`) ? 'auto' : false),
      onChange: this.handleComponentChange,
      onOpen: () => {
        this.opened = true;
        this.options.onOpen?.();
      },
      onClose: () => {
        this.opened = false;
        this.options.onClose?.();
      },
    };
  }

  private async mountComponent() {
    const component = await awaitEmit(GET_VUE_COMPONENT, {
      name: VUE_SELECT,
      propsData: this.props(),
    }) as MountableComponent<typeof VUE_SELECT>&VueSelectControls;
    if (this.destroyed) {
      component.destroy();
      return;
    }
    this.component = component;
    component.mount(this.mountPoint);
    this.select.hidden = true;
    this.required = this.select.required;
    this.select.required = false;
    this.refresh();
    if (this.pendingOpen) {
      this.open();
    }
  }

  private readonly handleComponentChange = (value: VueSelectValue) => {
    if (this.isDisabled || this.isLocked) {
      this.refresh();
      return;
    }
    this.setValue(value);
  };

  private readonly handleNativeChange = () => this.refresh();
  private readonly handleReset = (event: Event) => {
    // The browser resets selected options after dispatching the reset event.
    queueMicrotask(() => {
      if (!this.destroyed && !event.defaultPrevented) {
        this.refresh();
      }
    });
  };

  private readonly handleLabelClick = (event: MouseEvent) => {
    if (this.component) {
      event.preventDefault();
      this.focus();
    }
  };

  getValue(): VueSelectValue {
    const values = Array.from(this.select.selectedOptions, (option) => option.value);
    return this.select.multiple ? values : values[0] ?? null;
  }

  setValue(value: VueSelectValue, silent = false) {
    const previous = JSON.stringify(this.getValue());
    const values = value === null ? [] : Array.isArray(value) ? value : [value];
    const selected = !this.select.multiple && values.length === 0 && this.select.options[0]?.value === ''
      ? ['']
      : this.select.multiple ? values : values.slice(0, 1);
    this.select.selectedIndex = -1;
    for (const option of Array.from(this.select.options)) {
      option.selected = selected.includes(option.value);
    }
    if (!Array.from(this.select.options).some((option) => selected.includes(option.value))) {
      this.select.selectedIndex = -1;
    }
    this.refresh();
    if (!silent && previous !== JSON.stringify(this.getValue())) {
      this.select.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  clear(silent = false) {
    this.setValue(null, silent);
  }

  refresh() {
    if (this.destroyed) {
      return;
    }
    if (this.select.required && this.component) {
      this.required = true;
      this.select.required = false;
    }
    this.component?.setState(this.props());
    this.element.classList.toggle(`${appName}-vue-select-control--disabled`, this.isDisabled || this.isLocked);
    this.options.onUpdate?.();
  }

  refreshProperties() {
    this.refresh();
  }

  setRequired(required: boolean) {
    this.required = required;
    if (this.component) {
      this.select.required = false;
    } else {
      this.select.required = required;
    }
    this.refresh();
  }

  get isLocked() {
    return this.locked || this.select.hasAttribute('readonly') || this.select.classList.contains('readonly')
      || ('readOnly' in this.select && !!this.select.readOnly);
  }

  get isDisabled() {
    return this.select.disabled;
  }

  lock() {
    this.locked = true;
    this.refresh();
  }

  unlock() {
    this.locked = false;
    this.refresh();
  }

  disable() {
    this.select.disabled = true;
    this.refresh();
  }

  enable() {
    this.select.disabled = false;
    this.refresh();
  }

  focus() {
    this.component?.focus();
  }

  open() {
    this.pendingOpen = true;
    this.component?.open();
  }

  close() {
    this.pendingOpen = false;
    this.component?.close();
  }

  destroy() {
    if (this.destroyed) {
      return;
    }
    this.destroyed = true;
    instances.delete(this);
    bySelect.delete(this.select);
    this.select.removeEventListener('change', this.handleNativeChange);
    this.form?.removeEventListener('reset', this.handleReset);
    for (const label of this.labels) {
      label.removeEventListener('click', this.handleLabelClick);
    }
    this.component?.unmount();
    this.component?.destroy();
    this.component = undefined;
    this.element.remove();
    if (this.originalHidden === null) {
      this.select.removeAttribute('hidden');
    } else {
      this.select.setAttribute('hidden', this.originalHidden);
    }
    this.select.required = this.originalRequired;
    if (this.opened) {
      this.opened = false;
      this.options.onClose?.();
    }
    this.options.onDestroy?.();
    if (instances.size === 0) {
      removalObserver?.disconnect();
      removalObserver = undefined;
    }
  }
}

export const getMountedChosen = (select: HTMLSelectElement) => bySelect.get(select);

/**
 * Mount one native select, or the app-prefixed Vue selects in a container.
 *
 * @param target Select, containing element, or CSS selector.
 * @param options Legacy Chosen options applied to matching selects.
 */
export const mountChosen = async (target: HTMLElement|string, options: ChosenAdapterOptions = {}) => {
  const targets = typeof target === 'string' ? [...document.querySelectorAll<HTMLElement>(target)] : [target];
  const selects = [...new Set(targets.flatMap((element) => element instanceof HTMLSelectElement
    ? [element]
    : [...element.querySelectorAll<HTMLSelectElement>(`select.${appName}-vue-select`)]))];
  const created: ChosenService[] = [];
  const services = selects.map((select) => {
    const existing = getMountedChosen(select);
    const service = existing ?? ChosenService.create(select, options);
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

subscribe(LEGACY_PAGE_CLEANUP, () => {
  for (const instance of instances) {
    instance.destroy();
  }
});
