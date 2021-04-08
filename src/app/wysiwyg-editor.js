/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

import { $ } from './globals.js';
import { globalState } from './cafevdb.js';

/**
 * Add a WYSIWYG editor to the element specified by @a selector.
 *
 * @param {string} selector TBD.
 *
 * @param {Function} initCallback TBD.
 *
 * @param {int} initialHeight TBD.
 *
 */
const addEditor = function(selector, initCallback, initialHeight) {
  console.debug('WysiwygEditor.addEditor');
  const editorElement = $(selector);
  if (!editorElement.length) {
    if (typeof initCallback === 'function') {
      initCallback();
    }
    return;
  }
  switch (globalState.wysiwygEditor) {
  default:
  case 'ckeditor':
    if (typeof initCallback !== 'function') {
      initCallback = function() {};
    }
    console.debug('attach ckeditor');
    import('@ckeditor/ckeditor5-build-classic')
      .then(({ default: ClassicEditor }) => {
        editorElement.each(function(index) {
          ClassicEditor
            .create(this)
            .then(editor => {
              console.debug('ckeditor promise');
              editorElement.data('ckeditor', editor);
              initCallback();
            })
            .catch(error => {
              console.debug('There was a problem initializing the editor.', error);
              initCallback();
            });
        });
      })
      .catch(error => {
        console.error('There was a problem initializing the editor.', error);
        initCallback();
      });
    break;
  case 'tinymce': {
    // This is a Gurkerei
    $(document).on('focusin', function(e) {
      // e.stopImmediatePropagaion();
      // alert(CAFEVDB.print_r(e.target, true));
      if ($(e.target).closest('.mce-container').length) {
        e.stopImmediatePropagation();
      }
    });
    const plusConfig = {};
    if (!editorElement.is('textarea')) {
      plusConfig.inline = true;
    }
    if (typeof initialHeight !== 'undefined') {
      plusConfig.height = initialHeight;
    }
    const mceDeferred = $.Deferred();
    mceDeferred.then(
      function() {
        console.info('MCE promise succeeded');
        if (typeof initCallback === 'function') {
          initCallback();
        }
      },
      function() {
        console.error('MCE promise failed');
        if (typeof initCallback === 'function') {
          initCallback();
        }
        editorElement.css('visibility', '');
      }
    );
    import('./tinymceinit')
      .then((myTinyMCE) => {
        const mceConfig = myTinyMCE.getConfig(plusConfig);
        editorElement
          .off('cafevdb:tinymce-done')
          .on('cafevdb:tinymce-done', function(event) {
            console.info('tinyMCE init done callback');
            mceDeferred.resolve();
          });
        console.debug('attach tinymce');
        editorElement.tinymce(mceConfig);
      });
    // wait for at most 5 seconds, then cancel
    const timeout = 10;
    setTimeout(function() {
      mceDeferred.reject();
    }, timeout * 1000);
    break;
  }
  }
};

/**
 * Remove a WYSIWYG editor from the element specified by @a selector.
 *
 * @param {String} selector TBD.
 */
const removeEditor = function(selector) {
  const editorElement = $(selector);
  if (!editorElement.length) {
    return;
  }
  try {
    editorElement.ckeditor().remove();
  } catch (e) {
    // console.info('EXCEPTION', e);
  }
  try {
    editorElement.tinymce().remove();
  } catch (e) {
    // console.info('EXCEPTION', e);
  }
};

/**
 * Replace the contents of the given editor by contents.
 *
 * @param {String} selector TBD.
 *
 * @param {String} contents TBD.
 */
const updateEditor = function(selector, contents) {
  const editorElement = $(selector);
  let editor;
  if (!editorElement.length) {
    return;
  }
  switch (globalState.wysiwygEditor) {
  case 'ckeditor':
    if (editorElement.ckeditor) {
      editor = editorElement.ckeditor().ckeditorGet();
      editor.setData(contents);
      // ckeditor snapshots itself on update.
      // editor.undoManager.save(true);
    }
    break;
  case 'tinymce':
    editorElement.tinymce().setContent(contents);
    editorElement.tinymce().undoManager.add();
    break;
  default:
    if (editorElement.ckeditor) {
      editor = editorElement.ckeditor().ckeditorGet();
      editor.setData(contents);
      // ckeditor snapshots itself on update.
      // editor.undoManager.save(true);
    }
    break;
  }
};

/**
 * Generate a "snapshot", meaning an undo-level, for instance after
 * replacing all data by loading email templates and stuff.
 *
 * @param {String} selector TBD.
 */
const snapshotEditor = function(selector) {
  const editorElement = $(selector);
  let editor;
  if (!editorElement.length) {
    return;
  }
  switch (globalState.wysiwygEditor) {
  case 'ckeditor':
    if (editorElement.ckeditor) {
      editor = editorElement.ckeditor().ckeditorGet();
      editor.undoManager.save(true);
    }
    break;
  case 'tinymce':
    editorElement.tinymce().undoManager.add();
    break;
  default:
    if (editorElement.ckeditor) {
      editor = editorElement.ckeditor().ckeditorGet();
      editor.undoManager.save(true);
    }
    break;
  }
};

export {
  globalState,
  addEditor,
  removeEditor,
  updateEditor,
  snapshotEditor,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
