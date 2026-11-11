/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { readonly, shallowRef } from 'vue';

const minimizedTitle = shallowRef<string|null>(null);

export const minimizedEmailFormTitle = readonly(minimizedTitle);
export const setMinimizedEmailFormTitle = (title: string|null) => {
  minimizedTitle.value = title;
};
