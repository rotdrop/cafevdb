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
         "save table contextmenu directionality emoticons template paste textcolor"
   ],
   //content_css: "css/content.css",
   toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media fullpage | forecolor backcolor emoticons | code", 
   style_formats: [
        {title: 'Bold text', inline: 'b'},
        {title: 'Red text', inline: 'span', styles: {color: '#ff0000'}},
        {title: 'Red header', block: 'h1', styles: {color: '#ff0000'}},
        {title: 'Example 1', inline: 'span', classes: 'example1'},
        {title: 'Example 2', inline: 'span', classes: 'example2'},
        {title: 'Table styles'},
        {title: 'Table row 1', selector: 'tr', classes: 'tablerow1'}
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

