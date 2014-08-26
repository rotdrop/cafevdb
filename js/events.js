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

var CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {
  'use strict';
  var Events = function() {};
  Events.projectId = -1;
  Events.projectName = '';
  Events.Events = { /* nothing */ };
  Events.UI = {
    confirmText: { delete: '',
                   detach: '',
                   select: '',
                   deselect: ''
                 },
    /**Initialize the mess with contents
     *
     * @param[in] data JSON response with the fields data.status,
     *                 data.data.contents,
     *                 data.data.message is place in an error-popup if status != 'success'
     *                 data.data.debug. data.data.debug is placed
     *                 inside the '#debug' div.
     */
    init: function(data) {
      CAFEVDB.Events.UI.confirmText['delete'] =
        t('cafevdb', 'Do you really want to delete this event?');
      CAFEVDB.Events.UI.confirmText['detach'] =
        t('cafevdb', 'Do you really want to detach this event from the current project?');
      if (data.status == 'success') {
        $('#dialog_holder').html(data.data.contents);
        CAFEVDB.Events.projectId = data.data.projectId;
        CAFEVDB.Events.projectName = data.data.projectName;
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
      }
      if (typeof data.data.debug != 'undefined') {
	$('div.debug').html(data.data.debug);
	$('div.debug').show();
      }

      var popup = $('#events').dialog({
        position: { my: "middle top+50%",
                    at: "middle bottom",
                    of: "#controls" },
        width : "auto", //510,
        height: "auto",
	resizable: false,
        open  : function(){
          //$('.tipsy').remove();

          $('button').tipsy({gravity:'ne', fade:true});
          $('input').tipsy({gravity:'ne', fade:true});
          $('label').tipsy({gravity:'ne', fade:true});

          if (CAFEVDB.toolTips) {
            $.fn.tipsy.enable();
          } else {
            $.fn.tipsy.disable();
          }

          $('#events #eventlistform :button').click(CAFEVDB.Events.UI.buttonClick);
        },
        close : function(event, ui) {
          $('.tipsy').remove();
          $('#event').dialog('close');
          $(this).dialog('destroy').remove();
        }
      });
    },

    relist: function(data) {
      if (data.status == 'success') {
        $('#events div.listing').html(data.data.contents);
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
      }
      if (typeof data.data.debug != 'undefined') {
	$('#events #debug').html(data.data.debug);
	$('#events #debug').show();
      }

      $('.tipsy').remove();

      $('button').tipsy({gravity:'ne', fade:true});
      $('input').tipsy({gravity:'ne', fade:true});
      $('label').tipsy({gravity:'ne', fade:true});

      if (CAFEVDB.toolTips) {
        $.fn.tipsy.enable();
      } else {
        $.fn.tipsy.disable();
      }

      $('#events #eventlistform div.listing :button').click(CAFEVDB.Events.UI.buttonClick);

      return false;
    },
    redisplay: function() {
      var post = $('#eventlistform').serializeArray();

      var type = new Object();
      type['name']  = 'Action';
      type['value'] = 'redisplay';
      post.push(type);

      $.post(OC.filePath('cafevdb', 'ajax/events', 'execute.php'),
             post, CAFEVDB.Events.UI.relist);
        
      return true;
    },
    addEventSelection: function (post, emailForm, eventData) {
      var projectName ='';
      var projectId = '';
      var ids = '';
      emailForm.find('input[name^="EventSelect"]').each(function () { $(this).remove(); });
      jQuery.each(post, function (i, param) {
        if (param.name == 'EventSelect[]') {
          $('<input />').attr('type', 'hidden')
            .attr('name', 'EventSelect[]')
            .attr('value', param.value)
            .appendTo(emailForm);
          ids += param.value + ', ';
        }
      });
      if (emailForm.find('input[name="ProejctId"]').length == 0) {
        $('<input />').attr('type', 'hidden')
          .attr('name', 'ProjectId')
          .attr('value', CAFEVDB.Events.projectId)
          .appendTo(emailForm);
      }
      if (emailForm.find('input[name="Project"]').length == 0) {
        $('<input />').attr('type', 'hidden')
          .attr('name', 'Project')
          .attr('value', CAFEVDB.Events.projectName)
          .appendTo(emailForm);
      }
      ids = ids.substr(0, ids.length - 2);
      if (eventData != '') {
        eventData.html(ids);
      }
    },
    buttonClick: function(event) {
      event.preventDefault();

      var evntdlgopen = $('#event').dialog('isOpen');

      var post = $('#eventlistform').serializeArray();

      if(evntdlgopen == true){
        // TODO: save event
        $('#event').dialog('close');
        return false;
      }

      $('#events #debug').hide();
      $('#events #debug').empty();

      var name = $(this).attr('name');
      if (name == 'concerts' ||
          name == 'rehearsals' ||
          name == 'other' ||
          name == 'management') {
        // These are the new-event buttons.

        if(evntdlgopen == true){
          return false;
        }

        var type = new Object();
        type['name']  = 'EventKind';
        type['value'] = $(this).attr('name');
        post.push(type);

        if (false) {
          $.post(OC.filePath('cafevdb', 'ajax/events', 'new.form.php'),
                 post, CAFEVDB.Events.UI.relist, 'json');
        } else {
          $('#dialog_holder').load(OC.filePath('cafevdb',
                                               'ajax/events',
                                               'new.form.php'),
                                   post, Calendar.UI.startEventDialog);
        }
        
        return false;
      } else if (name == 'edit') {

        if(evntdlgopen == true){
          return false;
        }

        // Edit existing event

        var type = new Object();
        type['name']  = 'id';
        type['value'] = $(this).val();
        post.push(type);

        $('#dialog_holder').load(OC.filePath('calendar',
                                             'ajax/event',
                                             'edit.form.php'),
                                 post, Calendar.UI.startEventDialog);
        
        return false;
      } else if (name == 'delete' || name == 'detach' ||
                 name == 'select' || name == 'deselect') {
        // Execute the task and redisplay the event list.

        var type = new Object();
        type['name']  = 'EventId';
        type['value'] = $(this).val();
        post.push(type);

        var type = new Object();
        type['name']  = 'Action';
        type['value'] = $(this).attr('name');
        post.push(type);

        var really = CAFEVDB.Events.UI.confirmText[name];
        if (really != '') {
          
          // Attention: dialogs do not block, so the action needs to be
          // wrapped into the callback.
	  OC.dialogs.confirm(really,
                             t('cafevdb', 'Really delete?'),
                             function (decision) {
                               if (decision) {
                                 $.post(OC.filePath('cafevdb', 'ajax/events', 'execute.php'),
                                        post, CAFEVDB.Events.UI.relist);
                               }
                             },
                             true);
        } else {
          $.post(OC.filePath('cafevdb', 'ajax/events', 'execute.php'),
                 post, CAFEVDB.Events.UI.relist);
        }
        return false;
      } else if (name == 'sendmail') {

        // No need to relist, in principle ...
        $.post(OC.filePath('cafevdb', 'ajax/events', 'sendmail.php'),
               post, CAFEVDB.Events.UI.relist);


        // Ok, maybe not too elegant. We check whether we have been
        // opened by the email-form by searching for the respective
        // form-id. eventData is supposed to be able to contan
        // html-data. We use it to give some feedback.
        var emailForm = $('form.cafevdb-email-form');
        var eventData = $('#eventattachments');

        if (emailForm != '') {
          CAFEVDB.Events.UI.addEventSelection(post, emailForm, eventData);

          // Add another datum forcing the email form to stay in compose mode.
          $('<input />').attr('type', 'hidden')
            .attr('name', 'writeMail')
            .attr('value', 'reload')
            .appendTo(emailForm);

          emailForm.submit(); // This closes also the event-dialog.
        }

        // If we have not been called by the email-form then we try to
        // open it in project mode. To do so we search for an
        // "ordinaray" PME-form and submit it, with the proper
        // email-form request.
        //
        //<form class="pme-form" method="post" action="?app=cafevdb" name="PME_sys_form">
        //
        // We then add the selected events using hidden input elements.
        var emailForm = $('form.pme-form');
        if (emailForm != '') {
          CAFEVDB.Events.UI.addEventSelection(post, emailForm, '');

          // the submit button is
          //
          // <input class="pme-misc" name="PME_sys_operation" value="Em@il" type="submit">
          //
          $('<input />').attr('type', 'hidden')
            .attr('name', 'PME_sys_operation')
            .attr('value', 'Em@il')
            .appendTo(emailForm);

          emailForm.submit();
        }

        return false;

      } else if (name == 'download') {

        // As always, there may be a more elegant solution, but this
        // opens the "download" diaglog of my web-browser. Need to set
        // the form-method to "post" to do this.
        
        var exportscript = OC.filePath('cafevdb', 'ajax/events', 'download.php');
        $('#eventlistform').attr("method", "post");        
        $('#eventlistform').attr("action", exportscript);

        $('#eventlistform').submit();

        return false;
      }

      return false;
    }
  };

  CAFEVDB.Events = Events;

})(window, jQuery, CAFEVDB);


// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

