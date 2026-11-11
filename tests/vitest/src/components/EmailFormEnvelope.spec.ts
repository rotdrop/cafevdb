/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { emit, hasSubscriptions, subscribe, unsubscribeAll } from '@rotdrop/async-nextcloud-event-bus';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp } from 'vue';
import EmailFormEnvelope from '~/src/components/EmailFormEnvelope.vue';
import EmailFormRestoreButton from '~/src/components/EmailFormRestoreButton.vue';
import { emailForm } from '../../fixtures/email-form.ts';
import { EMAIL_FORM_COMMAND, EMAIL_FORM_EVENT, WIKI_POPUP } from '~/src/event-bus-events.ts';

vi.mock('~/src/config.ts', () => ({
  appName: 'cafevdb',
  appNameTag: 'app-cafevdb',
  appPrefix: (value: string) => `cafevdb-${value}`,
}));

const wrappers: ReturnType<typeof mount>[] = [];
const render = (single = false) => {
  const onReady = vi.fn();
  const onClose = vi.fn();
  const onCleanup = vi.fn();
  subscribe(EMAIL_FORM_EVENT, (event) => {
    if (event.action === 'ready') { onReady(event.container); }
    if (event.action === 'close') { onClose(); }
    if (event.action === 'cleanup') { onCleanup(event.container); }
  });
  const wrapper = mount(EmailFormEnvelope, {
    attachTo: document.body,
    props: {
      title: 'Email for project',
      form: emailForm,
      single,
    },
  });
  const toolbar = mount(EmailFormRestoreButton, { attachTo: document.body });
  wrappers.push(wrapper, toolbar);
  const controls = {
    setTitle: (title: string) => emit(EMAIL_FORM_COMMAND, { action: 'title', title }),
    setBlocked: (blocked: boolean) => emit(EMAIL_FORM_COMMAND, { action: 'blocked', blocked }),
    show: () => emit(EMAIL_FORM_COMMAND, { action: 'show' }),
  };
  return { wrapper, toolbar, controls, onReady, onClose, onCleanup };
};

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  unsubscribeAll(EMAIL_FORM_EVENT);
  unsubscribeAll(EMAIL_FORM_COMMAND);
  unsubscribeAll(WIKI_POPUP);
  document.body.replaceChildren();
});

describe('EmailFormEnvelope', () => {
  it('provides the connected legacy HTML island and preserves form submission data', () => {
    const { wrapper, onReady } = render();
    const container = wrapper.get('#emailformdialog').element;
    expect(onReady).toHaveBeenCalledExactlyOnceWith(container);
    expect(container.isConnected).toBe(true);
    const data = new FormData(wrapper.get('form').element as HTMLFormElement);
    expect(data.get('emailComposer[subject]')).toBe('Original');
    expect(data.getAll('emailRecipientsFilter[selectedRecipients][]')).toEqual(['42']);
    expect(data.get('requesttoken')).toBe('csrf-token');
    expect(wrapper.find('.ui-dialog').exists()).toBe(false);
  });

  it('does not patch legacy descendants when title and busy state change', async () => {
    const { wrapper, controls } = render();
    const input = wrapper.get('input[name="emailRecipientsFilter[selectedRecipients][]"]').element as HTMLInputElement;
    input.value = '43';
    const replacement = document.createElement('div');
    replacement.textContent = 'Reloaded legacy panel';
    wrapper.get('#emailformcomposer').element.replaceChildren(replacement);
    wrapper.get('form').element.classList.add('project-mode');
    controls.setTitle('Another project');
    controls.setBlocked(true);
    await flushPromises();
    expect(wrapper.get('h2').text()).toBe('Another project');
    expect(wrapper.get('input[name="emailRecipientsFilter[selectedRecipients][]"]').element).toBe(input);
    expect(input.value).toBe('43');
    expect(wrapper.get('#emailformcomposer').element.firstChild).toBe(replacement);
    expect(wrapper.get('form').classes()).toContain('project-mode');
  });

  it('minimizes and restores without recreating the editor or cleaning up the form', async () => {
    const { wrapper, toolbar, controls, onCleanup } = render();
    const container = wrapper.get('#emailformdialog').element;
    expect(toolbar.find('button').exists()).toBe(false);
    await wrapper.get('button[aria-label="Minimize"]').trigger('click');
    expect(wrapper.get('section').isVisible()).toBe(false);
    expect(wrapper.find('.emailform-envelope-restore').exists()).toBe(false);
    expect(toolbar.get('button').attributes('aria-label')).toBe('Restore Email for project');
    expect(onCleanup).not.toHaveBeenCalled();
    await toolbar.get('button').trigger('click');
    expect(wrapper.get('section').isVisible()).toBe(true);
    expect(toolbar.find('button').exists()).toBe(false);
    expect(wrapper.get('#emailformdialog').element).toBe(container);
    await wrapper.get('button[aria-label="Minimize"]').trigger('click');
    controls.show();
    await flushPromises();
    expect(wrapper.get('section').isVisible()).toBe(true);
  });

  it('retains the restore action across toolbar remounts and removes it when the envelope closes', async () => {
    const { wrapper, toolbar, controls } = render();
    await wrapper.get('button[aria-label="Minimize"]').trigger('click');
    controls.setTitle('Draft with a long title');
    await flushPromises();
    expect(toolbar.get('button').attributes('title')).toBe('Restore Draft with a long title');
    expect(toolbar.get('button').text()).toBe('');
    toolbar.unmount();
    const replacement = mount(EmailFormRestoreButton, { attachTo: document.body });
    wrappers.push(replacement);
    expect(replacement.get('button').attributes('aria-label')).toBe('Restore Draft with a long title');
    await replacement.get('button').trigger('click');
    expect(wrapper.get('section').isVisible()).toBe(true);
    await wrapper.get('button[aria-label="Minimize"]').trigger('click');
    wrapper.unmount();
    await flushPromises();
    expect(replacement.find('button').exists()).toBe(false);
  });

  it('blocks closing, minimizing and legacy input during a protected operation', async () => {
    const { wrapper, controls, onClose } = render();
    controls.setBlocked(true);
    await flushPromises();
    expect(wrapper.get('section').attributes('aria-busy')).toBe('true');
    expect(wrapper.get('#emailformdialog').attributes('inert')).toBeDefined();
    for (const label of ['Close', 'Minimize']) {
      const button = wrapper.get(`button[aria-label="${label}"]`);
      expect(button.attributes('disabled')).toBeDefined();
      await button.trigger('click');
    }
    expect(wrapper.get('section').isVisible()).toBe(true);
    expect(onClose).not.toHaveBeenCalled();
    controls.setBlocked(false);
    await flushPromises();
    expect(wrapper.get('#emailformdialog').attributes('inert')).toBeUndefined();
    await wrapper.get('button[aria-label="Close"]').trigger('click');
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('delegates closing to the legacy cancel/preview flow rather than removing markup', async () => {
    const { wrapper, onClose, onCleanup } = render();
    await wrapper.get('button[aria-label="Close"]').trigger('click');
    expect(onClose).toHaveBeenCalledOnce();
    expect(onCleanup).not.toHaveBeenCalled();
    expect(wrapper.get('#emailformdialog').element.isConnected).toBe(true);
    await wrapper.get('section').trigger('keydown', { key: 'Escape' });
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('keeps cleanup before DOM removal and restores focus to the opener', async () => {
    const opener = document.createElement('button');
    document.body.append(opener);
    opener.focus();
    const host = document.createElement('div');
    document.body.append(host);
    const onCleanup = vi.fn();
    subscribe(EMAIL_FORM_EVENT, (event) => {
      if (event.action === 'cleanup') { onCleanup(event.container); }
    });
    const app = createApp(EmailFormEnvelope, {
      title: 'Email',
      form: emailForm,
      single: false,
    });
    app.mount(host);
    await flushPromises();
    expect(document.activeElement).toBe(host.querySelector('h2'));
    const container = host.querySelector('#emailformdialog');
    onCleanup.mockImplementation((element: HTMLElement) => {
      expect(element).toBe(container);
      expect(element.isConnected).toBe(true);
    });
    app.unmount();
    expect(onCleanup).toHaveBeenCalledOnce();
    expect(document.activeElement).toBe(opener);
    expect(hasSubscriptions(EMAIL_FORM_COMMAND)).toBe(false);
  });

  it('shares the ui-front stack with legacy dialogs and raises an existing envelope on request', async () => {
    const dialog = document.createElement('div');
    dialog.className = 'ui-front';
    dialog.style.zIndex = '150';
    document.body.append(dialog);
    const { wrapper, controls } = render();
    await flushPromises();
    expect(wrapper.get('section').attributes('style')).toContain('z-index: 151');
    dialog.style.zIndex = '170';
    controls.show();
    await flushPromises();
    expect(wrapper.get('section').attributes('style')).toContain('z-index: 171');
  });

  it('switches persistent panels and runs activation hooks after visibility changes', async () => {
    const { wrapper } = render();
    const composer = wrapper.get('#emailformcomposer').element;
    const input = wrapper.get('input[name="emailComposer[subject]"]').element as HTMLInputElement;
    input.value = 'Unsaved message';
    const transitions: string[] = [];
    subscribe(EMAIL_FORM_EVENT, (event) => {
      if (event.action === 'before-tab-change' || event.action === 'tab-changed') {
        transitions.push(`${event.action}:${event.previous}:${event.tab}`);
        expect(wrapper.get('#emailformcomposer').isVisible()).toBe(event.action === 'tab-changed');
      }
    });
    expect(wrapper.get('#emailformrecipients').isVisible()).toBe(true);
    await wrapper.get('#emailformcomposer-tab').trigger('click');
    await flushPromises();
    expect(transitions).toEqual(['before-tab-change:recipients:composer', 'tab-changed:recipients:composer']);
    expect(wrapper.get('#emailformcomposer').element).toBe(composer);
    expect(new FormData(wrapper.get('form').element as HTMLFormElement).get('emailComposer[subject]')).toBe('Unsaved message');
    expect(input.isConnected).toBe(true);
    expect(wrapper.get('#emailformrecipients').isVisible()).toBe(false);
  });

  it('preserves legacy status markup through programmatic tab changes', async () => {
    const { wrapper } = render();
    const preview = document.createElement('div');
    preview.textContent = 'Preview with validation errors';
    wrapper.get('#emailformdebug').element.append(preview);
    await emit(EMAIL_FORM_COMMAND, { action: 'select-tab', tab: 'status' });
    expect(wrapper.get('#emailformdebug').isVisible()).toBe(true);
    await emit(EMAIL_FORM_COMMAND, { action: 'select-tab', tab: 'composer' });
    await emit(EMAIL_FORM_COMMAND, { action: 'select-tab', tab: 'status' });
    expect(wrapper.get('#emailformdebug').element.firstChild).toBe(preview);
    expect(wrapper.get('#emailformstatus-tab').attributes('aria-selected')).toBe('true');
  });

  it('starts single-recipient mode in composition and enables recipients only on request', async () => {
    const { wrapper } = render(true);
    expect(wrapper.get('#emailformcomposer').isVisible()).toBe(true);
    expect(wrapper.get('#emailformrecipients-tab').attributes('disabled')).toBeDefined();
    await emit(EMAIL_FORM_COMMAND, { action: 'select-tab', tab: 'recipients' });
    expect(wrapper.get('#emailformcomposer').isVisible()).toBe(true);
    await emit(EMAIL_FORM_COMMAND, { action: 'enable-recipients' });
    await wrapper.get('#emailformrecipients-tab').trigger('click');
    expect(wrapper.get('#emailformrecipients').isVisible()).toBe(true);
  });

  it('supports keyboard navigation, skips disabled tabs and blocks tab changes when busy', async () => {
    const { wrapper, controls } = render(true);
    await wrapper.get('#emailformcomposer-tab').trigger('keydown', { key: 'End' });
    await flushPromises();
    expect(document.activeElement?.id).toBe('emailformstatus-tab');
    await wrapper.get('#emailformstatus-tab').trigger('keydown', { key: 'Home' });
    await flushPromises();
    expect(document.activeElement?.id).toBe('emailformcomposer-tab');
    await controls.setBlocked(true);
    await wrapper.get('#emailformcomposer-tab').trigger('keydown', { key: 'ArrowRight' });
    await emit(EMAIL_FORM_COMMAND, { action: 'select-tab', tab: 'status' });
    expect(wrapper.get('#emailformcomposer').isVisible()).toBe(true);
    await controls.setBlocked(false);
    await wrapper.get('#emailformcomposer-tab').trigger('keydown', { key: 'ArrowLeft' });
    await flushPromises();
    expect(document.activeElement?.id).toBe('emailformstatus-tab');
  });

  it('opens the manual through the existing wiki bus without changing the active panel', async () => {
    const { wrapper } = render();
    const onManual = vi.fn();
    subscribe(WIKI_POPUP, onManual);
    const menu = wrapper.findAll('button').find((button) => button.text() === 'Manual')!;
    await menu.trigger('click');
    await flushPromises();
    let popup: HTMLButtonElement | undefined;
    await vi.waitFor(() => {
      popup = Array.from(document.querySelectorAll<HTMLButtonElement>('[role="menuitem"]'))
        .find((button) => button.textContent?.trim() === 'Manual (popup)');
      expect(popup).toBeDefined();
    });
    popup!.click();
    await flushPromises();
    expect(onManual).toHaveBeenCalledExactlyOnceWith({
      wikiPage: 'orchestra:cafevdb:documentation:user-manual:emailform',
      popupTitle: 'User Manual: Email Form',
    });
    expect(wrapper.get('#emailformrecipients').isVisible()).toBe(true);
  });
});
