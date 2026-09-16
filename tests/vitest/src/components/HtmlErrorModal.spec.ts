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

import { setSilent as setLoggerSilent } from '../toolkit/util/mock-console.ts';

import { beforeAll, describe, expect, it, vi } from 'vitest';
import Tooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip';
import { createTestingPinia } from '@pinia/testing';
import { setActivePinia } from 'pinia';
import { mount } from '@vue/test-utils';
import VueComponent from '~/src/components/HtmlErrorModal.vue';

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
    }) as typeof originalModule['loadState'],
  };
});

beforeAll(() => {
  document.body.id = 'body-user';
});

describe('HtmlErrorModal component', () => {

  const pinia = createTestingPinia();

  beforeEach(() => {
    setActivePinia(pinia);
  });

  const props: Record<string, unknown> = {
    open: true,
    caption: 'CAPTION',
    htmlString: '<div>CONTENTS</div>',
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
});
