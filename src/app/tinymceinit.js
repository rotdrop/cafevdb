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

/* eslint camelcase: ["error", {properties: "never"}] */

import { globalState, appName, nonce, $ } from './globals.js';

require('3rdparty/tinymce/tinymce.min');
require('3rdparty/tinymce/JqueryIntegration');

// console.info('CAFEVDB MCE INIT: ', $.fn.jquery);
// console.info('MCE: ', window.tinyMCE, window.tinymce);

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
  language: 'en',
  //    width: 300,
  //    height: 100,
  //    forced_root_block : '',
  //    force_br_newlines : false,
  //    force_p_newlines : true,
  browser_spellcheck: true,
  gecko_spellcheck: true,
  file_picker_types: 'file image media',
  // relative_urls: false,
  // convert_urls: false,
  relative_urls: true,
  suffix: '.min',
  base_url: OC.appswebroots[appName] + '/3rdparty/tinymce',
  // document_base_url: 'https://fritz.claus-justus-heine.info:8888/owncloud8/index.php/apps/cafevdb/',

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
    const mceContainer = inst.getContainer();
    console.debug(mceContainer);
    const ambientContainer = $(mceContainer).closest('.resize-target, .ui-dialog-content');
    mceWindow.oldWidth = [-1, -1];
    mceWindow.oldHeight = [-1, -1];
    mceWindow.onresize = function(e) {
      const win = this;
      if (!win.resizeTimeout) {
        const width = (win.innerWidth > 0) ? win.innerWidth : win.width;
        const height = (win.innerHeight > 0) ? win.innerHeight : win.height;
        if ((win.oldWidth[0] !== width && win.oldWidth[1] !== width)
            || (win.oldHeight[0] !== height && win.oldHeight[1] !== height)) {
          console.log('tinymce size change', width, win.oldWidth, height, win.oldHeight);
          win.resizeTimeout = setTimeout(
            function() {
              win.resizeTimeout = null;
              ambientContainer.trigger('resize');
            }, 50);
          win.oldWidth[1] = win.oldWidth[0];
          win.oldHeight[1] = win.oldHeight[0];
          win.oldWidth[0] = width;
          win.oldHeight[0] = height;
        }
      }
    };
    console.debug('trigger tinymce-done');
    $('#' + inst.id).trigger('cafevdb:tinymce-done');
  },

  // spellchecker_rpc_url: OC.filePath('cafevdb', '3rdparty/js/tinymce/plugins/spellchecker', 'rpc.php'),
  plugins: [
    'advlist autolink link image lists charmap print preview hr anchor pagebreak', // spellchecker
    'searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking',
    'save table directionality template paste textcolor emoticons', // emoticons smileys contextmenu
  ],
  // content_css: 'css/content.css',
  toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media fullscreen | forecolor backcolor emoticons | code', // emoticons
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
  const nonceConfig = {
    nonce,
  };
  // const width = (window.innerWidth > 0) ? window.innerWidth : screen.width;
  // if (width <= 768) { // perhaps mobile
  //   return $.extend({}, myConfig, mySmallConfig, plusConfig, { width });
  // } else {
  // //   return $.extend(nonceConfig, myConfig, plusConfig);
  // // }
  return $.extend(nonceConfig, myConfig, plusConfig);
};

const myInit = function(lang) {
  myConfig.language = lang;
  const allconfig = myGetConfig({
    selector: 'textarea.wysiwyg-editor',
    nonce,
  });
  // console.info('Try init tinymce');
  // console.info('tinymce: ', window.tinymce);
  window.tinymce.init(allconfig);
};

$(function() {
  myInit(globalState.language);
  console.info('MCE nonce', nonce);
});

export {
  myPostProcessCallback as postProcessCallback,
  myConfig as config,
  mySmallConfig as smallConfig,
  myInit as init,
  myGetConfig as getConfig,
};

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
