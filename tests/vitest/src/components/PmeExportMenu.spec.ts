/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type axiosFileDownload from '~/src/toolkit/util/axios-file-download.ts';

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PmeExportMenu from '~/src/components/PmeExportMenu.vue';

const { download, notify, emit, post, saveFile } = vi.hoisted(() => ({
  download: vi.fn(),
  notify: vi.fn(),
  emit: vi.fn(),
  post: vi.fn(),
  saveFile: vi.fn(),
}));
vi.mock('~/src/config.ts', () => ({ appName: 'cafevdb' }));
vi.mock('~/src/toolkit/util/axios-file-download.ts', () => ({ default: download }));
vi.mock('~/src/toolkit/util/toasts.ts', () => ({ showError: notify }));
vi.mock('@nextcloud/axios', () => ({ default: { post } }));
vi.mock('js-file-download', () => ({ default: saveFile }));
vi.mock('@rotdrop/async-nextcloud-event-bus', () => ({ emit }));
vi.mock('~/src/event-bus-events.ts', () => ({ PUSH_BUSY_STATE: 'push-busy', POP_BUSY_STATE: 'pop-busy' }));

const wrappers: ReturnType<typeof mount>[] = [];
const render = (disabled = false) => {
  const form = document.createElement('form');
  form.innerHTML = '<input name="template" value="project-participants">'
    + '<input name="filter[]" value="active"><input name="filter[]" value="confirmed">'
    + '<input name="ignored" value="disabled" disabled>'
    + '<input name="unchecked" type="checkbox" value="no">'
    + '<input name="checked" type="checkbox" value="yes" checked>';
  document.body.append(form);
  const wrapper = mount(PmeExportMenu, {
    attachTo: form,
    props: { form, disabled },
  });
  wrappers.push(wrapper);
  return { wrapper, form };
};
const activate = async (wrapper: ReturnType<typeof render>['wrapper'], name = 'CSV Export') => {
  await wrapper.get('button').trigger('click');
  await flushPromises();
  await vi.waitFor(() => expect(document.querySelectorAll('[role="menuitem"]')).toHaveLength(2));
  const button = Array.from(document.querySelectorAll<HTMLButtonElement>('[role="menuitem"]'))
    .find((button) => button.textContent?.includes(name))!;
  button.click();
  await flushPromises();
};

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  document.body.replaceChildren();
  vi.clearAllMocks();
});

describe('PmeExportMenu', () => {
  it('uses a named action-menu trigger, not a combobox or submit button', () => {
    const { wrapper } = render();
    expect(wrapper.get('button').text()).toContain('Export Table');
    expect(wrapper.find('[role="combobox"]').exists()).toBe(false);
    expect(wrapper.get('button').attributes('type')).toBe('button');
  });

  it('downloads from the current owning form, preserving repeated names and successful controls', async () => {
    const { wrapper, form } = render();
    const otherForm = document.createElement('form');
    otherForm.innerHTML = '<input name="other" value="unrelated">';
    document.body.append(otherForm);
    for (let index = 0; index < 2; ++index) {
      form.querySelector<HTMLInputElement>('[name="template"]')!.value = `template-${index}`;
      await activate(wrapper);
      expect(download.mock.calls[index]![0]).toBe('page/pme/export');
      const post: FormData = download.mock.calls[index]![1];
      expect(post.get('template')).toBe(`template-${index}`);
      expect(post.getAll('filter[]')).toEqual(['active', 'confirmed']);
      expect(post.get('checked')).toBe('yes');
      expect(post.has('ignored')).toBe(false);
      expect(post.has('unchecked')).toBe(false);
      expect(post.has('other')).toBe(false);
      expect(post.get('exportFormat')).toBe('CSV');
      expect(post.has('export')).toBe(false);
      await vi.waitFor(() => expect(document.querySelector('[role="menu"]')).toBeNull());
    }
    expect(download).toHaveBeenCalledTimes(2);
    expect(emit.mock.calls).toEqual([['push-busy'], ['pop-busy'], ['push-busy'], ['pop-busy']]);
    expect(wrapper.get('button').text()).toContain('Export Table');
  });

  it('offers Excel export directly', async () => {
    const { wrapper } = render();
    await activate(wrapper, 'Excel Export');
    expect(download.mock.calls[0]![1].get('exportFormat')).toBe('EXCEL');
  });

  it('uses the modern download helper to POST form data and save the named response blob', async () => {
    const { default: actualDownload } = await vi.importActual<{ default: typeof axiosFileDownload }>(
      '~/src/toolkit/util/axios-file-download.ts',
    );
    download.mockImplementationOnce(actualDownload);
    const blob = new Blob(['spreadsheet'], { type: 'application/vnd.ms-excel' });
    post.mockResolvedValueOnce({
      data: blob,
      headers: {
        'content-disposition': 'attachment; filename="participants.xlsx"',
        'content-type': 'application/vnd.ms-excel',
      },
    });
    const { wrapper } = render();
    await activate(wrapper, 'Excel Export');
    expect(post).toHaveBeenCalledWith(
      expect.stringContaining('/apps/cafevdb/page/pme/export'),
      expect.any(FormData),
      { responseType: 'blob' },
    );
    expect(post.mock.calls[0]![1].get('exportFormat')).toBe('EXCEL');
    expect(saveFile).toHaveBeenCalledWith(blob, 'participants.xlsx', 'application/vnd.ms-excel');
  });

  it('disables the menu during downloads and restores it afterwards', async () => {
    let resolve!: () => void;
    download.mockImplementationOnce(() => new Promise<void>((done) => { resolve = done; }));
    const { wrapper } = render();
    await activate(wrapper);
    expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    expect(emit).toHaveBeenCalledWith('push-busy');
    expect(emit).not.toHaveBeenCalledWith('pop-busy');
    resolve();
    await flushPromises();
    expect(wrapper.get('button').attributes('disabled')).toBeUndefined();
    expect(emit).toHaveBeenCalledWith('pop-busy');
  });

  it('reports failed downloads and balances the busy state', async () => {
    const error = new Error('failed');
    download.mockRejectedValueOnce(error);
    const log = vi.spyOn(console, 'error').mockImplementation(() => {});
    const { wrapper } = render();
    await activate(wrapper);
    expect(notify).toHaveBeenCalledWith('Unable to download table in format "CSV".');
    expect(log).toHaveBeenCalledWith('Unable to export table', { format: 'CSV', error });
    expect(wrapper.get('button').attributes('disabled')).toBeUndefined();
    expect(emit.mock.calls).toEqual([['push-busy'], ['pop-busy']]);
    log.mockRestore();
  });

  it('respects disabled state', () => {
    const { wrapper } = render(true);
    expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    expect(download).not.toHaveBeenCalled();
  });
});
