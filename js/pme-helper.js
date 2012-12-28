$(document).ready(function(){

    // $('td[class$="-money"]').filter(function() {
    //     return true; /*$.trim($(this).text()).indexOf('-') == 0;*/
    // }).addClass('negative')​;

    $('input[class^="pme-input-"][class$="-birthday"]').datepicker({
        dateFormat : 'dd.mm.yy'
    });

    $('td[class$="-money"]').filter(function() {
        return $.trim($(this).text()).indexOf("-") == 0;
    }).addClass("negative");

//     $('input[class^="pme-input-"][class$="-datetime"]').datetimepicker({
// //        dateFormat : 'dd-mm-yy'
// //			    showPeriodLabels: false
//     });

});
