/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterAll, afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import $ from '~/src/app/jquery.ts';

const { getEditor, init } = vi.hoisted(() => ({
  getEditor: vi.fn(),
  init: vi.fn().mockResolvedValue([]),
}));
vi.mock('tinymce', () => ({
  default: { get: getEditor, init },
}));
vi.mock('@nextcloud/l10n', () => ({ getLanguage: () => 'en' }));
vi.mock('@nextcloud/router', () => ({ getAppRootUrl: () => '/apps/cafevdb' }));
vi.mock('~/src/config.ts', () => ({ appName: 'cafevdb' }));
vi.mock('~/build/ts-types/php-modules/Controller/CssClasses.ts', () => ({
  RESIZE_TARGET: 'resize-target',
  WYSIWYG_EDITOR: 'wysiwyg-editor',
}));

const originalTextareaHook = $.valHooks.textarea;
const previousGet = vi.fn();
const previousSet = vi.fn();
$.valHooks.textarea = { get: previousGet, set: previousSet };
await import('~/src/app/tinymceinit.ts');

beforeEach(() => {
  vi.clearAllMocks();
  getEditor.mockReset();
  previousGet.mockReset();
  previousSet.mockReset();
  document.body.innerHTML = '<form>'
    + '<textarea id="editor" name="message">Stale content</textarea>'
    + '<textarea name="plain">Plain content</textarea>'
    + '<textarea id="disabled" name="disabled" disabled>Disabled content</textarea>'
    + '<input name="subject" value="Subject">'
    + '<select name="choice"><option selected>Choice</option></select>'
    + '</form>';
});

afterEach(() => {
  document.body.replaceChildren();
});

describe('TinyMCE textarea value hook', () => {
  const attachEditor = (content = '<p>Current content</p>') => {
    const element = document.querySelector<HTMLTextAreaElement>('#editor')!;
    const editor = {
      initialized: true,
      getElement: () => element,
      getContent: vi.fn(() => content),
    };
    getEditor.mockImplementation((id: string) => id === element.id ? editor : null);
    return { element, editor };
  };

  it('reads current editor content without changing the underlying textarea', () => {
    const { element, editor } = attachEditor();
    expect($(element).val()).toBe('<p>Current content</p>');
    editor.getContent.mockReturnValue('<p>Further edits</p>');
    expect($(element).val()).toBe('<p>Further edits</p>');
    expect(element.value).toBe('Stale content');
    expect(previousGet).not.toHaveBeenCalled();
  });

  it('serializes current editor content along with ordinary form controls', () => {
    attachEditor('<p>Current & edited</p>\n<p>Second line</p>');
    const values = [
      { name: 'message', value: '<p>Current & edited</p>\r\n<p>Second line</p>' },
      { name: 'plain', value: 'Plain content' },
      { name: 'subject', value: 'Subject' },
      { name: 'choice', value: 'Choice' },
    ];
    expect($('form').serializeArray()).toEqual(values);
    expect($('form').serialize()).toBe($.param(values));
  });

  it('preserves empty editor content', () => {
    attachEditor('');
    expect($('#editor').val()).toBe('');
  });

  it('preserves getters for plain textareas, other controls, and empty selections', () => {
    expect($('#editor').val()).toBe('Stale content');
    expect($('textarea[name="plain"]').val()).toBe('Plain content');
    expect($('input').val()).toBe('Subject');
    expect($('select').val()).toBe('Choice');
    expect($('#missing').val()).toBeUndefined();
  });

  it('falls back to the original hook while an editor is initializing', () => {
    const { editor } = attachEditor();
    editor.initialized = false;
    expect($('#editor').val()).toBe('Stale content');
    expect(editor.getContent).not.toHaveBeenCalled();
  });

  it('falls back to the original hook when an editor has been removed', () => {
    attachEditor();
    getEditor.mockReturnValue(null);
    expect($('#editor').val()).toBe('Stale content');
  });

  it('does not use an editor attached to a different element with the same id', () => {
    const { element, editor } = attachEditor();
    element.replaceWith(element.cloneNode(true));
    expect($('#editor').val()).toBe('Stale content');
    expect(editor.getContent).not.toHaveBeenCalled();
  });

  it('preserves an existing textarea getter hook', () => {
    previousGet.mockReturnValue('Previous hook content');
    expect($('#editor').val()).toBe('Previous hook content');
    expect(previousGet).toHaveBeenCalledWith(document.querySelector('#editor'));
  });

  it('leaves value setters and existing setter hooks unchanged', () => {
    const { element, editor } = attachEditor();
    const $element = $(element);
    expect($element.val('Replacement')).toBe($element);
    expect(previousSet).toHaveBeenCalledWith(element, 'Replacement', 'value');
    expect(element.value).toBe('Replacement');
    expect(editor.getContent).not.toHaveBeenCalled();
    $element.val((_index, value) => value + ' appended');
    expect(element.value).toBe('<p>Current content</p> appended');
    expect($('textarea[name="plain"]').val('Updated').val()).toBe('Updated');
  });
});

afterAll(() => {
  if (originalTextareaHook) {
    $.valHooks.textarea = originalTextareaHook;
  } else {
    delete $.valHooks.textarea;
  }
});
