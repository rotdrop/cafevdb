/**
 * @license Copyright (c) 2003-2013, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.html or http://ckeditor.com/license
 */

/**Remove one leading or trailing empty paragraphs
 */
var removeEmptyPFilter = {
    isEmpty : function (element) {
        var child = element.children[0];
        if (element.name == 'p' && child && !child.children
            && (!child.value || CKEDITOR.tools.trim( child.value ).match( /^(?:&nbsp;|\xa0|<br \/>)*$/ ))) {
            return true;
        } else {
            return false;
        }
    },
    elements : {
        p : function( element ) {
            // var tailEmpty = removeEmptyPFilter.isEmpty(element);
            // for (var el = element.next; el; el = el.next) {
            //     tailEmpty = tailEmpty && removeEmptyPFilter.isEmpty(el);
            // }
            if ((!element.previous || !element.next) && removeEmptyPFilter.isEmpty(element)) {
                return false;
            }
            return element;
        }
    }
};

CKEDITOR.plugins.add( 'trimEmptyP', {
    init: function( editor )
    {
        // Give the filters lower priority makes it get applied after the default ones.
        editor.dataProcessor.htmlFilter.addRules( removeEmptyPFilter, { priority:120, applyToAll:true } );
        editor.dataProcessor.dataFilter.addRules( removeEmptyPFilter, { priority:120, applyToAll:true } );
    }
});

/**Remove <p>-tags which surround the entire mess, we don't need that, really.
 */
var removeOuterPFilter = {
    elements : {
        p : function( element ) {
            if (!element.previous && !element.next) {
                return element.replaceWithChildren();
            }
            return element;
        }
    }
};

CKEDITOR.plugins.add('removeOuterP', {
    init: function( editor )
    {
        // Give the filters lower priority makes it get applied after the default ones.
        editor.dataProcessor.htmlFilter.addRules( removeOuterPFilter, { priority:110, applyToAll:true } );
        editor.dataProcessor.dataFilter.addRules( removeOuterPFilter, { priority:110, applyToAll:true } );
    }
});

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


    //config.enterMode = CKEDITOR.ENTER_BR;

    // Remove <p> tags surrounding the entire mess.
    config.extraPlugins = 'removeOuterP,trimEmptyP';

    //	config.autoParagraph = false;
};
