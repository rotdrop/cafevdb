/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/* eslint camelcase: ["error", {properties: "never"}] */

import { globalState, appName, webRoot, $ } from './globals.js';
import 'tinymce';
import '@tinymce/tinymce-jquery';

// console.info('CAFEVDB MCE INIT: ', $.fn.jquery);
// console.info('MCE: ', console, window.tinyMCE, window.tinymce);

const myPostProcessCallback = function(e) {
  e.content = e.content.replace(/((&nbsp;|[\n\r\s])*<p>(&nbsp;|[\n\r\s])*<\/p>(&nbsp;|[\n\r\s])*)+$/g, '');
  e.content = e.content.replace(/^((&nbsp;|[\n\r\s])*<p>(&nbsp;|[\n\r\s])*<\/p>(&nbsp;|[\n\r\s])*)+/g, '');
  e.content = e.content.replace(/^<p>(((?!<p>)[\s\S])*)<\/p>$/g, '$1');
};

const myConfig = {
  // auto_focus: 'mce_0',
  // theme_advanced_resizing: true,
  // theme_advanced_resizing_use_cookie : false,
  theme: 'silver',
  language: globalState.language || 'en',
  //    width: 300,
  //    height: 100,
  //    forced_root_block : '',
  //    force_br_newlines : false,
  //    force_p_newlines : true,
  browser_spellcheck: true,
  // gecko_spellcheck: true,
  file_picker_types: 'file image media',
  // convert_urls: false,
  relative_urls: true,
  base_url: webRoot + '3rdparty/tinymce',
  // document_base_url: OC.appswebroots[appName] + '/3rdparty/tinymce',
  suffix: '.min',
  promotion: false,

  setup(editor) {
    console.debug('tinyMCE::setup()');
    // editor.on('PostProcess', myPostProcessCallback);
    // editor.on('PostRender', function(e) { console.info('Event tinyMCE::PostRender()'); });
    // editor.on('init', function(e) { console.info('Event tinyMCE::init()'); });
    // editor.on('LoadContent', function(e) { console.info('Event tinyMCE::LoadContent()'); });
    // editor.on('PreInit', function(e) { console.info('Event tinyMCE::PreInit()'); });
    // editor.on('init', function(event) {
    //   alert('editor is shown');
    // });
  },
  init_instance_callback(inst) {

    console.debug('tinyMCE::init_instance_callback(), id is ' + inst.id);

    // Propagate the resize event to the enclosing div in order to
    // be able to resize dialog windows. As this potentially yields
    // an infinite recursion -- the resize of the enclosing
    // container will again fire a new resize event to the MCE
    // instance -- we try to be clever and only forward if the size
    // actually has changed.
    const mceWindow = inst.getWin();
    const $mceContainer = $(inst.getContainer());
    console.debug($mceContainer);
    const $ambientContainer = $mceContainer.closest('.resize-target, .ui-dialog-content');
    console.info('TINY AMBIENT CONTAINER', $ambientContainer);
    mceWindow.globalState = {
      oldWidth: [-1, -1],
      oldHeight: [-1, -1],
    };
    mceWindow.addEventListener('resize', (event) => {
      if (mceWindow.innerHeight === 0) {
        console.trace('MCE WINDOW RESIZE HEIGHT 0');
      }
      const myGlobalState = mceWindow.globalState;
      if (!myGlobalState.resizeTimeout) {
        const width = mceWindow.innerWidth;
        const height = mceWindow.innerHeight;
        if ((myGlobalState.oldWidth[0] !== width && myGlobalState.oldWidth[1] !== width)
            || (myGlobalState.oldHeight[0] !== height && myGlobalState.oldHeight[1] !== height)) {
          console.debug('tinymce size change', width, myGlobalState.oldWidth, height, myGlobalState.oldHeight);
          myGlobalState.resizeTimeout = setTimeout(
            function() {
              myGlobalState.resizeTimeout = null;
              $ambientContainer.trigger('resize.' + appName);
            }, 50);
          myGlobalState.oldWidth[1] = myGlobalState.oldWidth[0];
          myGlobalState.oldHeight[1] = myGlobalState.oldHeight[0];
          myGlobalState.oldWidth[0] = width;
          myGlobalState.oldHeight[0] = height;
        }
      }
    });

    inst.on('blur', function(event) {
      // FIXME: how the heck get the element the editor is attached to????
      $mceContainer.prev().trigger('blur');
    });

    console.debug('Resolve mceDeferred');
    const mceElement = $('#' + inst.id);
    mceElement.data('mceDeferred').resolve(inst.id);
    mceElement.removeData('mceDeferred');
  },

  // spellchecker_rpc_url: OC.filePath(appName, '3rdparty/js/tinymce/plugins/spellchecker', 'rpc.php'),
  plugins: [
    'advlist',
    'anchor',
    'autolink',
    'charmap',
    'code',
    'directionality',
    'emoticons',
    'fullscreen',
    'image',
    'insertdatetime',
    'link',
    'lists',
    'media',
    'nonbreaking',
    'pagebreak',
    'preview',
    'save',
    'searchreplace',
    'table',
    'visualblocks',
    'visualchars',
    'wordcount',
    // 'hr',
    // 'paste',
    // 'print',
    // 'spellchecker',
    // 'template',
    // 'textcolor',
    // emoticons smileys contextmenu
  ],
  // content_css: 'css/content.css',
  toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | pastetext | link image | print preview media fullscreen | forecolor backcolor emoticons | code', // emoticons
  style_formats_merge: true,
  style_formats: [
    {
      title: 'Image Left',
      selector: 'img',
      styles: {
        float: 'left',
        margin: '0 10px 0 10px',
      },
    },
    {
      title: 'Image Right',
      selector: 'img',
      styles: {
        float: 'right',
        margin: '0 0 10px 10px',
      },
    },
  ],
  // style_formats: [
  //   {title: 'Bold text', inline: 'b'},
  //   {title: 'Red text', inline: 'span', styles: {color: '#ff0000'}},
  //   {title: 'Red header', block: 'h1', styles: {color: '#ff0000'}},
  //   //{title: 'Example 1', inline: 'span', classes: 'example1'},
  //   //{title: 'Example 2', inline: 'span', classes: 'example2'},
  //   {title: 'Table styles'},
  //   {title: 'Table row 1', selector: 'tr', classes: 'tablerow1'}
  // ],
};

const mySmallConfig = {
  toolbar: 'fullscreen | undo redo | bold italic | bullist indent outdent',
  menubar: false,
  statusbar: false,
  init_instance_callback(editor) {
    myConfig.init_instance_callback(editor);
    editor.on('focus', function(event) {
      editor.execCommand('mceFullScreen');
    });
    editor.on('FullscreenStateChanged', function(event) {
      if (!event.state) {
        $('input#focusstealer').focus().blur();
      } else {
        editor.focus(true);
      }
    });
  },
};

const myGetConfig = function(plusConfig) {
  if (typeof plusConfig === 'undefined') {
    plusConfig = {};
  }
  return { ...myConfig, ...plusConfig };
};

const myInit = function(lang) {
  myConfig.language = lang;
  const allconfig = myGetConfig({
    selector: 'textarea.wysiwyg-editor',
  });
  // console.info('Try init tinymce');
  // console.info('tinymce: ', window.tinymce);
  window.tinymce.init(allconfig);
};

$(function() {
  myInit(globalState.language);
});

export {
  myPostProcessCallback as postProcessCallback,
  myConfig as config,
  mySmallConfig as smallConfig,
  myInit as init,
  myGetConfig as getConfig,
};
