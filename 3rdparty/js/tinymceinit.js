var myTinyMCE = {
  config: {
    script_url : OC.filePath('cafevdb', '3rdparty/js', 'tinymce/jscripts/tiny_mce/tiny_mce.js'),
    forced_root_block : false,
    // General options
    theme : "advanced",
    language : 'de',
    plugins : "legacyoutput,autolink,lists,spellchecker,pagebreak,style,layer,table,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",

    // Theme options
    theme_advanced_buttons1 : "newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,styleselect,formatselect,fontselect,fontsizeselect",
    theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
    theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
    theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,spellchecker,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,blockquote,pagebreak,|,insertfile,insertimage",
    theme_advanced_toolbar_location : "top",
    theme_advanced_toolbar_align : "left",
    theme_advanced_statusbar_location : "bottom",
    theme_advanced_resizing : true,

    formats : {
      inline : {block : 'div', classes : 'inline', remove : 'all', styles : {display : 'inline'}},
    },
    //theme_advanced_blockformats : "blah=inline,p=p", //,address,h1,h2,h3,h4,h5,h6,blockquote,div,pre,dt,dd,code,samp",
    theme_advanced_blockformats : "Inline=inline,p=p,address=address,h1=h1,h2=h2,h3=h3,h4=h4,h5=h5,h6=h6,blockquote=blockquote,div=div,pre=pre,dt=dt,dd=dd,code=code,samp=samp",

    // Skin options
    skin : "o2k7",
    skin_variant : "silver",

    // Example content CSS (should be your site CSS)
    content_css : OC.filePath('cafevdb', '3rdparty/css', 'tinymce.css'),

    // Style formats
    style_formats : [
      {title : 'Bold text', inline : 'b'},
      {title : 'Red text', inline : 'span', styles : {color : '#ff0000'}},
      {title : 'Red header', block : 'h1', styles : {color : '#ff0000'}},
      // {title : 'Example 1', inline : 'span', classes : 'example1'},
      // {title : 'Example 2', inline : 'span', classes : 'example2'},
      {title : 'Table styles'},
      {title : 'Table row 1', selector : 'tr', classes : 'tablerow1'}
    ],

    // Drop lists for link/image/media/template dialogs
    template_external_list_url : "js/template_list.js",
    external_link_list_url : "js/link_list.js",
    external_image_list_url : "js/image_list.js",
    media_external_list_url : "js/media_list.js",

    // Replace values for the template plugin
    template_replace_values : {
      username : "Some User",
      staffid : "991234"
    }
  },
  init: function() {
    var allconfig = myTinyMCE.config;
    allconfig.editor_deselector = "mceNoEditor";
    allconfig.mode = "textareas";
    tinyMCE.init(allconfig);
  }
};

$(document).ready(function() {

  myTinyMCE.init();
});


// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

