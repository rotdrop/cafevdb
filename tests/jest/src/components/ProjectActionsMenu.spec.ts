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

// mock-defining imports must come first
import { entityIdentifiers, projectFolders } from '../toolkit/services/mock-axios-entity-repository-controller.ts';
import { setSilent as setLoggerSilent } from '../toolkit/util/mock-console.ts';
//
import {
  mount,
  // shallowMount,
  createLocalVue,
  type Wrapper,
  type WrapperArray,
} from '@vue/test-utils';
import {
  NcActions,
  NcPopover,
  Tooltip,
} from '@nextcloud/vue';
import VueComponent from '@/src/components/ProjectActionsMenu.vue';
import VueRouter from 'vue-router';
// import { loadState } from '@nextcloud/initial-state';
import { expect, jest } from '@jest/globals';
// import useAppDataStore from '@/src/stores/app-data.ts';
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
    })),
  };
});

const localVue = createLocalVue();
localVue.directive('tooltip', Tooltip);
// @ts-expect-error 2769
localVue.use(createPinia());
localVue.use(VueRouter);

const router = new VueRouter({
  routes: appRoutes,
});

describe('ProjectActionsMenu component', () => {

  let wrapper: ReturnType<typeof mount<VueComponent> >;

  beforeEach(() => {
    const errorHandlerStore = useErrorHandler();
    errorHandlerStore.pushHandler(<E extends AppError>(error: E) => { console.error('Error handler called', error); });

    const propsData = {
      entityId: +entityIdentifiers.Project.id,
      projectName: undefined,
      enableOverviewItem: true,
      template: 'projects',
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

  it('should expose open menu control functions', async () => {
    expect(wrapper.vm.isOpen()).toBeFalsy();
    await wrapper.vm.openMenu();
    expect(wrapper.vm.isOpen()).toBeTruthy();
    await wrapper.vm.closeMenu();
    expect(wrapper.vm.isOpen()).toBeFalsy();
  });

  // localVue.nextTick = new Promise(r => setTimeout(r, 0));

  it(
    'should have links to project folders',
    async () => {
      await wrapper.vm.openMenu();
      let actionsWrappers: WrapperArray<typeof NcActions> = wrapper.findAllComponents<typeof NcActions>({ name: 'NcActions' });
      expect(actionsWrappers.length).toBe(1);
      await wrapper.vm.closeMenu();
      await wrapper.vm.openMenu(20, 20);
      actionsWrappers = wrapper.findAllComponents<typeof NcActions>({ name: 'NcActions' });
      expect(actionsWrappers.length).toBe(2);
      const actionsWrapper: Wrapper<typeof NcActions> = actionsWrappers.at(1); // the first one is a dummy dots provider
      const popover: typeof NcPopover = actionsWrapper.findComponent<typeof NcPopover>({ name: 'NcPopover' }).vm;
      const contentHolder = popover.getPopoverContentElement();
      const anchors: HTMLAnchorElement[] = [];
      for (const el of contentHolder.getElementsByTagName('a')) {
        anchors.push(el);
      }
      const hrefs = anchors.map(el => el.getAttribute('href'));
      const folders = hrefs
        .filter(url => url?.startsWith('/index.php/apps/files/?dir='))
        .map(url => url!.replace('/index.php/apps/files/?dir=', ''))
        .sort();
      const expectedFolders = [
        projectFolders.projectsFolder,
        projectFolders.balancesFolder,
      ].sort();
      expect(folders).toStrictEqual(expectedFolders);
    },
    // 10000,
  );
});
