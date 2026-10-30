/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { PmeSelectCreateOptions, PmeSelectOption } from '../types/pme-select.ts';

import axios from '@nextcloud/axios';
import generateAppUrl from '../toolkit/util/generate-url.ts';

export const createRemoteSelectOption = (
  configuration: PmeSelectCreateOptions & { url: string },
  mapResponse: (data: unknown) => PmeSelectOption,
) => async (input: string, signal: AbortSignal) => {
  const post = new URLSearchParams();
  for (const [key, value] of Object.entries(configuration.post ?? {})) {
    post.set(key, String(value));
  }
  post.set(configuration.inputField ?? 'input', input);
  const { data } = await axios.post<unknown>(generateAppUrl(configuration.url), post, { signal });
  return mapResponse(data);
};
