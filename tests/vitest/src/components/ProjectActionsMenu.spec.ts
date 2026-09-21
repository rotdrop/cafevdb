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

import type {
  NcActions,
  NcPopover,
} from '@nextcloud/vue';
import type { VueWrapper } from '@vue/test-utils';
import type { AppError } from '~/src/toolkit/types/errors.ts';

import { setSilent as setLoggerSilent } from '../toolkit/util/mock-console.ts';
setLoggerSilent(true);
import { entityIdentifiers, projectFolders } from '../toolkit/services/mock-axios-entity-repository-controller.ts';

import { beforeEach, describe, expect, it, vi } from 'vitest';
import Tooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip';
import { createTestingPinia } from '@pinia/testing';
import { setActivePinia } from 'pinia';
import { mount } from '@vue/test-utils';
import router from '~/src/router/app-router.ts';
import VueComponent from '~/src/components/ProjectActionsMenu.vue';
import useErrorHandler from '~/src/stores/error-handler.ts';

// this __is__ shitty:
type MyVueComponent = (typeof VueComponent) & {
  isOpen: () => boolean;
  openMenu: (x?: number, y?: number) => Promise<unknown>;
  closeMenu: () => Promise<unknown>;
};

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

vi.mock(import('vue-router'), async (originalComponent) => {
  const originalModule = await originalComponent();

  return {
    ...originalModule,
    useRoute: vi.fn(() => ({
      query: '',
    })) as unknown as typeof originalModule.useRoute,
    useRouter: vi.fn(() => ({
      push: () => {},
      replace: () => {},
      resolve: () => ({}),
      beforeEach: () => {},
      afterEach: () => {},
      onReady: () => {},
    })) as unknown as typeof originalModule.useRouter,
  };
});

describe('ProjectActionsMenu component', () => {

  let wrapper: ReturnType<typeof mount<MyVueComponent>>;

  beforeEach(() => {
    const pinia = createTestingPinia({ stubActions: [] });
    setActivePinia(pinia);

    const errorHandlerStore = useErrorHandler();
    errorHandlerStore.pushHandler(<E extends AppError>(error: E) => { console.error('Error handler called', error); });

    const props = {
      entityId: +entityIdentifiers.Project.id,
      projectName: undefined,
      enableOverviewItem: true,
      template: 'projects',
    };

    wrapper = mount(VueComponent, {
      props,
      global: {
        plugins: [pinia, router],
        directives: { tooltip: Tooltip },
        stubs: {
          RouterView: true,
          RouterLink: true,
        },
      },
    });
    // There is no "transionend" event, however, the NcPopover
    // component only fires 'after-show' and hence NcActions its
    // 'opened' event after the NcPopover has received the
    // 'transionend' event on the popover content element.
    const actionsWrapper = wrapper.findComponent<typeof NcActions>({ name: 'NcActions' });
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

  it('should expose open menu control functions', async () => {
    const vm = wrapper.vm as unknown as MyVueComponent;
    expect(vm.isOpen()).toBeFalsy();
    await vm.openMenu();
    expect(vm.isOpen()).toBeTruthy();
    await vm.closeMenu();
    expect(vm.isOpen()).toBeFalsy();
  });

  // localVue.nextTick = new Promise(r => setTimeout(r, 0));

  it(
    'should have links to project folders',
    async () => {
      const vm = wrapper.vm as unknown as MyVueComponent;
      await vm.openMenu();
      let actionsWrappers = wrapper.findAllComponents<typeof NcActions>({ name: 'NcActions' });
      expect(actionsWrappers.length).toBe(1);
      await vm.closeMenu();
      await vm.openMenu(20, 20);
      actionsWrappers = wrapper.findAllComponents<typeof NcActions>({ name: 'NcActions' });
      expect(actionsWrappers.length).toBe(2);
      const actionsWrapper: VueWrapper<typeof NcActions> = actionsWrappers.at(1)!; // the first one is a dummy dots provider
      const popover: typeof NcPopover = actionsWrapper.findComponent<typeof NcPopover>({ name: 'NcPopover' }).vm;
      const contentHolder = popover.getPopoverContentElement();
      const anchors: HTMLAnchorElement[] = [];
      for (const el of contentHolder.getElementsByTagName('a')) {
        anchors.push(el);
      }
      const hrefs = anchors.map((el) => el.getAttribute('href'));
      const folders = hrefs
        .filter((url) => url?.startsWith('/index.php/apps/files/?dir='))
        .map((url) => url!.replace('/index.php/apps/files/?dir=', ''))
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
