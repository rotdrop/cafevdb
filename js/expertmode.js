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

$(document).ready(function() {

  $('#appsettings_popup h2').html(t('cafevdb', 'Advanced operations, use with care'));

  $('#appsettings_popup #expertmode').
    off('click', 'button').
    on('click', 'button', function(event) {
    OC.dialogs.alert(t('cafevdb', 'Unhandled expert operation:')+
                     ' '+
                     $(this).val(),
                     t('cafevdb', 'Error'),
                     undefined, true, true);
    return false;
  });

  $('#setupdb').click(function() {
    var post  = $('#setupdb').serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'setupdb.php'),
           post,
           function(data) {
             if (!CAFEVDB.ajaxErrorHandler(data, ['success', 'error'])) {
               return;
             }
             OC.dialogs.alert(t('cafevdb', 'Successfull:')+
                              '<br/>'+
                              data.data.success+
                              '<br/>'+
                              t('cafevdb', 'Unsuccessfull:')+
                              '<br/>'+
                              '<pre>'+
                              data.data.error+
                              '</pre>',
                              t('cafevdb', 'Result of expert operation "setupdb"'),
                              undefined, true, true);
             $('#expertmode .msg').html(data.data.message);
           });
    return false;
  });

  $('#syncevents').click(function() {
    var post  = $('#syncevents').serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'syncevents.php'), post, function(data) {
      $('#expertmode .msg').html(data.data.message);
    });
    return false;
  });

  $('#makeviews').click(function() {
    var post  = $('#makeviews').serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'makeviews.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  $('#makewikiprojecttoc').click(function() {
    var post  = $(this).serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'makewikiprojecttoc.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  $('#attachwebpages').click(function() {
    var post  = $(this).serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'attachwebpages.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  $('#checkinstruments').click(function() {
    var post  = $('#checkinstruments').serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'checkinstruments.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  $('#adjustinstruments').click(function() {
    var post = $('#adjustinstruments').serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'adjustinstruments.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  $('#sanitizephones').click(function() {
    var post = $(this).serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'sanitizephones.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  // Update our cache of geo-data.
  $('#geodata').click(function() {
    var post = $(this).serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'geodata.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  // Update missing UUIDs
  $('#uuid').click(function() {
    var post = $(this).serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'uuid.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  // Update image meta-data (mime-type, MD5-hash)
  $('#imagemeta').click(function() {
    var post = $(this).serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'imagemeta.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  $('#example').click(function() {
    var post  = $('#example').serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'example.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  $('#clearoutput').click(function() {
    var post  = $('#clearoutput').serialize();
    $.post(OC.filePath('cafevdb', 'ajax/expertmode', 'clearoutput.php'), post, function(data) {
      $('#expertmode .msg').html(data);
    });
    return false;
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Tooltips
  //
  ///////////////////////////////////////////////////////////////////////////

  CAFEVDB.toolTipsInit('#appsettings_popup');

});
