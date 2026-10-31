/**
 * SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PmeSelectComponentProps, PmeSelectValue } from '~/src/types/pme-select.ts';

import { afterEach, describe, expect, it, vi } from 'vitest';
import $ from '~/src/app/jquery.ts';
import * as SelectUtils from '~/src/app/select-utils.ts';

import '~/src/app/jquery-readonly.ts';

interface FakeMountable {
  setRequired: ReturnType<typeof vi.fn>;
  props: PmeSelectComponentProps;
  element?: HTMLElement;
  parent?: HTMLElement;
  mount(element: HTMLElement): void;
  unmount(): void;
  destroy(): void;
  getValue(): PmeSelectValue;
  getOptions(): PmeSelectComponentProps['options'];
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
const { subscriptions, createComponent } = vi.hoisted(() => ({
  subscriptions: new Map<string, () => void>(),
  createComponent: vi.fn<(props: PmeSelectComponentProps) => FakeMountable>(),
}));

vi.mock('@rotdrop/async-nextcloud-event-bus', () => ({
  subscribe: (event: string, callback: () => void) => {
    subscriptions.set(event, callback);
  },
  awaitEmit: async (_event: string, payload: { propsData: PmeSelectComponentProps }) =>
    createComponent(payload.propsData),
}));

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
    setRequired: vi.fn(),
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
    getOptions: () => props.options,
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

createComponent.mockImplementation(createMountable);

import { getMountedSelectize, mountSelectize } from '~/src/services/mountable-select.ts';
import { destroyMountedSelects, mountedSelectScope } from '~/src/services/mounted-select-scope.ts';

const makeSelect = (html: string) => {
  const container = document.createElement('div');
  container.innerHTML = html;
  document.body.appendChild(container);
  return container;
};

afterEach(() => {
  subscriptions.get('cleanup')?.();
  for (const instance of mountables.splice(0)) {
    instance.unmount();
    instance.destroy();
  }
  document.body.replaceChildren();
  vi.clearAllMocks();
});

describe('mountSelectize', () => {
  it('updates callback-visible values before notification and native change handlers', async () => {
    const container = makeSelect('<form><select name="field" class="selectize"><option value="a">A</option><option value="b">B</option></select></form>');
    const select = container.querySelector('select')!;
    const form = container.querySelector('form')!;
    const events: string[] = [];
    const [service] = await mountSelectize(select, {
      onChange(value) {
        expect(service.getValue()).toBe(value);
        expect(new FormData(form).get('field')).toBe(value);
        events.push('callback');
      },
    });
    select.addEventListener('change', () => { events.push('native'); });
    mountables[0].setValue('b');
    expect(events).toEqual(['callback', 'native']);
    service.setValue('a', true);
    expect(events).toEqual(['callback', 'native']);
  });

  it('mirrors validated free-form contacts into their native optgroup', async () => {
    const container = makeSelect('<select class="selectize" multiple></select>');
    const select = container.querySelector('select')!;
    const [service] = await mountSelectize(select, {
      persist: true,
      groups: [{ value: 'free-form', label: 'Form Input' }],
    });
    mountables[0].props.options.push({ value: 'new@example.org', label: 'New', groups: ['free-form'] });
    mountables[0].setValue(['new@example.org']);
    const group = select.querySelector('optgroup')!;
    expect(group.dataset.groupId).toBe('free-form');
    expect(group.label).toBe('Form Input');
    expect(group.querySelector('option')!.selected).toBe(true);
    service.refresh();
    expect(mountables[0].setOptions).toHaveBeenLastCalledWith(
      expect.arrayContaining([expect.objectContaining({ value: 'new@example.org', groups: ['free-form'] })]),
      [{ value: 'free-form', label: 'Form Input', disabled: false }],
    );
  });

  it('preserves pending and dynamic required overrides', async () => {
    const container = makeSelect('<select class="selectize" required><option>A</option></select>');
    const select = container.querySelector('select')!;
    const scope = mountedSelectScope(container);
    const pending = scope.mount(select);
    getMountedSelectize(select)!.setRequired(false);
    const service = await pending;
    expect(select.required).toBe(false);
    expect(mountables[0].setRequired).toHaveBeenLastCalledWith(false);
    service.setRequired(true);
    expect(select.required).toBe(false);
    expect(mountables[0].setRequired).toHaveBeenLastCalledWith(true);
    destroyMountedSelects(container);
  });

  it('destroys scoped pending mounts before replacing their container', async () => {
    const container = makeSelect('<select class="selectize" name="field"><option>A</option></select>');
    const select = container.querySelector('select')!;
    const pending = mountedSelectScope(container).mount(select);
    destroyMountedSelects(container);
    container.replaceChildren();
    await pending;
    expect(container.children).toHaveLength(0);
    expect(select.hidden).toBe(false);
    expect(select.name).toBe('field');
  });

  it('does not dispose a replacement scope when an old scope is destroyed twice', async () => {
    const container = makeSelect('<select class="selectize"><option>A</option></select>');
    const old = mountedSelectScope(container);
    await old.mount(container.querySelector('select')!);
    old.destroy();
    const replacement = mountedSelectScope(container);
    old.destroy();
    expect(mountedSelectScope(container)).toBe(replacement);
    replacement.destroy();
  });

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

  describe('select-utils with Vue-backed selects', () => {
    const makeForm = (multiple = true) => {
      const container = makeSelect(`
          <form><select class="selectize" name="field${multiple ? '[]' : ''}" ${multiple ? 'multiple' : ''}>
            <optgroup label="Group"><option value="a">Alpha</option><option value="b">Beta</option></optgroup>
            <option value="c" disabled>Gamma</option>
          </select></form>
        `);
      return {
        select: container.querySelector('select')!,
        form: container.querySelector('form')!,
      };
    };

    it('detects the Vue widget without pretending it is a jQuery Selectize instance', async () => {
      const { select } = makeForm();
      const $select = $(select);
      expect(SelectUtils.isVanilla($select)).toBe(true);
      const [service] = await mountSelectize(select);
      expect(SelectUtils.isVanilla($select)).toBe(false);
      expect(SelectUtils.vueSelectActive($select)).toBe(true);
      expect(SelectUtils.selectizeActive($select)).toBe(false);
      expect(SelectUtils.isSelectizedJQuery($select)).toBe(false);
      expect(SelectUtils.getSelectize($select)).toBeUndefined();
      expect(SelectUtils.getControlObject($select)).toBe(service);
      expect(SelectUtils.widget($select)[0]).toBe(service.element);
      expect(SelectUtils.selectFromWidget(SelectUtils.widget($select))[0]).toBe(select);
      select.dataset.placeholder = 'Choose';
      SelectUtils.makePlaceholder($select);
      expect(select.options[0].value).toBe('a');
      service.destroy();
      expect(SelectUtils.isVanilla($select)).toBe(true);
      expect(SelectUtils.widget($select)[0]).toBe(select);
    });

    it('preserves legacy updates made while the asynchronous component is mounting', async () => {
      const { select, form } = makeForm();
      const $select = $(select);
      const request = mountSelectize(select);
      SelectUtils.selected($select, ['b', 'a']);
      SelectUtils.locked($select, true);
      $select.prop('disabled', true);
      SelectUtils.refreshWidgetProperties($select);
      expect(form.querySelectorAll('input[type="hidden"]')).toHaveLength(0);
      const [service] = await request;
      expect(service.getValue()).toEqual(['b', 'a']);
      expect(mountables[0].getValue()).toEqual(['b', 'a']);
      expect(service.isLocked).toBe(true);
      expect(service.isDisabled).toBe(true);
      expect(new FormData(form).getAll('field[]')).toEqual([]);
    });

    it('sets ordered values silently and preserves the explicit change-event contract', async () => {
      const { select, form } = makeForm();
      const $select = $(select);
      await mountSelectize(select);
      const changes = vi.fn(() => {
        expect(new FormData(form).getAll('field[]')).toEqual(['b', 'a']);
      });
      $select.on('change', changes);
      expect(SelectUtils.selected($select, ['b', 'a'])).toEqual([]);
      expect(changes).not.toHaveBeenCalled();
      expect(SelectUtils.selected($select)).toEqual(['b', 'a']);
      expect(mountables[0].getValue()).toEqual(['b', 'a']);
      expect(SelectUtils.selected($select, ['b', 'a'], true)).toEqual(['b', 'a']);
      expect(changes).toHaveBeenCalledOnce();
      SelectUtils.refreshWidget($select);
      expect(SelectUtils.selected($select)).toEqual(['b', 'a']);
      expect(SelectUtils.children($select)).toHaveLength(2);
      expect(SelectUtils.options($select)).toHaveLength(3);
      expect(SelectUtils.optionValues($select)).toEqual(['a', 'b']);
      expect(SelectUtils.optionByValue($select, 'b').text()).toBe('Beta');
      expect(SelectUtils.selectedOptions($select).map((_, option) => option.value).get()).toEqual(['a', 'b']);
      SelectUtils.deselectAll($select);
      expect(SelectUtils.selected($select)).toEqual([]);
      expect(SelectUtils.selectedOptions($select)).toHaveLength(0);
      expect(new FormData(form).getAll('field[]')).toEqual([]);
      expect(changes).toHaveBeenCalledOnce();
    });

    it('handles single-selection arrays and clears without a native fallback selection', async () => {
      const { select, form } = makeForm(false);
      const $select = $(select);
      await mountSelectize(select);
      expect(SelectUtils.selected($select, ['b', 'a'])).toBe('a');
      expect(SelectUtils.selected($select)).toBe('b');
      expect(mountables[0].getValue()).toBe('b');
      SelectUtils.selected($select, false);
      expect(SelectUtils.selected($select)).toBeNull();
      expect(select.selectedIndex).toBe(-1);
      SelectUtils.refreshWidget($select);
      expect(SelectUtils.selected($select)).toBeNull();
      expect(new FormData(form).has('field')).toBe(false);
    });

    it('refreshes native options and replaces options without remounting', async () => {
      const { select } = makeForm();
      const $select = $(select);
      await mountSelectize(select);
      SelectUtils.selected($select, ['b', 'a']);
      SelectUtils.optionByValue($select, 'b').text('Updated');
      SelectUtils.optionByValue($select, 'a').prop('disabled', true);
      SelectUtils.refreshWidget($select);
      expect(mountables[0].setOptions).toHaveBeenLastCalledWith([
        { value: 'a', label: 'Alpha', groups: ['0'], disabled: true },
        { value: 'b', label: 'Updated', groups: ['0'], disabled: false },
        { value: 'c', label: 'Gamma', groups: [], disabled: true },
      ], [{ value: '0', label: 'Group', disabled: false }]);
      SelectUtils.replaceOptions($select, '<option value="d" selected>Delta</option>');
      expect(SelectUtils.selected($select)).toEqual(['d']);
      expect(mountables[0].getValue()).toEqual(['d']);
      SelectUtils.replaceOptions($select, $('<option value="e" selected>Echo</option>'));
      expect(SelectUtils.selected($select)).toEqual(['e']);
      expect(mountables).toHaveLength(1);
    });

    it('locks without dropping submitted values and synchronizes disabled/readonly properties', async () => {
      const { select, form } = makeForm();
      const $select = $(select);
      await mountSelectize(select);
      SelectUtils.selected($select, ['b', 'a']);
      SelectUtils.locked($select, true);
      expect(SelectUtils.locked($select)).toBe(true);
      expect(new FormData(form).getAll('field[]')).toEqual(['b', 'a']);
      SelectUtils.locked($select, false);
      expect(SelectUtils.locked($select)).toBe(false);
      $select.prop('disabled', true);
      SelectUtils.refreshWidgetProperties($select);
      expect(SelectUtils.locked($select)).toBe(true);
      expect(new FormData(form).getAll('field[]')).toEqual([]);
      $select.prop('disabled', false).prop('readonly', true);
      SelectUtils.refreshWidgetProperties($select);
      expect(SelectUtils.locked($select)).toBe(true);
      expect(new FormData(form).getAll('field[]')).toEqual(['b', 'a']);
      $select.prop('readonly', false);
      SelectUtils.refreshWidgetProperties($select);
      expect(SelectUtils.locked($select)).toBe(false);
      expect(SelectUtils.selected($select)).toEqual(['b', 'a']);
    });

    it.each([false, true])('lets jquery-readonly lock Vue controls without duplicate placeholders (multiple=%s)', async (multiple) => {
      const { select, form } = makeForm(multiple);
      const $select = $(select);
      await mountSelectize(select);
      SelectUtils.selected($select, ['b', 'a']);
      const name = multiple ? 'field[]' : 'field';
      const values = multiple ? ['b', 'a'] : ['b'];
      $select.readonly(true);
      expect(SelectUtils.locked($select)).toBe(true);
      expect(select.disabled).toBe(false);
      expect(select.options[0].disabled).toBe(false);
      expect(form.querySelectorAll('.__jquery-readonly-placeholder__')).toHaveLength(0);
      expect(new FormData(form).getAll(name)).toEqual(values);
      $select.prop('disabled', true);
      expect(new FormData(form).getAll(name)).toEqual([]);
      $select.prop('disabled', false);
      expect(new FormData(form).getAll(name)).toEqual(values);
      $select.readonly(false);
      expect(SelectUtils.locked($select)).toBe(false);
    });

    it.each([false, true])('mirrors created Vue options for legacy lookup and submission (persist=%s)', async (persist) => {
      const { select, form } = makeForm();
      const $select = $(select);
      await mountSelectize(select, { create: true, persist });
      mountables[0].props.options.push({ value: 'new', label: 'New option' });
      mountables[0].setValue(['new']);
      expect(SelectUtils.selected($select)).toEqual(['new']);
      expect(SelectUtils.selectedOptions($select).text()).toBe('New option');
      expect(new FormData(form).getAll('field[]')).toEqual(['new']);
      SelectUtils.deselectAll($select);
      expect(SelectUtils.optionByValue($select, 'new')).toHaveLength(persist ? 1 : 0);
    });
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
