var tinyMCEUrl = OC.filePath('cafevdb', '3rdparty/js/tinymce', '');
var tinyMCESmileyUrl = tinyMCEUrl + '/plugins/emoticons/img/';
var myTinyMCE = myTinyMCE || {};

(function(window, $, myTinyMCE, undefined) {
  myTinyMCE.postProcessCallback = function(e) {
    e.content = e.content.replace(/((&nbsp;|[\n\r\s])*<p>(&nbsp;|[\n\r\s])*<\/p>(&nbsp;|[\n\r\s])*)+$/g, '');
    e.content = e.content.replace(/^((&nbsp;|[\n\r\s])*<p>(&nbsp;|[\n\r\s])*<\/p>(&nbsp;|[\n\r\s])*)+/g, '');
    e.content = e.content.replace(/^<p>(((?!<p>)[\s\S])*)<\/p>$/g, '$1');
  };
  myTinyMCE.config = {
    //auto_focus: 'mce_0',
    //theme_advanced_resizing: true,
    //theme_advanced_resizing_use_cookie : false,
    theme: "silver",
    language: 'en',
    //    width: 300,
    //    height: 100,
    //    forced_root_block : "",
    //    force_br_newlines : false,
    //    force_p_newlines : true,
    browser_spellcheck: true,
    gecko_spellcheck: true,
    file_picker_types: 'file image media',
    //relative_urls: false,
    //convert_urls: false,
    relative_urls: true,
    //document_base_url: 'https://fritz.claus-justus-heine.info:8888/owncloud8/index.php/apps/cafevdb/',

    setup: function(editor) {
      editor.on('PostProcess', myTinyMCE.postProcessCallback);
      // editor.on('init', function(event) {
      //   alert('editor is shown');
      // });
    },
    init_instance_callback: function(inst) {

      // Propagate the resize event to the enclosing div in order to
      // be able to resize dialog windows. As this potentially yields
      // an infinite recursion -- the resize of the enclosing
      // container will again fire a new resize event to the MCE
      // instance -- we try to be clever and only forward if the size
      // actually has changed.
      var mceWindow = inst.getWin();
      var mceContainer = inst.getContainer();
      var ambientContainer = $(mceContainer).closest('.resize-target, .ui-dialog-content');
      mceWindow.oldWidth = [ -1, -1 ];
      mceWindow.oldHeight = [ -1, -1 ];
      mceWindow.onresize = function(e) {
        var win = this;
        if (!win.resizeTimeout) {
          var width = (win.innerWidth > 0) ? win.innerWidth : win.width;
          var height = (win.innerHeight > 0) ? win.innerHeight : win.height;
          if ((win.oldWidth[0] != width && win.oldWidth[1] != width) ||
              (win.oldHeight[0] != height && win.oldHeight[1] != height)) {
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
    },

//    spellchecker_rpc_url: OC.filePath('cafevdb', '3rdparty/js/tinymce/plugins/spellchecker', 'rpc.php'),
    plugins: [
      "advlist autolink link image lists charmap print preview hr anchor pagebreak",  // spellchecker
      "searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
      "save table directionality template paste textcolor emoticons" // emoticons smileys contextmenu
    ],
    //content_css: "css/content.css",
    toolbar: "undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media fullscreen | forecolor backcolor emoticons | code", // emoticons
    style_formats_merge: true,
    style_formats: [
    {
        title: 'Image Left',
        selector: 'img',
        styles: {
            'float': 'left',
            'margin': '0 10px 0 10px'
        }
     },
     {
         title: 'Image Right',
         selector: 'img',
         styles: {
             'float': 'right',
             'margin': '0 0 10px 10px'
         }
     }
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
    smileys: [
      [
        { shortcut: 'B-)', url: tinyMCESmileyUrl + 'smiley-cool.gif', title: 'cool' },
        { shortcut: ':,(', url: tinyMCESmileyUrl + 'smiley-cry.gif', title: 'cry' },
        { shortcut: ':-[', url: tinyMCESmileyUrl + 'smiley-embarassed.gif', title: 'embarassed' },
        { shortcut: ':-!', url: tinyMCESmileyUrl + 'smiley-foot-in-mouth.gif', title: 'foot-in-mouth' },
      ],
      [
        { shortcut: ':-(', url: tinyMCESmileyUrl + 'smiley-frown.gif', title: 'frown' },
        { shortcut: '0:)', url: tinyMCESmileyUrl + 'smiley-innocent.gif', title: 'innocent' },
        { shortcut: ':-*', url: tinyMCESmileyUrl + 'smiley-kiss.gif', title: 'kiss' },
        { shortcut: ':-D', url: tinyMCESmileyUrl + 'smiley-laughing.gif', title: 'laughing' },
      ],
      [
        { shortcut: ':-$', url: tinyMCESmileyUrl + 'smiley-money-mouth.gif', title: 'money' },
        { shortcut: ':-#', url: tinyMCESmileyUrl + 'smiley-sealed.gif', title: 'sealed' },
        { shortcut: ':-)', url: tinyMCESmileyUrl + 'smiley-smile.gif', title: 'smile' },
        { shortcut: ':-O', url: tinyMCESmileyUrl + 'smiley-surprised.gif', title: 'surprised' },
      ],
      [
        { shortcut: ':-P', url: tinyMCESmileyUrl + 'smiley-tongue-out.gif', title: 'tongue' },
        { shortcut: ':-\\', url: tinyMCESmileyUrl + 'smiley-undecided.gif', title: 'undecided' },
        { shortcut: ';-)', url: tinyMCESmileyUrl + 'smiley-wink.gif', title: 'wink' },
        { shortcut: '>:O', url: tinyMCESmileyUrl + 'smiley-yell.gif', title: 'yell' },
      ]
    ]
  };
  myTinyMCE.smallConfig = {
    toolbar: "fullscreen | undo redo | bold italic | bullist indent outdent",
    menubar: false,
    statusbar: false,
    init_instance_callback: function(editor) {
      myTinyMCE.config.init_instance_callback(editor);
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
    }
  };
  myTinyMCE.getConfig = function(plusConfig) {
    if (typeof plusConfig === 'undefined') {
      plusConfig = {};
    }
    const nonceConfig = {
      nonce: btoa(OC.requestToken)
    };
    var width = (window.innerWidth > 0) ? window.innerWidth : screen.width;
    if (width <= 768) { // perhaps mobile
      return $.extend({}, myTinyMCE.config, myTinyMCE.smallConfig, plusConfig, { width: width });
    } else {
      return $.extend(nonceConfig, myTinyMCE.config, plusConfig);
    }
  };
  myTinyMCE.init = function(lang) {
    myTinyMCE.config.language = lang;
    var allconfig = myTinyMCE.getConfig({
      selector: "textarea.wysiwygeditor",
      nonce: btoa(OC.requestToken)
    });
    tinyMCE.init(allconfig);
  }
})(window, jQuery, myTinyMCE);

$(document).ready(function() {
  myTinyMCE.init(CAFEVDB.language);
});


// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
