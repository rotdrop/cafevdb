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

import { setSilent as setLoggerSilent } from './toolkit/util/mock-console.ts';
//
import {
  mount,
  // shallowMount,
  createLocalVue,
  // type Wrapper,
  // type WrapperArray,
} from '@vue/test-utils';
import {
  Tooltip,
} from '@nextcloud/vue';
import VueComponent from '@/src/CAFeVDB.vue';
import VueRouter from 'vue-router';
import useErrorHandler from '@/src/stores/error-handler.ts';
import { createPinia } from 'pinia';
import type { AppError } from '@/src/toolkit/types/errors.ts';
import appRoutes from '@/src/router/routes.ts';

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
        default:
          return null;
      }
    }),
  };
});

jest.mock('vue-router/composables', () => {
  const originalModule: object = jest.requireActual('vue-router/composables');

  return {
    __esModule: true,
    ...originalModule,
    useRoute: jest.fn(() => ({})),
    useRouter: jest.fn(() => ({
      push: () => {},
      resolve: () => ({}),
      beforeEach: () => {},
      afterEach: () => {},
      onReady: () => {},
    })),
  };
});

const router = new VueRouter({
  routes: appRoutes,
});

const localVue = createLocalVue();
localVue.directive('tooltip', Tooltip);
// @ts-expect-error 2769
localVue.use(createPinia());
localVue.use(VueRouter);

describe('App main component', () => {
  let wrapper: ReturnType<typeof mount<VueComponent> >;

  beforeEach(() => {
    document.body.id = 'body-user';
    const errorHandlerStore = useErrorHandler();
    errorHandlerStore.pushHandler(<E extends AppError>(error: E) => { console.error('Error handler called', error); });

    const propsData = {
    };

    wrapper = mount(VueComponent, {
      propsData,
      localVue,
      router,
    });
    // There is no "transionend" event, however, the NcPopover
    // component only fires 'after-show' and hence NcActions its
    // 'opened' event after the NcPopover has received the
    // 'transionend' event on the popover content element.
    const actionsWrapper = wrapper.findComponent({ name: 'NcActions' });
    const actionsPopover = actionsWrapper.findComponent({ ref: 'popover' });

    // @ts-expect-error 2339
    const originalAfterShow = actionsPopover.vm.afterShow;
    // @ts-expect-error 2339
    actionsPopover.vm.afterShow = async function() {
      await originalAfterShow.call(actionsPopover);
      // @ts-expect-error 2339
      actionsPopover.vm.getPopoverContentElement().dispatchEvent(new Event('transitionend'));
    };
  });

  it('should be a Vue instance', () => {
    expect(wrapper.vm).toBeTruthy();
  });

});
