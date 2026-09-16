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

import type { AppError } from '~/src/toolkit/types/errors.ts';

import { registerHistoryTimestampsGetter } from '../util/mock-axios.ts';
import { setSilent as setLoggerSilent } from './toolkit/util/mock-console.ts';
setLoggerSilent(true);

import { createTestingPinia } from '@pinia/testing';
import Tooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip';
import { mount } from '@vue/test-utils';
import { setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import VueComponent from '~/src/CAFeVDB.vue';
import { appName } from '~/src/config.ts';
import router from '~/src/router/app-router.ts';
import useErrorHandler from '~/src/stores/error-handler.ts';

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
            case 'apps':
              return [{ id: appName, name: 'CAFeVDB' }];
            default:
              return null;
          }
        default:
          return null;
      }
    }) as typeof originalModule.loadState,
  };
});

vi.mock(import('vue-router'), async (originalComponent) => {
  const originalModule = await originalComponent();

  return {
    ...originalModule,
    useRoute: vi.fn(() => ({})) as unknown as typeof originalModule.useRoute,
    useRouter: vi.fn(() => ({
      push: () => {},
      replace: () => {},
      resolve: () => ({}),
      beforeEach: () => {},
      afterEach: () => {},
    })) as unknown as typeof originalModule.useRouter,
  };
});

registerHistoryTimestampsGetter();

describe('App main component', () => {
  let wrapper: ReturnType<typeof mount<typeof VueComponent>>;

  beforeEach(() => {
    document.body.id = 'body-user';

    const pinia = createTestingPinia();
    setActivePinia(pinia);

    const errorHandlerStore = useErrorHandler();
    errorHandlerStore.pushHandler(<E extends AppError>(error: E) => { console.error('Error handler called', error); });

    const props = {
    };

    wrapper = mount(VueComponent, {
      // shallow: true,
      props,
      global: {
        plugins: [pinia, router],
        directives: { tooltip: Tooltip },
      },
    });
    // There is no "transionend" event, however, the NcPopover
    // component only fires 'after-show' and hence NcActions its
    // 'opened' event after the NcPopover has received the
    // 'transionend' event on the popover content element.
    const actionsWrapper = wrapper.findComponent({ name: 'NcActions' });
    const actionsPopover = actionsWrapper.findComponent({ ref: 'popover' });

    const originalAfterShow = actionsPopover.vm.afterShow;
    actionsPopover.vm.afterShow = async function() {
      await originalAfterShow.call(actionsPopover);
      actionsPopover.vm.getPopoverContentElement().dispatchEvent(new Event('transitionend'));
    };
  });

  it('should be a Vue instance', () => {
    expect(wrapper.vm).toBeTruthy();
  });

});
