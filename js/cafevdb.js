$(document).ready(function(){
    
    fillWindow($('#content'));

//    $('button.settings').tipsy({gravity:'ne', fade:true});
    $('button').tipsy({gravity:'w', fade:true});
    $('input.cafevdb-control').tipsy({gravity:'nw', fade:true});
    $('#controls button').tipsy({gravity:'nw', fade:true});
    $('.pme-sort').tipsy({gravity: 'n', fade:true});
    $('.pme-misc-check').tipsy({gravity: 'nw', fade:true});

    if (toolTips) {
        $.fn.tipsy.enable();
    } else {
        $.fn.tipsy.disable();
    }

    // $(window).click(function() {
    //     //hide the settings menu when clicking outside it
    //     if($('body').attr("id")==="body-user"){
    //         $('#appsettings_popup').hide();
    //     }
    // });

    $('#personalsettings .generalsettings').on('click keydown', function(event) {
	event.preventDefault();
	OC.appSettings({appid:'cafevdb', loadJS:true, cache:false, scriptName:'settings.php'});
    });

    $('#personalsettings .expert').on('click keydown', function(event) {
	event.preventDefault();
	OC.appSettings({appid:'cafevdb', loadJS:'expertmode.js', cache:false, scriptName:'expert.php'});
    });

    $(':button.events').on('click keydown', function(event) {
	if ($('#events').dialog('isOpen') == true) {
	    $('#events').dialog('destroy').remove();
	} else {
            var post = $(':button.events').serialize();
            $('#dialog_holder').load( OC.filePath('cafevdb', 'ajax/events', 'events.php'), post, function() {
		$('.tipsy').remove();
		//$('#fullcalendar').fullCalendar('unselect');
	        $('#events').tabs({ selected: 0});
                $('#events').dialog({
                    width : 500,
                    height: 700,
                    close : function(event, ui) {
                        $(this).dialog('destroy').remove();
                    }
                });
            });
        }
        return false;
	//OC.appSettings({appid:'cafevdb', loadJS:'expertmode.js', cache:false, scriptName:'expert.php'});
    });

});
