/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import pmeExportMenu from '~/src/app/pme-export.ts';

const { mount, notify } = vi.hoisted(() => ({
  mount: vi.fn(),
  notify: vi.fn(),
}));
vi.mock('~/src/config.ts', () => ({ appName: 'cafevdb', appPrefix: (value: string) => `cafevdb-${value}` }));
vi.mock('~/src/app/notification.ts', () => ({ show: notify }));
vi.mock('~/src/services/mountable-pme-export.ts', () => ({
  PmeExportService: { create: mount },
}));
afterEach(() => {
  document.body.replaceChildren();
  vi.clearAllMocks();
});

describe('PME export mounting', () => {
  const setup = () => {
    document.body.innerHTML = '<div id="cafevdb-page-body"><form class="pme-form">'
      + '<button type="button" class="pme-export-menu-placeholder">Export Table</button>'
      + '</form></div>';
    mount.mockReturnValue({ ready: Promise.resolve() });
    return document.querySelector('button')!;
  };

  it('mounts the placeholder without installing select or change handlers', () => {
    const button = setup();
    pmeExportMenu();
    expect(mount).toHaveBeenCalledWith(button);
    expect(document.querySelector('select')).toBeNull();
  });

  it('limits mounting to the requested container', () => {
    setup();
    pmeExportMenu('#missing');
    expect(mount).not.toHaveBeenCalled();
  });

  it('reports asynchronous mounting errors and preserves the placeholder', async () => {
    const button = setup();
    mount.mockReturnValueOnce({ ready: Promise.reject(new Error('failed')) });
    const log = vi.spyOn(console, 'error').mockImplementation(() => {});
    pmeExportMenu();
    await flushPromises();
    expect(notify).toHaveBeenCalledOnce();
    expect(button.textContent).toBe('Export Table');
    expect(button.style.display).toBe('');
    log.mockRestore();
  });

  it('reports synchronous initialization errors', async () => {
    setup();
    mount.mockImplementationOnce(() => { throw new Error('no form'); });
    const log = vi.spyOn(console, 'error').mockImplementation(() => {});
    pmeExportMenu();
    await flushPromises();
    expect(notify).toHaveBeenCalledOnce();
    log.mockRestore();
  });
});
