/**
 * @file
 *
 * Mount the PME Vue select in place of a legacy select, without jQuery.
 *
 * @license AGPL-3.0-or-later
 */

import type {
  PmeSelectComponentProps,
  PmeSelectCreateOptions,
  PmeSelectGroup,
  PmeSelectOption,
  PmeSelectValue,
} from '../types/pme-select.ts';
import type { MountableComponent } from './mountable-components.ts';

import {
  awaitEmit,
  subscribe,
} from '@rotdrop/async-nextcloud-event-bus';
import {
  GET_VUE_COMPONENT,
  LEGACY_PAGE_CLEANUP,
} from '../event-bus-events.ts';
import { PME_SELECT } from '../mountable-component-names.ts';

export interface SelectizeAdapterOptions {
  options?: PmeSelectOption[];
  items?: Array<string|number>;
  plugins?: Array<string|{ name?: string }>;
  valueField?: string;
  labelField?: string;
  create?: boolean|PmeSelectCreateOptions;
  createOnBlur?: boolean;
  persist?: boolean;
  hideSelected?: boolean;
  openOnFocus?: boolean;
  lockOptgroupOrder?: boolean;
  delimiter?: string;
  onChange?: (value: PmeSelectValue) => void;
  onOpen?: () => void;
  onClose?: () => void;
}

type ExposedSelect = {
  getValue(): PmeSelectValue;
  setValue(value: PmeSelectValue, silent?: boolean): void;
  setOptions(options: PmeSelectOption[], groups?: PmeSelectGroup[]): void;
  clear(silent?: boolean): void;
  moveItem(index: number, offset: number): void;
  createItem(input: string): Promise<boolean>;
  focus(): void;
  lock(): void;
  unlock(): void;
  disable(): void;
  enable(): void;
  isLocked: boolean;
  isDisabled: boolean;
  creating: boolean;
};

const adapterInstances = new Set<SelectizeService>();
const adapterBySelect = new WeakMap<HTMLSelectElement, SelectizeService>();

/**
 * Adapt one native PME select to the mountable Vue select component.
 *
 * The native select remains in the document (hidden) as the authoritative
 * source for PHP form submission and legacy change listeners.
 */
export class SelectizeService {
  private readonly originalName: string|null;
  private readonly originalRequired: boolean;
  private readonly originalHidden: string|null;
  private readonly originalAriaHidden: string|null;
  private readonly originalDisabled: boolean;
  private readonly mountHost: HTMLElement;
  private readonly mountPoint: HTMLElement;
  private component?: MountableComponent<typeof PME_SELECT>&ExposedSelect;
  private previousValue: string[];
  private destroyed = false;
  private updatingNativeSelect = false;
  private readonly handleNativeChange: () => void;
  private readonly selectizeOptions: SelectizeAdapterOptions;
  private ready: Promise<void> = Promise.resolve();

  private constructor(
    readonly select: HTMLSelectElement,
    options: SelectizeAdapterOptions,
  ) {
    this.selectizeOptions = options;
    this.originalName = select.getAttribute('name');
    this.originalRequired = select.required;
    this.originalHidden = select.getAttribute('hidden');
    this.originalAriaHidden = select.getAttribute('aria-hidden');
    this.originalDisabled = select.disabled;
    this.previousValue = this.readInitialValues(options);
    this.handleNativeChange = () => {
      if (!this.updatingNativeSelect && this.component) {
        this.applyValue(this.readNativeValues(), true, true);
      }
    };

    this.mountHost = document.createElement('span');
    this.mountHost.className = 'selectize-control pme-vue-select-control';
    this.mountHost.title = select.title || select.dataset.originalTitle || '';
    this.mountPoint = document.createElement('span');
    this.mountHost.appendChild(this.mountPoint);
    this.select.insertAdjacentElement('afterend', this.mountHost);
  }

  static async mount(select: HTMLSelectElement, options: SelectizeAdapterOptions = {}) {
    const existing = adapterBySelect.get(select);
    if (existing && !existing.destroyed) {
      await existing.ready;
      return existing;
    }

    const service = new SelectizeService(select, options);
    adapterBySelect.set(select, service);
    adapterInstances.add(service);
    try {
      service.ready = service.mountComponent();
      await service.ready;
      return service;
    } catch (error) {
      service.destroy();
      throw error;
    }
  }

  private readInitialValues(options: SelectizeAdapterOptions) {
    const values = options.items ?? this.readDataValues(this.select.dataset.initialValues) ?? this.readNativeValues();
    return this.normalizeValues(values.map(String));
  }

  private readNativeValues() {
    return Array.from(this.select.selectedOptions, (option) => option.value);
  }

  private normalizeValues(values: string[]) {
    return [...new Set(values)].filter((value) => this.findOption(value) !== undefined);
  }

  private findOption(value: string) {
    return Array.from(this.select.options).find((option) => option.value === value);
  }

  private readDataValues(raw: string|undefined) {
    if (raw === undefined) {
      return undefined;
    }
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed) || !parsed.every((value) => typeof value === 'string' || typeof value === 'number')) {
      throw new TypeError('The PME initial-values data attribute must contain an array of option values.');
    }
    return parsed as Array<string|number>;
  }

  private optionData() {
    const groups: PmeSelectGroup[] = [];
    const groupIds = new Map<HTMLOptGroupElement, string>();
    for (const [index, group] of Array.from(this.select.querySelectorAll<HTMLOptGroupElement>(':scope > optgroup')).entries()) {
      const value = group.dataset.groupId || String(index);
      groupIds.set(group, value);
      groups.push({
        value,
        label: group.label,
        disabled: group.disabled,
      });
    }
    const options = Array.from(this.select.options, (option) => {
      const group = option.parentElement instanceof HTMLOptGroupElement ? option.parentElement : undefined;
      const groupValue = group && groupIds.get(group);
      return {
        value: option.value,
        label: option.label || option.textContent || option.value,
        groups: groupValue === undefined ? [] : [groupValue],
        disabled: option.disabled,
      };
    });
    return { options, groups };
  }

  private getCreateOptions() {
    const create = this.selectizeOptions.create;
    if (!create) {
      return false;
    }
    if (create === true) {
      return true;
    }
    return {
      ...create,
      valueField: create.valueField ?? this.selectizeOptions.valueField ?? 'value',
      labelField: create.labelField ?? this.selectizeOptions.labelField ?? 'text',
    };
  }

  private getComponentProps(): PmeSelectComponentProps {
    const { options, groups } = this.optionData();
    const config = this.selectizeOptions;
    const plugins = config.plugins ?? [];
    return {
      options: config.options ?? options,
      groups,
      modelValue: this.previousValue,
      label: this.select.getAttribute('aria-label')
        || this.select.title
        || this.select.dataset.placeholder
        || this.select.id
        || this.select.name
        || '',
      rootClass: Array.from(this.select.classList)
        .filter((name) => !['selectize', 'no-chosen', 'not-empty'].includes(name))
        .join(' '),
      inputId: this.select.id ? `${this.select.id}-vue-select` : undefined,

      placeholder: this.select.dataset.placeholder || this.select.getAttribute('placeholder') || '',
      multiple: this.select.multiple,
      disabled: false,
      readonly: false,
      required: this.originalRequired,
      notEmpty: this.select.classList.contains('not-empty'),
      sortable: plugins.some((plugin) => (typeof plugin === 'string' ? plugin : plugin.name) === 'drag_drop'),
      create: this.getCreateOptions(),
      createOnBlur: config.createOnBlur ?? false,
      persist: config.persist ?? false,
      onChange: this.handleComponentChange,
      onOpen: config.onOpen,
      onClose: config.onClose,
    };
  }

  private readonly handleComponentChange = (value: PmeSelectValue) => {
    this.applyValue(value, false, false);
    this.selectizeOptions.onChange?.(value);
  };

  private async mountComponent() {
    const propsData = this.getComponentProps();
    const component = await awaitEmit(GET_VUE_COMPONENT, {
      name: PME_SELECT,
      propsData,
    });
    this.component = component as MountableComponent<typeof PME_SELECT>&ExposedSelect;
    if (this.destroyed) {
      this.component.unmount();
      this.component.destroy();
      this.component = undefined;
      return;
    }

    this.select.addEventListener('change', this.handleNativeChange);
    this.select.hidden = true;
    this.select.required = false;
    this.select.removeAttribute('name');

    this.component.mount(this.mountPoint);
    this.syncControlState();
    this.writeNativeValues(this.previousValue, false);
  }

  private writeNativeValues(values: string[], dispatchChange: boolean) {
    const normalized = this.normalizeValues(values);
    const changed = normalized.length !== this.previousValue.length
      || normalized.some((value, index) => value !== this.previousValue[index]);
    this.previousValue = normalized;
    this.updatingNativeSelect = true;
    try {
      for (const option of Array.from(this.select.options)) {
        option.selected = normalized.includes(option.value);
      }
      this.writeFormValues(normalized);
      if (dispatchChange && changed) {
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    } finally {
      this.updatingNativeSelect = false;
    }
  }

  private writeFormValues(values: string[]) {
    this.mountHost.querySelectorAll('[data-pme-select-value]').forEach((input) => input.remove());
    if (this.originalName === null) {
      return;
    }
    const disabled = this.component?.isDisabled ?? this.originalDisabled;
    for (const value of values) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = this.originalName;
      input.value = value;
      input.disabled = disabled;
      input.dataset.pmeSelectValue = '';
      this.mountHost.appendChild(input);
    }
  }

  private applyValue(value: PmeSelectValue, silent: boolean, updateComponent: boolean) {
    const values = value === null ? [] : Array.isArray(value) ? value : [value];
    const normalized = this.normalizeValues(values);
    if (updateComponent) {
      this.component?.setValue(this.select.multiple ? normalized : normalized[0] ?? null, true);
    }
    this.writeNativeValues(normalized, !silent);
  }

  /** Current selected option values, preserving their selection order. */
  getValue() {
    return this.select.multiple ? [...this.previousValue] : this.previousValue[0] ?? null;
  }

  /**
   * Set values, optionally suppressing the native `change` event.
   *
   * @param value Values to select.
   * @param silent Suppress native change notifications.
   */
  setValue(value: PmeSelectValue, silent = false) {
    this.applyValue(value, silent, true);
  }

  clear(silent = false) {
    this.setValue(null, silent);
  }

  /** Synchronize changed native options into the Vue select. */
  refresh() {
    const { options, groups } = this.optionData();
    this.component?.setOptions(this.selectizeOptions.options ?? options, groups);
    const nativeValues = this.readNativeValues();
    const previousValues = this.normalizeValues(this.previousValue);
    const selectionUnchanged = nativeValues.length === previousValues.length
      && nativeValues.every((value) => previousValues.includes(value));
    this.applyValue(selectionUnchanged ? previousValues : nativeValues, true, true);
    this.syncControlState();
    this.writeFormValues(this.previousValue);
  }

  private syncControlState() {
    if (this.select.disabled) {
      this.component?.disable();
    } else {
      this.component?.enable();
    }
    if (this.select.hasAttribute('readonly') || this.select.classList.contains('readonly')) {
      this.component?.lock();
    } else {
      this.component?.unlock();
    }
  }

  focus() {
    this.component?.focus();
  }

  lock() {
    this.component?.lock();
  }

  unlock() {
    this.component?.unlock();
  }

  disable() {
    this.component?.disable();
    this.writeFormValues(this.previousValue);
  }

  enable() {
    this.component?.enable();
    this.writeFormValues(this.previousValue);
  }

  get isLocked() {
    return this.component?.isLocked ?? this.select.disabled;
  }

  get isDisabled() {
    return this.component?.isDisabled ?? this.select.disabled;
  }

  destroy() {
    if (this.destroyed) {
      return;
    }
    this.destroyed = true;
    adapterInstances.delete(this);
    this.select.removeEventListener('change', this.handleNativeChange);
    this.component?.unmount();
    this.component?.destroy();
    this.mountHost.remove();
    if (this.originalHidden === null) {
      this.select.removeAttribute('hidden');
    } else {
      this.select.setAttribute('hidden', this.originalHidden);
    }
    this.select.required = this.originalRequired;
    if (this.originalName === null) {
      this.select.removeAttribute('name');
    } else {
      this.select.setAttribute('name', this.originalName);
    }
    if (this.originalAriaHidden === null) {
      this.select.removeAttribute('aria-hidden');
    } else {
      this.select.setAttribute('aria-hidden', this.originalAriaHidden);
    }
    adapterBySelect.delete(this.select);
    this.component = undefined;
  }
}

export const getMountedSelectize = (select: HTMLSelectElement) => adapterBySelect.get(select);

const parseSelectizeOptions = (select: HTMLSelectElement): SelectizeAdapterOptions => {
  const raw = select.dataset.selectizeOptions;
  if (!raw) {
    return {};
  }
  const parsed: unknown = JSON.parse(raw);
  if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
    throw new TypeError('The PME selectize-options data attribute must contain an object.');
  }
  return parsed as SelectizeAdapterOptions;
};

const findSelects = (target: HTMLElement|string) => {
  const elements = typeof target === 'string'
    ? Array.from(document.querySelectorAll<HTMLElement>(target))
    : [target];
  return elements.flatMap((element) => {
    if (element instanceof HTMLSelectElement) {
      return [element];
    }
    return Array.from(element.querySelectorAll<HTMLSelectElement>('select.selectize'));
  });
};

/**
 * Mount Vue select widgets for matching PME `<select>` elements.
 *
 * A selector can identify select elements directly or a container with
 * `.selectize` selects. Explicit options override the element's JSON data
 * attribute. The returned instances expose value, refresh, lock, and destroy
 * controls; no jQuery objects or plugins are installed.
 *
 * @param target Select, containing element, or CSS selector.
 * @param givenOptions Options applied to every matched select.
 */
export const mountSelectize = async (
  target: HTMLElement|string,
  givenOptions: SelectizeAdapterOptions = {},
): Promise<SelectizeService[]> => {
  const selectElements = findSelects(target);
  const uniqueElements = [...new Set(selectElements)];
  const requests = uniqueElements.map((select) => ({
    select,
    options: { ...parseSelectizeOptions(select), ...givenOptions },
  }));
  const instances: SelectizeService[] = [];
  try {
    for (const { select, options } of requests) {
      instances.push(await SelectizeService.mount(select, options));
    }
  } catch (error) {
    instances.forEach((instance) => instance.destroy());
    throw error;
  }
  return instances;
};

subscribe(LEGACY_PAGE_CLEANUP, async () => {
  await Promise.all([...adapterInstances].map(async (instance) => {
    instance.destroy();
  }));
});
