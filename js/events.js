/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
     * @param data JSON response with the fields data.status,
     *                 data.contents,
     *                 data.message,
     *                 data.debug
     */
    init: function(htmlContent, textStatus, request) {
      // @TODO why here
      CAFEVDB.Events.UI.confirmText['delete'] =
        t('cafevdb', 'Do you really want to delete this event?');
      CAFEVDB.Events.UI.confirmText['detach'] =
        t('cafevdb', 'Do you really want to detach this event from the current project?');
      //$('#dialog_holder').html(data.contents);

      CAFEVDB.Events.projectId = request.getResponseHeader('X-' + CAFEVDB.appName + '-project-id');
      CAFEVDB.Events.projectName = request.getResponseHeader('X-' + CAFEVDB.appName + '-project-name');

      //var popup = $('#events').cafevDialog({
      var dialogContent = $(htmlContent);
      var popup = dialogContent.cafevDialog({
        dialogClass: 'cafevdb-project-events no-scroll',
        position: {
          my: "middle top+50%",
          at: "middle bottom",
          of: "#app-content"
        },
        width : "auto", //510,
        height: "auto",
	resizable: false,
        open  : function(){
          //$.fn.cafevTooltip.remove();
          var dialogHolder = $(this);
          var dialogWidget = dialogHolder.dialog('widget');

          /* Adjust dimensions to do proper scrolling. */
	  Events.UI.adjustSize(dialogHolder, dialogWidget);

          var eventForm = dialogHolder.find('#eventlistform');
          var eventMenu = eventForm.find('select.event-menu');

          // style the menu with chosen
          eventMenu.chosen({
            inherit_select_classes:true,
            disable_search:true,
            width:'10em'
          });

          CAFEVDB.fixupNoChosenMenu(eventMenu);

          CAFEVDB.dialogToBackButton($(this));

          $.fn.cafevTooltip.remove();
          CAFEVDB.toolTipsInit('#events');

          eventMenu.on('change', function(event) {
            event.preventDefault();

            if ($('#event').dialog('isOpen') === true) {
              $('#event').dialog('close');
              return false;
            }

            $('#events #debug').hide();
            $('#events #debug').empty();

            var post = eventForm.serializeArray();
            var eventType = eventMenu.find('option:selected').val();
            post.push({ name: 'EventKind', value: eventType });

            $('#dialog_holder').load(
              OC.generateUrl('/apps/cafevdb/legacy/events/forms/new'),
              post, CAFEVDB.Legacy.Calendar.UI.startEventDialog);

            eventMenu.find('option').removeAttr('selected');
            $.fn.cafevTooltip.remove();

            eventMenu.trigger('chosen:updated');

            return false;
          });

          eventForm.
            off('click', ':button').
            on('click', ':button', CAFEVDB.Events.UI.buttonClick);

	  eventForm.
            off('click', 'td.eventdata').
	    on('click', 'td.eventdata', function(event) {
            $(this).parent().find(':button.edit').trigger('click');
            return false;
          });

          dialogHolder.
            off('cafevdb:events_changed').
            on('cafevdb:events_changed', function(event, events) {
              // @TODO
              $.post(
                OC.generateUrl('/apps/cafevdb/projects/events/redisplay'),
                   { ProjectId: Events.projectId,
                     ProjectName: Events.projectName,
                     EventSelect: events },
                   CAFEVDB.Events.UI.relist);
            return false;
          });
          dialogHolder.
            off('change', 'input.email-check').
            on('change', 'input.email-check', function(event) {
            Events.UI.updateEmailForm();
            return false;
          });
        },
        close : function(event, ui) {
          $.fn.cafevTooltip.remove();
          $('#event').dialog('close');
          $(this).dialog('destroy').remove();

          // Remove modal plane if appropriate
          CAFEVDB.modalizer(false);
        }
      });
    },
    updateEmailForm: function(post, emailFormDialog)
    {
      if (typeof emailFormDialog == 'undefined') {
        emailFormDialog = $('div#emailformdialog');
      }
      if (emailFormDialog.length > 0) {
        // Email dialog already open. We trigger a custom event to
        // propagte the data. We only submit the event ids.
        if (typeof post == 'undefined') {
          post = $('#eventlistform').serializeArray();
        }
        var events = [];
        $.each(post, function (i, param) {
          if (param.name == 'EventSelect[]') {
            events.push(param.value);
          }
        });
        emailFormDialog.trigger('cafevdb:events_changed', [ events ]);
      }
    },
    adjustSize: function(dialogHolder, dialogWidget) {
      var dimensionElement = dialogHolder.find('.size-holder');
      var scrollElement = dialogHolder.find('.scroller');
      var top    = scrollElement.position().top;
      var width  = dimensionElement.outerWidth(true);
      var height = dimensionElement.outerHeight(true);
      dialogWidget.innerHeight(top+height);
      dialogWidget.innerWidth(width);

      var needScroll = scrollElement.needScrollbars();
      if (!needScroll.horizontal) {
        scrollElement.addClass('inhibit-overflow-x');
      }
      if (!needScroll.vertical) {
        scrollElement.addClass('inhibit-overflow-y');
      }

      var scroll;
      scroll = scrollElement.horizontalScrollbarHeight();
      if (scroll > 0) {
        dialogWidget.innerHeight(top+height+scroll);
      }
      scroll = scrollElement.verticalScrollbarWidth();
      if (scroll > 0) {
        dialogWidget.innerWidth(width+scroll);
      }
    },
    relist: function(data) {
      var events =$('#events');
      var listing = events.find('#eventlistholder');
      listing.html(data.contents);

      /* Adjust dimensions to do proper scrolling. */
      var dialogWidget = events.dialog('widget');
      Events.UI.adjustSize(events, dialogWidget);
      if (typeof data.debug != 'undefined') {
	events.find('#debug').html(data.debug);
	events.find('#debug').show();
      }

      $.fn.cafevTooltip.remove();

      CAFEVDB.toolTipsInit(listing);

      Events.UI.updateEmailForm();

      return false;
    },
    redisplay: function() {
      const post = $('#eventlistform').serializeArray();

      $.post(
        OC.generateUrl('/apps/cafevdb/projects/events/redisplay'),
        post, CAFEVDB.Events.UI.relist);

      return true;
    },
    buttonClick: function(event) {
      event.preventDefault();

      var evntdlgopen = $('#event').dialog('isOpen');

      var post = $('#eventlistform').serializeArray();

      if(evntdlgopen === true){
        // TODO: maybe save event
        $('#event').dialog('close');
        return false;
      }

      $('#events #debug').hide();
      $('#events #debug').empty();

      var name = $(this).attr('name');

      if (name == 'edit') {

        // Edit existing event
        // @TODO use post?
        post.push({ name: 'id', value:  $(this).val()});
        $('#dialog_holder').load(
          OC.generateUrl('/apps/cafevdb/legacy/events/forms/edit'),
          post, CAFEVDB.Legacy.Calendar.UI.startEventDialog);

        return false;
      } else if (name == 'delete' || name == 'detach' ||
                 name == 'select' || name == 'deselect') {
        // Execute the task and redisplay the event list.

        post.push({ name: 'EventId', value: $(this).val() });

        const really = CAFEVDB.Events.UI.confirmText[name];
        if (really != '') {
          // Attention: dialogs do not block, so the action needs to be
          // wrapped into the callback.
	  OC.dialogs.confirm(really,
                             t('cafevdb', 'Really delete?'),
                             function (decision) {
                               if (decision) {
                                 $.post(
                                   OC.generateUrl('/apps/cafevdb/projects/events/' + name),
                                   post, CAFEVDB.Events.UI.relist);
                               }
                             },
                             true);
        } else {
          $.post(
            OC.generateUrl('/apps/cafevdb/projects/events/' + name),
            post, CAFEVDB.Events.UI.relist);
        }
        return false;
      } else if (name == 'sendmail') {

        var emailFormDialog = $('div#emailformdialog');
        if (emailFormDialog.length == 0) {
          // If the email-form is not open, then open it :)
          CAFEVDB.Email.emailFormPopup(post);
        } else {
          // Email dialog already open. We trigger a custom event to
          // propagte the data. We only submit the event ids.
          Events.UI.updateEmailForm(post, emailFormDialog);

          // and we move the email-dialog to front
          emailFormDialog.dialog('moveToTop');
        }

      } else if (name == 'download') {

        // As always, there may be a more elegant solution, but this
        // opens the "download" dialog of my web-browser. Need to set
        // the form-method to "post" to do this.

        const exportscript = OC.generateUrl('/apps/cafevdb/projects/events/download');
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
