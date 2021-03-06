/**
 * Original copyright below, slight changes for cafevdb by
 * Copyright (c) 2013-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Copyright (c) 2012 Georg Ehrke <ownclouddev at georgswebsite dot de>
 * Copyright (c) 2011 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

import { appName } from '../../app/config.js';
import * as Ajax from '../../app/ajax.js';
import * as CAFEVDB from '../../app/cafevdb.js';
import * as Dialogs from '../../app/dialogs.js';
import * as Events from '../../app/events.js';
import * as DialogUtils from '../../app/dialog-utils.js';

const Calendar={
  missing: {
    'caption': t(appName, 'Missing or invalid fields'),
    'title': t(appName, 'Title'),
    'calendar': t(appName, 'Calendar'),
    'fromdate': t(appName, 'From Date'),
    'fromtime': t(appName, 'From Time'),
    'todate': t(appName, 'To Date'),
    'totime': t(appName, 'To Time'),
    'startsbeforeends': t(appName, 'The event ends before it starts'),
    'dberror': t(appName, 'There was a database fail'),
    'interval': t(appName, 'Interval is not valid. It must be a positive integer!')
  },
  categories: [],
  Util:{
    // sendmail: function(eventURI, location, description, dtstart, dtend){
    //   Calendar.UI.loading(true);
    //   $.post(
    //     OC.filePath('calendar','ajax/event','sendmail.php'),
    //     {
    //       eventURI:eventURI,
    //       location:location,
    //       description:description,
    //       dtstart:dtstart,
    //       dtend:dtend
    //     },
    //     function(result){
    //       if(result.status !== 'success'){
    //         OC.dialogs.alert(result.data.message, 'Error sending mail');
    //       }
    //       Calendar.UI.loading(false);
    //     }
    //   );
    // },
    dateTimeToTimestamp:function(dateString, timeString){
      dateTuple = dateString.split('-');
      timeTuple = timeString.split(':');

      var day, month, year, minute, hour;
      day = parseInt(dateTuple[0], 10);
      month = parseInt(dateTuple[1], 10);
      year = parseInt(dateTuple[2], 10);
      hour = parseInt(timeTuple[0], 10);
      minute = parseInt(timeTuple[1], 10);

      var date = new Date(year, month-1, day, hour, minute);

      return parseInt(date.getTime(), 10);
    },
    formatDate:function(year, month, day){
      if(day < 10){
        day = '0' + day;
      }
      if(month < 10){
        month = '0' + month;
      }
      return day + '-' + month + '-' + year;
    },
    formatTime:function(hour, minute){
      if(hour < 10){
        hour = '0' + hour;
      }
      if(minute < 10){
        minute = '0' + minute;
      }
      return hour + ':' + minute;
    },
    adjustDate:function(){
      var fromTime = $('#fromtime').val();
      var fromDate = $('#from').val();
      var fromTimestamp = Calendar.Util.dateTimeToTimestamp(fromDate, fromTime);

      var toTime = $('#totime').val();
      var toDate = $('#to').val();
      var toTimestamp = Calendar.Util.dateTimeToTimestamp(toDate, toTime);

      if(fromTimestamp >= toTimestamp){
        fromTimestamp += 30*60*1000;

        var date = new Date(fromTimestamp);
        movedTime = Calendar.Util.formatTime(date.getHours(), date.getMinutes());
        movedDate = Calendar.Util.formatDate(date.getFullYear(),
                                             date.getMonth()+1, date.getDate());

        $('#to').val(movedDate);
        $('#totime').val(movedTime);
      }
    },
    getDayOfWeek:function(iDay){
      var weekArray=['sun','mon','tue','wed','thu','fri','sat'];
      return weekArray[iDay];
    },
    setTimeline : function() {
      var curTime = new Date();
      if (curTime.getHours() == 0 && curTime.getMinutes() <= 5)// Because I am calling this function every 5 minutes
      {
        // the day has changed
        var todayElem = $(".fc-today");
        todayElem.removeClass("fc-today");
        todayElem.removeClass("fc-state-highlight");

        todayElem.next().addClass("fc-today");
        todayElem.next().addClass("fc-state-highlight");
      }

      var parentDiv = $(".fc-agenda-slots:visible").parent();
      var timeline = parentDiv.children(".timeline");
      if (timeline.length == 0) {//if timeline isn't there, add it
        timeline = $("<hr>").addClass("timeline");
        parentDiv.prepend(timeline);
      }

      var curCalView = $('#fullcalendar').fullCalendar("getView");
      if (curCalView.visStart < curTime && curCalView.visEnd > curTime) {
        timeline.show();
      } else {
        timeline.hide();
      }

      var curSeconds = (curTime.getHours() * 60 * 60) + (curTime.getMinutes() * 60) + curTime.getSeconds();
      var percentOfDay = curSeconds / 86400;
      //24 * 60 * 60 = 86400, # of seconds in a day
      var topLoc = Math.floor(parentDiv.height() * percentOfDay);
      var appNavigationWidth = ($(window).width() > 768) ? $('#app-navigation').width() : 0;
      timeline.css({'left':($('.fc-today').offset().left-appNavigationWidth),'width': $('.fc-today').width(),'top':topLoc + 'px'});
    },
    openLocationMap:function(){
      var address = $('#event-location').val();
      address = encodeURIComponent(address);
      var newWindow = window.open('http://open.mapquest.com/?q='+address, '_blank');
      newWindow.focus();
    }
  },
  UI:{
    Share: {},
    loading: function(isLoading){
      if (isLoading){
        $('#loading').show();
      }else{
        $('#loading').hide();
      }
    },
    startEventDialog:function(){
      Calendar.UI.loading(false);
      $.fn.cafevTooltip.remove();
      //                      $('#fullcalendar').fullCalendar('unselect');
      Calendar.UI.lockTime();
      $( "#from" ).datepicker({
        dateFormat : 'dd-mm-yy',
        onSelect: function(){ Calendar.Util.adjustDate(); }
      });
      $( "#to" ).datepicker({
        dateFormat : 'dd-mm-yy'
      });
      $('#fromtime').timepicker({
        showPeriodLabels: false,
        onSelect: function(){ Calendar.Util.adjustDate(); }
      });
      $('#totime').timepicker({
        showPeriodLabels: false
      });
      $('#category').multiple_autocomplete({source: Calendar.categories});
      Calendar.UI.repeat('init');
      $('#end').change(function(){
        Calendar.UI.repeat('end');
      });
      $('#repeat').change(function(){
        Calendar.UI.repeat('repeat');
      });
      $('#advanced_year').change(function(){
        Calendar.UI.repeat('year');
      });
      $('#advanced_month').change(function(){
        Calendar.UI.repeat('month');
      });
      $('#event-title').bind('keydown', function(event){
        if (event.which == 13){
          $('#event_form #submitNewEvent').click();
        }
      });
      $( "#event" ).tabs({ selected: 0});
      var eventForm = $('#event_form');
      console.log(eventForm);
      var calSelect = eventForm.find('select[name="calendar"]');
      // Search for the selected option, if none is
      // selected, take the first (new event)
      var cal = calSelect.children(':selected');
      if (cal.length == 0) {
        cal = calSelect.children('option').first();
      }
      var calId = cal.val();
      calSelect.prop('disabled', true);
      eventForm.append('<input type="hidden" name="calendar" value="'+calId+'"/>');
      $('#event').cafevDialog({
        // position: {
        //   my: "left-40% top+50%",
        //   at: "left top",
        //   of: 'div[aria-describedby="events"]'
        // },
        width : 520,
        height: 600,
        resizable: false,
        dialogClass: 'cafevdb eventdlg',
        //draggable: false,
        open  : function() {
          DialogUtils.dialogToBackButton($(this));
        },
        close : function(event, ui) {
          $(this).dialog('destroy').remove();
          if ($('#event_googlemap').dialog('isOpen') == true){
            $('#event_googlemap').dialog('close').remove();
          }
        }
      });
      // Calendar.UI.Share.init();
      $('#sendemailbutton').click(function() {
        Calendar.Util.sendmail($(this).attr('data-eventuri'), $(this).attr('data-location'), $(this).attr('data-description'), $(this).attr('data-dtstart'), $(this).attr('data-dtend'));
      });
      // Focus the title, and reset the text value so that it isn't selected.
      var val = $('#event-title').val();
      $('#event-title').focus().val('').val(val);
    },
    // newEvent:function(start, end, allday){
    //   start = Math.round(start.getTime()/1000);
    //   if (end){
    //     end = Math.round(end.getTime()/1000);
    //   }
    //   if($('#event').dialog('isOpen') == true){
    //     // TODO: save event
    //     $('#event').dialog('destroy').remove();
    //   }else{
    //     Calendar.UI.loading(true);
    //     $('#dialog_holder').load(OC.filePath('calendar', 'ajax/event', 'new.form.php'), {start:start, end:end, allday:allday?1:0}, Calendar.UI.startEventDialog);
    //   }
    // },
    // editEvent:function(calEvent, jsEvent, view){
    //   if (calEvent.editable == false || calEvent.source.editable == false) {
    //     return;
    //   }
    //   var id = calEvent.id;
    //   if($('#event').dialog('isOpen') == true){
    //     // TODO: save event
    //     $('#event').dialog('destroy').remove();
    //   }else{
    //     Calendar.UI.loading(true);
    //     $('#dialog_holder').load(OC.filePath('calendar', 'ajax/event', 'edit.form.php'), {id: id}, Calendar.UI.startEventDialog);
    //   }
    // },
    submitDeleteEventForm:function(url){
      const post = {
        'calendarid': $('input[name="calendarid"]').val(),
        'uri': $('input[name="uri"]').val()
      };
      $('#errorbox').empty();
      Calendar.UI.loading(true);
      $.post(url, post)
        .done(function(data){
          Calendar.UI.loading(false);
          // $('#fullcalendar').fullCalendar('removeEvents', $('#event_form input[name=id]').val());
          $('#event').dialog('destroy').remove();
          Events.redisplay();
        })
	.fail(function(xhr, status, errorThrown) {
          Calendar.UI.loading(false);
          Ajax.handleError(xhr, status, errorThrown);
          const msg = Ajax.failMessage(xhr, status, errorThrown);
          $('#errorbox').html(t('calendar', 'Deletion failed'));
        }, "json");
    },
    validateEventForm:function(url){
      var post = $( "#event_form" ).serialize();
      $("#errorbox").empty();
      Calendar.UI.loading(true);
      $.post(url, post)
        .done(function(data) {
          Calendar.UI.loading(false);
          $('#event').dialog('destroy').remove();
          //$('#fullcalendar').fullCalendar('refetchEvents');
          Events.redisplay();
        })
	.fail(function(xhr, status, errorThrown) {
          Calendar.UI.loading(false);
          const msg = Ajax.failMessage(xhr, status, errorThrown);
          Ajax.handleError(xhr, status, errorThrown, function(data) {
            var output = t(appName, "Error") + ": <br />";
            output = output + msg + ": <br />";
            output = output + Calendar.missing.caption + ": <br />";
            if(data.title == "true"){
              output = output + Calendar.missing.title + "<br />";
            }
            if(data.cal == "true"){
              output = output + Calendar.missing.calendar + "<br />";
            }
            if(data.from == "true"){
              output = output + Calendar.missing.fromdate + "<br />";
            }
            if(data.fromtime == "true"){
              output = output + Calendar.missing.fromtime + "<br />";
            }
            if(data.interval == "true"){
              output = output + Calendar.missing.interval + "<br />";
            }
            if(data.to == "true"){
              output = output + Calendar.missing.todate + "<br />";
            }
            if(data.totime == "true"){
              output = output + Calendar.missing.totime + "<br />";
            }
            if(data.endbeforestart == "true"){
              output = output + Calendar.missing.startsbeforeends + "!<br/>";
            }
            if(data.dberror == "true"){
              output = "There was a database failure!";
            }
            Dialogs.alert(
              output,
              t(appName, 'Calendar event validation caught an error.'),
              null, false, true);
            $('#errorbox').html(output);
          });
        });
    },
    // moveEvent:function(event, dayDelta, minuteDelta, allDay, revertFunc){
    //   $.fn.cafevTooltip.remove();
    //   if ($('#event').length != 0) {
    //     revertFunc();
    //     return;
    //   }
    //   Calendar.UI.loading(true);
    //   $.post(OC.filePath('calendar', 'ajax/event', 'move.php'), { id: event.id, dayDelta: dayDelta, minuteDelta: minuteDelta, allDay: allDay?1:0, lastmodified: event.lastmodified},
    //          function(data) {
    //            Calendar.UI.loading(false);
    //            if (data.status == 'success'){
    //              event.lastmodified = data.lastmodified;
    //              console.log("Event moved successfully");
    //            }else{
    //              revertFunc();
    //              //$('#fullcalendar').fullCalendar('refetchEvents');
    //              Events.redisplay();
    //            }
    //          });
    // },
    // resizeEvent:function(event, dayDelta, minuteDelta, revertFunc){
    //   $.fn.cafevTooltip.remove();
    //   Calendar.UI.loading(true);
    //   $.post(OC.filePath('calendar', 'ajax/event', 'resize.php'), { id: event.id, dayDelta: dayDelta, minuteDelta: minuteDelta, lastmodified: event.lastmodified},
    //          function(data) {
    //            Calendar.UI.loading(false);
    //            if (data.status == 'success'){
    //              event.lastmodified = data.lastmodified;
    //              console.log("Event resized successfully");
    //            }else{
    //              revertFunc();
    //              //$('#fullcalendar').fullCalendar('refetchEvents');
    //              Events.redisplay();
    //            }
    //          });
    // },
    googlepopup:function(latlng, location) {
      if ($('#event_googlemap').dialog('isOpen') == true){
        $('#event_googlemap').dialog('close').remove();
      }
      $('#event_map').html('<div id="event_googlemap"></div>');
      var mapOptions = {
        zoom: 15,
        center: latlng,
        mapTypeId: google.maps.MapTypeId.ROADMAP
      };
      var map = new google.maps.Map(document.getElementById("event_googlemap"), mapOptions);
      $('#event_googlemap').cafevDialog({
        title : 'Google Maps',
        dialogClass: 'google-popup',
        position : { my: "left top",
                     at: "center center",
                     of: "#event",
                     offset: "0 0" },
        resizable: true,
        resize: 'auto',
        width : 500,
        height: 600,
        close : function(event, ui) {
          $(this).dialog('destroy').remove();
        },
        open  : function () {
          $(this).css('overflow', 'hidden');
          var googlesearch = '';
          if (location == '') {
            googlesearch = latlng.lat()+','+latlng.lng();
            location = t('calendar','Browser determined position')+'<br/>'+googlesearch;
          } else {
            googlesearch = location;
          }
          var infowindow = new google.maps.InfoWindow();
          var marker = new google.maps.Marker({
            map: map,
            position: latlng
          });
          google.maps.event.addListener(
            marker, 'click', function () {
              infowindow.setContent(
                location+'</br>'+
                  '<a href="https://maps.google.com/maps?q='+googlesearch+'" style="color:#00f;text-decoration:underline;" target="_blank">'+t('calendar','Detailed search at Google-Maps')+'</a>');
              infowindow.open(map, marker);
            });
          google.maps.event.trigger(map, "resize");
          map.setCenter(latlng);
        },
        resizeStop: function (event, ui) {
          var center = map.getCenter();
          google.maps.event.trigger(map, "resize");
          map.setCenter(center);
        }
      });

    },
    googlelocation:function() {
      if ($('#event_googlemap').dialog('isOpen') == true){
        $('#event_googlemap').dialog('close').remove();
      }

      var location = $('input[name=location]').val();
      geocoder = new google.maps.Geocoder();
      geocoder.geocode( { 'address': location}, function(results, status) {
        if (status == google.maps.GeocoderStatus.OK) {
          var latlng = results[0].geometry.location;
          Calendar.UI.googlepopup(latlng, location);
        } else {
          var alerttext;
          if (location) {
            alerttext = t('calendar', 'Location not found:')+' '+location;
          } else {
            alerttext = t('calendar', 'No location specified.');
          }
          Dialogs.alert(alerttext, t('calendar','Unknown location'), null, false, true );
          if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
              var latlng = new google.maps.LatLng(position.coords.latitude,
                                                  position.coords.longitude);
              Calendar.UI.googlepopup(latlng, '');
            });
          }
        }
      });
    },
    hideadvancedoptions:function(){
      $("#advanced_options").slideUp('slow');
      $("#advanced_options_button").css("display", "inline-block");
    },
    showadvancedoptions:function(){
      $("#advanced_options").slideDown('slow');
      $("#advanced_options_button").css("display", "none");
    },
    showadvancedoptionsforrepeating:function(){
      if($("#advanced_options_repeating").is(":hidden")){
        $('#advanced_options_repeating').slideDown('slow');
      }else{
        $('#advanced_options_repeating').slideUp('slow');
      }
    },
    lockTime:function(){
      if($('#allday_checkbox').is(':checked')) {
        $("#fromtime").attr('disabled', true)
          .addClass('disabled');
        $("#totime").attr('disabled', true)
          .addClass('disabled');
      } else {
        $("#fromtime").attr('disabled', false)
          .removeClass('disabled');
        $("#totime").attr('disabled', false)
          .removeClass('disabled');
      }
    },
    showCalDAVUrl:function(username, calname){
      $('#caldav_url').val(totalurl + '/' + encodeURIComponent(username) + '/' + calname);
      $('#caldav_url').show();
      $("#caldav_url_close").show();
    },
    repeat:function(task){
      if(task=='init'){

        var byWeekNoTitle = $('#advanced_byweekno').attr('title');
        $('#byweekno').multiselect({
          header: false,
          noneSelectedText: byWeekNoTitle,
          selectedList: 2,
          minWidth : 60
        });

        var weeklyoptionsTitle = $('#weeklyoptions').attr('title');
        $('#weeklyoptions').multiselect({
          header: false,
          noneSelectedText: weeklyoptionsTitle,
          selectedList: 2,
          minWidth : 110
        });
        $('input[name="bydate"]').datepicker({
          dateFormat : 'dd-mm-yy'
        });

        var byyeardayTitle = $('#byyearday').attr('title');
        $('#byyearday').multiselect({
          header: false,
          noneSelectedText: byyeardayTitle,
          selectedList: 2,
          minWidth : 60
        });

        var bymonthTitle = $('#bymonth').attr('title');
        $('#bymonth').multiselect({
          header: false,
          noneSelectedText: bymonthTitle,
          selectedList: 2,
          minWidth : 110
        });

        var bymonthdayTitle = $('#bymonthday').attr('title');
        $('#bymonthday').multiselect({
          header: false,
          noneSelectedText: bymonthdayTitle,
          selectedList: 2,
          minWidth : 60
        });
        Calendar.UI.repeat('end');
        Calendar.UI.repeat('month');
        Calendar.UI.repeat('year');
        Calendar.UI.repeat('repeat');
      }
      if(task == 'end'){
        $('#byoccurrences').css('display', 'none');
        $('#bydate').css('display', 'none');
        if($('#end option:selected').val() == 'count'){
          $('#byoccurrences').css('display', 'block');
        }
        if($('#end option:selected').val() == 'date'){
          $('#bydate').css('display', 'block');
        }
      }
      if(task == 'repeat'){
        $('#advanced_month').css('display', 'none');
        $('#advanced_weekday').css('display', 'none');
        $('#advanced_weekofmonth').css('display', 'none');
        $('#advanced_byyearday').css('display', 'none');
        $('#advanced_bymonth').css('display', 'none');
        $('#advanced_byweekno').css('display', 'none');
        $('#advanced_year').css('display', 'none');
        $('#advanced_bymonthday').css('display', 'none');
        if($('#repeat option:selected').val() == 'monthly'){
          $('#advanced_month').css('display', 'block');
          Calendar.UI.repeat('month');
        }
        if($('#repeat option:selected').val() == 'weekly'){
          $('#advanced_weekday').css('display', 'block');
        }
        if($('#repeat option:selected').val() == 'yearly'){
          $('#advanced_year').css('display', 'block');
          Calendar.UI.repeat('year');
        }
        if($('#repeat option:selected').val() == 'doesnotrepeat'){
          $('#advanced_options_repeating').slideUp('slow');
        }
      }
      if(task == 'month'){
        $('#advanced_weekday').css('display', 'none');
        $('#advanced_weekofmonth').css('display', 'none');
        if($('#advanced_month_select option:selected').val() == 'weekday'){
          $('#advanced_weekday').css('display', 'block');
          $('#advanced_weekofmonth').css('display', 'block');
        }
      }
      if(task == 'year'){
        $('#advanced_weekday').css('display', 'none');
        $('#advanced_byyearday').css('display', 'none');
        $('#advanced_bymonth').css('display', 'none');
        $('#advanced_byweekno').css('display', 'none');
        $('#advanced_bymonthday').css('display', 'none');
        if($('#advanced_year_select option:selected').val() == 'byyearday'){
          //$('#advanced_byyearday').css('display', 'block');
        }
        if($('#advanced_year_select option:selected').val() == 'byweekno'){
          $('#advanced_byweekno').css('display', 'block');
        }
        if($('#advanced_year_select option:selected').val() == 'bydaymonth'){
          $('#advanced_bymonth').css('display', 'block');
          $('#advanced_bymonthday').css('display', 'block');
          $('#advanced_weekday').css('display', 'block');
        }
      }

    },
    // categoriesChanged:function(newcategories){
    //   categories = $.map(newcategories, function(v) {return v.name;});
    //   console.log('Calendar categories changed to: ' + categories);
    //   $('#category').multiple_autocomplete('option', 'source', categories);
    // },
  },
  Settings:{
    //
  },
};

$.extend(Calendar, OCP.InitialState.loadState(appName, 'Calendar'));

export default Calendar;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
