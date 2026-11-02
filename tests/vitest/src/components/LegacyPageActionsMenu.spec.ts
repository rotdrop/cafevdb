/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 */

import { NcActions, NcPopover } from '@nextcloud/vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import LegacyPageActionsMenu from '~/src/components/LegacyPageActionsMenu.vue';

vi.mock('@nextcloud/vue', async () => ({
  NcActionButton: (await import('@nextcloud/vue/components/NcActionButton')).default,
  NcActionCaption: (await import('@nextcloud/vue/components/NcActionCaption')).default,
  NcActionSeparator: (await import('@nextcloud/vue/components/NcActionSeparator')).default,
  NcActions: (await import('@nextcloud/vue/components/NcActions')).default,
  NcPopover: (await import('@nextcloud/vue/components/NcPopover')).default,
}));

vi.mock('@rotdrop/async-nextcloud-event-bus', () => ({
  emit: vi.fn(),
  subscribe: vi.fn(),
}));

vi.mock('~/src/services/navigation.ts', () => ({
  closeNavigation: vi.fn(),
}));

vi.mock('~/src/util/console.ts', () => ({
  default: class {
    debug = vi.fn();
  },
}));

describe('LegacyPageActionsMenu', () => {
  const wrappers: ReturnType<typeof mount<typeof LegacyPageActionsMenu>>[] = [];

  afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  });

  const mountComponent = () => {
    const wrapper = mount(LegacyPageActionsMenu, {
      props: { entityId: 45, template: 'invoices' },
      attachTo: document.body,
    });
    wrappers.push(wrapper);
    const actions = wrapper.findComponent(NcActions);
    const popover = actions.findComponent(NcPopover);
    const originalAfterShow = popover.vm.afterShow;
    popover.vm.afterShow = vi.fn(async () => {
      await originalAfterShow.call(popover.vm);
      popover.vm.getPopoverContentElement().dispatchEvent(new Event('transitionend'));
    });
    const originalAfterHide = popover.vm.afterHide;
    popover.vm.afterHide = vi.fn(() => originalAfterHide.call(popover.vm));
    return { wrapper, actions, popover };
  };

  it('keeps the menu open when a previous hide transition finishes after reopening', async () => {
    const { wrapper, actions, popover } = mountComponent();
    void wrapper.vm.openMenu();
    await vi.waitFor(() => expect(actions.emitted('opened')).toHaveLength(1));
    await actions.vm.closeMenu();
    await vi.waitFor(() => expect(popover.vm.afterHide).toHaveBeenCalled());
    await actions.get('button').trigger('click');
    await vi.waitFor(() => expect(actions.emitted('opened')).toHaveLength(2));
    expect(wrapper.vm.isOpen()).toBe(true);
    expect(popover.emitted('afterHide')?.length).toBeGreaterThan(0);
    expect(actions.vm.opened).toBe(true);
  });

  it('does not reset the position of a menu reopened during close cleanup', async () => {
    const { wrapper, actions, popover } = mountComponent();
    void wrapper.vm.openMenu(10, 20);
    await vi.waitFor(() => expect(actions.emitted('opened')).toHaveLength(1));
    const closing = wrapper.vm.closeMenu();
    await nextTick();
    void wrapper.vm.openMenu(30, 40);
    await closing;
    const reference = popover.vm.getPopoverTriggerContainerElement();
    expect(wrapper.vm.isOpen()).toBe(true);
    expect(reference.style.position).toBe('fixed');
    expect(reference.style.left).toBe('30px');
    expect(reference.style.top).toBe('40px');
  });

  it('clears the context-menu position when the menu stays closed', async () => {
    const { wrapper, actions, popover } = mountComponent();
    void wrapper.vm.openMenu(10, 20);
    await vi.waitFor(() => expect(actions.emitted('opened')).toHaveLength(1));
    await wrapper.vm.closeMenu();
    const reference = popover.vm.getPopoverTriggerContainerElement();
    expect(wrapper.vm.isOpen()).toBe(false);
    expect(reference.style.position).toBe('');
    expect(reference.style.left).toBe('');
    expect(reference.style.top).toBe('');
  });
});
