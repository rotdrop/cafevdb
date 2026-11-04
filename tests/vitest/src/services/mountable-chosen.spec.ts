/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { VueSelectComponentProps, VueSelectValue } from '~/src/types/vue-select.ts';

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import VueSelect from '~/src/components/VueSelect.vue';
import $ from '~/src/app/jquery.ts';
import * as SelectUtils from '~/src/app/select-utils.ts';
import { ChosenService, getMountedChosen, mountChosen } from '~/src/services/mountable-chosen.ts';

import '~/src/app/jquery-chosen.ts';
import '~/src/app/jquery-readonly.ts';

const { callbacks, requestComponent, notify } = vi.hoisted(() => ({
  callbacks: [] as Array<() => void>,
  requestComponent: vi.fn(),
  notify: vi.fn(),
}));
vi.mock('@rotdrop/async-nextcloud-event-bus', () => ({
  subscribe: (_name: string, callback: () => void) => callbacks.push(callback),
  awaitEmit: (_name: string, payload: { propsData: VueSelectComponentProps }) => requestComponent(payload.propsData),
}));
vi.mock('~/src/event-bus-events.ts', () => ({
  GET_VUE_COMPONENT: 'get-component',
  LEGACY_PAGE_CLEANUP: 'cleanup',
}));
vi.mock('~/src/config.ts', () => ({ appName: 'cafevdb' }));
vi.mock('~/src/util/console.ts', () => ({
  default: class {
    error = vi.fn();
  },
}));
vi.mock('~/src/app/notification.ts', () => ({ show: notify }));

const createComponent = (props: VueSelectComponentProps) => ({
  props,
  mount: vi.fn(),
  unmount: vi.fn(),
  destroy: vi.fn(),
  setState: vi.fn(),
  getValue: () => props.modelValue,
  open: vi.fn(() => props.onOpen?.()),
  close: vi.fn(() => props.onClose?.()),
  focus: vi.fn(),
});
requestComponent.mockImplementation(async (props: VueSelectComponentProps) => createComponent(props));
const makeSelect = (attributes = '', options = '<option value="a">A</option><option value="b">B</option>') => {
  const form = document.createElement('form');
  form.innerHTML = `<label for="field">Field label</label><select id="field" name="field" ${attributes}>${options}</select>`;
  document.body.append(form);
  return form.querySelector('select')!;
};
const component = async () => requestComponent.mock.results.at(-1)!.value;
const changeComponentValue = async (value: VueSelectValue) => {
  (await component()).props.onChange(value);
};

afterEach(() => {
  callbacks.forEach((callback) => callback());
  document.body.replaceChildren();
  vi.clearAllMocks();
  requestComponent.mockImplementation(async (props: VueSelectComponentProps) => createComponent(props));
});

describe('mountChosen', () => {
  it('uses the shared select host and preserves native form submission', async () => {
    const select = makeSelect('class="cafevdb-vue-select extra" multiple', '<option value="a" selected>A</option><option value="b" selected>B</option>');
    const [service] = await mountChosen(select, { inherit_select_classes: true, width: '100%' });
    expect(getMountedChosen(select)).toBe(service);
    expect(service.element.classList.contains('extra')).toBe(true);
    expect(service.element.classList.contains('cafevdb-vue-select-control')).toBe(true);
    expect(service.element.className).not.toMatch(/chosen|selectize/);
    expect(service.element.style.width).toBe('100%');
    expect(select.hidden).toBe(true);
    expect(select.name).toBe('field');
    expect(new FormData(select.form!).getAll('field')).toEqual(['a', 'b']);
    expect((await component()).props).toMatchObject({
      label: 'Field label',
      modelValue: ['a', 'b'],
      multiple: true,
      hideSelected: true,
      nativeOrder: true,
      removeOnBackspace: false,
    });
  });

  it('synchronizes component changes before dispatching one bubbling change event', async () => {
    const select = makeSelect();
    await mountChosen(select);
    const listener = vi.fn(() => {
      expect(select.value).toBe('b');
      expect(new FormData(select.form!).get('field')).toBe('b');
    });
    select.form!.addEventListener('change', listener);
    await changeComponentValue('b');
    expect(listener).toHaveBeenCalledOnce();
    await changeComponentValue('b');
    expect(listener).toHaveBeenCalledOnce();
  });

  it('refreshes native selections silently through chosen:updated and change', async () => {
    const select = makeSelect();
    $(select).chosen();
    await getMountedChosen(select)!.ready;
    const widget = await component();
    const listener = vi.fn();
    $(select).on('change', listener).val('b').trigger('chosen:updated');
    expect(widget.setState.mock.calls.at(-1)[0].modelValue).toBe('b');
    expect(listener).not.toHaveBeenCalled();
    select.value = 'a';
    select.dispatchEvent(new Event('change'));
    expect(widget.setState.mock.calls.at(-1)[0].modelValue).toBe('a');
  });

  it('submits the empty placeholder option when clearing a single selection', async () => {
    const select = makeSelect('', '<option value=""></option><option value="b" selected>B</option>');
    const [service] = await mountChosen(select, { allow_single_deselect: true });
    await changeComponentValue(null);
    expect(service.getValue()).toBe('');
    expect(new FormData(select.form!).get('field')).toBe('');
  });

  it('refreshes options, placeholders, and disabled optgroups', async () => {
    const select = makeSelect();
    const [service] = await mountChosen(select);
    select.innerHTML = '<optgroup label="Group" disabled><option value="c">C</option></optgroup>';
    select.dataset.placeholder = 'Choose';
    service.refresh();
    expect((await component()).setState.mock.calls.at(-1)[0]).toMatchObject({
      placeholder: 'Choose',
      options: [{ value: 'c', label: 'C', groups: ['0'] }],
      groups: [{ value: '0', label: 'Group', disabled: true }],
    });
  });

  it('maps every behavioral option used by the legacy initializers', async () => {
    const select = makeSelect('class="cafevdb-vue-select--up"');
    await mountChosen(select, {
      disable_search: true,
      disable_search_threshold: 8,
      no_results_text: 'No matches',
      allow_single_deselect: true,
      single_backstroke_delete: false,
    });

    expect((await component()).props).toMatchObject({
      disableSearch: true,
      disableSearchThreshold: 8,
      noResultsText: 'No matches',
      allowSingleDeselect: true,
      dropup: true,
    });
  });

  it.each([
    { classes: '', option: undefined, expected: false },
    { classes: 'cafevdb-vue-select--auto', option: undefined, expected: 'auto' },
    { classes: 'cafevdb-vue-select--auto cafevdb-vue-select--up', option: undefined, expected: true },
    { classes: 'cafevdb-vue-select--up', option: 'auto', expected: 'auto' },
    { classes: 'cafevdb-vue-select--up', option: false, expected: false },
    { classes: '', option: true, expected: true },
  ] as const)('maps direction classes and options: %j', async ({ classes, option, expected }) => {
    const select = makeSelect(`class="${classes}"`);
    $(select).chosen({ dropup: option });
    await getMountedChosen(select)!.ready;
    expect((await component()).props.dropup).toBe(expected);
  });

  it('supports select-utils selection, replacement, clearing, and widget lookup', async () => {
    const select = makeSelect();
    const [service] = await mountChosen(select);
    const $select = $(select);
    expect(SelectUtils.vueSelectActive($select)).toBe(true);
    expect(SelectUtils.widget($select)[0]).toBe(service.element);
    expect(SelectUtils.selectFromWidget($(service.element))[0]).toBe(select);
    SelectUtils.selected($select, 'b');
    expect(SelectUtils.selected($select)).toBe('b');
    SelectUtils.replaceOptions($select, '<option value="c">C</option>');
    expect(SelectUtils.selected($select)).toBe('c');
    SelectUtils.deselectAll($select);
    expect(select.selectedIndex).toBe(-1);
  });

  it('locks readonly fields without disabling form submission or adding placeholders', async () => {
    const select = makeSelect('multiple', '<option value="a" selected>A</option>');
    const [service] = await mountChosen(select);
    $(select).readonly(true);
    expect(service.isLocked).toBe(true);
    expect(select.disabled).toBe(false);
    expect(new FormData(select.form!).getAll('field')).toEqual(['a']);
    expect(select.form!.querySelector('input[type="hidden"]')).toBeNull();
    $(select).readonly(false);
    expect(service.isLocked).toBe(false);
  });

  it('supports disabling, enabling, and rejecting stale blocked component changes', async () => {
    const select = makeSelect();
    const [service] = await mountChosen(select);
    service.disable();
    expect(new FormData(select.form!).has('field')).toBe(false);
    await changeComponentValue('b');
    expect(select.value).toBe('a');
    service.enable();
    service.lock();
    await changeComponentValue('b');
    expect(select.value).toBe('a');
    expect(new FormData(select.form!).get('field')).toBe('a');
    service.unlock();
    await changeComponentValue('b');
    expect(select.value).toBe('b');
  });

  it('delegates required validation and label focus, restoring the native select on destroy', async () => {
    const select = makeSelect('required');
    const [service] = await mountChosen(select);
    expect(select.required).toBe(false);
    expect((await component()).props.required).toBe(true);
    select.labels![0].click();
    expect((await component()).focus).toHaveBeenCalledOnce();
    service.setRequired(false);
    expect((await component()).setState.mock.calls.at(-1)[0].required).toBe(false);
    service.destroy();
    expect(select.required).toBe(true);
    expect(select.hidden).toBe(false);
    expect(getMountedChosen(select)).toBeUndefined();
    expect(document.getElementById('field-vue-select-control')).toBeNull();
  });

  it('resets the visible selection with its native form and respects cancelled resets', async () => {
    const select = makeSelect();
    const [service] = await mountChosen(select);
    service.setValue('b');
    select.form!.reset();
    await Promise.resolve();
    expect((await component()).setState.mock.calls.at(-1)[0].modelValue).toBe('a');
    service.setValue('b');
    select.form!.addEventListener('reset', (event) => event.preventDefault());
    select.form!.reset();
    await Promise.resolve();
    expect(service.getValue()).toBe('b');
  });

  it('does not duplicate instances on repeated initialization', async () => {
    const select = makeSelect('class="cafevdb-vue-select"');
    const [first] = await mountChosen(select);
    const [second] = await mountChosen(select.parentElement!);
    expect(second).toBe(first);
    expect(requestComponent).toHaveBeenCalledOnce();
  });

  it('destroys instances when the select or its host is removed', async () => {
    const select = makeSelect();
    const [service] = await mountChosen(select);
    service.element.remove();
    await Promise.resolve();
    expect(getMountedChosen(select)).toBeUndefined();
    expect((await component()).destroy).toHaveBeenCalledOnce();
  });

  it('does not mount an instance destroyed while loading', async () => {
    const select = makeSelect('required');
    const widget = createComponent({ options: [], label: 'Field' });
    let resolve!: (value: typeof widget) => void;
    requestComponent.mockReturnValueOnce(new Promise((done) => { resolve = done; }));
    const service = ChosenService.create(select);
    service.destroy();
    resolve(widget);
    await service.ready;
    expect(widget.mount).not.toHaveBeenCalled();
    expect(widget.destroy).toHaveBeenCalledOnce();
    expect(select.hidden).toBe(false);
    expect(select.required).toBe(true);
  });

  it('restores native controls on load failure and surfaces the error', async () => {
    const select = makeSelect('required');
    requestComponent.mockRejectedValueOnce(new Error('Load failed'));
    await expect(mountChosen(select)).rejects.toThrow('Load failed');
    expect(getMountedChosen(select)).toBeUndefined();
    expect(select.hidden).toBe(false);
    expect(select.required).toBe(true);
  });
});

describe('legacy chosen bridge', () => {
  it('connects the real Vue control to native forms and legacy refresh/open events', async () => {
    const mountWidget = (props: VueSelectComponentProps, element: HTMLElement) => mount(VueSelect, { props, attachTo: element });
    let wrapper: ReturnType<typeof mountWidget>;
    requestComponent.mockImplementationOnce(async (props: VueSelectComponentProps) => ({
      mount: (element: HTMLElement) => {
        wrapper = mountWidget(props, element.parentElement!);
        element.remove();
      },
      unmount: () => wrapper.unmount(),
      destroy: () => {},
      setState: (state: VueSelectComponentProps) => wrapper.vm.setState(state),
      open: () => wrapper.vm.open(),
      close: () => wrapper.vm.close(),
      focus: () => wrapper.vm.focus(),
    }));
    const select = makeSelect('required', '<option value=""></option><option value="b">B</option>');
    const $select = $(select).chosen({ allow_single_deselect: true });
    const service = getMountedChosen(select)!;
    await service.ready;
    await flushPromises();
    expect(select.form!.checkValidity()).toBe(false);
    $select.trigger('chosen:open');
    await flushPromises();
    const option = wrapper!.findAll('[role="option"]').find((option) => option.text() === 'B')!;
    await option.trigger('click');
    expect(new FormData(select.form!).get('field')).toBe('b');
    expect(select.form!.checkValidity()).toBe(true);
    $select.val('').trigger('chosen:updated');
    await flushPromises();
    expect(wrapper!.vm.getValue()).toBeNull();
    expect(service.getValue()).toBe('');
    expect(new FormData(select.form!).get('field')).toBe('');
    expect(select.options[0].selected).toBe(true);
    expect(select.form!.checkValidity()).toBe(false);
    service.destroy();
    expect(select.required).toBe(true);
    expect(select.hidden).toBe(false);
  });

  it('is chainable, exposes the service, and handles dropdown events without tooltip internals', async () => {
    const select = makeSelect();
    const opened = vi.fn();
    const closed = vi.fn();
    $(select).on('chosen:showing_dropdown', opened).on('chosen:hiding_dropdown', closed);
    const $select = $(select);
    expect($select.chosen({ width: 'auto' })).toBe($select);
    const service = getMountedChosen(select)!;
    await service.ready;
    expect($select.data('chosen')).toBe(service);
    $select.trigger('chosen:open');
    expect(opened).toHaveBeenCalledOnce();
    $select.trigger('chosen:close');
    expect(closed).toHaveBeenCalledOnce();
    $select.chosen('destroy');
    expect($select.data('chosen')).toBeUndefined();
    expect(select.hidden).toBe(false);
  });

  it('queues opening until the component is available and reads late native changes', async () => {
    const select = makeSelect();
    const $select = $(select).chosen();
    $select.val('b').trigger('chosen:updated').trigger('chosen:open');
    await getMountedChosen(select)!.ready;
    expect((await component()).open).toHaveBeenCalledOnce();
    expect((await component()).setState.mock.calls.at(-1)[0].modelValue).toBe('b');
  });

  it('runs dropdown cleanup when an open control is destroyed', async () => {
    const select = makeSelect();
    const closed = vi.fn();
    $(select).on('chosen:hiding_dropdown', closed).chosen();
    await getMountedChosen(select)!.ready;
    $(select).trigger('chosen:open').chosen('destroy');
    expect(closed).toHaveBeenCalledOnce();
  });

  it('notifies the user on initialization failure', async () => {
    const select = makeSelect();
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    requestComponent.mockRejectedValueOnce(new Error('Load failed'));
    $(select).chosen();
    await vi.waitFor(() => expect(notify).toHaveBeenCalledOnce());
    expect(error).toHaveBeenCalled();
    expect($(select).data('chosen')).toBeUndefined();
    error.mockRestore();
  });
});
