var tinyMCEUrl = OC.filePath('cafevdb', '3rdparty/js/tinymce', '');
var tinyMCESmileyUrl = tinyMCEUrl + '/plugins/emoticons/img/';
var myTinyMCE = myTinyMCE || {
  config: {
    theme: "modern",
    language: 'en',
//    width: 300,
//    height: 300,
//    forced_root_block : "",
//    force_br_newlines : false,
//    force_p_newlines : true,
    browser_spellcheck: true,
    setup: function(editor) {
      editor.on('PostProcess', function(e) {
        e.content = e.content.replace(/((&nbsp;|[\n\r\s])*<p>(&nbsp;|[\n\r\s])*<\/p>(&nbsp;|[\n\r\s])*)+$/g, '');
        e.content = e.content.replace(/^((&nbsp;|[\n\r\s])*<p>(&nbsp;|[\n\r\s])*<\/p>(&nbsp;|[\n\r\s])*)+/g, '');
        e.content = e.content.replace(/^<p>(((?!<p>)[\s\S])*)<\/p>$/g, '$1');
      });
    },
    spellchecker_rpc_url: OC.filePath('cafevdb', '3rdparty/js/tinymce/plugins/spellchecker', 'rpc.php'),
    plugins: [
         "advlist autolink link image lists charmap print preview hr anchor pagebreak",  // spellchecker
         "searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
      "save table contextmenu directionality template paste textcolor smileys" // emoticons smileys
   ],
   //content_css: "css/content.css",
   toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media fullpage | forecolor backcolor smileys | code", // emoticons
   style_formats: [
        {title: 'Bold text', inline: 'b'},
        {title: 'Red text', inline: 'span', styles: {color: '#ff0000'}},
        {title: 'Red header', block: 'h1', styles: {color: '#ff0000'}},
        {title: 'Example 1', inline: 'span', classes: 'example1'},
        {title: 'Example 2', inline: 'span', classes: 'example2'},
        {title: 'Table styles'},
        {title: 'Table row 1', selector: 'tr', classes: 'tablerow1'}
    ],
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
  },
  init: function(lang) {
    myTinyMCE.config.language = lang;
    var allconfig = myTinyMCE.config;
    allconfig.selector = "textarea.tinymce";
    tinyMCE.init(allconfig);
  }
};

$(document).ready(function() {
  myTinyMCE.init(CAFEVDB.language);
});


// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

