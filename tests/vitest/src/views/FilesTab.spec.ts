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

// ... because mocks have to come top level.
/* eslint-disable perfectionist/sort-imports */

import type { FilesInitialState } from '~/build/ts-types/php-modules/Controller/DTO.ts';

import { setSilent as setLoggerSilent } from '../toolkit/util/mock-console.ts';

import { createTestingPinia } from '@pinia/testing';
import Tooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip';
import {
  // shallowMount,
  // createLocalVue,
  mount,
} from '@vue/test-utils';
import {
  // createPinia,
  setActivePinia,
} from 'pinia';
// import { loadState } from '@nextcloud/initial-state';
import { expect, vi } from 'vitest';
import VueComponent from '~/src/views/FilesTab.vue';
import { EnumInitialStateKey } from '~/build/ts-types/php-modules/Controller.ts';
import { appName } from '~/src/config.ts';

setLoggerSilent(true);

vi.mock(import('@nextcloud/initial-state'), async (originalImport) => {
  const originalModule = await originalImport();

  return {
    ...originalModule,
    loadState: vi.fn((app: string, section: string) => {
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
    }) as typeof originalModule['loadState'],
  };
});


describe('FilesTab component', () => {
  it('should be a Vue instance', () => {

    const pinia = createTestingPinia();
    setActivePinia(pinia);

    const wrapper = mount(VueComponent, {
      global: {
        plugins: [pinia],
        directives: { tooltip: Tooltip },
      },
    });

    expect(wrapper.vm).toBeTruthy();
  });
});
