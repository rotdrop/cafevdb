/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { VueWrapper } from '@vue/test-utils';
import type { ChosenSelectComponentProps } from '~/src/types/chosen-select.ts';

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { h, ref } from 'vue';
import ChosenSelect from '~/src/components/ChosenSelect.vue';

vi.mock('~/src/config.ts', () => ({ appName: 'cafevdb' }));

const options = [
  { value: '', label: '' },
  { value: 'violin', label: 'Violin', groups: ['strings'] },
  { value: 'viola', label: 'Viola', groups: ['strings'] },
  { value: 'flute', label: 'Flûte' },
  { value: 'disabled', label: 'Disabled', disabled: true },
  { value: 'retired', label: 'Retired', groups: ['retired'] },
];
const groups = [
  { value: 'strings', label: 'Strings' },
  { value: 'retired', label: 'Retired group', disabled: true },
];
const wrappers: VueWrapper[] = [];
const render = (props: Partial<ChosenSelectComponentProps> = {}) => {
  const componentProps: ChosenSelectComponentProps = { options, groups, label: 'Instrument', ...props };
  const wrapper = mount(ChosenSelect, {
    attachTo: document.body,
    props: componentProps,
  });
  wrappers.push(wrapper);
  return wrapper;
};
const open = async (wrapper: ReturnType<typeof render>) => {
  wrapper.vm.open();
  await flushPromises();
  expect(wrapper.get('[role="combobox"]').attributes('aria-expanded')).toBe('true');
};
const choose = async (wrapper: ReturnType<typeof render>, label: string) => {
  await open(wrapper);
  const option = wrapper.findAll('[role="option"]').find((option) => option.text() === label);
  expect(option).toBeDefined();
  await option!.trigger('click');
};

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
});

describe('ChosenSelect', () => {
  it('selects a single value and emits the native-shaped value', async () => {
    const wrapper = render();
    await choose(wrapper, 'Violin');
    expect(wrapper.vm.getValue()).toBe('violin');
    expect(wrapper.emitted('change')).toEqual([['violin']]);
    expect(wrapper.emitted('update:modelValue')).toEqual([['violin']]);
    expect(wrapper.find('button.vs__clear').isVisible()).toBe(false);
  });

  it('allows clearing only when explicitly enabled', async () => {
    const wrapper = render({ modelValue: 'violin', allowSingleDeselect: true });
    await wrapper.get('button.vs__clear').trigger('click');
    expect(wrapper.vm.getValue()).toBeNull();
    expect(wrapper.emitted('change')).toEqual([[null]]);
  });

  it('selects multiple values and supports removing a selected item', async () => {
    const wrapper = render({ multiple: true });
    await choose(wrapper, 'Violin');
    await choose(wrapper, 'Viola');
    expect(wrapper.vm.getValue()).toEqual(['violin', 'viola']);
    await open(wrapper);
    expect(wrapper.findAll('[role="option"]').some((option) => option.text() === 'Violin')).toBe(false);
    await wrapper.get('button.vs__deselect').trigger('mousedown');
    expect(wrapper.vm.getValue()).toEqual(['viola']);
  });

  it('searches accent-insensitively and renders non-selectable group headings', async () => {
    const wrapper = render();
    await open(wrapper);
    const heading = wrapper.findAll('[role="option"]').find((option) => option.text() === 'Strings')!;
    expect(heading.classes()).toContain('vs__dropdown-option--disabled');
    await wrapper.get('input[role="combobox"]').setValue('flute');
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual(['Flûte']);
  });

  it('honors disabled options and disabled optgroups', async () => {
    const wrapper = render();
    await open(wrapper);
    for (const label of ['Disabled', 'Retired']) {
      const option = wrapper.findAll('[role="option"]').find((option) => option.text() === label)!;
      expect(option.classes()).toContain('vs__dropdown-option--disabled');
      await option.trigger('click');
      expect(wrapper.vm.getValue()).toBeNull();
    }
  });

  it.each([{ disableSearch: true }, { disableSearchThreshold: 10 }])('disables search with %j', async (props) => {
    const wrapper = render(props);
    await open(wrapper);
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.readOnly).toBe(true);
    expect(wrapper.findAll('[role="option"]').length).toBeGreaterThan(1);
  });

  it('keeps search for multiple selects below the threshold', () => {
    const wrapper = render({ multiple: true, disableSearchThreshold: 10 });
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.readOnly).toBe(false);
  });

  it('does not delete selected values with Backspace', async () => {
    const wrapper = render({ multiple: true, modelValue: ['violin'] });
    await wrapper.get('input[role="combobox"]').trigger('keydown', { key: 'Backspace', keyCode: 8 });
    expect(wrapper.vm.getValue()).toEqual(['violin']);
    expect(wrapper.emitted('change')).toBeUndefined();
  });

  it.each([{ disabled: true }, { readonly: true }])('blocks interaction with %j', async (props) => {
    const wrapper = render({ ...props, modelValue: 'violin' });
    wrapper.vm.open();
    await flushPromises();
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.disabled).toBe(true);
    expect(wrapper.emitted('open')).toBeUndefined();
    expect(wrapper.vm.getValue()).toBe('violin');
  });

  it('provides a labelled, required search input', async () => {
    const wrapper = render({ required: true });
    const input = wrapper.get<HTMLInputElement>('input[role="combobox"]').element;
    expect(input.labels?.[0]?.textContent).toContain('Instrument');
    expect(input.checkValidity()).toBe(false);
    await choose(wrapper, 'Flûte');
    expect(input.checkValidity()).toBe(true);
    expect(wrapper.vm.getValue()).toBe('flute');
  });

  it('updates parent-provided values without emitting changes', async () => {
    const value = ref<string|null>(null);
    const changed = vi.fn();
    const wrapper = mount({
      setup: () => () => h(ChosenSelect, { options, label: 'Instrument', required: true, modelValue: value.value, onChange: changed }),
    }, { attachTo: document.body });
    wrappers.push(wrapper);
    const input = wrapper.get<HTMLInputElement>('input[role="combobox"]').element;
    expect(input.checkValidity()).toBe(false);
    value.value = 'flute';
    await flushPromises();
    expect(input.checkValidity()).toBe(true);
    expect(wrapper.get('.vs__selected').text()).toBe('Flûte');
    expect(changed).not.toHaveBeenCalled();
  });

  it('validates required fields even when searching is disabled', () => {
    const wrapper = render({ required: true, disableSearch: true });
    const input = wrapper.get<HTMLInputElement>('input[role="combobox"]').element;
    expect(input.checkValidity()).toBe(false);
  });

  it('does not treat a blank single option or unselected search text as a valid selection', async () => {
    const wrapper = render({ required: true, modelValue: '' });
    const input = wrapper.get<HTMLInputElement>('input[role="combobox"]');
    expect(input.element.checkValidity()).toBe(false);
    await input.setValue('violin');
    expect(input.element.checkValidity()).toBe(false);
    await choose(wrapper, 'Violin');
    expect(input.element.checkValidity()).toBe(true);
  });

  it('refreshes options, properties, and values without emitting changes', async () => {
    const wrapper = render({ modelValue: 'violin' });
    wrapper.vm.setState({
      options: [{ value: 'new', label: 'New' }],
      label: 'Replacement',
      modelValue: 'new',
      dropup: true,
    });
    await open(wrapper);
    expect(wrapper.vm.getValue()).toBe('new');
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual(['New']);
    expect(wrapper.classes()).toContain('chosen-select--dropup');
    expect(wrapper.emitted('change')).toBeUndefined();
  });

  it('uses the configured no-results message as plain text', async () => {
    const wrapper = render({ noResultsText: '<b>No match</b>' });
    await open(wrapper);
    await wrapper.get('input[role="combobox"]').setValue('unknown');
    expect(wrapper.text()).toContain('<b>No match</b>: unknown');
    expect(wrapper.find('b').exists()).toBe(false);
  });

  it('can open and close programmatically', async () => {
    const wrapper = render();
    await open(wrapper);
    wrapper.vm.close();
    await flushPromises();
    expect(wrapper.get('[role="combobox"]').attributes('aria-expanded')).toBe('false');
    expect(wrapper.emitted('open')).toHaveLength(1);
    expect(wrapper.emitted('close')).toHaveLength(1);
  });
});
