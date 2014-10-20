$(document).ready(function(){

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
