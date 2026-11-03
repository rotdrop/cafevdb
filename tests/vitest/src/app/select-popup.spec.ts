/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import $ from '~/src/app/jquery.ts';
import { show } from '~/src/app/notification.ts';
import selectPopup from '~/src/app/select-popup.ts';
import { destroyMountedSelects, mountedSelectScope } from '~/src/services/mounted-select-scope.ts';

const mocks = vi.hoisted(() => ({ mount: vi.fn(), getValue: vi.fn() }));
vi.mock('~/src/config.ts', () => ({ appName: 'cafevdb' }));
vi.mock('~/src/app/cafevdb.ts', () => ({ toolTipsInit: vi.fn() }));
vi.mock('~/src/app/notification.ts', () => ({ show: vi.fn() }));
vi.mock('~/src/app/jquery-extensions.ts', () => ({}));
vi.mock('~/src/services/mountable-select.ts', () => ({
  getMountedSelectize: () => ({ getValue: mocks.getValue }),
}));
vi.mock('~/src/services/mounted-select-scope.ts', () => ({
  mountedSelectScope: vi.fn(() => ({ mount: mocks.mount })),
  destroyMountedSelects: vi.fn(),
}));

let options: JQueryUI.DialogOptions;
let holder: HTMLElement;
let widget: JQuery<HTMLElement>;
const originalDialog = $.fn.dialog;
const originalCafevDialog = $.fn.cafevDialog;
const originalTooltip = $.fn.cafevTooltip;

beforeEach(() => {
  mocks.mount.mockResolvedValue({});
  $.fn.extend({ cafevDialog: vi.fn(function(this: JQuery<HTMLElement>, configuration: JQueryUI.DialogOptions) {
    options = configuration;
    holder = this[0];
    widget = $('<div class="ui-dialog"><div class="ui-dialog-buttonset"><button class="ui-button save"></button></div></div>');
    widget.append(this);
    $('body').append(widget);
    options.open?.call(holder, $.Event('dialogopen'), {});
    return this;
  }) });
  $.fn.extend({ dialog: vi.fn(function(this: JQuery<HTMLElement>, command: string) {
    if (command === 'widget') {
      return widget;
    }
    if (command === 'close') {
      options.close?.call(holder, $.Event('dialogclose'), {});
    }
    return this;
  }) });
  $.fn.extend({ cafevTooltip: Object.assign(vi.fn(), { remove: vi.fn() }) });
});

afterEach(() => {
  $.fn.dialog = originalDialog;
  $.fn.cafevDialog = originalCafevDialog;
  $.fn.cafevTooltip = originalTooltip;
  document.body.replaceChildren();
  vi.clearAllMocks();
});

describe('Vue select popup', () => {
  const contents = '<select multiple><option value="a">Alpha</option><option value="b">Beta</option></select>';

  it('disables acceptance until mounting finishes and preserves ordered DTOs', async () => {
    const saveCallback = vi.fn();
    const openCallback = vi.fn();
    mocks.getValue.mockReturnValue(['b', 'a']);
    selectPopup(contents, { saveCallback, openCallback });
    expect(mountedSelectScope).toHaveBeenCalledWith(holder);
    expect(widget.find('button.save').prop('disabled')).toBe(true);
    await flushPromises();
    expect(widget.find('button.save').prop('disabled')).toBe(false);
    expect(openCallback).toHaveBeenCalledOnce();
    const buttons = options.buttons as JQueryUI.DialogButtonOptions[];
    buttons[0].click?.call(holder, $.Event('click'));
    expect(saveCallback.mock.calls[0][1]).toEqual([
      { value: 'b', html: 'Beta', text: 'Beta' },
      { value: 'a', html: 'Alpha', text: 'Alpha' },
    ]);
  });

  it('destroys pending mounts on close and does not reopen a closed popup', async () => {
    let finish!: () => void;
    mocks.mount.mockReturnValueOnce(new Promise<void>((resolve) => { finish = resolve; }));
    const openCallback = vi.fn();
    const closeCallback = vi.fn();
    selectPopup(contents, { openCallback, closeCallback });
    $(holder).dialog('close');
    expect(destroyMountedSelects).toHaveBeenCalledWith(holder);
    expect(closeCallback).toHaveBeenCalledOnce();
    finish();
    await flushPromises();
    expect(openCallback).not.toHaveBeenCalled();
    expect(holder.isConnected).toBe(false);
  });

  it('reports mounting errors and cleans up the popup', async () => {
    const error = new Error('Mount failed');
    mocks.mount.mockRejectedValueOnce(error);
    const log = vi.spyOn(console, 'error').mockImplementation(() => {});
    selectPopup(contents, {});
    await flushPromises();
    expect(log).toHaveBeenCalledWith('Unable to mount popup select controls.', error);
    expect(show).toHaveBeenCalledOnce();
    expect(destroyMountedSelects).toHaveBeenCalledWith(holder);
    expect(holder.isConnected).toBe(false);
    log.mockRestore();
  });
});
