/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DualListboxComponentProps, DualListboxProps } from '~/src/types/dual-listbox.ts';

import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import DualListbox from '~/src/components/DualListbox.vue';
import $ from '~/src/app/jquery.ts';
import { DualListboxService, getMountedDualListbox } from '~/src/services/mountable-dual-listbox.ts';
import { destroyMountedSelects } from '~/src/services/mounted-select-scope.ts';

import '~/src/app/jquery-readonly.ts';

const { callbacks, requestComponent } = vi.hoisted(() => ({
  callbacks: [] as Array<() => void>,
  requestComponent: vi.fn(),
}));
vi.mock('@rotdrop/async-nextcloud-event-bus', () => ({
  subscribe: (_name: string, callback: () => void) => callbacks.push(callback),
  awaitEmit: (_name: string, payload: { propsData: DualListboxComponentProps }) => requestComponent(payload.propsData),
}));
vi.mock('~/src/event-bus-events.ts', () => ({
  GET_VUE_COMPONENT: 'get-component',
  LEGACY_PAGE_CLEANUP: 'cleanup',
}));
vi.mock('~/src/config.ts', () => ({ appName: 'cafevdb' }));
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, text: string) => text }));
vi.mock('~/src/app/select-utils.ts', () => ({
  isVanilla: () => true,
  vueSelectActive: () => false,
}));

const createComponent = (props: DualListboxComponentProps) => ({
  props,
  mount: vi.fn(),
  unmount: vi.fn(),
  destroy: vi.fn(),
  setState: vi.fn(),
});
requestComponent.mockImplementation(async (props: DualListboxComponentProps) => createComponent(props));
const component = async () => requestComponent.mock.results.at(-1)!.value;
const tick = () => new Promise((resolve) => setTimeout(resolve));
const makeForm = () => {
  const form = document.createElement('form');
  form.innerHTML = '<select id="recipients" name="recipients[]" multiple>'
    + '<option value="a">Alice</option><option value="b" selected>Bob</option>'
    + '<option value="c" disabled>Disabled</option><option value="d" disabled selected>Disabled selected</option>'
    + '</select>';
  document.body.append(form);
  return { form, select: form.querySelector('select')! };
};
const mountSelect = async (select: HTMLSelectElement) => {
  const service = DualListboxService.mount(select);
  await service.ready;
  return service;
};

afterEach(() => {
  callbacks.forEach((callback) => callback());
  document.body.replaceChildren();
  vi.clearAllMocks();
  requestComponent.mockImplementation(async (props: DualListboxComponentProps) => createComponent(props));
});

describe('mountable dual listbox', () => {
  it('connects Vue moves, AJAX refreshes, locking and panel teardown to the native form', async () => {
    const { form, select } = makeForm();
    requestComponent.mockImplementationOnce(async (props: DualListboxComponentProps) => {
      const wrapper = mount(DualListbox, { props });
      return {
        mount: (element: HTMLElement) => element.replaceWith(wrapper.element),
        unmount: () => wrapper.unmount(),
        destroy() {},
        setState: (state: DualListboxProps) => wrapper.vm.setState(state),
      };
    });
    await mountSelect(select);
    await tick();
    const listener = vi.fn();
    $(form).on('change', listener);
    const lists = form.querySelectorAll<HTMLSelectElement>('.dual-listbox select');
    lists[0]!.options[0]!.selected = true;
    lists[0]!.dispatchEvent(new Event('change', { bubbles: true }));
    await tick();
    expect(new FormData(form).getAll('recipients[]')).toEqual(['a', 'b', 'd']);
    expect(listener).toHaveBeenCalledOnce();
    select.innerHTML = '<option value="new">New recipient</option>';
    await tick();
    expect(lists[0]!.options[0]!.text).toBe('New recipient');
    expect(lists[1]!.options).toHaveLength(0);
    $(select).readonly(true);
    await tick();
    expect(lists[0]!.disabled).toBe(true);
    $(select).readonly(false);
    await tick();
    expect(lists[0]!.disabled).toBe(false);
    destroyMountedSelects(form);
    expect(form.querySelector('.dual-listbox')).toBeNull();
    expect(select.style.display).toBe('');
    expect(getMountedDualListbox(select)).toBeUndefined();
  });

  it('preserves serialization and emits one bubbling change after updating the native selection', async () => {
    const { form, select } = makeForm();
    await mountSelect(select);
    expect(select.style.display).toBe('none');
    expect(new FormData(form).getAll('recipients[]')).toEqual(['b', 'd']);
    const snapshots: FormDataEntryValue[][] = [];
    $(form).on('change', () => snapshots.push(new FormData(form).getAll('recipients[]')));
    (await component()).props.onChange(['a', 'c']);
    expect(Array.from(select.selectedOptions, (option) => option.value)).toEqual(['a', 'd']);
    expect(snapshots).toEqual([['a', 'd']]);
    (await component()).props.onChange(['a', 'd']);
    expect(snapshots).toHaveLength(1);
  });

  it('refreshes option replacements, attributes, and native change events without emitting', async () => {
    const { select } = makeForm();
    await mountSelect(select);
    const listener = vi.fn();
    select.addEventListener('change', listener);
    select.innerHTML = '<option value="new" selected>New recipient</option>';
    select.setAttribute('readonly', 'readonly');
    await tick();
    expect((await component()).setState.mock.calls.at(-1)[0]).toMatchObject({
      modelValue: ['new'],
      readonly: true,
      options: [{ value: 'new', label: 'New recipient' }],
    });
    expect(listener).not.toHaveBeenCalled();
    select.options[0]!.selected = false;
    select.dispatchEvent(new Event('change'));
    expect((await component()).setState.mock.calls.at(-1)[0].modelValue).toEqual([]);
  });

  it('locks via the legacy readonly helper without placeholder options or losing submitted values', async () => {
    const { form, select } = makeForm();
    await mountSelect(select);
    $(select).readonly(true);
    expect(select.options).toHaveLength(4);
    expect(form.querySelector('input')).toBeNull();
    expect(new FormData(form).getAll('recipients[]')).toEqual(['b', 'd']);
    expect((await component()).setState.mock.calls.at(-1)[0].readonly).toBe(true);
    (await component()).props.onChange(['a']);
    expect(select.options[0]!.selected).toBe(false);
    $(select).readonly(false);
    expect((await component()).setState.mock.calls.at(-1)[0].readonly).toBe(false);
    expect(select.options).toHaveLength(4);
    (await component()).props.onChange(['a', 'd']);
    expect(new FormData(form).getAll('recipients[]')).toEqual(['a', 'd']);
  });

  it('rejects disabled changes and respects disabled optgroups', async () => {
    const { select } = makeForm();
    select.innerHTML = '<optgroup label="Locked" disabled><option value="a" selected>Alice</option></optgroup>'
      + '<option value="b">Bob</option>';
    await mountSelect(select);
    expect((await component()).props.options[0].disabled).toBe(true);
    (await component()).props.onChange(['b']);
    expect(Array.from(select.selectedOptions, (option) => option.value)).toEqual(['a', 'b']);
    select.disabled = true;
    (await component()).props.onChange([]);
    expect(Array.from(select.selectedOptions, (option) => option.value)).toEqual(['a', 'b']);
  });

  it('mounts once, destroys detached instances, and restores the native control', async () => {
    const { form, select } = makeForm();
    const first = await mountSelect(select);
    expect(await mountSelect(select)).toBe(first);
    expect(requestComponent).toHaveBeenCalledTimes(1);
    form.remove();
    await tick();
    expect(getMountedDualListbox(select)).toBeUndefined();
    expect(select.style.display).toBe('');
    expect((await component()).unmount).toHaveBeenCalledOnce();
    expect((await component()).destroy).toHaveBeenCalledOnce();
  });

  it('does not mount an instance destroyed while loading the component', async () => {
    const { select } = makeForm();
    const pending = Promise.withResolvers<ReturnType<typeof createComponent>>();
    requestComponent.mockReturnValueOnce(pending.promise);
    const service = DualListboxService.mount(select);
    const widget = createComponent({ options: [], modelValue: [] });
    service.destroy();
    pending.resolve(widget);
    await service.ready;
    expect(widget.mount).not.toHaveBeenCalled();
    expect(widget.destroy).toHaveBeenCalledOnce();
    expect(select.style.display).toBe('');
  });

  it('restores the native control on failure and permits retry', async () => {
    const { form, select } = makeForm();
    requestComponent.mockRejectedValueOnce(new Error('failed'));
    await expect(mountSelect(select)).rejects.toThrow('failed');
    expect(select.style.display).toBe('');
    expect(form.querySelector('.dual-listbox-control')).toBeNull();
    expect(getMountedDualListbox(select)).toBeUndefined();
    await mountSelect(select);
    expect(select.style.display).toBe('none');
  });
});
