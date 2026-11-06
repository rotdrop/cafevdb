/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ComponentProps } from '~/src/mountable-component-names.ts';
import type { MountableComponent } from '~/src/services/mountable-components.ts';
import type { EmailFormEnvelopeControls } from '~/src/types/email-form-envelope.ts';

import { flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp } from 'vue';
import { provideMountableComponents } from '~/src/services/mountable-components.ts';

const { bus } = vi.hoisted(() => ({
  bus: { request: undefined as undefined | ((event: { name: string; propsData: ComponentProps['EmailFormEnvelope'] }) => Promise<MountableComponent<'EmailFormEnvelope'> & EmailFormEnvelopeControls>) },
}));
vi.mock('@rotdrop/async-nextcloud-event-bus', () => ({
  subscribe: (_name: string, callback: typeof bus.request) => { bus.request = callback; },
}));
vi.mock('~/src/config.ts', () => ({
  appName: 'cafevdb',
  appNameTag: 'app-cafevdb',
  appPrefix: (value: string) => `cafevdb-${value}`,
}));

afterEach(() => document.body.replaceChildren());

describe('mountable email form', () => {
  it('resolves through the component bus, exposes controls and cleans up before unmounting', async () => {
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
    const component = await bus.request!({
      name: 'EmailFormEnvelope',
      propsData: {
        title: 'Email for project',
        contents: '<form><input name="subject" value="Message"></form>',
        onReady,
        onCleanup,
      },
    });
    component.mount(mountPoint);
    await flushPromises();
    const container = host.querySelector('#emailformdialog');
    expect(onReady).toHaveBeenCalledExactlyOnceWith(container);
    expect(component.vNode?.appContext).toBe(app._context);
    component.setTitle('Draft from another project');
    component.setBlocked(true);
    await flushPromises();
    expect(host.querySelector('h2')?.textContent).toBe('Draft from another project');
    expect(host.querySelector('#emailformdialog')?.hasAttribute('inert')).toBe(true);
    expect(host.querySelector('#emailformdialog')).toBe(container);
    component.setBlocked(false);
    component.show();
    await flushPromises();
    component.unmount();
    expect(onCleanup).toHaveBeenCalledExactlyOnceWith(container);
    expect(host.querySelector('#emailformdialog')).toBeNull();
    expect(mountPoint.parentElement).toBe(host);
    component.destroy();
    component.unmount();
    expect(onCleanup).toHaveBeenCalledOnce();
    expect(component.vNode).toBeUndefined();
  });
});
