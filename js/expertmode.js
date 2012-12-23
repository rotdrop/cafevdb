$(document).ready(function(){

    $('button').tipsy({gravity:'ne', fade:true});
    $('input').tipsy({gravity:'ne', fade:true});
    $('label').tipsy({gravity:'ne', fade:true});

    if (toolTips) {
        $.fn.tipsy.enable();
    } else {
        $.fn.tipsy.disable();
    }

    $('#syncevents').click(function(){
        var post  = $( '#syncevents' ).serialize();
        $.post( OC.filePath('cafevdb', 'ajax/expertmode', 'syncevents.php'), post, function(data){
	    $('#expertmode .msg').html(data);
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

});
