/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import $ from './jquery.js';
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
  const $editorElements = $(selector);
  initCallback = (typeof initCallback === 'function') ? initCallback : () => {};
  if (!$editorElements.length) {
    initCallback();
    return;
  }
  switch (globalState.wysiwygEditor) {
  case 'ckeditor':
    console.debug('attach ckeditor');
    import('@ckeditor/ckeditor5-build-classic')
      .then(({ default: ClassicEditor }) => {
        // this is a Gurkerei becauser jQuery is missing allSettled
        $.when
          .apply(
            $,
            $editorElements.map(function(index, editorElement) {
              return ClassicEditor
                .create(editorElement)
                .catch(error => {
                  console.error('There was a problem initializing the editor.', error);
                  return $.Deferred().resolveWith(this, arguments);
                });
            }).get()
          )
          .then(() => {
            console.debug('ckeditor promise(s) settled.');
            initCallback();
          });
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
    if (!$editorElements.is('textarea')) {
      plusConfig.inline = true;
    }
    if (typeof initialHeight !== 'undefined') {
      plusConfig.height = initialHeight;
    }
    // wait for at most 10 seconds, then cancel
    const mceDeferredTimeout = 10 * 1000;
    import('./tinymceinit')
      .then((myTinyMCE) => {
        const mceConfig = myTinyMCE.getConfig(plusConfig);
        $.when
          .apply(
            $,
            $editorElements.map(function(index, editorElement) {
              const $editorElement = $(editorElement);
              const mceDeferred = $.Deferred();
              $editorElement.data('mceDeferred', mceDeferred);
              $editorElement.tinymce(mceConfig);
              const mceDeferredTimer = setTimeout(function() { console.info('MCE Deferred Timeout'); mceDeferred.reject(); }, mceDeferredTimeout);
              return mceDeferred.then(
                id => {
                  clearTimeout(mceDeferredTimer);
                  console.debug('MCE deferred resolved for id ' + id);
                },
                error => {
                  console.error('There was a problem initializing the editor.', error);
                  try {
                    $editorElement.tinymce().remove();
                  } catch (e) {
                    console.error('EXCEPTION', e);
                  }
                  return $.Deferred().resolveWith(this, arguments);
                });
            }).get()
          )
          .then(() => {
            console.debug('tinyMCE promise(s) settled.');
            initCallback();
          });
      });
    break;
  }
  default:
    console.error('UNSUPPORTED WYSIWYG EDITOR', globalState);
    break;
  }
};

/**
 * Remove a WYSIWYG editor from the element specified by @a selector.
 *
 * @param {String} selector TBD.
 */
const removeEditor = function(selector) {
  const $editorElements = $(selector);
  if (!$editorElements.length) {
    return;
  }
  try {
    $editorElements.ckeditor().remove();
  } catch (e) {
    // console.info('EXCEPTION', e);
  }
  try {
    $editorElements.tinymce().remove();
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
  const $editorElements = $(selector);
  let editor;
  if (!$editorElements.length) {
    return;
  }
  switch (globalState.wysiwygEditor) {
  case 'ckeditor':
    if ($editorElements.ckeditor) {
      editor = $editorElements.ckeditor().ckeditorGet();
      editor.setData(contents);
      // ckeditor snapshots itself on update.
      // editor.undoManager.save(true);
    }
    break;
  case 'tinymce':
    $editorElements.tinymce().setContent(contents);
    $editorElements.tinymce().undoManager.add();
    break;
  default:
    if ($editorElements.ckeditor) {
      editor = $editorElements.ckeditor().ckeditorGet();
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
  const $editorElements = $(selector);
  let editor;
  if (!$editorElements.length) {
    return;
  }
  switch (globalState.wysiwygEditor) {
  case 'ckeditor':
    if ($editorElements.ckeditor) {
      editor = $editorElements.ckeditor().ckeditorGet();
      editor.undoManager.save(true);
    }
    break;
  case 'tinymce':
    $editorElements.tinymce().undoManager.add();
    break;
  default:
    if ($editorElements.ckeditor) {
      editor = $editorElements.ckeditor().ckeditorGet();
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
