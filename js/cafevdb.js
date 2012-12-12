$(document).ready(function(){
    $('#personalsettings .generalsettings').on('click keydown', function(event) {
	event.preventDefault();
	OC.appSettings({appid:'cafevdb', loadJS:true, cache:false, scriptName:'settings.php'});
    });
});
