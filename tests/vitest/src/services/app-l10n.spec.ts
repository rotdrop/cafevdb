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

/* eslint perfectionist/sort-imports: off */

import type { loadTranslations } from '@nextcloud/l10n';

import '../toolkit/util/mock-console.ts'; // must come first

import { getLanguage, register, setLanguage } from '@nextcloud/l10n';
import fs from 'fs';
import path from 'path';
import { describe, expect, it, vi } from 'vitest';
import {
  PROJECT_REGISTRATION_CATEGORY,
  RECORD_ABSENCE_CATEGORY,
} from '~/build/ts-types/php-modules/Service/EventsService.ts';
import logger from '~/src/logger.ts';
import {
  appTranslate,
  appTranslatePlural,
  getAppLanguage,
  getAppLocale,
  setupAppBundle,
} from '~/src/services/app-l10n.ts';
import globalState, { resolveGlobalStateInitialized } from '~/src/services/legacy-global-state.ts';

type AppTranslations = Awaited<ReturnType<typeof loadTranslations>>;

declare global {
  const APP_ROOT: string;
}

vi.mock(import('~/src/app/globalstate.ts'), async (originalImport) => {
  const originalModule = await originalImport();

  return {
    ...originalModule,
    default: {
      ...(originalModule.default),
      appLocale: 'fr_FR.UTF-8',
      // initialized: true,
    },
  };
});

vi.mock(import('@nextcloud/l10n'), async (originalImport) => {
  const originalModule = await originalImport();

  return {
    ...originalModule,
    loadTranslations: async (appName: string) => {
      const language = getLanguage();
      let bundle: AppTranslations;
      try {
        const l10nJSON = await new Promise<Buffer<ArrayBuffer>>((resolve, reject) => {
          fs.readFile(path.join(APP_ROOT, 'l10n', language + '.json'), (err, data) => {
            if (err) {
              reject(err);
            }
            resolve(data);
          });
        });
        bundle = JSON.parse(l10nJSON.toString());
      } catch (e) {
        if (language === 'de' || language === 'de_DE') {
          throw e;
        }
        bundle = {
          translations: {},
          pluralFunction: (number: number) => number,
        };
      }
      register(appName, bundle.translations);
      return bundle;
    },
  };
});

const translationData = {
  // actual translation
  'de_DE.UTF-8': {
    [PROJECT_REGISTRATION_CATEGORY]: 'Projekt-Anmeldung',
    [RECORD_ABSENCE_CATEGORY]: 'Abwesenheit erfassen',
  },
  'de.UTF-8': {
    [PROJECT_REGISTRATION_CATEGORY]: 'Projekt-Anmeldung',
    [RECORD_ABSENCE_CATEGORY]: 'Abwesenheit erfassen',
  },
  // non-existing translation
  'fr_FR.UTF-8': {
    [PROJECT_REGISTRATION_CATEGORY]: PROJECT_REGISTRATION_CATEGORY,
    [RECORD_ABSENCE_CATEGORY]: RECORD_ABSENCE_CATEGORY,
  },
  // default translation
  'en_US.UTF-8': {
    [PROJECT_REGISTRATION_CATEGORY]: PROJECT_REGISTRATION_CATEGORY,
    [RECORD_ABSENCE_CATEGORY]: RECORD_ABSENCE_CATEGORY,
  },
};

const setupLocale = (locale: string) => {
  globalState.appLocale = locale;
  if (!globalState.initialized) {
    globalState.initialized = true;
    resolveGlobalStateInitialized(globalState);
  }
  return setupAppBundle();
};

describe('app-l10n', () => {
  for (const [locale, translations] of Object.entries(translationData)) {
    globalState.appLocale = locale;
    const language = (new Intl.Locale(locale.replace('_', '-').replace('.UTF-8', ''))).language;
    for (const [key, value] of Object.entries(translations)) {
      it('should translate the project registration category for locale ' + locale, async () => {
        await setupLocale(locale);
        expect(appTranslate(key)).toBe(value);
      });
      it('should be able to handle plural translations', async () => {
        await setupLocale(locale);
        expect(
          appTranslatePlural(
            'Import %n calendar into {collection}',
            'Import %n calendars into {collection}',
            1,
            { collection: 'Nextcloud' },
          ),
        ).toBe('Import 1 calendar into Nextcloud');
        expect(
          appTranslatePlural(
            'Import %n calendar into {collection}',
            'Import %n calendars into {collection}',
            3,
            { collection: 'Nextcloud' },
          ),
        ).toBe('Import 3 calendars into Nextcloud');
      });
      it('should translate the project registration category for locale ' + locale, async () => {
        await setupLocale('en_US.UTF-8');
        setLanguage(language);
        await setupLocale(locale);
        expect(appTranslate(key)).toBe(value);
      });
      it('should return the configured app locale and language for ' + locale, async () => {
        expect(getAppLocale()).toBe(locale);
        expect(getAppLanguage()).toBe(language);
      });
      it('should fail to setup the language given an invalid locale', async () => {
        // @ts-expect-error 2339 Blah
        logger.setSilent(true);
        await setupLocale('!"§$%&/()=');
        // @ts-expect-error 2339 Blah
        logger.setSilent(false);
        expect(getAppLanguage()).toBe('en');
      });
    }
  }
});
