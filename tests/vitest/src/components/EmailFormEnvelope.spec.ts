/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EmailFormEnvelopeControls } from '~/src/types/email-form-envelope.ts';

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp } from 'vue';
import EmailFormEnvelope from '~/src/components/EmailFormEnvelope.vue';

vi.mock('~/src/config.ts', () => ({
  appName: 'cafevdb',
  appNameTag: 'app-cafevdb',
  appPrefix: (value: string) => `cafevdb-${value}`,
}));

const wrappers: ReturnType<typeof mount>[] = [];
const render = () => {
  const onReady = vi.fn();
  const onClose = vi.fn();
  const onCleanup = vi.fn();
  const wrapper = mount(EmailFormEnvelope, {
    attachTo: document.body,
    props: {
      title: 'Email for project',
      contents: '<div id="emailformwrapper"><ul id="emailformtabs"></ul><form class="cafevdb-email-form">'
        + '<input name="emailComposer[subject]" value="Original">'
        + '<input name="emailRecipientsFilter[selectedRecipients][]" value="42">'
        + '<input name="requesttoken" value="csrf-token">'
        + '<div id="emailformcomposer"><textarea>Message</textarea></div></form></div>',
      onReady,
      onClose,
      onCleanup,
    },
  });
  wrappers.push(wrapper);
  const controls = wrapper.vm as typeof wrapper.vm & EmailFormEnvelopeControls;
  return { wrapper, controls, onReady, onClose, onCleanup };
};

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
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
    const input = wrapper.get('input').element as HTMLInputElement;
    input.value = 'Unsaved message';
    const replacement = document.createElement('div');
    replacement.textContent = 'Reloaded legacy panel';
    wrapper.get('#emailformcomposer').element.replaceChildren(replacement);
    controls.setTitle('Another project');
    controls.setBlocked(true);
    await flushPromises();
    expect(wrapper.get('h2').text()).toBe('Another project');
    expect(wrapper.get('input').element).toBe(input);
    expect(input.value).toBe('Unsaved message');
    expect(wrapper.get('#emailformcomposer').element.firstChild).toBe(replacement);
  });

  it('minimizes and restores without recreating the editor or cleaning up the form', async () => {
    const { wrapper, controls, onCleanup } = render();
    const container = wrapper.get('#emailformdialog').element;
    await wrapper.get('button[aria-label="Minimize"]').trigger('click');
    expect(wrapper.get('section').isVisible()).toBe(false);
    expect(wrapper.get('.emailform-envelope-restore').text()).toBe('Email for project');
    expect(onCleanup).not.toHaveBeenCalled();
    await wrapper.get('.emailform-envelope-restore').trigger('click');
    expect(wrapper.get('section').isVisible()).toBe(true);
    expect(wrapper.get('#emailformdialog').element).toBe(container);
    await wrapper.get('button[aria-label="Minimize"]').trigger('click');
    controls.show();
    await flushPromises();
    expect(wrapper.get('section').isVisible()).toBe(true);
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
    const app = createApp(EmailFormEnvelope, {
      title: 'Email',
      contents: '<form><textarea>Message</textarea></form>',
      onCleanup,
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
});
