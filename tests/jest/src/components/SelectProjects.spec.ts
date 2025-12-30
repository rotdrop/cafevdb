/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import VueComponent from '@/src/components/SelectProjects.vue';
// import { loadState } from '@nextcloud/initial-state';
import { expect, jest } from '@jest/globals';
import { Tooltip } from '@nextcloud/vue';
// import useAppDataStore from '@/src/stores/app-data.ts';
import useErrorHandler from '@/src/stores/error-handler.ts';
import type { AppError } from '@/src/types/errors.ts';

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

const PROJECT_ID = 1;

// Mock axios and set the type
jest.mock('@nextcloud/axios', () => {
  const originalModule: object = jest.requireActual('@nextcloud/axios');

  return {
    __esModule: true,
    ...originalModule,
    default: {
      get: async (url: string) => {
        switch (url) {
          case '/index.php/apps/cafevdb/projects':
            return [PROJECT_ID];
        }
        // switch (url) {
        // }
      },
    },
  };
});

const localVue = createLocalVue();
localVue.directive('tooltip', Tooltip);
// @ts-expect-error 2769
localVue.use(createTestingPinia());

describe('SettingsSelectUsers component', () => {

  const propsData = {
    // clear all options, only makes sense if multiple == true
    clearAction: false,
    clearable: true,
    label: 'LABEL',
    loading: false,
    loadingIndicator: true,
    multiple: false,
    placeholder: 'PLACEHOLDER',
    value: { id: 1 },
  };

  it('should be a Vue instance', () => {

    const errorHandlerStore = useErrorHandler();
    errorHandlerStore.pushHandler(<E extends AppError>(error: E) => { console.error('Error handler called', error); });

    // const appDataStore = useAppDataStore();
    // appDataStore.projects = {
    //   1: { id: 1 },
    //   state: {
    //     findProjectIds: () => [ 1 ],
    //   },
    // };

    const wrapper = mount(VueComponent, {
      propsData,
      localVue,
    });
    expect(wrapper.vm).toBeTruthy();
  });
});
