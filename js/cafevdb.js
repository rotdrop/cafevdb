$(document).ready(function(){
    
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
	OC.appSettings({appid:'cafevdb', loadJS:'expertmode.js', cache:false, scriptName:'expert.php'});
    });

});
