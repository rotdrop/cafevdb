/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

import { emit as asyncEmit } from '@rotdrop/async-nextcloud-event-bus';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import InvoiceActionsMenu from '~/src/components/InvoiceActionsMenu.vue';
import * as BusEvents from '~/src/event-bus-events.ts';
import axiosFileDownload from '~/src/toolkit/util/axios-file-download.ts';

vi.mock('@rotdrop/async-nextcloud-event-bus', () => ({
  emit: vi.fn(() => Promise.resolve()),
}));

vi.mock('~/src/toolkit/util/axios-file-download.ts', () => ({
  default: vi.fn(() => Promise.resolve()),
}));

vi.mock('~/src/stores/error-handler.ts', () => ({
  default: () => ({ getHandler: () => vi.fn() }),
}));

vi.mock('~/src/stores/tooltips.ts', () => ({
  default: () => ({ provideTooltips: vi.fn(), tooltipsData: {} }),
}));

vi.mock('~/src/util/console.ts', () => ({
  default: class {
    error = vi.fn();
  },
}));

vi.mock('@nextcloud/vue', async () => {
  const { defineComponent, h } = await import('vue');
  return {
    NcActionButton: defineComponent({
      props: { name: String },
      emits: ['click'],
      setup(props, { emit }) {
        return () => h('button', { onClick: () => emit('click') }, props.name);
      },
    }),
  };
});

vi.mock('~/src/components/LegacyPageActionsMenu.vue', async () => {
  const { defineComponent, h } = await import('vue');
  return {
    default: defineComponent({
      setup(_props, { slots }) {
        return () => h('div', slots.actions?.());
      },
    }),
  };
});

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
    props,
    global: {
      directives: { tooltip: () => {} },
    },
  });

  beforeEach(() => {
    vi.clearAllMocks();
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
