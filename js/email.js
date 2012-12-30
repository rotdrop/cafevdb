/**Collapse the somewhat lengthy text at the head of the email page.
 */
collapseEmailPageHeader = function()
{
  var box    = $('div[class$="-email-header-box"]');
  var header = $('div[class$="-email-header"]');
  var body   = $('div[class$="-email-body"]');    

  if (box.data('CAFEVDBheaderboxheight') === undefined) {
    box.data('CAFEVDBheaderboxheight', box.css('height'));
    box.data('CAFEVDBheaderheight', header.css('height'));
    box.data('CAFEVDBbodypadding', body.css('padding-top'));
  }
  box.css('height','4ex');
  header.css('height','3ex');
  body.css('padding-top', '12ex');
  box.data('CAFEVDBheadermodheight', box.css('height'));
  $('input[name="headervisibility"]').val('collapsed');
}

/**Expand the somewhat lengthy text at the head of the email page.
 */
expandEmailPageHeader = function()
{
  var box    = $('div[class$="-email-header-box"]');
  var header = $('div[class$="-email-header"]');
  var body   = $('div[class$="-email-body"]');    

  var boxheight = box.data('CAFEVDBheaderboxheight');
  var height    = box.data('CAFEVDBheaderheight');
  var padding   = box.data('CAFEVDBbodypadding');
  box.css('height', boxheight);
  header.css('height', height);
  body.css('padding-top', padding);
  $('input[name="headervisibility"]').val('expanded');
}

$(document).ready(function(){

  if (headervisibility == 'collapsed') {
    collapseEmailPageHeader();
  }

  $('div[class$="-email-header-box"] :button.viewtoggle').click(function(event) {
    event.preventDefault();
    var box    = $('div[class$="-email-header-box"]');
    var header = $('div[class$="-email-header"]');
    var body   = $('div[class$="-email-body"]');    

    if (box.data('CAFEVDBheaderboxheight') === undefined) {
      collapseEmailPageHeader();
    } else if (box.css('height') == box.data('CAFEVDBheadermodheight')) {
      expandEmailPageHeader();
    } else {
      collapseEmailPageHeader();
    }
    return false;
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
