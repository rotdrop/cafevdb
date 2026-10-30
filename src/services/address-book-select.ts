/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { SelectizeAdapterOptions } from './mountable-select.ts';

import { createRemoteSelectOption } from './select-option-creation.ts';
import { selectedOptionData } from './select-options.ts';

export const addressBookSelectOptions = (
  url: string,
  inputField: string,
  freeFormLabel: string,
  onCreate: () => void,
): SelectizeAdapterOptions => ({
  plugins: ['drag_drop'],
  restoreOnBackspace: true,
  createOnBlur: true,
  persist: true,
  groups: [{ value: 'free-form', label: freeFormLabel }],
  create: {
    resolve: createRemoteSelectOption({ url, inputField, post: { failure: 'error' } }, (data) => {
      if (typeof data !== 'object' || data === null || !('email' in data)
        || typeof data.email !== 'string' || !data.email.trim()) {
        throw new Error('Invalid email validation response.');
      }
      const email = data.email;
      const details = 'details' in data ? data.details : undefined;
      const name = typeof details === 'object' && details !== null ? Reflect.get(details, email) : undefined;
      return {
        value: email,
        label: typeof name === 'string' && name ? `${name} <${email}>` : email,
        groups: ['free-form'],
      };
    }),
  },
  onCreate,
});

export const selectedFreeFormContacts = (select: HTMLSelectElement, values: string[]) =>
  selectedOptionData(select, values).filter(({ value }) => {
    const option = Array.from(select.options).find((option) => option.value === value);
    return option?.parentElement instanceof HTMLOptGroupElement
      && (option.parentElement.dataset.groupId === 'free-form' || option.parentElement.classList.contains('free-form'));
  });
