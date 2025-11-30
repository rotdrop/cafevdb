import { mount, shallowMount, createLocalVue } from '@vue/test-utils';
import { createTestingPinia } from '@pinia/testing';
import AdminSettings from '../../../../src/components/AdminSettings.vue';
// import { loadState } from '@nextcloud/initial-state';
import { jest } from '@jest/globals';
import { INITIAL_STATE_SECTION, AUTHORIZATION_GROUP_SUFFIXES } from '../../../../build/ts-types/php-modules/Settings/Admin.ts';
import type { AdminInitialState } from '../../../../build/ts-types/php-modules/Settings.ts';
import { Tooltip } from '@nextcloud/vue';

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
    }),
  };
});

const localVue = createLocalVue();
localVue.directive('tooltip', Tooltip);
localVue.use(createTestingPinia());

describe('AdminSettings component', () => {
  it('should be a Vue instance', () => {
    const wrapper = mount(AdminSettings, {
      localVue,
    });
    expect(wrapper.vm).toBeTruthy();
  });
});
