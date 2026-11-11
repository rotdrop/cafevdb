/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PmeExportMenuComponentProps } from '~/src/types/pme-export-menu.ts';

import { flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PmeExportService } from '~/src/services/mountable-pme-export.ts';

const { cleanup, request } = vi.hoisted(() => ({
  cleanup: [] as Array<(event?: { containers: HTMLElement[] }) => void>,
  request: vi.fn(),
}));
vi.mock('@rotdrop/async-nextcloud-event-bus', () => ({
  subscribe: (_name: string, callback: (event?: { containers: HTMLElement[] }) => void) => cleanup.push(callback),
  awaitEmit: (_name: string, payload: { name: string; propsData: PmeExportMenuComponentProps }) => request(payload),
}));
vi.mock('~/src/event-bus-events.ts', () => ({
  GET_VUE_COMPONENT: 'get-component',
  LEGACY_PAGE_CLEANUP: 'cleanup',
}));
const createComponent = () => ({
  mount: vi.fn(),
  unmount: vi.fn(),
  destroy: vi.fn(),
});
request.mockImplementation(async () => createComponent());
const makeButton = () => {
  document.body.innerHTML = '<form class="pme-form"><button type="button" class="pme-export-menu-placeholder">'
    + 'Export Table</button></form>';
  return document.querySelector('button')!;
};
const component = async () => request.mock.results.at(-1)!.value;
afterEach(() => {
  cleanup.forEach((callback) => callback());
  document.body.replaceChildren();
  vi.clearAllMocks();
  request.mockImplementation(async () => createComponent());
});

describe('PmeExportService', () => {
  it('only cancels pending mounts inside the page being replaced', async () => {
    const button = makeButton();
    const page = button.form!;
    const persistentForm = document.createElement('form');
    const persistentButton = document.createElement('button');
    persistentForm.append(persistentButton);
    document.body.append(persistentForm);
    const pending = Promise.withResolvers<ReturnType<typeof createComponent>>();
    request.mockReturnValue(pending.promise);
    const pageService = PmeExportService.create(button);
    const persistentService = PmeExportService.create(persistentButton);
    cleanup.forEach((callback) => callback({ containers: [] }));
    cleanup.forEach((callback) => callback({ containers: [page] }));
    const instance = createComponent();
    pending.resolve(instance);
    await Promise.all([pageService.ready, persistentService.ready]);
    expect(instance.destroy).toHaveBeenCalledOnce();
    expect(instance.mount).toHaveBeenCalledOnce();
    expect(button.style.display).toBe('');
    expect(persistentButton.style.display).toBe('none');
    cleanup.forEach((callback) => callback({ containers: [persistentForm] }));
    expect(instance.unmount).toHaveBeenCalledOnce();
    expect(persistentButton.style.display).toBe('');
  });

  it('mounts once, hides the button and supplies its owning form', async () => {
    const button = makeButton();
    const service = PmeExportService.create(button);
    await service.ready;
    expect(PmeExportService.create(button)).toBe(service);
    expect(request).toHaveBeenCalledTimes(1);
    expect(button.style.display).toBe('none');
    expect(request).toHaveBeenCalledWith({
      name: 'PmeExportMenu',
      propsData: { form: button.form, disabled: false },
    });
    expect(new FormData(button.form!).has('export')).toBe(false);
  });

  it('supports buttons associated with an external form', async () => {
    const button = makeButton();
    button.form!.id = 'export-form';
    document.body.append(button);
    button.setAttribute('form', 'export-form');
    await PmeExportService.create(button).ready;
    expect(request.mock.calls[0]![0].propsData.form).toBe(document.getElementById('export-form'));
  });

  it('rejects placeholders with no associated form', () => {
    const button = document.createElement('button');
    expect(() => PmeExportService.create(button)).toThrow('requires an associated form');
    expect(request).not.toHaveBeenCalled();
  });

  it('preserves disabled state and restores the original display on cleanup', async () => {
    const button = makeButton();
    button.disabled = true;
    button.style.display = 'inline-flex';
    const service = PmeExportService.create(button);
    await service.ready;
    expect(request.mock.calls[0]![0].propsData.disabled).toBe(true);
    cleanup.forEach((callback) => callback());
    expect(button.style.display).toBe('inline-flex');
    expect((await component()).unmount).toHaveBeenCalledOnce();
    service.destroy();
    expect((await component()).unmount).toHaveBeenCalledOnce();
  });

  it('cleans up removed markup', async () => {
    const button = makeButton();
    await PmeExportService.create(button).ready;
    button.form!.remove();
    await flushPromises();
    expect((await component()).destroy).toHaveBeenCalledOnce();
  });

  it('restores the placeholder after failure and allows retrying', async () => {
    const button = makeButton();
    request.mockRejectedValueOnce(new Error('failed'));
    await expect(PmeExportService.create(button).ready).rejects.toThrow('failed');
    expect(button.style.display).toBe('');
    expect(document.querySelector('.pme-export-menu-control')).toBeNull();
    await PmeExportService.create(button).ready;
    expect(button.style.display).toBe('none');
  });

  it('cancels a pending mount during cleanup', async () => {
    const button = makeButton();
    let resolve!: (value: ReturnType<typeof createComponent>) => void;
    request.mockImplementationOnce(() => new Promise((done) => { resolve = done; }));
    const service = PmeExportService.create(button);
    cleanup.forEach((callback) => callback());
    const instance = createComponent();
    resolve(instance);
    await service.ready;
    expect(instance.mount).not.toHaveBeenCalled();
    expect(instance.destroy).toHaveBeenCalledOnce();
    expect(button.style.display).toBe('');
  });
});
