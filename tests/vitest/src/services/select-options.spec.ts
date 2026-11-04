/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { addressBookSelectOptions, selectedFreeFormContacts } from '~/src/services/address-book-select.ts';
import { appendSelectOption, orderSelectOptions, readSelectOptions, selectedOptionData } from '~/src/services/select-options.ts';

vi.mock('@nextcloud/axios', () => ({ default: { post: vi.fn() } }));
vi.mock('~/src/toolkit/util/generate-url.ts', () => ({
  default: (url: string) => `/apps/cafevdb/${url}`,
}));

afterEach(() => {
  vi.clearAllMocks();
});

const makeSelect = () => {
  const select = document.createElement('select');
  select.multiple = true;
  select.innerHTML = `
    <optgroup label="People"><option value="person@example.org">Person &lt;person@example.org&gt;</option></optgroup>
    <optgroup class="free-form" label="Form Input"><option value="free@example.org">Free</option></optgroup>
  `;
  return select;
};

describe('shared select option data', () => {
  it.each([false, true])('omits empty placeholders without altering native options when multiple=%s', (multiple) => {
    const select = document.createElement('select');
    select.multiple = multiple;
    select.innerHTML = `
      <option value="" selected></option>
      <option value="" label="None"></option>
      <option value="">All</option>
      <option value="0"></option>
      <optgroup label="Group"><option value=""></option><option value="a">A</option></optgroup>
    `;
    const { options } = readSelectOptions(select);
    expect(options.map(({ value, label }) => ({ value, label }))).toEqual([
      { value: '', label: 'None' },
      { value: '', label: 'All' },
      { value: '0', label: '0' },
      { value: 'a', label: 'A' },
    ]);
    expect(select.options).toHaveLength(6);
    expect(select.options[0].selected).toBe(true);
    expect(select.options[0].value).toBe('');
  });

  it('preserves stable group IDs and converts HTML tooltip metadata to plain multiline text', () => {
    const select = makeSelect();
    select.options[0].title = 'Subject: A &amp; B<br/>Created yesterday';
    const { options, groups } = readSelectOptions(select);
    expect(groups.map((group) => group.value)).toEqual(['0', 'free-form']);
    expect(options[0]).toMatchObject({ groups: ['0'], title: 'Subject: A & B\nCreated yesterday' });
    expect(options[1].groups).toEqual(['free-form']);
  });

  it('appends created contacts to an existing free-form group without duplicating it', () => {
    const select = makeSelect();
    appendSelectOption(select, { value: 'new@example.org', label: 'New <new@example.org>', groups: ['free-form'] }, []);
    expect(select.querySelectorAll('optgroup')).toHaveLength(2);
    expect(select.options[2].parentElement).toBe(select.querySelector('.free-form'));
    expect(select.options[2].text).toBe('New <new@example.org>');
    expect(readSelectOptions(select).options[2].groups).toEqual(['free-form']);
  });

  it('creates a persistent named group when the server has no supplementary contacts', () => {
    const select = document.createElement('select');
    const option = appendSelectOption(select, {
      value: 'new@example.org',
      label: 'New',
      groups: ['free-form'],
    }, [{ value: 'free-form', label: 'Form Input' }]);
    expect(option.parentElement?.getAttribute('label')).toBe('Form Input');
    expect(readSelectOptions(select).groups[0].value).toBe('free-form');
  });

  it('returns ordered DTOs and limits saved contacts to the free-form group', () => {
    const select = makeSelect();
    const values = ['free@example.org', 'person@example.org'];
    expect(selectedOptionData(select, values)).toEqual([
      { value: 'free@example.org', text: 'Free', html: 'Free' },
      { value: 'person@example.org', text: 'Person <person@example.org>', html: 'Person &lt;person@example.org&gt;' },
    ]);
    expect(selectedFreeFormContacts(select, values)).toEqual([{ value: 'free@example.org', text: 'Free', html: 'Free' }]);
  });

  it('orders interleaved optgroups without losing option identity or group metadata', () => {
    const select = makeSelect();
    select.querySelector('optgroup')!.id = 'people';
    const anotherPerson = new Option('Another', 'another@example.org');
    select.querySelector('optgroup')!.append(anotherPerson);
    const originalOptions = Array.from(select.options);
    originalOptions.forEach((option) => { option.selected = true; });
    const values = ['another@example.org', 'free@example.org', 'person@example.org'];
    orderSelectOptions(select, values);
    expect(Array.from(select.selectedOptions, (option) => option.value)).toEqual(values);
    expect(Array.from(select.options)).toEqual([anotherPerson, originalOptions[2], originalOptions[0]]);
    expect(select.querySelectorAll('optgroup')).toHaveLength(3);
    expect(select.querySelectorAll('#people')).toHaveLength(1);
    expect(readSelectOptions(select)).toMatchObject({
      groups: [
        { value: '0', label: 'People', disabled: false },
        { value: 'free-form', label: 'Form Input', disabled: false },
      ],
      options: [
        { value: 'another@example.org', groups: ['0'] },
        { value: 'free@example.org', groups: ['free-form'] },
        { value: 'person@example.org', groups: ['0'] },
      ],
    });
    orderSelectOptions(select, [...values].reverse());
    expect(Array.from(select.selectedOptions, (option) => option.value)).toEqual([...values].reverse());
    expect(readSelectOptions(select).groups).toHaveLength(2);
    appendSelectOption(select, { value: 'new@example.org', label: 'New', groups: ['free-form'] }, []);
    expect(readSelectOptions(select).options.at(-2)).toMatchObject({ value: 'new@example.org', groups: ['free-form'] });
  });
});

describe('address-book select creation', () => {
  const configuration = () => {
    const options = addressBookSelectOptions('validate/email', 'PME_data_email', 'Form Input', vi.fn());
    if (typeof options.create !== 'object' || !options.create.resolve) {
      throw new Error('Missing address-book resolver.');
    }
    return options.create.resolve;
  };

  it('posts validation fields with cancellation and maps names into the free-form group', async () => {
    const email = 'normalized@example.org';
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { email, details: { [email]: 'Person' } } });
    const controller = new AbortController();
    const option = await configuration()('Person@example.org', controller.signal);
    expect(option).toEqual({ value: email, label: `Person <${email}>`, groups: ['free-form'] });
    expect(axios.post).toHaveBeenCalledWith(
      '/apps/cafevdb/validate/email',
      expect.any(URLSearchParams),
      { signal: controller.signal },
    );
    const post = vi.mocked(axios.post).mock.calls[0][1] as URLSearchParams;
    expect([...post.entries()]).toEqual([['failure', 'error'], ['PME_data_email', 'Person@example.org']]);
  });

  it('uses the email itself when optional name metadata is absent', async () => {
    vi.mocked(axios.post).mockResolvedValueOnce({ data: { email: 'person@example.org' } });
    expect(await configuration()('person@example.org', new AbortController().signal))
      .toMatchObject({ label: 'person@example.org' });
  });

  it.each([null, {}, { email: '' }, { email: ' ' }, { email: 123 }])('rejects invalid responses: %j', async (data) => {
    vi.mocked(axios.post).mockResolvedValueOnce({ data });
    await expect(configuration()('bad', new AbortController().signal)).rejects.toThrow('Invalid email validation response.');
  });

  it('propagates validation errors to the component instead of fabricating an option', async () => {
    const failure = new Error('Invalid address');
    vi.mocked(axios.post).mockRejectedValueOnce(failure);
    await expect(configuration()('bad', new AbortController().signal)).rejects.toBe(failure);
  });
});
