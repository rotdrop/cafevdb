/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { MountableComponent } from '~/src/services/mountable-components.ts';

import { awaitEmit, emit, hasSubscriptions, subscribe, unsubscribeAll } from '@rotdrop/async-nextcloud-event-bus';
import { flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp } from 'vue';
import { emailForm } from '../../fixtures/email-form.ts';
import { EMAIL_FORM_COMMAND, EMAIL_FORM_EVENT, GET_VUE_COMPONENT } from '~/src/event-bus-events.ts';
import { provideMountableComponents } from '~/src/services/mountable-components.ts';

vi.mock('~/src/config.ts', () => ({
  appName: 'cafevdb',
  appNameTag: 'app-cafevdb',
  appPrefix: (value: string) => `cafevdb-${value}`,
}));

afterEach(() => {
  unsubscribeAll(GET_VUE_COMPONENT);
  unsubscribeAll(EMAIL_FORM_EVENT);
  unsubscribeAll(EMAIL_FORM_COMMAND);
  document.body.replaceChildren();
});

describe('mountable email form', () => {
  it('resolves through the component bus, accepts bus commands and cleans up before unmounting', async () => {
    const app = createApp({});
    provideMountableComponents(app);
    const host = document.createElement('div');
    const mountPoint = document.createElement('div');
    host.append(mountPoint);
    document.body.append(host);
    const onReady = vi.fn();
    const onCleanup = vi.fn((container: HTMLElement) => {
      expect(container.isConnected).toBe(true);
    });
    subscribe(EMAIL_FORM_EVENT, (event) => {
      if (event.action === 'ready') { onReady(event.container); }
      if (event.action === 'cleanup') { onCleanup(event.container); }
    });
    const component = await awaitEmit(GET_VUE_COMPONENT, {
      name: 'EmailFormEnvelope',
      propsData: {
        title: 'Email for project',
        form: emailForm,
        single: false,
      },
    }) as MountableComponent<'EmailFormEnvelope'>;
    component.mount(mountPoint);
    await flushPromises();
    const container = host.querySelector('#emailformdialog');
    expect(onReady).toHaveBeenCalledExactlyOnceWith(container);
    expect(component.vNode?.appContext).toBe(app._context);
    await emit(EMAIL_FORM_COMMAND, { action: 'title', title: 'Draft from another project' });
    await emit(EMAIL_FORM_COMMAND, { action: 'blocked', blocked: true });
    await flushPromises();
    expect(host.querySelector('h2')?.textContent).toBe('Draft from another project');
    expect(host.querySelector('#emailformdialog')?.hasAttribute('inert')).toBe(true);
    expect(host.querySelector('#emailformdialog')).toBe(container);
    await emit(EMAIL_FORM_COMMAND, { action: 'blocked', blocked: false });
    await emit(EMAIL_FORM_COMMAND, { action: 'show' });
    await flushPromises();
    component.unmount();
    expect(onCleanup).toHaveBeenCalledExactlyOnceWith(container);
    expect(host.querySelector('#emailformdialog')).toBeNull();
    expect(mountPoint.parentElement).toBe(host);
    component.destroy();
    component.unmount();
    expect(onCleanup).toHaveBeenCalledOnce();
    expect(component.vNode).toBeUndefined();
    expect(hasSubscriptions(EMAIL_FORM_COMMAND)).toBe(false);
  });
});
