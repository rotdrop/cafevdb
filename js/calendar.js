/**
 * Copyright (c) 2012 Georg Ehrke <ownclouddev at georgswebsite dot de>
 * Copyright (c) 2011 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

var Calendar={
	Util:{
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
			$('.tipsy').remove();
//			$('#fullcalendar').fullCalendar('unselect');
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
			$('#category').multiple_autocomplete({source: categories});
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
			$( "#event" ).tabs({ selected: 0});
			$('#event').dialog({
                                position: {
                                        my: "left-40% top+50%",
                                        at: "left top",
                                        of: 'div[aria-describedby="events"]',
                                        
                                },
				width : 520,
				height: 600,
				resizable: false,
				close : function(event, ui) {
					$(this).dialog('destroy').remove();
			                if ($('#event_googlemap').dialog('isOpen') == true){
				                $('#event_googlemap').dialog('close').remove();
			                }
				}
			});
		},
		newEvent:function(start, end, allday){
			start = Math.round(start.getTime()/1000);
			if (end){
				end = Math.round(end.getTime()/1000);
			}
			if($('#event').dialog('isOpen') == true){
				// TODO: save event
				$('#event').dialog('destroy').remove();
			}else{
				Calendar.UI.loading(true);
				$('#dialog_holder').load(OC.filePath('calendar', 'ajax/event', 'new.form.php'), {start:start, end:end, allday:allday?1:0}, Calendar.UI.startEventDialog);
			}
		},
		editEvent:function(calEvent, jsEvent, view){
			if (calEvent.editable == false || calEvent.source.editable == false) {
				return;
			}
			var id = calEvent.id;
			if($('#event').dialog('isOpen') == true){
				// TODO: save event
				$('#event').dialog('destroy').remove();
			}else{
				Calendar.UI.loading(true);
				$('#dialog_holder').load(OC.filePath('calendar', 'ajax/event', 'edit.form.php'), {id: id}, Calendar.UI.startEventDialog);
			}
		},
		submitDeleteEventForm:function(url){
			var id = $('input[name="id"]').val();
			$('#errorbox').empty();
			Calendar.UI.loading(true);
			$.post(url, {id:id}, function(data){
					Calendar.UI.loading(false);
					if(data.status == 'success'){
//						$('#fullcalendar').fullCalendar('removeEvents', $('#event_form input[name=id]').val());
						$('#event').dialog('destroy').remove();
                                        	CAFEVDB.Events.UI.redisplay();
					} else {
						$('#errorbox').html(t('calendar', 'Deletion failed'));
					}

			}, "json");
		},
		validateEventForm:function(url){
			var post = $( "#event_form" ).serialize();
			$("#errorbox").empty();
			Calendar.UI.loading(true);
			$.post(url, post,
				function(data){
					Calendar.UI.loading(false);
					if(data.status == "error"){
						var output = missing_field + ": <br />";
						if(data.title == "true"){
							output = output + missing_field_title + "<br />";
						}
						if(data.cal == "true"){
							output = output + missing_field_calendar + "<br />";
						}
						if(data.from == "true"){
							output = output + missing_field_fromdate + "<br />";
						}
						if(data.fromtime == "true"){
							output = output + missing_field_fromtime + "<br />";
						}
						if(data.to == "true"){
							output = output + missing_field_todate + "<br />";
						}
						if(data.totime == "true"){
							output = output + missing_field_totime + "<br />";
						}
						if(data.endbeforestart == "true"){
							output = output + missing_field_startsbeforeends + "!<br/>";
						}
						if(data.dberror == "true"){
							output = "There was a database fail!";
						}
						$("#errorbox").html(output);
					} else
					if(data.status == 'success'){
						$('#event').dialog('destroy').remove();
                                        	CAFEVDB.Events.UI.redisplay();
					}
				},"json");
		},
		moveEvent:function(event, dayDelta, minuteDelta, allDay, revertFunc){
			$('.tipsy').remove();
			Calendar.UI.loading(true);
			$.post(OC.filePath('calendar', 'ajax/event', 'move.php'), { id: event.id, dayDelta: dayDelta, minuteDelta: minuteDelta, allDay: allDay?1:0, lastmodified: event.lastmodified},
			function(data) {
				Calendar.UI.loading(false);
				if (data.status == 'success'){
					event.lastmodified = data.lastmodified;
					console.log("Event moved successfully");
				}else{
					revertFunc();
                                	CAFEVDB.Events.UI.redisplay();
				}
			});
		},
		resizeEvent:function(event, dayDelta, minuteDelta, revertFunc){
			$('.tipsy').remove();
			Calendar.UI.loading(true);
			$.post(OC.filePath('calendar', 'ajax/event', 'resize.php'), { id: event.id, dayDelta: dayDelta, minuteDelta: minuteDelta, lastmodified: event.lastmodified},
			function(data) {
				Calendar.UI.loading(false);
				if (data.status == 'success'){
					event.lastmodified = data.lastmodified;
					console.log("Event resized successfully");
				}else{
					revertFunc();
                                	CAFEVDB.Events.UI.redisplay();
				}
			});
		},
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
			$('#event_googlemap').dialog({
				title : 'Google Maps',
				position : { my: "left top",
					     at: "center center",
					     of: "#event",
					     offset: "0 0" },
				width : 500,
				height: 600,
				close : function(event, ui) {
					$(this).dialog('destroy').remove();
				},
				open  : function () {
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
					OC.dialogs.alert(alerttext, t('calendar','Unknown location'));
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
			$('#caldav_url').val(totalurl + '/' + username + '/' + calname);
			$('#caldav_url').show();
			$("#caldav_url_close").show();
		},
		initScroll:function(){
			if(window.addEventListener)
				document.addEventListener('DOMMouseScroll', Calendar.UI.scrollCalendar, false);
			//}else{
				document.onmousewheel = Calendar.UI.scrollCalendar;
			//}
		},
		repeat:function(task){
			if(task=='init'){
				$('#byweekno').multiselect({
					header: false,
					noneSelectedText: $('#advanced_byweekno').attr('title'),
					selectedList: 2,
					minWidth:'auto'
				});
				$('#weeklyoptions').multiselect({
					header: false,
					noneSelectedText: $('#weeklyoptions').attr('title'),
					selectedList: 2,
					minWidth:'auto'
				});
				$('input[name="bydate"]').datepicker({
					dateFormat : 'dd-mm-yy'
				});
				$('#byyearday').multiselect({
					header: false,
					noneSelectedText: $('#byyearday').attr('title'),
					selectedList: 2,
					minWidth:'auto'
				});
				$('#bymonth').multiselect({
					header: false,
					noneSelectedText: $('#bymonth').attr('title'),
					selectedList: 2,
					minWidth:'auto'
				});
				$('#bymonthday').multiselect({
					header: false,
					noneSelectedText: $('#bymonthday').attr('title'),
					selectedList: 2,
					minWidth:'auto'
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
		setViewActive: function(view){
			$('#view input[type="button"]').removeClass('active');
			var id;
			switch (view) {
				case 'agendaWeek':
					id = 'oneweekview_radio';
					break;
				case 'month':
					id = 'onemonthview_radio';
					break;
				case 'list':
					id = 'listview_radio';
					break;
			}
			$('#'+id).addClass('active');
		},
		categoriesChanged:function(newcategories){
			categories = $.map(newcategories, function(v) {return v;});
			console.log('Calendar categories changed to: ' + categories);
			$('#category').multiple_autocomplete('option', 'source', categories);
		},
	},
	Settings:{
		//
	},

};

// Local Variables: ***
// js-indent-level: 8 ***
// End: ***