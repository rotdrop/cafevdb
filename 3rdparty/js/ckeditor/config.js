/**
 * @license Copyright (c) 2003-2013, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.html or http://ckeditor.com/license
 */
// var removeEmptyPFilter = {
//   elements : {
//     p : function( element ) {
//       var child = element.children[0];
//         if (!(element.previous && element.next) && child && !child.children
//             && (!child.value || CKEDITOR.tools.trim( child.value ).match( /^(?:&nbsp;|\xa0|<br \/>)$/ ))) {
//           return false;
//         }
//       return element;
//     }
//   }
// };

// CKEDITOR.plugins.add( 'removeEmptyP',
// {
//   init: function( editor )
//   {
//      // Give the filters lower priority makes it get applied after the default ones.
//      editor.dataProcessor.htmlFilter.addRules( removeEmptyPFilter, 100 );
//      editor.dataProcessor.dataFilter.addRules( removeEmptyPFilter, 100 );
//   }
// });

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here.
	// For the complete reference:
	// http://docs.ckeditor.com/#!/api/CKEDITOR.config

	// The toolbar groups arrangement, optimized for two toolbar rows.
	config.toolbarGroups = [
		{ name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
		{ name: 'editing',     groups: [ 'find', 'selection', 'spellchecker' ] },
		{ name: 'links' },
		{ name: 'insert' },
		{ name: 'forms' },
		{ name: 'tools' },
		{ name: 'document',	   groups: [ 'mode', 'document', 'doctools' ] },
		{ name: 'others' },
		'/',
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
		{ name: 'styles' },
		{ name: 'colors' },
		{ name: 'about' }
	];

	// Remove some buttons, provided by the standard plugins, which we don't
	// need to have in the Standard(s) toolbar.
	config.removeButtons = 'Underline,Subscript,Superscript';

	// Se the most common block elements.
	config.format_tags = 'p;h1;h2;h3;pre';

	// Make dialogs simpler.
	config.removeDialogTabs = 'image:advanced;link:advanced';


//	config.enterMode = CKEDITOR.ENTER_BR;
//	config.extraPlugins = 'removeEmptyP';

//	config.autoParagraph = false;
};
