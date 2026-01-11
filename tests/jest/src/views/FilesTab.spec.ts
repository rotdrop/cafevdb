/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import FilesTab from '@/src/views/FilesTab.vue';
// import { loadState } from '@nextcloud/initial-state';
import { expect, jest } from '@jest/globals';
import { type FilesInitialState } from '@/build/ts-types/php-modules/Controller/DTO.ts';
import { EnumInitialStateKey } from '@/build/ts-types/php-modules/Controller.ts';
import { Tooltip } from '@nextcloud/vue';

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
        case appName:
          switch (section) {
            case EnumInitialStateKey.FILES: {
              const result: FilesInitialState = {
                sharing: {
                  files: {
                    folders: {
                      root: 'root',
                      balances: 'balances',
                      donationReceipts: 'donationReceipts',
                      finance: 'finance',
                      invoices: 'invoices',
                      projectBalances: 'projectBalances',
                      projectManagement: 'projectManagement',
                      templates: 'templates',
                    },
                    subFolders: {
                      supportingDocuments: 'supportingDocuments',
                      projectParticipants: 'projectParticipants',
                    },
                  },
                },
                personal: {
                  userId: 'john.doe',
                  musicianId: 1,
                  musicianPublicName: 'Doe, John',
                  musicianPersonalPublicName: 'John Doe',
                },
                contacts: {
                  addressBooks: {
                    key: {
                      displayName: 'Display Name',
                      key: 'key',
                      uri: 'uri',
                      isShared: false,
                      isSystemAddressBook: false,
                      permissions: 0,
                    },
                  },
                },
                debugMode: 0,
              };
              return result;
            }
            default:
              return null;
          }
        default:
          return null;
      }
    }),
  };
});

const localVue = createLocalVue();
localVue.directive('tooltip', Tooltip);
// @ts-expect-error 2769
localVue.use(createTestingPinia());

describe('FilesTab component', () => {
  it('should be a Vue instance', () => {
    const wrapper = mount(FilesTab, {
      localVue,
    });
    expect(wrapper.vm).toBeTruthy();
  });
});
