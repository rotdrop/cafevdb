/* Orchestra member, musicion and project management application.
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

$(document).ready(function(){

  var popup = $('#appsettings_popup');
  var button = $('#expertbutton');
  var arrow = popup.find('span.arrow.up');

  var offset = (popup.offset().left + popup.outerWidth()
               - button.offset().left - button.outerWidth()/2
               - arrow.outerWidth()/2);

  arrow.css({ right: offset });

  $('#appsettings_popup h2').html(t('cafevdb', 'Advanced operations, use with care'));

  $('#syncevents').click(function(){
    var post  = $( '#syncevents' ).serialize();
    $.post( OC.filePath('cafevdb', 'ajax/expertmode', 'syncevents.php'), post, function(data){
      $('#expertmode .msg').html(data.data.message);
    });
  });

  $('#makeviews').click(function(){
    var post  = $( '#makeviews' ).serialize();
    $.post( OC.filePath('cafevdb', 'ajax/expertmode', 'makeviews.php'), post, function(data){
      $('#expertmode .msg').html(data);
    });
  });

  $('#makewikiprojecttoc').click(function(){
    var post  = $(this).serialize();
    $.post( OC.filePath('cafevdb', 'ajax/expertmode', 'makewikiprojecttoc.php'), post, function(data){
      $('#expertmode .msg').html(data);
    });
  });

  $('#checkinstruments').click(function(){
    var post  = $( '#checkinstruments' ).serialize();
    $.post( OC.filePath('cafevdb', 'ajax/expertmode', 'checkinstruments.php'), post, function(data){
      $('#expertmode .msg').html(data);
    });
  });

  $('#adjustinstruments').click(function(){
    var post  = $( '#adjustinstruments' ).serialize();
    $.post( OC.filePath('cafevdb', 'ajax/expertmode', 'adjustinstruments.php'), post, function(data){
      $('#expertmode .msg').html(data);
    });
  });

  $('#example').click(function(){
    var post  = $( '#example' ).serialize();
    $.post( OC.filePath('cafevdb', 'ajax/expertmode', 'example.php'), post, function(data){
      $('#expertmode .msg').html(data);
    });
  });

  $('#clearoutput').click(function(){
    var post  = $( '#clearoutput' ).serialize();
    $.post( OC.filePath('cafevdb', 'ajax/expertmode', 'clearoutput.php'), post, function(data){
      $('#expertmode .msg').html(data);
    });
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Tooltips
  //
  ///////////////////////////////////////////////////////////////////////////

  CAFEVDB.tipsy('#appsettings_popup');

});
