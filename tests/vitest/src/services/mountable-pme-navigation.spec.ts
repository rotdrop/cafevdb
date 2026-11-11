/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PmeNavigationSelectComponentProps } from '~/src/types/pme-navigation-select.ts';

import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  getMountedPmeNavigation,
  mountPmeNavigation,
  refreshPmeNavigation,
} from '~/src/services/mountable-pme-navigation.ts';

const { callbacks, requestComponent } = vi.hoisted(() => ({
  callbacks: [] as Array<(event?: { containers: HTMLElement[] }) => void>,
  requestComponent: vi.fn(),
}));
vi.mock('@rotdrop/async-nextcloud-event-bus', () => ({
  subscribe: (_name: string, callback: (event?: { containers: HTMLElement[] }) => void) => callbacks.push(callback),
  awaitEmit: (_name: string, payload: { name: string; propsData: PmeNavigationSelectComponentProps }) =>
    requestComponent(payload.name, payload.propsData),
}));
vi.mock('~/src/event-bus-events.ts', () => ({
  GET_VUE_COMPONENT: 'get-component',
  LEGACY_PAGE_CLEANUP: 'cleanup',
}));

const createComponent = (_name: string, props: PmeNavigationSelectComponentProps) => ({
  props,
  mount: vi.fn(),
  unmount: vi.fn(),
  destroy: vi.fn(),
  setState: vi.fn(),
  getValue: () => props.modelValue,
  open: vi.fn(),
  close: vi.fn(),
  focus: vi.fn(),
});
requestComponent.mockImplementation(async (name: string, props: PmeNavigationSelectComponentProps) => createComponent(name, props));
const component = async () => requestComponent.mock.results.at(-1)!.value;

const makeForm = (selectAttributes = '') => {
  const form = document.createElement('form');
  form.innerHTML = '<span class="pme-pagerows">'
    + '<input type="submit" class="pme-pagerows" name="navop" value="Rows/Page">'
    + `<select class="pme-pagerows" name="navnp" ${selectAttributes}>`
    + '<option value="-1">∞</option><option value="5" selected>5</option><option value="10">10</option>'
    + '</select></span>';
  document.body.append(form);
  return {
    form,
    button: form.querySelector('input')!,
    select: form.querySelector('select')!,
  };
};

afterEach(() => {
  callbacks.forEach((callback) => callback());
  document.body.replaceChildren();
  vi.clearAllMocks();
  requestComponent.mockImplementation(async (name: string, props: PmeNavigationSelectComponentProps) => createComponent(name, props));
});

describe('mountPmeNavigation', () => {
  it('only destroys controls inside the page being replaced', async () => {
    const page = makeForm();
    const persistent = makeForm();
    await mountPmeNavigation(page.form, 'select');
    const [persistentService] = await mountPmeNavigation(persistent.form, 'select');
    callbacks.forEach((callback) => callback({ containers: [] }));
    expect(getMountedPmeNavigation(page.select)).toBeDefined();
    callbacks.forEach((callback) => callback({ containers: [page.form] }));
    expect(getMountedPmeNavigation(page.select)).toBeUndefined();
    expect(page.select.style.display).toBe('');
    expect(getMountedPmeNavigation(persistent.select)).toBe(persistentService);
    expect(persistent.select.style.display).toBe('none');
    callbacks.forEach((callback) => callback({ containers: [persistent.form] }));
    expect(getMountedPmeNavigation(persistent.select)).toBeUndefined();
  });

  it('replaces the caption button and select by one component', async () => {
    const { form, button, select } = makeForm();
    const [service] = await mountPmeNavigation(form, 'select.pme-pagerows', { searchThreshold: 10 });
    expect(getMountedPmeNavigation(select)).toBe(service);
    expect(requestComponent.mock.calls[0]![0]).toBe('PmeNavigationSelect');
    expect((await component()).props).toMatchObject({
      caption: 'Rows/Page',
      modelValue: '5',
      searchThreshold: 10,
      disabled: false,
      options: [{ value: '-1', label: '∞' }, { value: '5', label: '5' }, { value: '10', label: '10' }],
    });
    expect(button.style.display).toBe('none');
    expect(select.style.display).toBe('none');
    expect(select.nextElementSibling).toBe(service!.element);
    expect(new FormData(form).get('navnp')).toBe('5');
    expect(new FormData(form).has('navop')).toBe(false);
  });

  it('updates the native select before dispatching one bubbling change', async () => {
    const { form, select } = makeForm();
    await mountPmeNavigation(form, 'select');
    const values: string[] = [];
    form.addEventListener('change', (event) => values.push((event.target as HTMLSelectElement).value));
    (await component()).props.onChange('10');
    (await component()).props.onChange('10');
    expect(select.value).toBe('10');
    expect(values).toEqual(['10']);
  });

  it('ignores changes of disabled selects', async () => {
    const { form, select } = makeForm('disabled data-placeholder="1"');
    await mountPmeNavigation(form, 'select');
    expect((await component()).props).toMatchObject({ disabled: true, placeholder: '1' });
    const listener = vi.fn();
    form.addEventListener('change', listener);
    (await component()).props.onChange('10');
    expect(select.value).toBe('5');
    expect(listener).not.toHaveBeenCalled();
  });

  it('refreshes the caption after legacy code changed the button', async () => {
    const { form, button } = makeForm();
    await mountPmeNavigation(form, 'select');
    button.value = '#columns';
    refreshPmeNavigation(form);
    expect((await component()).setState.mock.calls.at(-1)[0].caption).toBe('#columns');
  });

  it('does not mount twice and restores the native controls on cleanup', async () => {
    const { form, button, select } = makeForm();
    const [first] = await mountPmeNavigation(form, 'select');
    const [second] = await mountPmeNavigation(form, 'select');
    expect(second).toBe(first);
    expect(requestComponent).toHaveBeenCalledTimes(1);
    callbacks.forEach((callback) => callback());
    expect((await component()).unmount).toHaveBeenCalled();
    expect(button.style.display).toBe('');
    expect(select.style.display).toBe('');
    expect(getMountedPmeNavigation(select)).toBeUndefined();
  });

  it('destroys the instance when the legacy markup is removed', async () => {
    const { form, select } = makeForm();
    await mountPmeNavigation(form, 'select');
    form.remove();
    await new Promise((resolve) => setTimeout(resolve));
    expect(getMountedPmeNavigation(select)).toBeUndefined();
    expect((await component()).destroy).toHaveBeenCalled();
  });

  it('leaves the native controls visible when mounting fails', async () => {
    const { form, button, select } = makeForm();
    requestComponent.mockRejectedValueOnce(new Error('failed'));
    await expect(mountPmeNavigation(form, 'select')).rejects.toThrow('failed');
    expect(button.style.display).toBe('');
    expect(select.style.display).toBe('');
    expect(form.querySelector('.pme-navigation-select-control')).toBeNull();
  });
});
