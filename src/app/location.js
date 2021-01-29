/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from './config.js';

const popupId = 'locationPopup';

const googlePopup = function(latlng, location, popupId, position) {
  const self = this;

  if (typeof popupId === 'undefined') {
    popupId = self.popupSelector;
  }
  if (typeof position === 'undefined') {
    position = {
      my: 'center center',
      at: 'center center',
      of: window,
      offset: '0 0'
    };
  }

  var popupSelector = '#'+popupId;
  if ($(popupSelector).dialog('isOpen') == true) {
    $(popupSelector).dialog('close').remove();
  }
  var popup = $('<div id=''+popupId+''></div>');
  var mapOptions = {
    zoom: 15,
    center: latlng,
    mapTypeId: google.maps.MapTypeId.ROADMAP
  };
  var map = new google.maps.Map(popup[0], mapOptions);
  popup.cafevDialog({
    title : 'Google Maps',
    dialogClass: 'google-popup',
    position : position,
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
	location = t(appName, 'Browser determined position')+'<br/>'+googlesearch;
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
	      '<a href='https://maps.google.com/maps?q='+googlesearch+'' style='color:#00f;text-decoration:underline;' target='_blank'>'+t('calendar','Detailed search at Google-Maps')+'</a>');
	  infowindow.open(map, marker);
	});
      google.maps.event.trigger(map, 'resize');
      map.setCenter(latlng);
    },
    resizeStop: function (event, ui) {
      var center = map.getCenter();
      google.maps.event.trigger(map, 'resize');
      map.setCenter(center);
    }
  });

};

const googleLocation = function(location, popupId, position) {
  var self = this;
  if (typeof popupId === 'undefined') {
    popupId = self.popupId;
  }
  var popupSelector = '#'+popupId;
  if ($(popupSelector).dialog('isOpen') == true) {
    $(popupSelector).dialog('close').remove();
  }
  if (typeof location === 'undefined') {
    location = $('input[name=location]').val();
  }
  var geocoder = new google.maps.Geocoder();
  geocoder.geocode( { 'address': location}, function(results, status) {
    if (status == google.maps.GeocoderStatus.OK) {
      var latlng = results[0].geometry.location;
      self.googlePopup(latlng, location, popupId, position);
    } else {
      var alerttext;
      if (location) {
	alerttext = t(appName, 'Location not found:')+' '+location;
      } else {
	alerttext = t(appName, 'No location specified.');
      }
      OC.dialogs.alert(alerttext, t(appName, 'Unknown location'));
      if (navigator.geolocation) {
	navigator.geolocation.getCurrentPosition(function(position) {
	  var latlng = new google.maps.LatLng(position.coords.latitude,
					      position.coords.longitude);
	  self.googlePopup(latlng, '', popupId, position);
	});
      }
    }
  });
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
