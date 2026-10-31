/**
 * SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { VueWrapper } from '@vue/test-utils';
import type { PmeSelectComponentProps, PmeSelectOption, PmeSelectValue } from '~/src/types/pme-select.ts';

import axios from '@nextcloud/axios';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PmeSelect from '~/src/components/PmeSelect.vue';

vi.mock('~/src/config.ts', () => ({ appName: 'cafevdb' }));
vi.mock('~/src/util/console.ts', () => ({
  default: class {
    error = vi.fn();
  },
}));
vi.mock('@nextcloud/axios', () => ({ default: { post: vi.fn() } }));
vi.mock('~/src/toolkit/util/generate-url.ts', () => ({
  default: (url: string) => `/apps/cafevdb/${url}`,
}));

const options = [
  { value: 'violin', label: 'Violin', groups: ['strings'] },
  { value: 'viola', label: 'Viola', groups: ['strings'] },
  { value: 'flute', label: 'Flute', groups: ['winds'] },
  { value: 'retired', label: 'Retired', disabled: true },
];
const groups = [
  { value: 'strings', label: 'Strings' },
  { value: 'winds', label: 'Winds' },
];
const emailCreation = {
  url: 'validate/musicians/email',
  inputField: 'PME_data_email',
  post: { failure: 'error' },
  valueField: 'email',
  labelField: 'email',
};

const wrappers: VueWrapper[] = [];
const render = (props: Partial<PmeSelectComponentProps> & { 'onUpdate:modelValue'?: (value: PmeSelectValue) => void } = {}) => {
  const wrapper = mount(PmeSelect, {
    attachTo: document.body,
    props: { options, groups, label: 'Instruments', ...props },
  });
  wrappers.push(wrapper);
  return wrapper;
};

const open = async (wrapper: VueWrapper) => {
  await wrapper.get('input[role="combobox"]').trigger('focus');
  if (wrapper.get('input[role="combobox"]').attributes('aria-expanded') !== 'true') {
    await wrapper.get('input[role="combobox"]').trigger('keydown', { key: 'ArrowDown', keyCode: 40 });
  }
};

const choose = async (wrapper: VueWrapper, label: string) => {
  await open(wrapper);
  const option = wrapper.findAll('[role="option"]').find((option) => option.text() === label);
  expect(option, `Missing option "${label}"`).toBeDefined();
  await option!.trigger('click');
};

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  vi.clearAllMocks();
});

describe('PmeSelect', () => {
  it.each([false, true])('provides a visible input label when multiple is %s', (multiple) => {
    const wrapper = render({ multiple });
    const input = wrapper.get<HTMLInputElement>('input[role="combobox"]').element;
    expect(input.labels?.[0]?.textContent).toBe('Instruments');
  });

  it('selects and clears a single value without submitting the form', async () => {
    const wrapper = render({ name: 'instrument' });
    await choose(wrapper, 'Violin');
    expect(wrapper.vm.getValue()).toBe('violin');
    expect(wrapper.emitted('update:modelValue')).toEqual([['violin']]);
    expect(wrapper.emitted('change')).toEqual([['violin']]);
    expect(wrapper.get<HTMLInputElement>('input[type="hidden"]').element.value).toBe('violin');
    await wrapper.get('button.vs__clear').trigger('click');
    expect(wrapper.vm.getValue()).toBeNull();
    expect(wrapper.get<HTMLInputElement>('input[type="hidden"]').element.value).toBe('');
    expect(wrapper.findAll('button').every((button) => button.attributes('type') === 'button')).toBe(true);
  });

  it('preserves initial selection order, deduplicates values, and submits them in order', async () => {
    const wrapper = render({
      multiple: true,
      name: 'instruments[]',
      modelValue: ['flute', 'violin', 'flute'],
    });
    expect(wrapper.vm.getValue()).toEqual(['flute', 'violin']);
    expect(wrapper.findAll('[data-value] > span:first-child').map((item) => item.text())).toEqual(['Flute', 'Violin']);
    const form = document.createElement('form');
    wrapper.element.appendChild(form);
    wrapper.findAll('input[type="hidden"]').forEach((input) => form.appendChild(input.element));
    expect(new FormData(form).getAll('instruments[]')).toEqual(['flute', 'violin']);
    expect(wrapper.emitted('change')).toBeUndefined();
  });

  it('follows external values and option label updates without echoing changes', async () => {
    const wrapper = render({ modelValue: 'missing' });
    expect(wrapper.text()).toContain('missing');
    await wrapper.setProps({ options: [...options, { value: 'missing', label: 'Now available' }] });
    expect(wrapper.text()).toContain('Now available');
    await wrapper.setProps({ modelValue: 'viola' });
    expect(wrapper.vm.getValue()).toBe('viola');
    expect(wrapper.emitted('change')).toBeUndefined();
  });

  it('filters labels by all query words and only shows nonempty groups in declared order', async () => {
    const wrapper = render({
      options: [
        { value: '1', label: 'Bass flute', groups: ['winds'] },
        { value: '2', label: 'Bass violin', groups: ['strings'] },
      ],
    });
    await open(wrapper);
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual(['Strings', 'Bass violin', 'Winds', 'Bass flute']);
    await wrapper.get('input[role="combobox"]').setValue('FLU bass');
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual(['Winds', 'Bass flute']);
  });

  it('supports membership in multiple groups without duplicate dropdown keys', async () => {
    const wrapper = render({ options: [{ value: '1', label: 'Both', groups: ['strings', 'winds'] }] });
    await open(wrapper);
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual(['Strings', 'Both', 'Winds', 'Both']);
    await choose(wrapper, 'Both');
    expect(wrapper.vm.getValue()).toBe('1');
  });

  it('matches labels without requiring diacritics', async () => {
    const wrapper = render({ options: [{ value: '1', label: 'Flöte' }] });
    await wrapper.get('input[role="combobox"]').setValue('FLO');
    await open(wrapper);
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual(['Flöte']);
  });

  it('supports keyboard selection and emits dropdown lifecycle events', async () => {
    const wrapper = render({ options: [{ value: '1', label: 'Violin' }] });
    await open(wrapper);
    expect(wrapper.emitted('open')).toHaveLength(1);
    await wrapper.get('input[role="combobox"]').trigger('keydown', { key: 'ArrowDown', keyCode: 40 });
    await wrapper.get('input[role="combobox"]').trigger('keydown', { key: 'Enter', keyCode: 13 });
    expect(wrapper.vm.getValue()).toBe('1');
    expect(wrapper.emitted('close')).toHaveLength(1);
  });

  it('does not select group headings, disabled options, or disabled groups', async () => {
    const wrapper = render({ groups: [{ ...groups[0], disabled: true }, groups[1]] });
    await choose(wrapper, 'Strings');
    await choose(wrapper, 'Violin');
    await choose(wrapper, 'Retired');
    expect(wrapper.vm.getValue()).toBeNull();
    await choose(wrapper, 'Flute');
    expect(wrapper.vm.getValue()).toBe('flute');
  });

  it('leaves selected options visible but does not add duplicates', async () => {
    const wrapper = render({ multiple: true, modelValue: ['violin'] });
    await choose(wrapper, 'Violin');
    expect(wrapper.vm.getValue()).toEqual(['violin']);
    await choose(wrapper, 'Viola');
    expect(wrapper.vm.getValue()).toEqual(['violin', 'viola']);
  });

  it('removes individual selections and prevents emptying not-empty fields', async () => {
    const wrapper = render({ multiple: true, notEmpty: true, modelValue: ['violin', 'viola'] });
    await wrapper.get('button[aria-label="Remove Violin"]').trigger('click');
    expect(wrapper.vm.getValue()).toEqual(['viola']);
    expect(wrapper.get('button[aria-label="Remove Viola"]').attributes('disabled')).toBeDefined();
    await wrapper.get('button[aria-label="Remove Viola"]').trigger('click');
    expect(wrapper.vm.getValue()).toEqual(['viola']);
  });

  it('does not clear a not-empty single selection using the keyboard', async () => {
    const wrapper = render({ notEmpty: true, modelValue: 'violin' });
    const input = wrapper.get('input[role="combobox"]');
    await input.trigger('keydown', { key: 'Backspace', keyCode: 8 });
    expect(wrapper.vm.getValue()).toBe('violin');
  });

  it('removes multiple selections with Backspace, retaining the final not-empty value', async () => {
    const wrapper = render({ multiple: true, notEmpty: true, modelValue: ['violin', 'viola'] });
    const input = wrapper.get('input[role="combobox"]');
    await input.trigger('keydown', { key: 'Backspace', keyCode: 8 });
    expect(wrapper.vm.getValue()).toEqual(['violin']);
    await input.trigger('keydown', { key: 'Backspace', keyCode: 8 });
    expect(wrapper.vm.getValue()).toEqual(['violin']);
  });

  it('moves selections with buttons and reports the new order', async () => {
    const wrapper = render({ multiple: true, sortable: true, modelValue: ['violin', 'viola', 'flute'] });
    await wrapper.get('button[aria-label="Move Violin later"]').trigger('click');
    expect(wrapper.vm.getValue()).toEqual(['viola', 'violin', 'flute']);
    await wrapper.get('button[aria-label="Move Flute earlier"]').trigger('click');
    expect(wrapper.vm.getValue()).toEqual(['viola', 'flute', 'violin']);
    expect(wrapper.emitted('change')?.at(-1)).toEqual([['viola', 'flute', 'violin']]);
  });

  it('reorders selections using drag and drop', async () => {
    const wrapper = render({ multiple: true, sortable: true, modelValue: ['violin', 'viola', 'flute'] });
    const dataTransfer = { setData: vi.fn(), effectAllowed: '' };
    await wrapper.get('[data-value="flute"]').trigger('dragstart', { dataTransfer });
    await wrapper.get('[data-value="violin"]').trigger('drop');
    expect(wrapper.vm.getValue()).toEqual(['flute', 'violin', 'viola']);
    expect(dataTransfer.setData).toHaveBeenCalledWith('text/plain', 'flute');
  });

  it('does not reorder when sorting is disabled or indices are out of range', () => {
    const wrapper = render({ multiple: true, modelValue: ['violin', 'viola'] });
    wrapper.vm.moveItem(0, 1);
    wrapper.vm.moveItem(-1, 1);
    expect(wrapper.vm.getValue()).toEqual(['violin', 'viola']);
  });

  it.each(['disabled', 'readonly', 'locked'] as const)('blocks edits when %s without losing the value', async (property) => {
    const wrapper = render({ multiple: true, sortable: true, modelValue: ['violin', 'viola'], [property]: true });
    expect(wrapper.get('input[role="combobox"]').attributes('disabled')).toBeDefined();
    wrapper.vm.moveItem(0, 1);
    await wrapper.get('button[aria-label="Remove Violin"]').trigger('click');
    expect(wrapper.vm.getValue()).toEqual(['violin', 'viola']);
  });

  it('submits readonly and locked values, but not disabled values', async () => {
    const wrapper = render({ name: 'instrument', modelValue: 'violin', readonly: true });
    expect(wrapper.get('input[type="hidden"]').attributes('disabled')).toBeUndefined();
    await wrapper.setProps({ readonly: false, locked: true });
    expect(wrapper.get('input[type="hidden"]').attributes('disabled')).toBeUndefined();
    await wrapper.setProps({ disabled: true });
    expect(wrapper.get('input[type="hidden"]').attributes('disabled')).toBeDefined();
  });

  it('exposes programmatic value, lock, and enable controls including silent updates', async () => {
    const wrapper = render();
    wrapper.vm.setValue('viola', true);
    expect(wrapper.vm.getValue()).toBe('viola');
    expect(wrapper.emitted('change')).toBeUndefined();
    wrapper.vm.lock();
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.isLocked).toBe(true);
    wrapper.vm.unlock();
    wrapper.vm.disable();
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.isDisabled).toBe(true);
    wrapper.vm.enable();
    wrapper.vm.clear();
    expect(wrapper.vm.getValue()).toBeNull();
    expect(wrapper.emitted('change')).toEqual([[null]]);
  });

  it('provides native required validation and focus', async () => {
    const wrapper = render({ required: true });
    wrapper.vm.focus();
    expect(document.activeElement).toBe(wrapper.get('input[role="combobox"]').element);
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.checkValidity()).toBe(false);
    await wrapper.get('input[role="combobox"]').setValue('vi');
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.checkValidity()).toBe(false);
    await choose(wrapper, 'Violin');
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.checkValidity()).toBe(true);
  });

  it('updates both native required and custom validity through setRequired', async () => {
    const wrapper = render({ required: true });
    const input = wrapper.get<HTMLInputElement>('input[role="combobox"]').element;
    expect(input.checkValidity()).toBe(false);
    wrapper.vm.setRequired(false);
    await wrapper.vm.$nextTick();
    expect(input.required).toBe(false);
    expect(input.checkValidity()).toBe(true);
    wrapper.vm.setRequired(true);
    await wrapper.vm.$nextTick();
    expect(input.required).toBe(true);
    expect(input.checkValidity()).toBe(false);
  });

  it.each([false, true])('honors closeAfterSelect=%s for multiple selection', async (closeAfterSelect) => {
    const wrapper = render({ multiple: true, closeAfterSelect });
    await choose(wrapper, 'Violin');
    expect(wrapper.get('input[role="combobox"]').attributes('aria-expanded')).toBe(String(!closeAfterSelect));
  });

  it('searches option values as well as labels and preserves plain metadata titles', async () => {
    const wrapper = render({
      options: [{ value: 'person@example.org', label: 'Person', title: 'Subject: Example\nCreated yesterday' }],
    });
    await wrapper.get('input[role="combobox"]').setValue('example.org');
    await open(wrapper);
    const choice = wrapper.findAll('[role="option"]').find((option) => option.text() === 'Person')!;
    expect(choice.get('span[title]').attributes('title')).toBe('Subject: Example\nCreated yesterday');
  });

  it.each([false, true])('restores a removed label to the search when multiple=%s', async (multiple) => {
    const wrapper = render({ multiple, modelValue: multiple ? ['violin'] : 'violin', restoreOnBackspace: true });
    const input = wrapper.get<HTMLInputElement>('input[role="combobox"]');
    await input.trigger('keydown', { key: 'Backspace', keyCode: 8 });
    expect(wrapper.vm.getValue()).toEqual(multiple ? [] : null);
    expect(input.element.value).toBe('Violin');
  });

  it('reports asynchronous creation before selection change and retains optgroup data', async () => {
    const events: string[] = [];
    const option = { value: 'new@example.org', label: 'New Person', groups: ['free-form'] };
    const resolve = vi.fn(async () => option);
    const wrapper = render({
      create: { resolve },
      onCreate: () => { events.push('create'); },
      onChange: () => { events.push('change'); },
    });
    expect(await wrapper.vm.createItem('new')).toBe(true);
    expect(resolve).toHaveBeenCalledWith('new', expect.any(AbortSignal));
    expect(wrapper.emitted('create')).toEqual([[option]]);
    expect(events).toEqual(['create', 'change']);
    expect(wrapper.vm.getValue()).toBe(option.value);
    expect(wrapper.vm.getOptions()).toContainEqual(option);
  });

  it('cancels asynchronous resolvers without applying their stale result', async () => {
    let finish!: (option: PmeSelectOption) => void;
    const resolve = vi.fn((_input: string, _signal: AbortSignal) => new Promise<PmeSelectOption>((done) => { finish = done; }));
    const wrapper = render({ create: { resolve } });
    const pending = wrapper.vm.createItem('new');
    wrapper.vm.setValue('violin');
    expect(resolve.mock.calls[0][1].aborted).toBe(true);
    finish({ value: 'new', label: 'New' });
    expect(await pending).toBe(false);
    expect(wrapper.vm.getValue()).toBe('violin');
    expect(wrapper.emitted('create')).toBeUndefined();
  });

  it('creates local options from the dropdown and removes nonpersistent options after deselection', async () => {
    const wrapper = render({ create: true, multiple: true });
    await wrapper.get('input[role="combobox"]').setValue('Cello');
    await choose(wrapper, 'Add "Cello"');
    await flushPromises();
    expect(wrapper.vm.getValue()).toEqual(['Cello']);
    await wrapper.get('button[aria-label="Remove Cello"]').trigger('click');
    await open(wrapper);
    expect(wrapper.findAll('[role="option"]').some((option) => option.text() === 'Cello')).toBe(false);
  });

  it('retains persistent created options after deselection', async () => {
    const wrapper = render({ create: true, persist: true });
    await wrapper.vm.createItem('Cello');
    wrapper.vm.clear();
    await open(wrapper);
    expect(wrapper.findAll('[role="option"]').some((option) => option.text() === 'Cello')).toBe(true);
  });

  it('creates on blur and clears the search only after successful creation', async () => {
    const wrapper = render({ create: true, createOnBlur: true });
    const input = wrapper.get<HTMLInputElement>('input[role="combobox"]');
    await input.setValue('Cello');
    await input.trigger('blur');
    await flushPromises();
    expect(wrapper.vm.getValue()).toBe('Cello');
    expect(input.element.value).toBe('');
  });

  it('creates comma-delimited entries without submitting the form', async () => {
    const wrapper = render({ create: true, multiple: true });
    const input = wrapper.get<HTMLInputElement>('input[role="combobox"]');
    await input.setValue('Cello');
    await input.trigger('keydown', { key: ',', keyCode: 188 });
    await flushPromises();
    expect(wrapper.vm.getValue()).toEqual(['Cello']);
    expect(input.element.value).toBe('');
  });

  it('does not create new options unless creation is explicitly enabled', async () => {
    const wrapper = render();
    expect(await wrapper.vm.createItem('Cello')).toBe(false);
    expect(wrapper.vm.getValue()).toBeNull();
    expect(axios.post).not.toHaveBeenCalled();
  });

  it('validates email creation using the configured URL, form fields, and response fields', async () => {
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { email: 'normalized@example.org' } });
    const wrapper = render({ multiple: true, create: emailCreation, modelValue: ['violin'] });
    expect(await wrapper.vm.createItem('  Person@example.org  ')).toBe(true);
    expect(axios.post).toHaveBeenCalledWith(
      '/apps/cafevdb/validate/musicians/email',
      expect.any(URLSearchParams),
      { signal: expect.any(AbortSignal) },
    );
    const post = vi.mocked(axios.post).mock.calls[0][1] as URLSearchParams;
    expect([...post.entries()]).toEqual([['failure', 'error'], ['PME_data_email', 'Person@example.org']]);
    expect(wrapper.vm.getValue()).toEqual(['violin', 'normalized@example.org']);
  });

  it.each([null, {}, { email: '' }, { email: 123 }, { email: ' ' }])('rejects malformed validation responses: %j', async (data) => {
    vi.mocked(axios.post).mockResolvedValueOnce({ data });
    const wrapper = render({ create: emailCreation, modelValue: 'violin' });
    expect(await wrapper.vm.createItem('broken')).toBe(false);
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.getValue()).toBe('violin');
    expect(wrapper.get('[role="alert"]').text()).toContain('validation response');
    expect(wrapper.emitted('error')).toHaveLength(1);
    expect(wrapper.emitted('change')).toBeUndefined();
  });

  it('shows validation failures without altering the selection', async () => {
    const failure = Object.assign(new Error('Request failed'), {
      isAxiosError: true,
      response: { data: { messages: ['Invalid email address'] } },
    });
    vi.mocked(axios.post).mockRejectedValueOnce(failure);
    const wrapper = render({ create: emailCreation, modelValue: 'violin' });
    await wrapper.vm.createItem('broken');
    await wrapper.vm.$nextTick();
    expect(wrapper.get('[role="alert"]').text()).toContain('Invalid email address');
    expect(wrapper.vm.getValue()).toBe('violin');
    expect(wrapper.emitted('error')?.[0]).toEqual([failure]);
    await wrapper.get('input[role="combobox"]').setValue('corrected');
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
  });

  it('does not select a disabled option through a normalized creation response', async () => {
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { email: 'retired' } });
    const wrapper = render({ create: emailCreation, modelValue: 'violin' });
    expect(await wrapper.vm.createItem('Retired@example.org')).toBe(false);
    expect(wrapper.vm.getValue()).toBe('violin');
    expect(wrapper.emitted('error')).toHaveLength(1);
  });

  it('clears creation errors when an existing valid option is selected instead', async () => {
    vi.mocked(axios.post).mockRejectedValueOnce(new Error('Invalid address'));
    const wrapper = render({ create: emailCreation, required: true });
    await wrapper.vm.createItem('broken');
    await wrapper.vm.$nextTick();
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.checkValidity()).toBe(false);
    await choose(wrapper, 'Flute');
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.checkValidity()).toBe(true);
  });

  it('does not cancel creation when the parent echoes its v-model update', async () => {
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { email: 'new@example.org' } });
    const wrapper = render({
      create: emailCreation,
      'onUpdate:modelValue': (value) => { void wrapper.setProps({ modelValue: value }); },
    });
    await wrapper.vm.createItem('new@example.org');
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.getValue()).toBe('new@example.org');
    expect(wrapper.emitted('change')).toHaveLength(1);
    expect(vi.mocked(axios.post).mock.calls[0][2]?.signal?.aborted).toBe(false);
  });

  it('blocks submission while validation is pending and prevents duplicate requests', async () => {
    let resolve!: (value: { data: { email: string } }) => void;
    vi.mocked(axios.post).mockReturnValueOnce(new Promise((done) => { resolve = done; }));
    const wrapper = render({ create: emailCreation });
    const pending = wrapper.vm.createItem('new@example.org');
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.creating).toBe(true);
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.checkValidity()).toBe(false);
    const inputEvent = new InputEvent('beforeinput', { bubbles: true, cancelable: true, data: 'x' });
    wrapper.get('input[role="combobox"]').element.dispatchEvent(inputEvent);
    expect(inputEvent.defaultPrevented).toBe(true);
    expect(await wrapper.vm.createItem('duplicate@example.org')).toBe(false);
    expect(axios.post).toHaveBeenCalledTimes(1);
    resolve({ data: { email: 'new@example.org' } });
    await pending;
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.creating).toBe(false);
    expect(wrapper.get<HTMLInputElement>('input[role="combobox"]').element.checkValidity()).toBe(true);
  });

  it.each(['value', 'lock', 'unmount'] as const)('ignores a stale validation result after %s', async (action) => {
    let resolve!: (value: { data: { email: string } }) => void;
    vi.mocked(axios.post).mockReturnValueOnce(new Promise((done) => { resolve = done; }));
    const wrapper = render({ create: emailCreation, modelValue: 'violin' });
    const pending = wrapper.vm.createItem('new@example.org');
    const signal = vi.mocked(axios.post).mock.calls[0][2]?.signal;
    if (action === 'value') {
      wrapper.vm.setValue('flute');
    } else if (action === 'lock') {
      wrapper.vm.lock();
      await wrapper.vm.$nextTick();
    } else {
      wrapper.unmount();
    }
    expect(signal?.aborted).toBe(true);
    const changes = wrapper.emitted('change')?.length;
    resolve({ data: { email: 'new@example.org' } });
    await pending;
    expect(wrapper.emitted('change')?.length).toBe(changes);
    expect(wrapper.emitted('error')).toBeUndefined();
  });
});
