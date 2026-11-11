/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { MountableComponent } from '~/src/services/mountable-components.ts';

import { awaitEmit, emit, hasSubscriptions, subscribe, unsubscribeAll } from '@rotdrop/async-nextcloud-event-bus';
import { flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp } from 'vue';
import { emailForm } from '../../fixtures/email-form.ts';
import { EMAIL_FORM_COMMAND, EMAIL_FORM_EVENT, GET_VUE_COMPONENT, LEGACY_PAGE_CLEANUP } from '~/src/event-bus-events.ts';
import { getMountedChosen, mountChosen } from '~/src/services/mountable-chosen.ts';
import { provideMountableComponents } from '~/src/services/mountable-components.ts';
import { DualListboxService, getMountedDualListbox } from '~/src/services/mountable-dual-listbox.ts';
import { getMountedSelectize, mountSelectize } from '~/src/services/mountable-selectize.ts';
import { destroyMountedSelects } from '~/src/services/mounted-select-scope.ts';

vi.mock('~/src/config.ts', () => ({
  appName: 'cafevdb',
  appNameTag: 'app-cafevdb',
  appPrefix: (value: string) => `cafevdb-${value}`,
}));
vi.mock('~/src/util/console.ts', () => ({
  default: class {
    error = vi.fn();
  },
}));

afterEach(() => {
  unsubscribeAll(GET_VUE_COMPONENT);
  unsubscribeAll(EMAIL_FORM_EVENT);
  unsubscribeAll(EMAIL_FORM_COMMAND);
  document.body.replaceChildren();
});

describe('mountable email form', () => {
  it.each([
    {
      name: 'ordered select',
      mount: async (select: HTMLSelectElement) => (await mountSelectize(select))[0]!,
      get: getMountedSelectize,
      hidden: (select: HTMLSelectElement) => select.hidden,
    },
    {
      name: 'chosen select',
      mount: async (select: HTMLSelectElement) => (await mountChosen(select))[0]!,
      get: getMountedChosen,
      hidden: (select: HTMLSelectElement) => select.hidden,
    },
    {
      name: 'dual listbox',
      mount: async (select: HTMLSelectElement) => {
        const service = DualListboxService.mount(select);
        await service.ready;
        return service;
      },
      get: getMountedDualListbox,
      hidden: (select: HTMLSelectElement) => select.style.display === 'none',
    },
  ])('preserves the email $name across page cleanup and disposes it when the envelope closes', async ({ mount, get, hidden }) => {
    provideMountableComponents(createApp({}));
    const host = document.createElement('div');
    const mountPoint = document.createElement('div');
    const ambient = document.createElement('div');
    const header = document.createElement('header');
    const body = document.createElement('main');
    host.append(mountPoint);
    ambient.append(header, body, host);
    document.body.append(ambient);
    subscribe(EMAIL_FORM_EVENT, (event) => {
      if (event.action === 'cleanup') {
        destroyMountedSelects(event.container);
      }
    });
    const envelope = await awaitEmit(GET_VUE_COMPONENT, {
      name: 'EmailFormEnvelope',
      propsData: { title: 'Persistent draft', form: emailForm, single: false },
    }) as MountableComponent<'EmailFormEnvelope'>;
    envelope.mount(mountPoint);
    await flushPromises();
    const form = host.querySelector<HTMLElement>('#emailformcomposer')!;
    const makeSelect = (container: HTMLElement) => {
      const select = document.createElement('select');
      select.multiple = true;
      select.innerHTML = '<option value="recipient" selected>Recipient</option>';
      container.append(select);
      return select;
    };
    const emailSelect = makeSelect(form);
    const control = await mount(emailSelect);
    const widget = emailSelect.nextElementSibling;
    // Both header and body controls belong to the replaceable page.
    for (let navigation = 0; navigation < 2; ++navigation) {
      const pageSelects = [makeSelect(header), makeSelect(body)];
      await Promise.all(pageSelects.map(mount));
      await emit(LEGACY_PAGE_CLEANUP, { containers: [header, body] });
      header.replaceChildren();
      body.replaceChildren();
      await flushPromises();
      expect(pageSelects.map(get)).toEqual([undefined, undefined]);
      expect(pageSelects.map(hidden)).toEqual([false, false]);
      expect(get(emailSelect)).toBe(control);
      expect(hidden(emailSelect)).toBe(true);
      expect(emailSelect.nextElementSibling).toBe(widget);
      expect(emailSelect.selectedOptions[0]!.value).toBe('recipient');
    }
    // Minimize and restore must not replace the legacy HTML islands either.
    host.querySelector<HTMLButtonElement>('button[aria-label="Minimize"]')!.click();
    await flushPromises();
    await emit(EMAIL_FORM_COMMAND, { action: 'show' });
    await flushPromises();
    expect(get(emailSelect)).toBe(control);
    expect(emailSelect.nextElementSibling).toBe(widget);
    envelope.unmount();
    envelope.destroy();
    await flushPromises();
    expect(get(emailSelect)).toBeUndefined();
    expect(hidden(emailSelect)).toBe(false);
  });

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
