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

// Pure actions (no value) are sent without a request body, like the legacy code did.
export const setAdminSetting = (key: string, value?: unknown) =>
  axios.post<AdminSettingResponse>(setAppUrl(key), value === undefined ? undefined : { value });

export const getAdminSetting = (key: string, value?: unknown) =>
  axios.post<AdminSettingResponse>(getUrl(key), value === undefined ? {} : { value });

export type LocaleInfoResponse = {
  localeSymbol: string;
  currencySymbol: string;
  currencyCode: string;
  dateTimeZone: string;
};

export const getLocaleInfo = (scope: 'personal'|'app') =>
  axios.post<LocaleInfoResponse>(getUrl('locale-info'), { scope });
