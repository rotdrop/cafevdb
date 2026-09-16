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

// ... because mocks have to come top level.
/* eslint-disable perfectionist/sort-imports */

import type { AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import type { JqJsonXHR } from '~/src/types/ajax/jqxhr-error.ts';
import type { NextcloudLogEntry } from '~/src/types/ajax/nextcloud-log.ts';

import { setSilent as setLoggerSilent } from '../toolkit/util/mock-console.ts';
setLoggerSilent(true);

import { describe, expect, it, vi } from 'vitest';
import Tooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip';
import { setActivePinia } from 'pinia';
import { createTestingPinia } from '@pinia/testing';
import { mount } from '@vue/test-utils';
import { AxiosError } from 'axios';
import fs from 'fs';
import { StatusCodes as HttpStatusCodes } from 'http-status-codes';
import path from 'path';
import VueComponent from '~/src/components/ErrorPageModal.vue';
import { isJqNextcloudLogEntryXHR } from '~/src/types/ajax/jqxhr-error.ts';
import { isNextcloudExceptionResponse } from '~/src/types/ajax/php-exception-response.ts';

vi.mock(import('@nextcloud/initial-state'), async (originalImport) => {
  const originalModule = await originalImport();

  return {
    ...originalModule,
    loadState: vi.fn((app: string, section: string, fallback?: unknown) => {
      switch (app) {
        case 'core':
          switch (section) {
            case 'capabilities':
              return { passwordPolicy: null };
            default:
              return null;
          }
        // case appName:
        //   switch (section) {
        //     case INITIAL_STATE_SECTION: {
        //       const result: AdminInitialState = {
        //         officeFonts: {},
        //         authorizationGroupSuffixes: AUTHORIZATION_GROUP_SUFFIXES,
        //         cloudUserBackend: 'LDAP',
        //         haveCloudUserBackendConfig: false,
        //         isAdmin: false,
        //         isSubAdmin: false,
        //         officeFontsFolder: '',
        //         personalAppSettingsLink: '',
        //         sharedFolder: '',
        //         userAndGroupBackends: [],
        //       };
        //       return result;
        //     }
        //     default:
        //       return null;
        //   }
        default:
          return fallback ?? null;
      }
    }) as typeof originalModule['loadState'],
  };
});

let exceptionLogEntry: NextcloudLogEntry;

const generateJqNextcloudLogEntryXHR = (): JqJsonXHR<NextcloudLogEntry> => {
  //   // eslint-disable-next-line @typescript-eslint/no-explicit-any
  // (!!error
  //  && typeof error === 'object'
  //  && !!(error as Keyable).responseText
  //  && !!(error as Keyable).status
  //  && !!(error as Keyable).abort
  //  && !!(error as Keyable).done
  //  && !!(error as Keyable).fail);
  return {
    responseJSON: exceptionLogEntry,
    responseText: JSON.stringify(exceptionLogEntry),
    status: HttpStatusCodes.BAD_REQUEST,
    abort: () => {},
    done: () => {},
    fail: () => {},
  } as unknown as JqJsonXHR<NextcloudLogEntry>;
};

const generateNextcloudExceptionResponse = () => {
  const response: AxiosResponse<NextcloudLogEntry> = {
    data: exceptionLogEntry,
    status: HttpStatusCodes.BAD_REQUEST,
    statusText: 'STATUS_TEXT',
    headers: {},
    config: {} as InternalAxiosRequestConfig,
  };
  return new AxiosError<NextcloudLogEntry, unknown>(
    'MESSAGE',
    undefined, // code
    undefined, // config
    undefined, // request
    response,
  );
};

beforeAll(() => {
  document.body.id = 'body-user';
  const exceptionLogEntryJSON = fs.readFileSync(path.join(__dirname, 'exception-log-entry.json'));
  exceptionLogEntry = JSON.parse(exceptionLogEntryJSON.toString()) as NextcloudLogEntry;
});

describe('HtmlErrorModal component', () => {

  const pinia = createTestingPinia({ stubActions: [] });

  beforeEach(() => {
    setActivePinia(pinia);
  });

  const error = new Error('blah');

  const props: Record<string, unknown> = {
    error,
    heading: 'HEADING',
    initialView: 'details',
    noSummary: false,
    closeDetailsLabel: 'CLOSE DETAILS LABEL',
  };

  const mountOptions = {
    props,
    global: {
      plugins: [pinia],
      directives: { tooltip: Tooltip },
    },
  };

  it('should be a Vue instance', () => {

    const wrapper = mount(VueComponent, mountOptions);
    expect(wrapper.vm).toBeTruthy();
  });

  it('should handle a Nextcloud exception response', () => {
    const exceptionResponse = generateNextcloudExceptionResponse();
    props.error = exceptionResponse;

    // self test
    expect(isNextcloudExceptionResponse(exceptionResponse)).toBeTruthy();

    const wrapper = mount(VueComponent, mountOptions);
    expect(wrapper.vm).toBeTruthy();
  });

  it('should handle a JQuery XHR Nextcloud exception response', () => {
    const exceptionResponse = generateJqNextcloudLogEntryXHR();
    props.error = exceptionResponse;

    // self test
    expect(isJqNextcloudLogEntryXHR(exceptionResponse)).toBeTruthy();

    const wrapper = mount(VueComponent, mountOptions);
    expect(wrapper.vm).toBeTruthy();
  });
});
