/**
 * SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PmeSelectComponentProps, PmeSelectValue } from '~/src/types/pme-select.ts';

import { afterEach, describe, expect, it, vi } from 'vitest';

interface FakeMountable {
  props: PmeSelectComponentProps;
  element?: HTMLElement;
  parent?: HTMLElement;
  mount(element: HTMLElement): void;
  unmount(): void;
  destroy(): void;
  getValue(): PmeSelectValue;
  setValue(value: PmeSelectValue, silent?: boolean): void;
  setOptions: ReturnType<typeof vi.fn>;
  clear: ReturnType<typeof vi.fn>;
  moveItem: ReturnType<typeof vi.fn>;
  createItem: ReturnType<typeof vi.fn>;
  focus: ReturnType<typeof vi.fn>;
  lock: ReturnType<typeof vi.fn>;
  unlock: ReturnType<typeof vi.fn>;
  disable: ReturnType<typeof vi.fn>;
  enable: ReturnType<typeof vi.fn>;
  isLocked: boolean;
  isDisabled: boolean;
  creating: boolean;
}

const mountables: FakeMountable[] = [];
type MockBus = typeof globalThis & {
  __mountableSubscriptions: Map<string, () => void>;
  __createMountable: (props: PmeSelectComponentProps) => FakeMountable;
};
const mockBus = globalThis as MockBus;
const subscriptions = mockBus.__mountableSubscriptions;

vi.mock('~/src/event-bus-events.ts', () => ({
  GET_VUE_COMPONENT: 'get-vue-component',
  LEGACY_PAGE_CLEANUP: 'cleanup',
}));
vi.mock('~/src/mountable-component-names.ts', () => ({
  PME_SELECT: 'PmeSelect',
}));

function createMountable(props: PmeSelectComponentProps): FakeMountable {
  let selected = props.modelValue ?? null;
  const instance: FakeMountable = {
    props,
    element: undefined as HTMLElement|undefined,
    parent: undefined as HTMLElement|undefined,
    mount(element: HTMLElement) {
      this.element = element;
      this.parent = element.parentElement!;
      element.remove();
      const widget = document.createElement('span');
      widget.className = 'pme-select';
      this.parent.replaceChildren(widget);
    },
    unmount() {
      if (this.parent && this.element) {
        this.parent.replaceChildren(this.element);
      }
    },
    destroy() {},
    getValue: () => selected,
    setValue(value: PmeSelectValue, silent = false) {
      selected = value;
      if (!silent) {
        props.onChange?.(value);
      }
    },
    setOptions: vi.fn(),
    clear: vi.fn(),
    moveItem: vi.fn(),
    createItem: vi.fn(),
    focus: vi.fn(),
    lock: vi.fn(() => {
      instance.isLocked = true;
    }),
    unlock: vi.fn(() => {
      instance.isLocked = false;
    }),
    disable: vi.fn(() => {
      instance.isDisabled = true;
    }),
    enable: vi.fn(() => {
      instance.isDisabled = false;
    }),
    isLocked: false,
    isDisabled: false,
    creating: false,
  };
  mountables.push(instance);
  return instance;
}

mockBus.__createMountable = createMountable;

import { mountSelectize } from '~/src/services/mountable-select.ts';

const makeSelect = (html: string) => {
  const container = document.createElement('div');
  container.innerHTML = html;
  document.body.appendChild(container);
  return container;
};

afterEach(() => {
  for (const instance of mountables.splice(0)) {
    instance.unmount();
    instance.destroy();
  }
  document.body.replaceChildren();
  vi.clearAllMocks();
});

describe('mountSelectize', () => {
  it('mounts a native select through the Vue event bus and preserves form values', async () => {
    const container = makeSelect(`
      <form>
        <select id="instrument" name="instrument" class="pme-input selectize not-empty">
          <optgroup label="Strings" data-group-id="4">
            <option value="violin">Violin</option>
            <option value="viola" selected>Viola</option>
          </optgroup>
        </select>
      </form>
    `);
    const select = container.querySelector('select')!;
    const changes = vi.fn();
    select.addEventListener('change', changes);
    const [instance] = await mountSelectize(select);

    expect(instance).toBeTruthy();
    expect(instance.getValue()).toBe('viola');
    expect(select.hidden).toBe(true);
    expect(select.required).toBe(false);
    expect(select.hasAttribute('name')).toBe(false);
    expect(mountables[0].props).toMatchObject({
      inputId: 'instrument-vue-select',
      label: 'instrument',
      modelValue: ['viola'],
      multiple: false,
      notEmpty: true,
      groups: [{ value: '4', label: 'Strings', disabled: false }],
      options: [
        { value: 'violin', label: 'Violin', groups: ['4'], disabled: false },
        { value: 'viola', label: 'Viola', groups: ['4'], disabled: false },
      ],
    });

    const form = container.querySelector('form')!;
    const valuesSeenByChangeListener: string[][] = [];
    select.addEventListener('change', () => {
      valuesSeenByChangeListener.push(new FormData(form).getAll('instrument') as string[]);
    });
    mountables[0].setValue('violin');
    expect(select.options[0].selected).toBe(true);
    expect(select.options[1].selected).toBe(false);
    expect(changes).toHaveBeenCalledTimes(1);
    expect(valuesSeenByChangeListener).toEqual([['violin']]);
    expect(new FormData(form).getAll('instrument')).toEqual(['violin']);
    expect(instance.getValue()).toBe('violin');
  });

  it('reads legacy data attributes, converts option values, and maps plugin settings', async () => {
    const container = makeSelect(`
      <select class="selectize" multiple name="instruments[]"
        data-initial-values="[2,1]"
        data-selectize-options='{"create":false,"plugins":["drag_drop"],"persist":true,"lockOptgroupOrder":true}'>
        <option value="1">One</option>
        <option value="2">Two</option>
      </select>
    `);
    const select = container.querySelector('select')!;
    const [instance] = await mountSelectize(select);
    expect(instance.getValue()).toEqual(['2', '1']);
    expect(mountables[0].props).toMatchObject({
      multiple: true,
      modelValue: ['2', '1'],
      sortable: true,
      persist: true,
    });
    expect(select.hidden).toBe(true);
    expect(select.name).toBe('');
  });

  it('merges explicit configuration over data attributes and adapts remote creation fields', async () => {
    const container = makeSelect(`
      <select class="selectize" data-selectize-options='{"create":false,"valueField":"name","labelField":"label","createOnBlur":false}'>
        <option value="1">One</option>
      </select>
    `);
    const select = container.querySelector('select')!;
    await mountSelectize(select, {
      create: { url: 'validate/musicians/email', post: { failure: 'error' }, inputField: 'email' },
      createOnBlur: true,
      valueField: 'email',
      labelField: 'email',
    });
    expect(mountables[0].props).toMatchObject({
      create: {
        url: 'validate/musicians/email',
        post: { failure: 'error' },
        inputField: 'email',
        valueField: 'email',
        labelField: 'email',
      },
      createOnBlur: true,
    });
  });

  it('mounts each matching select under a selector and ignores unrelated selects', async () => {
    const container = makeSelect(`
      <select class="selectize"><option value="a">A</option></select>
      <select><option value="b">B</option></select>
      <div class="nested"><select class="selectize"><option value="c">C</option></select></div>
    `);
    const instances = await mountSelectize(container);
    expect(instances).toHaveLength(2);
    expect(instances.map((instance) => instance.getValue())).toEqual(['a', 'c']);
    expect(await mountSelectize('.nested')).toHaveLength(1);
    expect(mountables).toHaveLength(2);
  });

  it('does not mount an element twice', async () => {
    const container = makeSelect('<select class="selectize"><option value="a">A</option></select>');
    const select = container.querySelector('select')!;
    const [first] = await mountSelectize(select);
    const [second] = await mountSelectize(select);
    expect(second).toBe(first);
    expect(mountables).toHaveLength(1);
  });

  it('deduplicates concurrent mount requests for the same select', async () => {
    const container = makeSelect('<select class="selectize"><option value="a">A</option></select>');
    const select = container.querySelector('select')!;
    const [first, second] = await Promise.all([
      mountSelectize(select),
      mountSelectize(select),
    ]);
    expect(first[0]).toBe(second[0]);
    expect(mountables).toHaveLength(1);
  });

  it('refreshes native options and synchronizes external native selection changes', async () => {
    const container = makeSelect('<select class="selectize"><option value="a" selected>A</option></select>');
    const select = container.querySelector('select')!;
    const [instance] = await mountSelectize(select);
    const newOption = document.createElement('option');
    newOption.value = 'b';
    newOption.textContent = 'B';
    select.add(newOption);
    newOption.selected = true;
    await instance.refresh();
    expect(mountables[0].setOptions).toHaveBeenCalledWith(
      [
        { value: 'a', label: 'A', groups: [], disabled: false },
        { value: 'b', label: 'B', groups: [], disabled: false },
      ],
      [],
    );
    expect(instance.getValue()).toBe('b');

    select.options[0].selected = true;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    expect(mountables[0].getValue()).toBe('a');

    select.disabled = true;
    select.classList.add('readonly');
    instance.refresh();
    expect(instance.isDisabled).toBe(true);
    expect(instance.isLocked).toBe(true);
    select.disabled = false;
    select.classList.remove('readonly');
    instance.refresh();
    expect(instance.isDisabled).toBe(false);
    expect(instance.isLocked).toBe(false);
  });

  it('exposes control methods and dispatches native change for programmatic value updates', async () => {
    const container = makeSelect('<select class="selectize" multiple><option value="a">A</option><option value="b">B</option></select>');
    const select = container.querySelector('select')!;
    const [instance] = await mountSelectize(select);
    mountables[0].lock.mockClear();
    mountables[0].unlock.mockClear();
    mountables[0].disable.mockClear();
    mountables[0].enable.mockClear();
    const changes = vi.fn();
    select.addEventListener('change', changes);
    instance.setValue(['b', 'a']);
    expect(instance.getValue()).toEqual(['b', 'a']);
    expect(select.options[0].selected).toBe(true);
    expect(changes).toHaveBeenCalledTimes(1);
    instance.setValue(['a'], true);
    expect(changes).toHaveBeenCalledTimes(1);
    instance.clear();
    expect(instance.getValue()).toEqual([]);
    instance.lock();
    instance.unlock();
    instance.disable();
    instance.enable();
    instance.focus();
    expect(mountables[0].lock).toHaveBeenCalledOnce();
    expect(mountables[0].unlock).toHaveBeenCalledOnce();
    expect(mountables[0].disable).toHaveBeenCalledOnce();
    expect(mountables[0].enable).toHaveBeenCalledOnce();
    expect(mountables[0].focus).toHaveBeenCalledOnce();
  });

  it('restores the original select and submits ordered hidden values on destroy', async () => {
    const container = makeSelect('<form><select class="selectize" name="instrument[]" multiple required><option value="a">A</option><option value="b">B</option></select></form>');
    const select = container.querySelector('select')!;
    const [instance] = await mountSelectize(select);
    instance.setValue(['b', 'a']);
    expect(new FormData(container.querySelector('form')!).getAll('instrument[]')).toEqual(['b', 'a']);
    instance.disable();
    expect(new FormData(container.querySelector('form')!).getAll('instrument[]')).toEqual([]);
    instance.enable();
    expect(new FormData(container.querySelector('form')!).getAll('instrument[]')).toEqual(['b', 'a']);
    instance.destroy();
    expect(select.hidden).toBe(false);
    expect(select.required).toBe(true);
    expect(select.name).toBe('instrument[]');
    expect(container.querySelector('.pme-vue-select-control')).toBeNull();
    expect(container.querySelector('input[type="hidden"]')).toBeNull();
  });

  it('restores the select when malformed option configuration fails before mounting', async () => {
    const container = makeSelect('<select class="selectize" required name="field" data-selectize-options="[]"><option value="a">A</option></select>');
    const select = container.querySelector('select')!;
    await expect(mountSelectize(select)).rejects.toThrow('must contain an object');
    expect(select.hidden).toBe(false);
    expect(select.required).toBe(true);
    expect(select.name).toBe('field');
    expect(container.querySelector('.pme-vue-select-control')).toBeNull();
  });

  it('cleans up mounted services on legacy page cleanup', async () => {
    const container = makeSelect('<select class="selectize"><option value="a">A</option></select>');
    const select = container.querySelector('select')!;
    const [instance] = await mountSelectize(select);
    await subscriptions.get('cleanup')?.();
    expect(select.hidden).toBe(false);
    expect(instance.getValue()).toBe('a');
    expect(container.querySelector('.pme-vue-select-control')).toBeNull();
  });
});
