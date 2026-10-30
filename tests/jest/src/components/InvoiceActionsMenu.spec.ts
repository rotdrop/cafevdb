/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

import { emit as asyncEmit } from '@/src/services/async-event-bus.ts';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import InvoiceActionsMenu from '@/src/components/InvoiceActionsMenu.vue';
import * as BusEvents from '@/src/event-bus-events.ts';
import axiosFileDownload from '@/src/toolkit/util/axios-file-download.ts';

jest.mock('@/src/services/async-event-bus.ts', () => ({
  emit: jest.fn(() => Promise.resolve()),
}));

jest.mock('@/src/toolkit/util/axios-file-download.ts', () => ({
  __esModule: true,
  default: jest.fn(() => Promise.resolve()),
}));

jest.mock('@/src/stores/error-handler.ts', () => ({
  __esModule: true,
  default: () => ({ getHandler: () => jest.fn() }),
}));

jest.mock('@/src/stores/tooltips.ts', () => ({
  __esModule: true,
  default: () => ({ provideTooltips: jest.fn(), tooltipsData: {} }),
}));

jest.mock('@/src/util/console.ts', () => ({
  __esModule: true,
  default: class {
    error = jest.fn();
  },
}));

jest.mock('@nextcloud/vue', () => ({
  NcActionButton: {
    props: ['name'],
    template: '<button @click="$emit(\'click\')">{{ name }}</button>',
  },
}));

jest.mock('@/src/components/LegacyPageActionsMenu.vue', () => ({
  __esModule: true,
  default: {
    template: '<div><slot name="actions" /></div>',
  },
}));

describe('InvoiceActionsMenu', () => {
  const props = {
    entityId: 45,
    invoiceNumber: 'INVOICE-45',
    originatorId: 12,
    projectId: 7,
    projectName: 'Project',
    template: 'invoices',
  };
  const mountComponent = () => mount(InvoiceActionsMenu, {
    propsData: props,
    directives: { tooltip: () => {} },
  });

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('passes the invoice id to the email form', async () => {
    const wrapper = mountComponent();
    const button = wrapper.findAll('button').find((candidate) => candidate.text() === 'Email Standard Invoice');
    expect(button).toBeDefined();
    await button!.trigger('click');
    expect(asyncEmit).toHaveBeenCalledWith(BusEvents.EMAIL_POPUP, {
      projectId: props.projectId,
      post: expect.objectContaining({
        invoiceId: props.entityId,
        projectId: props.projectId,
        projectName: props.projectName,
      }),
    });
    expect(axiosFileDownload).not.toHaveBeenCalled();
  });

  it('preserves the standard invoice download', async () => {
    const wrapper = mountComponent();
    const button = wrapper.findAll('button').find((candidate) => candidate.text() === 'Download Standard Invoice');
    expect(button).toBeDefined();
    await button!.trigger('click');
    expect(axiosFileDownload).toHaveBeenCalledWith('documents/mail-merge', {
      templateName: 'invoice',
      senderId: props.originatorId,
      invoiceIds: [props.entityId],
      projectId: props.projectId,
      operation: 'download',
    });
  });
});
