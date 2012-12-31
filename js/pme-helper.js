/**Collapse the somewhat lengthy text at the head of the email page.
 */
collapsePMEPageHeader = function()
{
  var box    = $('div[class$="-pme-header-box"]');
  var header = $('div[class$="-pme-header"]');
  var body   = $('form.pme-form');    

  if (box.data('CAFEVDBheaderboxheight') === undefined) {
    box.data('CAFEVDBheaderboxheight', box.css('height'));
    box.data('CAFEVDBheaderheight', header.css('height'));
    box.data('CAFEVDBbodypadding', body.css('padding-top'));
  }
  box.css('height','4ex');
  header.css('height','3ex');
  body.css('padding-top', '12ex');
  box.data('CAFEVDBheadermodheight', box.css('height'));
  $('input[name="headervisibility"]').each(function (idx) {
    $(this).val('collapsed');
  });
  $('#viewtoggle-img').attr(
    'src', OC.filePath('', 'core/img/actions', 'download.svg'));
}

/**Expand the somewhat lengthy text at the head of the email page.
 */
expandPMEPageHeader = function()
{
  var box    = $('div[class$="-pme-header-box"]');
  var header = $('div[class$="-pme-header"]');
  var body   = $('form.pme-form');    

  var boxheight = box.data('CAFEVDBheaderboxheight');
  var height    = box.data('CAFEVDBheaderheight');
  var padding   = box.data('CAFEVDBbodypadding');
  box.css('height', boxheight);
  header.css('height', height);
  body.css('padding-top', padding);
  $('input[name="headervisibility"]').each(function (idx) {
    $(this).val('expanded');
  });
  $('#viewtoggle-img').attr(
    'src', OC.filePath('', 'core/img/actions', 'delete.svg'));
}

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

  if (headervisibility == 'collapsed') {
    collapsePMEPageHeader();
  }

  $('div[class$="-pme-header-box"] :button.viewtoggle').click(function(event) {
    event.preventDefault();
    var box    = $('div[class$="-pme-header-box"]');
    var header = $('div[class$="-pme-header"]');
      var body   = $('form.pme-form');    

    if (box.data('CAFEVDBheaderboxheight') === undefined) {
      collapsePMEPageHeader();
    } else if (box.css('height') == box.data('CAFEVDBheadermodheight')) {
      expandPMEPageHeader();
    } else {
      collapsePMEPageHeader();
    }
    return false;
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
