/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import type Console from '../util/console.ts';

import {
  getLanguage,
  loadTranslations,
  register,
  setLanguage,
  translate,
  translatePlural,
} from '@nextcloud/l10n';
import { appName } from '../config.ts';
import globalState, { globalStateInitialized } from '../services/legacy-global-state.ts';

type TranslationOptions = Exclude<Parameters<typeof translatePlural>[5], undefined>;
type TranslationVariables<T extends string> = Exclude<Parameters<typeof translate<T>>[2], undefined>;
type AppTranslationsPromise = ReturnType<typeof loadTranslations>;

let logger: Console;

let appBundle: Awaited<AppTranslationsPromise>;
let appLanguage: string;

export const getAppLocale = () => globalState.appLocale;
export const getAppLanguage = () => appLanguage;

export const setupAppBundle = async () => {
  await globalStateInitialized;
  logger = (await import('../logger.ts')).default;
  let locale: Intl.Locale;
  try {
    // funny JavaScript conventions ...
    locale = new Intl.Locale(globalState.appLocale.replace('_', '-').replace('.UTF-8', ''));
  } catch (e) {
    logger.error('Error constructing locale', { e });
    locale = new Intl.Locale('en-US');
  }
  if (locale.language === appLanguage && appBundle) {
    logger.debug('Already initialized', { localeLanguage: locale.language, appLanguage });
    return;
  }
  appLanguage = locale.language;
  const oldLanguage = getLanguage();
  const oldBundle = await loadTranslations(appName);
  if (appLanguage === oldLanguage) {
    logger.debug('NO CHANGE IN LANGUAGE', { appLanguage, oldLanguage });
    appBundle = oldBundle;
    return;
  }
  setLanguage(appLanguage);
  appBundle = await loadTranslations(appName);
  // undo
  setLanguage(oldLanguage);
  register(appName, oldBundle.translations);
  logger.debug('APP_LOCALE', {
    appLocale: globalState.appLocale,
    numTranslation: Object.keys(appBundle.translations).length,
    locale,
    oldLanguage,
    appLanguage,
  });
};

setupAppBundle();

export const appTranslate = <T extends string>(
  text: Parameters<typeof translate<T>>[1],
  placeholdersOrNumber?: Parameters<typeof translate<T>>[2],
  options?: TranslationOptions,
) => {
  options = { ...options, bundle: appBundle };
  return translate(appName, text, placeholdersOrNumber, options);
};

export const appTranslatePlural = <T extends string, K extends string>(
  textSingular: T,
  textPlural: K,
  number: number,
  vars?: TranslationVariables<T> & TranslationVariables<K>,
  options?: TranslationOptions,
) => {
  options = { ...options, bundle: appBundle };
  return translatePlural(appName, textSingular, textPlural, number, vars, options);
};

export default appTranslate;
