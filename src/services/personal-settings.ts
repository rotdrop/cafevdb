/**
 * @file
 *
 * @license AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import {
  BASE_PATH,
  END_POINT_PERSONAL_SET,
} from '../../build/ts-types/php-modules/Controller/PersonalSettingsController.ts'
import generateAppUrl from '../toolkit/util/generate-url.ts'

export type PersonalSettingKey =
  | 'toolTipsEnabled'
  | 'restoreHistory'
  | 'initialFilterVisibility'
  | 'directChange'
  | 'deselectInvisibleMiscRecs'
  | 'financeMode'
  | 'expertMode'
  | 'showDisabled'
  | 'pageRowsDefault'
  | 'debugMode'
  | 'wysiwygEditor'
  | 'debugQuerySqlFilter'

export type PersonalSettingResponse = { messages?: string[], value?: unknown }

export const setPersonalSetting = (key: PersonalSettingKey, value: unknown) => {
  const url = generateAppUrl(`${BASE_PATH}/${END_POINT_PERSONAL_SET}/{parameter}`, { parameter: key })
  return axios.post<PersonalSettingResponse>(url, { value })
}
