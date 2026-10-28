/**
 * @file
 *
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * @module
 */

import type { EnumPersonalSettingsKey } from '../../../build/ts-types/php-modules/Controller.ts';

import axios from '@nextcloud/axios';
import * as Ajax from '../ajax.ts';
import { setPersonalUrl } from '../settings-urls.ts';

export interface SettingResponse<T = unknown> {
  messages: string[];
  value?: T;
}

/**
 * Store a personal setting. Errors are reported through the legacy
 * error handler and then re-thrown.
 *
 * @param key The setting to change.
 *
 * @param value The new value.
 */
const postSetting = async <T = unknown>(
  key: EnumPersonalSettingsKey|`${EnumPersonalSettingsKey}`,
  value: unknown,
): Promise<SettingResponse<T>> => {
  try {
    const response = await axios.post<SettingResponse<T>>(setPersonalUrl(key), { value });
    return response.data;
  } catch (error) {
    await Ajax.handleAxiosError(error);
    throw error;
  }
};

export default postSetting;
