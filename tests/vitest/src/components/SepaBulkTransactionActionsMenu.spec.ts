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

import type { AppError } from '~/src/toolkit/types/errors.ts';

import { setSilent as setLoggerSilent } from '../toolkit/util/mock-console.ts';

import { createTestingPinia } from '@pinia/testing';
import Tooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip';
import { mount } from '@vue/test-utils';
import { setActivePinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import VueComponent from '~/src/components/SepaBulkTransactionActionsMenu.vue';
import useErrorHandler from '~/src/stores/error-handler.ts';

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
        default:
          return null;
      }
    }) as typeof originalModule.loadState,
  };
});

describe('SettingsSelectUsers component', () => {

  const props = {
    enableOverviewItem: true,
    entityId: -1,
    menuCaption: 'Hello World!',
    projectId: -1,
    projectName: 'ProjectName',
    template: 'TemplateName',
  };

  it('should be a Vue instance', () => {

    const pinia = createTestingPinia();
    setActivePinia(pinia);

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
      props,
      global: {
        plugins: [pinia],
        directives: { tooltip: Tooltip },
      },
    });
    expect(wrapper.vm).toBeTruthy();
  });
});
