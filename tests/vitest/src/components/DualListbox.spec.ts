/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type * as NextcloudL10n from '@nextcloud/l10n';
import type { VueWrapper } from '@vue/test-utils';
import type { DualListboxProps } from '~/src/types/dual-listbox.ts';

import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { h, nextTick, reactive } from 'vue';
import TextFieldWithTrailingButton from '@rotdrop/nextcloud-vue-components/lib/components/TextFieldWithTrailingButton.vue';
import DualListbox from '~/src/components/DualListbox.vue';

vi.mock('~/src/config.ts', () => ({ appName: 'cafevdb' }));
vi.mock('@nextcloud/l10n', async (importOriginal) => ({
  ...await importOriginal<typeof NextcloudL10n>(),
  translate: (_app: string, text: string) => text,
}));

const options = [
  { value: 'a', label: 'Alice' },
  { value: 'b', label: 'Bob' },
  { value: 'c', label: 'Carol' },
  { value: 'd', label: 'Disabled', disabled: true },
  { value: 'e', label: 'Selected disabled', disabled: true },
];
const wrappers: VueWrapper[] = [];
const render = (props: Partial<DualListboxProps> = {}): VueWrapper<InstanceType<typeof DualListbox>> => {
  const wrapper = mount(DualListbox, {
    props: { options, modelValue: ['c', 'e'], ...props },
  });
  wrappers.push(wrapper);
  return wrapper;
};
const labels = (wrapper: Pick<VueWrapper, 'findAll'>, pane: number) =>
  wrapper.findAll('select')[pane]!.findAll('option').map((option) => option.text());

afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));

describe('DualListbox', () => {
  it('uses shared clearable filter fields with leading search icons', () => {
    const wrapper = render();
    const fields = wrapper.findAllComponents(TextFieldWithTrailingButton);
    expect(fields).toHaveLength(2);
    for (const [index, field] of fields.entries()) {
      expect(field.props()).toMatchObject({ trailingButton: 'clear' });
      expect(field.find('.input-field__icon .magnify-icon').exists()).toBe(true);
      expect(field.get('input').attributes('type')).toBe('search');
      expect(field.get('input').attributes('aria-label')).toBe(
        `Filter: ${index === 0 ? 'Remaining Recipients' : 'Selected Recipients'}`,
      );
    }
  });

  it('splits the original options and labels both lists', () => {
    const wrapper = render({ inputId: 'recipients' });
    expect(labels(wrapper, 0)).toEqual(['Alice', 'Bob', 'Disabled']);
    expect(labels(wrapper, 1)).toEqual(['Carol', 'Selected disabled']);
    expect(wrapper.get('label').attributes('for')).toBe('recipients-0');
    expect(wrapper.get('select').attributes('id')).toBe('recipients-0');
    expect(wrapper.find('[name]').exists()).toBe(false);
  });

  it('moves chosen names immediately in either direction', async () => {
    const wrapper = render();
    await wrapper.findAll('select')[0]!.setValue(['a', 'b']);
    expect(labels(wrapper, 0)).toEqual(['Disabled']);
    expect(labels(wrapper, 1)).toEqual(['Alice', 'Bob', 'Carol', 'Selected disabled']);
    expect(wrapper.emitted('change')).toEqual([[['a', 'b', 'c', 'e']]]);
    await wrapper.findAll('select')[1]!.setValue(['a']);
    expect(labels(wrapper, 0)).toEqual(['Alice', 'Disabled']);
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['b', 'c', 'e']]);
  });

  it('filters independently, shows counts, clears, and moves only filtered enabled options', async () => {
    const wrapper = render();
    await wrapper.findAll('input')[0]!.setValue('AL');
    await wrapper.findAll('input')[1]!.setValue('car');
    expect(labels(wrapper, 0)).toEqual(['Alice']);
    expect(labels(wrapper, 1)).toEqual(['Carol']);
    expect(wrapper.findAll('.dual-listbox__info')[0]!.text()).toBe('Filtered 1 from 3');
    await wrapper.findAll('.dual-listbox__move-all')[0]!.trigger('click');
    expect(wrapper.emitted('change')?.at(-1)).toEqual([['a', 'c', 'e']]);
    await wrapper.findAll('.dual-listbox__move-all')[1]!.trigger('click');
    expect(wrapper.emitted('change')?.at(-1)).toEqual([['a', 'e']]);
    await wrapper.findAll('.dual-listbox__filter button')[0]!.trigger('click');
    expect(labels(wrapper, 0)).toEqual(['Bob', 'Carol', 'Disabled']);
    expect(wrapper.findAll('input')[1]!.element.value).toBe('car');
  });

  it('treats filter punctuation as text, not a regular expression', async () => {
    const wrapper = render({ options: [{ value: 'a', label: 'Alice [solo]' }], modelValue: [] });
    await wrapper.findAll('input')[0]!.setValue('[');
    expect(labels(wrapper, 0)).toEqual(['Alice [solo]']);
  });

  it('preserves disabled choices on bulk moves and reports an empty list', async () => {
    const wrapper = render();
    await wrapper.findAll('.dual-listbox__move-all')[1]!.trigger('click');
    expect(labels(wrapper, 1)).toEqual(['Selected disabled']);
    expect(wrapper.findAll('.dual-listbox__move-all')[1]!.attributes('disabled')).toBeDefined();
    wrapper.vm.setState({ options, modelValue: [] });
    await nextTick();
    expect(wrapper.findAll('.dual-listbox__info')[1]!.text()).toBe('Empty list');
  });

  it.each(['readonly', 'disabled'] as const)('locks all controls when %s', async (property) => {
    const wrapper = render({ [property]: true });
    for (const control of wrapper.findAll('input, select, button')) {
      expect(control.attributes('disabled')).toBeDefined();
    }
    await wrapper.findAll('.dual-listbox__move-all')[0]!.trigger('click');
    expect(wrapper.emitted('change')).toBeUndefined();
  });

  it('accepts AJAX state refreshes without emitting or resetting filters', async () => {
    const wrapper = render();
    await wrapper.findAll('input')[0]!.setValue('bo');
    wrapper.vm.setState({
      options: [...options, { value: 'f', label: 'Boris' }],
      modelValue: ['a'],
    });
    await wrapper.vm.$nextTick();
    expect(labels(wrapper, 0)).toEqual(['Bob', 'Boris']);
    expect(labels(wrapper, 1)).toEqual(['Alice']);
    expect(wrapper.emitted('change')).toBeUndefined();
  });

  it('follows externally controlled props after a local move', async () => {
    const state = reactive<DualListboxProps>({ options, modelValue: ['c'] });
    const parent = mount({ setup: () => () => h(DualListbox, { ...state }) });
    wrappers.push(parent);
    const wrapper = parent.getComponent(DualListbox);
    await wrapper.findAll('select')[0]!.setValue(['a']);
    state.modelValue = ['b'];
    await nextTick();
    expect(labels(wrapper, 1)).toEqual(['Bob']);
    state.readonly = true;
    await nextTick();
    expect(wrapper.get('select').attributes('disabled')).toBeDefined();
  });
});
