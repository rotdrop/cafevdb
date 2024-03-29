import $ from '../../app/jquery.js';
import Calendar from './calendar.js';
import fileDownload from '../../app/file-download.js';

// $(document).on('click', '#newCalendar', function () {
//      Calendar.UI.Calendar.newCalendar(this);
// });
// $(document).on('click', '#caldav_url_close', function () {
//      $('#caldav_url').hide();$('#caldav_url_close').hide();
// });
// $(document).on('mouseover', '#caldav_url', function () {
//      $('#caldav_url').select();
// });
// $(document).on('click', '#primarycaldav', function () {
//      $('#primarycaldav').select();
// });
// $(document).on('click', '#ioscaldav', function () {
//      $('#ioscaldav').select();
// });
$(document).on('click', '#viewOnMap', function() {
  $.fn.cafevTooltip('hide');
  Calendar.Util.openLocationMap();
});
$(document).on('click', '#editCategories', function() {
  $.fn.cafevTooltip('hide');
  OC.Tags.edit('event');
});
$(document).on('click', '#allday_checkbox', function() {
  Calendar.UI.lockTime();
});
$(document).on('click', '#advanced_options_button', function() {
  const isVisible = $('#advanced_options').is(':visible');
  $(this)
    .toggleClass('options-visible', !isVisible)
    .toggleClass('options-hidden', isVisible);
  if (isVisible) {
    Calendar.UI.hideadvancedoptions();
  } else {
    Calendar.UI.showadvancedoptions();
  }
});
$(document).on('click', '#advanced_options_button_repeat', function() {
  const isVisible = $('#advanced_options_repeating').is(':visible');
  $(this)
    .toggleClass('options-visible', !isVisible)
    .toggleClass('options-hidden', isVisible);
  Calendar.UI.showadvancedoptionsforrepeating();
});
// $(document).on('click', '#google-location', function() {
//   Calendar.UI.googlelocation();
// });
$(document).on('click', '#submitNewEvent', function() {
  Calendar.UI.validateEventForm($(this).data('link'));
});
$(document).on('click', '#chooseCalendar', function() {
  Calendar.UI.Calendar.newCalendar(this);
});
// $(document).on('change', '.activeCalendar', function() {
//      Calendar.UI.Calendar.activation(this,$(this).data('id'));
// });
$(document).on('change', '#active_shared_events', function() {
  Calendar.UI.Calendar.sharedEventsActivation(this);
});
$(document).on('click', '#allday_checkbox', function() {
  Calendar.UI.lockTime();
});
$(document).on('click', '#editEvent-submit', function() {
  console.log('submit-event');
  Calendar.UI.validateEventForm($(this).data('link'));
});
$(document).on('click', '#editEvent-clone', function() {
  Calendar.UI.submitCloneEventForm($(this).data('link'));
});
$(document).on('click', '#editEvent-delete', function() {
  Calendar.UI.submitDeleteEventForm($(this).data('link'));
});
$(document).on('click', '#editEvent-export', function() {
  fileDownload($(this).data('link'));
});
// $(document).on('click', '#chooseCalendar-showCalDAVURL', function() {
//      Calendar.UI.showCalDAVUrl($(this).data('user'), $(this).data('caldav'));
// });
// $(document).on('click', '#chooseCalendar-edit', function() {
//      Calendar.UI.Calendar.edit($(this), $(this).data('id'));
// });
// $(document).on('click', '#chooseCalendar-delete', function() {
//      Calendar.UI.Calendar.deleteCalendar($(this).data('id'));
// });
// $(document).on('click', '#editCalendar-submit', function() {
//      Calendar.UI.Calendar.submit($(this), $(this).data('id'));
// });
// $(document).on('click', '#editCalendar-cancel', function() {
//      Calendar.UI.Calendar.cancel($(this), $(this).data('id'));
// });
// $(document).on('click', '.choosecalendar-rowfield-active', function() {
//      Calendar.UI.Share.activation($(this), $(this).data('id'));
// });
// @TODO this was autocomplete from addressbook
// $(document).on('focus', '#event-location:not(.ui-autocomplete-input)', function(event) {
//   $(this).autocomplete({
//     source: OC.linkTo('calendar', 'ajax/search-location.php'),
//     minLength: 2,
//   });
// });
$(document).on('blur', '#event-location', function(event) {
  const $this = $(this);
  const $mapLink = $this.next();
  const mapUrl = $mapLink.attr('href');
  $mapLink.attr('href', mapUrl.replace(/search=.*$/gi, 'search=' + encodeURIComponent($this.val())));
});
// $(document).on('keydown', '#newcalendar_dialog #displayname_new', function(event){
//      if (event.which == 13){
//              $('#newcalendar_dialog #editCalendar-submit').click();
//      }
// });
// $(document).on('keydown', '#editcalendar_dialog > span > input:text', function(event){
//      if (event.which == 13){
//              $('#editcalendar_dialog #editCalendar-submit').click();
//      }
// });

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
