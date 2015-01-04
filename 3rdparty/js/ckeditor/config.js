/**
 * @license Copyright (c) 2003-2013, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.html or http://ckeditor.com/license
 */

/**Remove one leading or trailing empty paragraphs
 */
var removeEmptyPFilter = {
    isEmpty : function (element) {
        if (element.name != 'p') {
            return false;
        }
        var child = element.children[0];
        if (element.children.length == 1 &&
            (!child.children || child.children.length == 0) &&
            (!child.value || CKEDITOR.tools.trim( child.value ).match( /^(?:&nbsp;|\xa0|<br \/>)*$/ ))) {
            return true;
        } else {
            return false;
        }
    },
    elements : {
        p : function( element ) {
            var selfEmpty = removeEmptyPFilter.isEmpty(element);
            var headEmpty = !element.previous;

            var tailEmpty = true;
            for (var el = element.next; el; el = el.next) {
                if (!removeEmptyPFilter.isEmpty(el)) {
                    tailEmpty = false;
                    break;
                }
            }

            if ((headEmpty || tailEmpty) && selfEmpty) {
                return false;
            }

            if (headEmpty && tailEmpty) {
                // Otherwise remove lonely <p>...</p> tag
                return element.replaceWithChildren();
            }

            // Otherwise keep it.
            return element;
        }
    }
};

CKEDITOR.plugins.add( 'trimEmptyP', {
    init: function( editor )
    {
        // Give the filters lower priority makes it get applied after the default ones.
        editor.dataProcessor.htmlFilter.addRules( removeEmptyPFilter, { priority:10000, applyToAll:false } );
        editor.dataProcessor.dataFilter.addRules( removeEmptyPFilter, { priority:10000, applyToAll:false } );
    }
});

CKEDITOR.editorConfig = function( config ) {
    // Define changes to default configuration here.
    // For the complete reference:
    // http://docs.ckeditor.com/#!/api/CKEDITOR.config

    // The toolbar groups arrangement, optimized for two toolbar rows.
    config.toolbarGroups = [
	{ name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
	{ name: 'editing',     groups: [ 'find', 'selection' ] },
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
    config.extraPlugins = 'trimEmptyP';

    //	config.autoParagraph = false;

    // smileys
    // Use textual emoticons as description.
    config.smiley_descriptions = [
        ':)', ':(', ';)', ':D', ':/', ':P', ':*)', ':-o',
        ':|', '>:(', 'o:)', '8-)', '>:-)', ';(', '', '', '',
        '', '', ':-*', ''
    ];

    config.smiley_path = '../../apps/cafevdb/3rdparty/js/ckeditor/plugins/smiley/images/';

    config.disableNativeSpellChecker = false;
};
