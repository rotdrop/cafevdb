/**Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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

var PHPMYEDIT = PHPMYEDIT || {};

(function(window, $, PHPMYEDIT, undefined) {
  'use strict';

  PHPMYEDIT.filterSelectPlaceholder = 'Select a filter Option';
  PHPMYEDIT.filterSelectNoResult    = 'No values match';
  PHPMYEDIT.selectChosen            =  true;
  PHPMYEDIT.filterSelectChosenTitle = 'Select from the pull-down menu. Double-click will submit the form.';
  PHPMYEDIT.inputSelectPlaceholder  = 'Select an option';
  PHPMYEDIT.inputSelectNoResult     = 'No values match';
  PHPMYEDIT.inputSelectChosenTitle  = 'Select from the pull-down menu.';
  PHPMYEDIT.chosenPixelWidth        = [];

  PHPMYEDIT.defaultSelector         = '#cafevdb-page-body';
  PHPMYEDIT.dialogCSSId             = 'pme-table-dialog';
  PHPMYEDIT.tableLoadCallbacks      = [];

  PHPMYEDIT.popupPosition           = { my: "middle top",
                                        at: "middle bottom+50px",
                                        of: "#header" };

  /**Genereate the default selector. */
  PHPMYEDIT.selector = function(selector) {
    if (typeof selector === 'undefined') {
      selector = this.defaultSelector;
    }
    return selector;
  };

  /**Generate the jQuery object corresponding to the ambient
   * element. If the given argument is already a jQuery object, then
   * just return the argument.
   */
  PHPMYEDIT.container = function(selector) {
    var container;
    selector = this.selector(selector);
    if (selector instanceof jQuery) {
      container = selector;
    } else {
      container = $(selector);
    }
    return container;
  }

  PHPMYEDIT.addTableLoadCallback = function(dpyClass, cbObject) {
    if (typeof cbObject.context === 'undefined') {
      cbObject.context = this;
    }
    if (typeof cbObject.parameters === 'undefined') {
      cbObject.parameters = [];
    }
    if (typeof cbObject.parameters !== 'object') {
      cbObject.parameters = [ cbObject.parameters ];
    }
    this.tableLoadCallbacks[dpyClass] = cbObject;
  };

  PHPMYEDIT.tableLoadCallback = function(dpyClass, selector, resizeReadyCB) {
    var cbHandle;

    if (typeof this.tableLoadCallbacks[dpyClass] !== 'undefined') {
      cbHandle = this.tableLoadCallbacks[dpyClass];
    } else {
      alert("No Callback for "+dpyClass);
      return false;
    }

    if (typeof selector == 'undefined') {
      selector = PHPMYEDIT.defaultSelector;
    }
    if (typeof resizeReadyCB != 'function') {
      resizeReadyCB = function() {};
    }

    var callback = cbHandle.callback;
    var context  = cbHandle.context;
    var args     = $.merge([], cbHandle.parameters);
    args.push(selector);
    args.push(resizeReadyCB);
    
    return callback.apply(context, args);
  };

  /**Submit the base form in order to synchronize any changes caused
   * by the dialog form.
   */
  PHPMYEDIT.submitOuterForm = function(selector) {
    var outerSel = this.selector(selector);

    // try a reload while saving data. This is in order to resolve
    // inter-table dependencies like changed instrument lists and so
    // on.
    var outerForm = $(outerSel + ' form.pme-form');

    var button = button = $(outerForm).find('input[name$="morechange"],'+
                                            'input[name$="applyadd"],'+
                                            'input[name$="applycopy"]');
    if (button.length > 0) {
      button = button.first(); // don't trigger up _and_ down buttons.
      button.trigger('click');
    } else {
      // submit the outer form
      //outerForm.submit();
      this.pseudoSubmit(outerForm, $(), outerSel, 'pme');
    }
  }

  /**Overload the phpMyEdit submit buttons in order to be able to
   * display the single data-set display, edit, add and copy form in a
   * popup.
   *
   * @param[in] options Object with additional params to the
   * pme-table.php AJAX callback. Must at least contain the
   * DisplayClass component.
   *
   * @param[in] callback Additional form validation callback. If
   * callback also attaches handlers to the save, change etc. buttons
   * then these should be attached as delegate event handlers to the
   * pme-form. The event handlers installed by this functions are
   * installed as delegate handlers at the #pme-table-dialog div.
   *
   * @bug This function is by far too long.
   *
   * @param options Some stuff like the PHP content provider class, see
   * tableDialog().
   *
   * @param callback Function to be called in order to style the
   * dynamically loaded content.
   */
  PHPMYEDIT.tableDialogHandlers = function(options, callback) {
    var pme = this;

    if (typeof callback === 'undefined') {
      callback = function() { return false; };
    }
    
    var self = this;
    var containerSel = '#'+self.dialogCSSId;
    var container = $(containerSel);
    var contentsChanged = false;

    container.off('click', '**');

    // The easy one, but for changed contents
    var cancelButton = $(container).find('input.pme-cancel');
    cancelButton.off('click');
    cancelButton.on('click', function(event) {
      event.preventDefault();

      container.dialog('close');

      return false;
    });
    
    // The complicated ones. This reload new data.
    var CAMButtonSel = 'input.pme-change,input.pme-apply,input.pme-more';
    var changeMoreApplyButton = $(container).find(CAMButtonSel);
    
    // remove non-delegate handlers and stop default actions in any case.
    changeMoreApplyButton.off('click');
    changeMoreApplyButton.click(function(event) {
      event.preventDefault();
      return true; // allow bubble up
    });

    // install a delegate handler on the outer-most container which
    // finally will run after possible inner data-validation handlers
    // have been executed.
    container.off('click', CAMButtonSel);
    container.on(
      'click',
      CAMButtonSel,
      function(event) {
        if (!event.isDefaultPrevented()) {
          event.preventDefault(); // neurotic me
        }

        var post = $(container).find('form.pme-form').serialize();
        post += '&' + $.param(options);
        if (changeMoreApplyButton.length > 0) {
          var name  = changeMoreApplyButton.attr('name');
          var value = changeMoreApplyButton.val();
          post += '&' + name + "=" + value;
        }
        $.post(OC.filePath('cafevdb', 'ajax/pme', 'pme-table.php'),
               post,
               function (data) {
                 // error handling?
                 if (data.status == 'success') {
                   // remove the WYSIWYG editor, if any is attached
                   CAFEVDB.removeEditor(container.find('textarea.wysiwygeditor'));

                   container.css('height', 'auto');
                   container.html(data.data.contents);

                   // general styling
                   pme.init('pme', containerSel);

                   // attach the WYSIWYG editor, if any
                   // editors may cause additional resizing
                   CAFEVDB.addEditor(container.find('textarea.wysiwygeditor'), function() {
                     container.dialog('option', 'height', 'auto');
                     container.dialog('option', 'position', pme.popupPosition);

                     // re-attach events
                     options.modified = !changeMoreApplyButton.hasClass('pme-change');
                     self.tableDialogHandlers(options, callback);
                   });
                 }
               });
        return false;
      });

    /**************************************************************************
     *
     * In "edit" mode submit the "more" action and reload the
     * surrounding form. When not in edit mode the base form must be the same
     * as the overlay form and a simple form submit should suffice, in principle.
     * For "more add" we will have to adjust the logic
     *
     */
    var saveButtonSel = 'input.pme-save';
    var saveButton = $(container).find(saveButtonSel);
    saveButton.off('click');
    saveButton.click(function(event) {
      event.preventDefault();
      return true; // allow bubble up
    });

    container.off('click', saveButtonSel);
    container.on(
      'click',
      saveButtonSel,
      function(event) {
                   
        if (!event.isDefaultPrevented()) {
          event.preventDefault(); // neurotic me
        }

        var applySelector =
          'input[name$="morechange"],'+
          'input[name$="applyadd"],'+
          'input[name$="applycopy"]';
        var deleteSelector = 'input[name$="savedelete"]';

        var post = $(container).find('form.pme-form').serialize();
        post += '&' + $.param(options);
        var name, value;

        var deleteButton = container.find(deleteSelector);
        if (deleteButton.length > 0) {
          name  = deleteButton.attr('name');
          value = deleteButton.val();
        } else {
          var applyButton = container.find(applySelector);
          if (applyButton.length > 0) {
            name  = applyButton.attr('name');
            value = applyButton.val();
          }
        }
        var obj = {};
        obj[name] = value;
        post += '&' + $.param(obj);

        //alert(post);

        $.post(OC.filePath('cafevdb', 'ajax/pme', 'pme-table.php'),
             post,
             function (data) {
               // error handling?
               if (data.status == 'success') {
                 container.dialog('close');
                 
                 self.submitOuterForm();
                 
                 return false;
               }
             });
      return false;
    });

    // Finally do the styling ...
    callback();

  };

  /**Post the contents of a pme-form via AJAX into a dialog
   * widget. Useful for editing, viewing etc. because this avoids the
   * need to reload the base table (when only viewing single
   * data-sets).
   *
   * @param form The form to take the informatoin from, including the
   * name of the PHP class which generates the response.
   *
   * @param containerSel A selector for the container which holds the
   * form response.
   */
  PHPMYEDIT.tableDialog = function(form, element) {
    var pme  = this;
    
    var post = form.serialize();
    var dpyClass = form.find('input[name="DisplayClass"]');

    if (dpyClass.length == 0) {
      // This just does not work.
      return false;
    }
    dpyClass = dpyClass.val();

    post += '&DisplayClass='+dpyClass;
    if (element.attr('name')) { // undefined == false
      var name  = element.attr('name');
      var value = element.val();
      var obj = {};
      obj[name] = value;
      post += '&' + $.param(obj);
    }

    $.post(OC.filePath('cafevdb', 'ajax/pme', 'pme-table.php'),
           post,
           function (data) {
             var containerSel = '#'+pme.dialogCSSId;
             var dialogHolder;
             if (data.status == 'success') {
               dialogHolder = $('<div id="'+pme.dialogCSSId+'"></div>');
               dialogHolder.html(data.data.contents);
                $('body').append(dialogHolder);
               dialogHolder = $(containerSel);
             } else {
               var info = '';
               if (typeof data.data.message != 'undefined') {
	         info = data.data.message;
               } else {
	         info = t('cafevdb', 'Unknown error :(');
               }
               if (typeof data.data.error != 'undefined' && data.data.error == 'exception') {
	         info += '<p><pre>'+data.data.exception+'</pre>';
	         info += '<p><pre>'+data.data.trace+'</pre>';
               }
               OC.dialogs.alert(info, t('cafevdb', 'Error'));
               if (data.data.debug != '') {
                 OC.dialogs.alert(data.data.debug, t('cafevdb', 'Debug Information'), null, true);
               }
               return false;
             }
             var tableOptions = {
               DisplayClass: dpyClass,
               ClassArguments: [], // TODO
               modified: false // avoid reload of base table unless necessary
             };

             var popup = dialogHolder.dialog({
               title: dialogHolder.find('span.pme-short-title').html(),
               position: pme.popupPosition,
               width: 'auto',
               height: 'auto',
               modal: true,
               closeOnEscape: false,
               dialogClass: 'pme-table-dialog',
               resizable: false,
               open: function() {

                 var dialogHolder = $(this);
                 var dialogWidget = dialogHolder.dialog('widget');

                 // general styling
                 pme.init('pme', containerSel);

                 pme.tableDialogHandlers(
                   tableOptions,
                   function() {

                     dialogHolder.css('height', 'auto');
                     CAFEVDB.addEditor(dialogHolder.find('textarea.wysiwygeditor'), function() {
                       pme.transposeReady(containerSel);
                       pme.tableLoadCallback(dpyClass, containerSel, function() {
                         dialogHolder.dialog('option', 'height', 'auto');
                         dialogHolder.dialog('option', 'width', 'auto');
                         var newHeight = dialogWidget.height()
                           - dialogWidget.find('.ui-dialog-titlebar').outerHeight();
                         newHeight -= dialogHolder.outerHeight() - dialogHolder.height();
                         //alert("Setting height to " + newHeight);
                         dialogHolder.height(newHeight);
                       });
                       CAFEVDB.tipsy(containerSel);
                     });
                   });
               },
               close: function() {
                 $('.tipsy').remove();
                 var dialogHolder = $(this);

                 if (tableOptions.modified === true) {
                   pme.submitOuterForm();
                 }

                 dialogHolder.dialog('close');
                 dialogHolder.dialog('destroy').remove();
               },
             });
           });
    return true;
  };

  /**Quasi-submit the pme-form.
   *
   * @param[in] form The jQuery object corresponding to the pme-form.
   * 
   * @param[in] element The jQuery object corresponding to the element
   * causing the submit.
   *
   * @param[in] selector The CSS selector corresponding to the
   * surrounding container (div element)
   * 
   * @param[in] pmepfx CSS prefix for the PME form elements
   */
  PHPMYEDIT.pseudoSubmit = function(form, element, selector, pmepfx) {
    var self = this;

    if (typeof pmepfx == 'undefined') {
      pmepfx = 'pme';
    }

    var selector = this.selector(selector);
    var container = this.container(selector);

    var dpyClass = form.find('input[name="DisplayClass"]');
    if (dpyClass.length <= 0 || element.hasClass('formsubmit')) {
      form.off('submit');
      if (element.attr('name')) { // undefined == false
        form.append('<input type="hidden" '+
                    'name="'+element.attr('name')+'" '+
                    'value="'+element.val()+'"/>');
      }
      return form.submit();
    }
    dpyClass = dpyClass.val();
    // TODO: arguments
    var post = form.serialize();
    post += '&DisplayClass='+dpyClass;
    if (element.attr('name')) { // undefined == false
      var name  = element.attr('name');
      var value = element.val();
      var obj = {};
      obj[name] = value;
      post += '&' + $.param(obj);
    }
    $.post(OC.filePath('cafevdb', 'ajax/pme', 'pme-table.php'),
           post,
           function (data) {
             if (data.status == 'success') {
               $('.tipsy').remove();
               CAFEVDB.removeEditor(container.find('textarea.wysiwygeditor'));
               container.html(data.data.contents);
               self.init(pmepfx, selector);
               CAFEVDB.addEditor(container.find('textarea.wysiwygeditor'), function() {
                 self.transposeReady(selector);
                 self.tableLoadCallback(dpyClass, selector, function() {});
                 CAFEVDB.tipsy(selector);
               });
             } else {
               var info = '';
               if (typeof data.data.message != 'undefined') {
	         info = data.data.message;
               } else {
	         info = t('cafevdb', 'Unknown error :(');
               }
               if (typeof data.data.error != 'undefined' && data.data.error == 'exception') {
	         info += '<p><pre>'+data.data.exception+'</pre>';
	         info += '<p><pre>'+data.data.trace+'</pre>';
               }
               OC.dialogs.alert(info, t('cafevdb', 'Error'));
               if (data.data.debug != '') {
                 OC.dialogs.alert(data.data.debug, t('cafevdb', 'Debug Information'), null, true);
               }
               return false;               
             }
             return false;
           });
    return false;
  };

  /**Trigger either one of the upper or the lower button controls (but
   * not both!)
   */
  PHPMYEDIT.triggerSubmit = function(buttonName, containerSel) {
    var container = this.container(containerSel);    
    var button = container.find('input[name="PME_sys_'+buttonName+'"]').first();

    if (button.length > 0) {
      button.trigger('click');
      return true;
    } else {
      return false;
    }
  };

  /**Transpose the main tabel if desired. */
  PHPMYEDIT.transposeMainTable = function(selector, containerSel) {
    var container = this.container(containerSel);
    var table = container.find(selector);

    var headerRow = table.find('thead tr');
    headerRow.detach();
    if (headerRow.length > 0) {
      headerRow.prependTo( table.find('tbody') );
    }
    var t = table.find('tbody').eq(0);
    var sortinfo  = t.find('tr.pme-sortinfo');
    var queryinfo = t.find('tr.pme-queryinfo');
    // These are huge cells spanning the entire table, move them on
    // top of the transposed table afterwards.
    sortinfo.detach();
    queryinfo.detach();
    var r = t.find('tr');
    var cols= r.length;
    var rows= r.eq(0).find('td,th').length;
    var cell, next, tem, i = 0;
    var tb= $('<tbody></tbody>');

    while(i<rows){
      cell= 0;
      tem= $('<tr></tr>');
      while(cell<cols){
	next= r.eq(cell++).find('td,th').eq(0);
	tem.append(next);
      }
      tb.append(tem);
      ++i;
    }
    table.find('tbody').remove();
    $(tb).appendTo(table);
    if (table.find('thead').length > 0) {
      $(table)
	.find('tbody tr:eq(0)')
	.detach()
	.appendTo( table.find('thead') )
	.children()
	.each(function(){
          var tdclass = $(this).attr('class');
          if (tdclass.length > 0) {
            tdclass = ' class="'+tdclass+'"';
          } else {
            tdclass = "";
          }
          $(this).replaceWith('<th'+tdclass+' scope="col">'+$(this).html()+'</th>');
	});
    }
    queryinfo.prependTo(table.find('tbody'));
    sortinfo.prependTo(table.find('tbody'));

    if (true) {
      $(table)
        .find('tbody tr th:first-child')
        .each(function(){
          var thclass = $(this).attr('class');
          if (thclass.length > 0) {
            thclass = ' class="'+thclass+'"';
          } else {
            thclass = "";
          }
	  $(this).replaceWith('<td'+thclass+' scope="row">'+$(this).html()+'</td>');
        });
    }
    table.show();
  };

  /**Transpose the main table based on boolean value of transpose. */
  PHPMYEDIT.maybeTranspose = function(transpose, containerSel) {
    var container = this.container(containerSel);
    var pageitems;
    var tipsy = container.find('.tipsy');
    var doTipsy = tipsy.length > 0;

    if (transpose) {
      tipsy.remove();
      this.transposeMainTable('table.pme-main', container);
      pageitems = t('cafevdb', '#columns');

      container.find('input[name="Transpose"]').val('transposed');
      container.find('#pme-transpose-up').removeClass('pme-untransposed').addClass('pme-transposed');
      container.find('#pme-transpose-down').removeClass('pme-untransposed').addClass('pme-transposed');
      container.find('#pme-transpose').removeClass('pme-untransposed').addClass('pme-transposed');
    } else {
      tipsy.remove();
      this.transposeMainTable('table.pme-main', container);
      pageitems = t('cafevdb', '#rows');

      container.find('input[name="Transpose"]').val('untransposed');
      container.find('#pme-transpose-up').removeClass('pme-transposed').addClass('pme-untransposed');
      container.find('#pme-transpose-down').removeClass('pme-transposed').addClass('pme-untransposed');
      container.find('#pme-transpose').removeClass('pme-transposed').addClass('pme-untransposed');
    }
    container.find('input.pme-pagerows').val(pageitems);
  };
  PHPMYEDIT.transposeReady = function(containerSel)  {
    var container = PHPMYEDIT.container(containerSel);

    // Transpose or not: if there is a transpose button
    var inhibitTranspose = container.find('input[name="InhibitTranspose"]').val() == 'true';
    var controlTranspose = (container.find('input[name="Transpose"]').val() == 'transposed' ||
                            container.find('#pme-transpose-up').hasClass('pme-transposed') ||
                            container.find('#pme-transpose-down').hasClass('pme-transposed') ||
                            container.find('#pme-transpose').hasClass('pme-transposed'));

    //alert('Inhibit: '+inhibitTranspose+' control: '+controlTranspose);

    if (!inhibitTranspose && controlTranspose) {
      this.maybeTranspose(true);
    } else {
      // Initially the tabel _is_ untransposed
      //CAFEVDB.PME.maybeTranspose(false); // needed?
    }
  };


  PHPMYEDIT.init = function(pmepfx, containerSel) {
    var self = this;

    if (typeof pmepfx === 'undefined') {
      pmepfx = 'pme';
    }
    
    var containerSel = this.selector(containerSel);
    var container = this.container(containerSel);
    var form = container.find('form.pme-form');

    //alert(containerSel+" "+container.length);

    var onChangeSel =
      'input[type="checkbox"].'+pmepfx+'-sort'+','+
      'select.'+pmepfx+'-goto'+','+
      'select.'+pmepfx+'-pagerows';
    if (!this.selectChosen) {
      onChangeSel += ','+'select[class^="'+pmepfx+'-filter"]';
    }
    container.off('change', onChangeSel);
    container.on('change', onChangeSel, function(event) {
      event.preventDefault();
      return PHPMYEDIT.pseudoSubmit($(this.form), $(this), containerSel, pmepfx);
    });      

    if (form.find('input[name="DisplayClass"]').length > 0) {
      var submitSel = 'form.'+pmepfx+'-form input[class$="navigation"]:submit'+','+
        'form.'+pmepfx+'-form input.pme-add:submit';
      container.off('click', submitSel);
      container.on('click', submitSel, function(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        PHPMYEDIT.tableDialog($(this.form), $(this));

        return false;
      });
    }

    var submitSel = 'form.'+pmepfx+'-form :submit';
    container.off('click', submitSel);
    container.on('click', submitSel, function(event) {
      event.preventDefault();

      //alert("Button: "+$(this).attr('name'));

      return PHPMYEDIT.pseudoSubmit($(this.form), $(this), containerSel, pmepfx);
    });

    if (this.selectChosen) {
      var gotoSelect = container.find("select."+pmepfx+"-goto");
      gotoSelect.chosen({width:"auto", disable_search_threshold: 10});
      if (gotoSelect.is(':disabled')) {
        // there is only one page
        gotoSelect.attr('data-placeholder', '1');
      } else {
        gotoSelect.attr('data-placeholder', ' ');
      }
      container.find("select."+pmepfx+"-goto").trigger('chosen:updated');

      container.find("select."+pmepfx+"-pagerows").chosen({width:"auto", disable_search_threshold: 10});      
    }

    var keyPressSel = 'input[class^="'+pmepfx+'-filter"]';
    container.off('keypress', keyPressSel);
    container.on('keypress', keyPressSel, function(event) {
      var pressed_key;
      if (event.which) {
        pressed_key = event.which;
      } else {
        pressed_key = event.keyCode;
      }
      if (pressed_key == 13) { // enter pressed
        event.preventDefault();
        return PHPMYEDIT.pseudoSubmit($(this.form), $(this), containerSel, pmepfx);
      }
      return true; // other key pressed
    });

    if (this.selectChosen) {
      var noRes = this.filterSelectNoResult;

      container.find("select[class^='"+pmepfx+"-comp-filter']").chosen({width:"auto", disable_search_threshold: 10});

      // Provide a data-placeholder and also remove the match-all
      // filter, which is not needed when using chosen.
      container.find("select[class^='"+pmepfx+"-filter']").attr("data-placeholder", this.filterSelectPlaceholder);
      container.off('change', 'select[class^="'+pmepfx+'-filter"]');
      container.find("select[class^='"+pmepfx+"-filter'] option[value='*']").remove();

      // Play a dirty trick in order not to pass width:auto to chosen
      // for some particalar thingies
      var k;
      for (k = 0; k < PHPMYEDIT.chosenPixelWidth.length; ++k) {
        var tag = PHPMYEDIT.chosenPixelWidth[k];
        var pxlWidth = Math.round(container.find("td[class^='"+pmepfx+"-filter-"+tag+"']").width());
        container.find("select[class^='"+pmepfx+"-filter-"+tag+"']").chosen({width:pxlWidth+60+'px',
                                                                             no_results_text:noRes});
      }
        
      // Then the general stuff
      container.find("select[class^='"+pmepfx+"-filter']").chosen({width:'100%',
                                                                   no_results_text:noRes});

      var dblClickSel =
        'td[class^="'+pmepfx+'-filter"] ul.chosen-choices li.search-field input[type="text"]'+','+
        'td[class^="'+pmepfx+'-filter"] div.chosen-container';
      container.off('dblclick', dblClickSel);
      container.on('dblclick', dblClickSel, function(event) {
        event.preventDefault();
        // There doesn't seem to be a "this" for dblclick, though
        // searching the web did not reveal similar problems. Doesn't
        // matter, use the div as dummy
        PHPMYEDIT.blah = event;
        PHPMYEDIT.blah2 = $(event.target);
        return PHPMYEDIT.pseudoSubmit(container.find('form.pme-form'), $(event.target), containerSel, pmepfx);
      });

      container.find("td[class^='"+pmepfx+"-filter'] div.chosen-container").attr("title", this.filterSelectChosenTitle);
    }

    if (this.selectChosen) {
      var noRes = this.inputSelectNoResult;

      // Provide a data-placeholder and also remove the match-all
      // filter, which is not needed when using chosen.
      container.find("select[class^='"+pmepfx+"-input']").attr("data-placeholder", this.inputSelectPlaceholder);
      container.off('change', 'select[class^="'+pmepfx+'-input"]');
      container.find("select[class^='"+pmepfx+"-input'] option[value='*']").remove();

      // Play a dirty trick in order not to pass width:auto to chosen
      // for some particalar thingies
      var k;
      for (k = 0; k < PHPMYEDIT.chosenPixelWidth.length; ++k) {
        var tag = PHPMYEDIT.chosenPixelWidth[k];
        var pxlWidth = Math.round(container.find("td[class^='"+pmepfx+"-input-"+tag+"']").width());
        container.find("select[class^='"+pmepfx+"-input-"+tag+"']").chosen({width:pxlWidth+'px',
                                                                            disable_search_threshold: 10,
                                                                            no_results_text:noRes});
      }
       
      // Then the general stuff
      container.find("select[class^='"+pmepfx+"-input']").chosen({//width:'100%',
        disable_search_threshold: 10,
        no_results_text:noRes});

      // Set title explicitly
      container.find("td[class^='"+pmepfx+"-input'] div.chosen-container").attr("title", this.inputSelectChosenTitle);
      container.find("td[class^='"+pmepfx+"-value'] div.chosen-container").attr("title", this.inputSelectChosenTitle);

      // Copy over titles
      container.find("td[class^='"+pmepfx+"-value']").each(function(index) {
        var selectBox;
        var selectTitle = "";
        selectBox = $(this).children("select[class^='"+pmepfx+"-input']").first();
        if (typeof $(selectBox).attr("title") !== 'undefined') {
          selectTitle = selectBox.attr("title");
        } else if (typeof $(selectBox).attr("original-title") !== 'undefined') {
          selectTitle = selectBox.attr("original-title");
        }
        if (selectTitle.length != 0) {
          $(this).children("div.chosen-container").first().attr("title", selectTitle);
        }
      });

    }

  };
})(window, jQuery, PHPMYEDIT);

$(document).ready(function(){

  PHPMYEDIT.transposeReady();
  PHPMYEDIT.init('pme');

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
