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

import { setSilent as setLoggerSilent } from '../toolkit/util/mock-console.ts';
import {
  mount,
  // shallowMount,
  createLocalVue,
} from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import VueComponent from '@/src/components/ErrorPage.vue';
// import { loadState } from '@nextcloud/initial-state';
import { expect, jest } from '@jest/globals';
import { Tooltip } from '@nextcloud/vue';
// import useAppDataStore from '@/src/stores/app-data.ts';
// import useErrorHandler from '@/src/stores/error-handler.ts';
// import type { AppError } from '@/src/toolkit/types/errors.ts';

setLoggerSilent(true);

jest.mock('@nextcloud/initial-state', () => {
  const originalModule: object = jest.requireActual('@nextcloud/initial-state');

  return {
    __esModule: true,
    ...originalModule,
    loadState: jest.fn((app: string, section: string) => {
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
          return null;
      }
    }),
  };
});

beforeAll(() => {
  document.body.id = 'body-user';
});

const localVue = createLocalVue();
localVue.directive('tooltip', Tooltip);
// @ts-expect-error 2769
localVue.use(createTestingPinia());

describe('HtmlErrorModal component', () => {

  const error = new Error('blah');

  const propsData = {
    error,
    initialView: 'details',
    noSummary: false,
    closeDetailsLabel: 'CLOSE DETAILS LABEL',
  };

  it('should be a Vue instance', () => {

    const wrapper = mount(VueComponent, {
      propsData,
      localVue,
    });
    expect(wrapper.vm).toBeTruthy();
  });
});
