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

import { emit as asyncEmit } from '@rotdrop/async-nextcloud-event-bus';
import Tooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import VueComponent from '~/src/components/SepaBulkTransactionActionsMenu.vue';
import * as BusEvents from '~/src/event-bus-events.ts';
import axiosFileDownload from '~/src/toolkit/util/axios-file-download.ts';
import { EnumSepaBulkTransactionsExportPurpose, EnumSepaBulkTransactionsTopic } from '../../../../build/ts-types/php-modules/Controller.ts';
import { END_POINT } from '../../../../build/ts-types/php-modules/Controller/SepaBulkTransactionsController.ts';

setLoggerSilent(true);

const errorHandlerMock = vi.fn();
const provideTooltipsMock = vi.fn();

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

vi.mock(import('@rotdrop/async-nextcloud-event-bus'), async (originalImport) => {
  const originalModule = await originalImport();

  return {
    ...originalModule,
    emit: vi.fn(() => Promise.resolve()) as unknown as typeof originalModule.emit,
  };
});

vi.mock('~/src/toolkit/util/axios-file-download.ts', () => ({
  default: vi.fn(() => Promise.resolve()),
}));

vi.mock('~/src/stores/error-handler.ts', () => ({
  default: () => ({ getHandler: () => errorHandlerMock }),
}));

vi.mock('~/src/stores/tooltips.ts', () => ({
  default: () => ({ provideTooltips: provideTooltipsMock, tooltipsData: {} }),
}));

const legacyMenuMocks = {
  isOpen: vi.fn(() => true),
  openMenu: vi.fn(),
  closeMenu: vi.fn(),
};

// Render the action slot directly, such that the buttons are in the DOM
// without having to open a popover.
const LegacyPageActionsMenuStub = defineComponent({
  name: 'LegacyPageActionsMenu',
  props: {
    enableOverviewItem: { type: Boolean, default: true },
    entityId: { type: Number, default: undefined },
    menuCaption: { type: String, default: undefined },
    projectId: { type: Number, default: undefined },
    projectName: { type: String, default: undefined },
    template: { type: String, default: undefined },
  },
  setup(_props, { slots, expose }) {
    expose(legacyMenuMocks);
    return () => h('div', { class: 'legacy-page-actions-menu-stub' }, slots.actions?.());
  },
});

describe('SepaBulkTransactionActionsMenu component', () => {

  const props = {
    enableOverviewItem: true,
    entityId: 42,
    menuCaption: 'Hello World!',
    projectId: 7,
    projectName: 'ProjectName',
    template: 'TemplateName',
  };

  const BUTTON_NAMES = [
    'Bank Transaction Data',
    'SEPA-XML Data',
    'Email Pre-Notification',
    'GnuCash Balance Data',
  ];

  const TOOLTIP_KEYS = [
    'sepa-bulk-transaction:download',
    'sepa-bulk-transaction:download-xml',
    'sepa-bulk-transaction:announce',
    'sepa-bulk-transaction:gnucash-balance',
  ];

  const mountComponent = () => mount(VueComponent, {
    props,
    global: {
      directives: { tooltip: Tooltip },
      stubs: { LegacyPageActionsMenu: LegacyPageActionsMenuStub },
    },
  });

  const findButtons = (wrapper: ReturnType<typeof mountComponent>) => wrapper.findAllComponents({ name: 'NcActionButton' });

  const findButton = (wrapper: ReturnType<typeof mountComponent>, name: string) => {
    const button = findButtons(wrapper).find((candidate) => candidate.props('name') === name);
    expect(button, `button "${name}" not found`).toBeDefined();
    return button!;
  };

  const expectedPost = (purpose: EnumSepaBulkTransactionsExportPurpose, format: string) => ({
    bulkTransactionId: props.entityId,
    projectId: props.projectId,
    projectName: props.projectName,
    purpose,
    format,
  });

  const downloadEndPoint = `${END_POINT}/${EnumSepaBulkTransactionsTopic.EXPORT}`;

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should be a Vue instance', () => {
    const wrapper = mountComponent();
    expect(wrapper.vm).toBeTruthy();
  });

  describe('rendering', () => {

    it('should render the action buttons in order, with the SEPA-XML button below the first one', () => {
      const wrapper = mountComponent();
      const names = findButtons(wrapper).map((button) => button.props('name'));
      expect(names).toStrictEqual(BUTTON_NAMES);
    });

    it('should tag all action buttons with the actions CSS class', () => {
      const wrapper = mountComponent();
      const buttons = findButtons(wrapper);
      expect(buttons.length).toBe(BUTTON_NAMES.length);
      for (const button of buttons) {
        expect(button.classes()).toContain('cafevdb-sepa-bulk-transaction-actions');
      }
    });

    it('should request the tooltips for all buttons', () => {
      mountComponent();
      expect(provideTooltipsMock).toHaveBeenCalledTimes(1);
      const keys = provideTooltipsMock.mock.calls[0][0] as string[];
      expect([ ...keys ].sort()).toStrictEqual([ ...TOOLTIP_KEYS ].sort());
    });

    it('should pass its props through to the legacy page actions menu', () => {
      const wrapper = mountComponent();
      const legacyMenu = wrapper.findComponent({ name: 'LegacyPageActionsMenu' });
      expect(legacyMenu.props()).toMatchObject({
        enableOverviewItem: props.enableOverviewItem,
        entityId: props.entityId,
        menuCaption: props.menuCaption,
        projectId: props.projectId,
        projectName: props.projectName,
        template: props.template,
      });
    });

    it('should pass a disabled overview item through to the legacy page actions menu', () => {
      const wrapper = mount(VueComponent, {
        props: { ...props, enableOverviewItem: false },
        global: {
          directives: { tooltip: Tooltip },
          stubs: { LegacyPageActionsMenu: LegacyPageActionsMenuStub },
        },
      });
      expect(wrapper.findComponent({ name: 'LegacyPageActionsMenu' }).props('enableOverviewItem')).toBe(false);
    });

  });

  describe('downloads', () => {

    it('should download the AqBanking data for the bank transaction button', async () => {
      const wrapper = mountComponent();
      findButton(wrapper, 'Bank Transaction Data').vm.$emit('click');
      await vi.waitFor(() => expect(axiosFileDownload).toHaveBeenCalledTimes(1));
      expect(axiosFileDownload).toHaveBeenCalledWith(
        downloadEndPoint,
        expectedPost(EnumSepaBulkTransactionsExportPurpose.BANK_IMPORT, 'aqbanking'),
      );
    });

    it('should download the SEPA-XML data for the SEPA-XML button', async () => {
      const wrapper = mountComponent();
      findButton(wrapper, 'SEPA-XML Data').vm.$emit('click');
      await vi.waitFor(() => expect(axiosFileDownload).toHaveBeenCalledTimes(1));
      expect(axiosFileDownload).toHaveBeenCalledWith(
        downloadEndPoint,
        expectedPost(EnumSepaBulkTransactionsExportPurpose.BANK_IMPORT, 'sepa-xml'),
      );
    });

    it('should download the balancing items for the GnuCash button', async () => {
      const wrapper = mountComponent();
      findButton(wrapper, 'GnuCash Balance Data').vm.$emit('click');
      await vi.waitFor(() => expect(axiosFileDownload).toHaveBeenCalledTimes(1));
      expect(axiosFileDownload).toHaveBeenCalledWith(
        expect.stringContaining('bulk-transactions/export'),
        expectedPost(EnumSepaBulkTransactionsExportPurpose.BALANCING_ITEMS, 'gnucash'),
      );
    });

    it.each([ 'Bank Transaction Data', 'SEPA-XML Data', 'GnuCash Balance Data' ])(
      'should push and pop the busy state around a successful download for "%s"',
      async (name) => {
        const wrapper = mountComponent();
        findButton(wrapper, name).vm.$emit('click');
        await vi.waitFor(() => expect(asyncEmit).toHaveBeenCalledTimes(2));
        expect(vi.mocked(asyncEmit).mock.calls.map((call) => call[0])).toStrictEqual([
          BusEvents.PUSH_BUSY_STATE,
          BusEvents.POP_BUSY_STATE,
        ]);
        expect(errorHandlerMock).not.toHaveBeenCalled();
      },
    );

    it.each([ 'Bank Transaction Data', 'SEPA-XML Data', 'GnuCash Balance Data' ])(
      'should pop the busy state and report an error if the download for "%s" fails',
      async (name) => {
        const cause = new Error('download failed');
        vi.mocked(axiosFileDownload).mockRejectedValueOnce(cause);

        const wrapper = mountComponent();
        findButton(wrapper, name).vm.$emit('click');
        await vi.waitFor(() => expect(errorHandlerMock).toHaveBeenCalledTimes(1));

        expect(vi.mocked(asyncEmit).mock.calls.map((call) => call[0])).toStrictEqual([
          BusEvents.PUSH_BUSY_STATE,
          BusEvents.POP_BUSY_STATE,
        ]);

        const error = errorHandlerMock.mock.calls[0][0] as AppError;
        expect(error.name).toBe('AppError');
        expect(error.message).toContain(String(props.entityId));
        expect(error.cause).toBe(cause);
      },
    );

  });

  describe('pre-notification email', () => {

    it('should open the email popup with project and bulk-transaction ids', async () => {
      const wrapper = mountComponent();
      findButton(wrapper, 'Email Pre-Notification').vm.$emit('click');
      await vi.waitFor(() => expect(asyncEmit).toHaveBeenCalledTimes(1));
      expect(asyncEmit).toHaveBeenCalledWith(BusEvents.EMAIL_POPUP, {
        projectId: props.projectId,
        projectName: props.projectName,
        post: {
          bulkTransactionId: props.entityId,
          projectId: props.projectId,
          projectName: props.projectName,
        },
      });
      expect(axiosFileDownload).not.toHaveBeenCalled();
    });

  });

  describe('exposed menu control', () => {

    it('should delegate isOpen(), openMenu() and closeMenu() to the legacy page actions menu', () => {
      const wrapper = mountComponent();
      const vm = wrapper.vm as unknown as {
        isOpen: () => boolean
        openMenu: (x?: number, y?: number) => void
        closeMenu: () => void
      };

      expect(vm.isOpen()).toBe(true);
      expect(legacyMenuMocks.isOpen).toHaveBeenCalledTimes(1);

      vm.openMenu(10, 20);
      expect(legacyMenuMocks.openMenu).toHaveBeenCalledWith(10, 20);

      vm.closeMenu();
      expect(legacyMenuMocks.closeMenu).toHaveBeenCalledTimes(1);
    });

  });
});
