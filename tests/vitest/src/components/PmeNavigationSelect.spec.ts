/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { VueWrapper } from '@vue/test-utils';
import type { PmeNavigationSelectProps } from '~/src/types/pme-navigation-select.ts';

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import PmeNavigationSelect from '~/src/components/PmeNavigationSelect.vue';

const options = [
  { value: '-1', label: '∞' },
  { value: '5', label: '5' },
  { value: '10', label: '10' },
  { value: '15', label: '15', disabled: true },
];
const wrappers: VueWrapper[] = [];
const render = (props: Partial<PmeNavigationSelectProps> = {}) => {
  const wrapper = mount(PmeNavigationSelect, {
    attachTo: document.body,
    props: { caption: 'Rows/Page', options, modelValue: '5', ...props },
  });
  wrappers.push(wrapper);
  return wrapper;
};
const open = async (wrapper: ReturnType<typeof render>) => {
  wrapper.vm.open();
  await flushPromises();
  expect(wrapper.get('[role="combobox"]').attributes('aria-expanded')).toBe('true');
};
const menuOptions = () => Array.from(document.body.querySelectorAll<HTMLElement>('[role="option"]'));

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  document.body.replaceChildren();
});

describe('PmeNavigationSelect', () => {
  it('renders the caption and the current value inside the trigger', () => {
    const wrapper = render();
    expect(wrapper.get('.pme-navigation-select__caption').text()).toBe('Rows/Page');
    expect(wrapper.get('.pme-navigation-select__value').text()).toBe('5');
    expect(wrapper.get('label').text()).toBe('Rows/Page');
  });

  it('shows caption and placeholder when nothing is selected', () => {
    const wrapper = render({ caption: 'Go to', modelValue: null, placeholder: '1', disabled: true });
    expect(wrapper.get('.pme-navigation-select__caption').text()).toBe('Go to');
    expect(wrapper.get('.pme-navigation-select__value').text()).toBe('1');
    expect(wrapper.vm.getValue()).toBeNull();
  });

  it('emits the chosen value once and ignores disabled options', async () => {
    const wrapper = render();
    await open(wrapper);
    expect(document.body.querySelector('.vs__dropdown-menu')?.closest('.pme-navigation-select')).toBeNull();
    menuOptions().find((option) => option.textContent?.trim() === '15')!.click();
    await flushPromises();
    expect(wrapper.emitted('change')).toBeUndefined();
    menuOptions().find((option) => option.textContent?.trim() === '10')!.click();
    await flushPromises();
    expect(wrapper.emitted('change')).toEqual([['10']]);
    expect(wrapper.vm.getValue()).toBe('10');
  });

  it('only allows searching above the threshold', () => {
    expect(render().get('input').attributes('readonly')).toBeDefined();
    expect(render({ searchThreshold: 3 }).get('input').attributes('readonly')).toBeUndefined();
  });

  it('does not open when disabled', async () => {
    const wrapper = render({ disabled: true });
    wrapper.vm.open();
    await flushPromises();
    expect(wrapper.get('[role="combobox"]').attributes('aria-expanded')).toBe('false');
  });

  it('accepts refreshed state from the native controls', async () => {
    const wrapper = render();
    wrapper.vm.setState({ caption: '#columns', options, modelValue: '-1' });
    await flushPromises();
    expect(wrapper.get('.pme-navigation-select__caption').text()).toBe('#columns');
    expect(wrapper.get('.pme-navigation-select__value').text()).toBe('∞');
    expect(wrapper.vm.getValue()).toBe('-1');
  });
});
