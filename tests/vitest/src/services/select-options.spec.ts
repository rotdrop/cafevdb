/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { addressBookSelectOptions, selectedFreeFormContacts } from '~/src/services/address-book-select.ts';
import { appendSelectOption, readSelectOptions, selectedOptionData } from '~/src/services/select-options.ts';

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
