/**
 * @file
 *
 * @license AGPL-3.0-or-later
 */

import type { PersonalSettingResponse } from './personal-settings.ts';

import axios from '@nextcloud/axios';
import { getUrl, setAppUrl } from '../app/settings-urls.ts';

export type AdminSettingResponse = PersonalSettingResponse & {
  message?: string|string[];
  localeInfo?: string;
  number?: string;
};

export const setAdminSetting = (key: string, value: unknown) =>
  axios.post<AdminSettingResponse>(setAppUrl(key), { value });

export const getAdminSetting = (key: string, value?: unknown) =>
  axios.post<AdminSettingResponse>(getUrl(key), value === undefined ? {} : { value });
