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
  vi.restoreAllMocks();
});

describe('ChosenSelect automatic direction', () => {
  const geometry = (top: number, height = 200) => {
    const layout = { top, height };
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function(this: HTMLElement) {
      if (this.classList.contains('vs__dropdown-toggle')) {
        return new DOMRect(0, layout.top, 200, 40);
      }
      if (this.classList.contains('vs__dropdown-menu')) {
        return new DOMRect(0, 0, 200, layout.height);
      }
      return new DOMRect();
    });
    return layout;
  };

  it.each([
    { top: 100, height: 200, up: false, reason: 'prefers down when the menu fits below' },
    { top: window.innerHeight - 240, height: 200, up: false, reason: 'keeps down on an exact fit' },
    { top: window.innerHeight - 100, height: 200, up: true, reason: 'flips up when below is insufficient' },
    { top: 100, height: window.innerHeight * 2, up: false, reason: 'uses down when neither fits and below is larger' },
    { top: window.innerHeight - 100, height: window.innerHeight * 2, up: true, reason: 'uses up when neither fits and above is larger' },
    { top: (window.innerHeight - 40) / 2, height: window.innerHeight * 2, up: false, reason: 'prefers down on equal space' },
  ])('$reason', async ({ top, height, up }) => {
    geometry(top, height);
    const wrapper = render({ dropup: 'auto' });
    expect(wrapper.classes()).not.toContain('chosen-select--dropup');
    await open(wrapper);
    expect(wrapper.classes().includes('chosen-select--dropup')).toBe(up);
  });

  it.each([undefined, false, true])('preserves fixed direction %s regardless of available space', async (dropup) => {
    geometry(dropup ? 0 : window.innerHeight - 50);
    const wrapper = render({ dropup });
    await open(wrapper);
    expect(wrapper.classes().includes('chosen-select--dropup')).toBe(!!dropup);
  });

  it('updates while open on viewport resize, ancestor scroll, and filtering', async () => {
    const layout = geometry(window.innerHeight - 100);
    const wrapper = render({ dropup: 'auto' });
    await open(wrapper);
    expect(wrapper.classes()).toContain('chosen-select--dropup');
    layout.top = 100;
    window.dispatchEvent(new Event('resize'));
    await flushPromises();
    expect(wrapper.classes()).not.toContain('chosen-select--dropup');
    layout.top = window.innerHeight - 100;
    wrapper.element.parentElement!.dispatchEvent(new Event('scroll'));
    await flushPromises();
    expect(wrapper.classes()).toContain('chosen-select--dropup');
    layout.height = 20;
    await wrapper.get('input[role="combobox"]').setValue('violin');
    await flushPromises();
    expect(wrapper.classes()).not.toContain('chosen-select--dropup');
  });

  it('measures clipped ancestor content rather than only the viewport', async () => {
    geometry(300);
    const wrapper = render({ dropup: 'auto' });
    const container = wrapper.element.parentElement!;
    container.style.overflowY = 'auto';
    Object.defineProperty(container, 'getBoundingClientRect', {
      configurable: true,
      value: () => new DOMRect(0, 100, 300, 300),
    });
    vi.spyOn(container, 'clientHeight', 'get').mockReturnValue(300);
    expect(getComputedStyle(container).overflowY).toBe('auto');
    expect(container.clientHeight).toBe(300);
    await open(wrapper);
    expect(wrapper.classes()).toContain('chosen-select--dropup');
    container.style.overflowY = '';
  });

  it('rechecks when observed layout sizes change and releases observers on close', async () => {
    const layout = geometry(window.innerHeight - 100);
    const disconnect = vi.fn();
    let update!: () => void;
    vi.spyOn(globalThis, 'ResizeObserver').mockImplementation(class {
      constructor(callback: ResizeObserverCallback) { update = () => callback([], this); }
      observe = vi.fn();
      unobserve = vi.fn();
      disconnect = disconnect;
    });
    const wrapper = render({ dropup: 'auto' });
    await open(wrapper);
    expect(wrapper.classes()).toContain('chosen-select--dropup');
    layout.height = 10;
    update();
    await flushPromises();
    expect(wrapper.classes()).not.toContain('chosen-select--dropup');
    wrapper.vm.close();
    await flushPromises();
    expect(disconnect).toHaveBeenCalled();
    layout.top = window.innerHeight - 50;
    layout.height = 200;
    window.dispatchEvent(new Event('resize'));
    await flushPromises();
    expect(wrapper.classes()).not.toContain('chosen-select--dropup');
    await open(wrapper);
    expect(wrapper.classes()).toContain('chosen-select--dropup');
    const removeListener = vi.spyOn(window, 'removeEventListener');
    wrapper.unmount();
    wrappers.splice(wrappers.indexOf(wrapper), 1);
    expect(removeListener).toHaveBeenCalledWith('scroll', expect.any(Function), true);
    expect(removeListener).toHaveBeenCalledWith('resize', expect.any(Function));
  });
});

describe('ChosenSelect', () => {
  it('reserves one action slot only for non-clearable single selects', async () => {
    const wrapper = render();
    expect(wrapper.classes()).toContain('chosen-select--single-no-clear');
    await wrapper.setProps({ allowSingleDeselect: true });
    expect(wrapper.classes()).not.toContain('chosen-select--single-no-clear');
    await wrapper.setProps({ multiple: true, allowSingleDeselect: false });
    expect(wrapper.classes()).not.toContain('chosen-select--single-no-clear');
    wrapper.vm.setState({ options, label: 'Comparison', multiple: false });
    await flushPromises();
    expect(wrapper.classes()).toContain('chosen-select--single-no-clear');
  });

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
