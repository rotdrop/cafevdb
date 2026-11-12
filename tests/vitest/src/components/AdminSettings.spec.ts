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

import type { AdminInitialState } from '~/build/ts-types/php-modules/Settings.ts';

// import { loadState } from '@nextcloud/initial-state';
import { describe, expect, it, vi } from 'vitest';
import { createTestingPinia } from '@pinia/testing';
import { setActivePinia } from 'pinia';
import Tooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip';
import {
  mount,
  // shallowMount,
  // createLocalVue,
} from '@vue/test-utils';
import AdminSettings from '~/src/components/AdminSettings.vue';
import ErrorPageModal from '~/src/components/ErrorPageModal.vue';
import { AppError } from '~/src/toolkit/types/errors.ts';
import { loadState } from '@nextcloud/initial-state';
import { AUTHORIZATION_GROUP_SUFFIXES, INITIAL_STATE_SECTION } from '~/build/ts-types/php-modules/Settings/Admin.ts';
import { appName } from '~/src/config.ts';

// No server is available: fail requests without network access and do not
// try to render toasts, which jsdom cannot do reliably.
vi.mock(import('@nextcloud/axios'), () => {
  const reject = vi.fn(() => Promise.reject(new Error('no server in unit tests')));
  return { default: { get: reject, post: reject, put: reject, delete: reject, patch: reject } } as never;
});

vi.mock(import('@nextcloud/dialogs'), async (originalImport) => {
  const originalModule = await originalImport();
  return {
    ...originalModule,
    showError: vi.fn(),
    showSuccess: vi.fn(),
    showInfo: vi.fn(),
    showWarning: vi.fn(),
  };
});

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
            case INITIAL_STATE_SECTION: {
              const result: AdminInitialState = {
                officeFonts: {},
                authorizationGroupSuffixes: AUTHORIZATION_GROUP_SUFFIXES,
                cloudUserBackend: 'LDAP',
                haveCloudUserBackendConfig: false,
                isAdmin: false,
                isSubAdmin: false,
                officeFontsFolder: '',
                personalAppSettingsLink: '',
                sharedFolder: '',
                userAndGroupBackends: [],
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

describe('AdminSettings component', () => {
  it('should be a Vue instance', () => {

    const pinia = createTestingPinia();
    setActivePinia(pinia);

    const wrapper = mount(AdminSettings, {
      global: {
        plugins: [pinia],
        directives: { tooltip: Tooltip },
      },
    });
    expect(wrapper.vm).toBeTruthy();
    expect(wrapper.find('.test-problem-report').exists()).toBe(false);
    wrapper.unmount();
  });

  it('opens a reportable artificial error for group admins and can generate a fresh error after closing', async () => {
    const initialState = loadState<AdminInitialState>(appName, INITIAL_STATE_SECTION);
    vi.mocked(loadState).mockReturnValueOnce({ ...initialState, isSubAdmin: true });

    const pinia = createTestingPinia();
    setActivePinia(pinia);
    const wrapper = mount(AdminSettings, {
      global: {
        plugins: [pinia],
        directives: { tooltip: Tooltip },
        stubs: { ErrorPageModal: true },
      },
    });
    expect(wrapper.findComponent(ErrorPageModal).exists()).toBe(false);
    await wrapper.get('.test-problem-report').trigger('click');

    const modal = wrapper.getComponent(ErrorPageModal);
    const error = modal.props('error');
    expect(error).toBeInstanceOf(AppError);
    expect(error.message).toBe('Artificial error for testing problem reports.');
    expect(error.cause).toBeInstanceOf(Error);
    expect(error.stack).toBeTruthy();
    expect(error.cause).toHaveProperty('stack');

    modal.vm.$emit('update:show', false);
    await wrapper.vm.$nextTick();
    expect(wrapper.findComponent(ErrorPageModal).exists()).toBe(false);

    await wrapper.get('.test-problem-report').trigger('click');
    expect(wrapper.getComponent(ErrorPageModal).props('error')).not.toBe(error);
    wrapper.unmount();
  });
});
