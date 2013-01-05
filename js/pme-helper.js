CAFEVDB.PME = {
  /**Collapse the somewhat lengthy text at the head of the PME-tables
   */
  collapsePageHeader: function() {
    var pfx    = 'div.'+CAFEVDB.name+'-pme-';
    var box    = $(pfx+'header-box');
    var header = $(pfx+'header');
    var body   = $(pfx+'body');
    var button = $(pfx+'header-box #viewtoggle');
    
    box.removeClass('expanded').addClass('collapsed');
    header.removeClass('expanded').addClass('collapsed');
    body.removeClass('expanded').addClass('collapsed');
    button.removeClass('expanded').addClass('collapsed');

    CAFEVDB.broadcastHeaderVisibility('collapsed');
  },
  /**Expand the somewhat lengthy text at the head of the email page.
   */
  expandPageHeader: function() {
    var pfx    = 'div.'+CAFEVDB.name+'-pme-';
    var box    = $(pfx+'header-box');
    var header = $(pfx+'header');
    var body   = $(pfx+'body');
    var button = $(pfx+'header-box #viewtoggle');

    box.addClass('expanded').removeClass('collapsed');
    header.addClass('expanded').removeClass('collapsed');
    body.addClass('expanded').removeClass('collapsed');
    button.addClass('expanded').removeClass('collapsed');

    CAFEVDB.broadcastHeaderVisibility('expanded');
  }
};

$(document).ready(function(){

  $('input[class^="pme-input-"][class$="-birthday"]').datepicker({
    dateFormat : 'dd.mm.yy'
  });

  $('td[class$="-money"]').filter(function() {
    return $.trim($(this).text()).indexOf("-") == 0;
  }).addClass("negative");
  
  $('#cafevdb-pme-header-box .viewtoggle').click(function(event) {
    event.preventDefault();

    var pfx    = 'div.'+CAFEVDB.name+'-pme-';
    var box    = $(pfx+'header-box');
    var header = $(pfx+'pme-header');
    var body   = $(pfx+'body');

    if (CAFEVDB.headervisibility == 'collapsed') {
      CAFEVDB.PME.expandPageHeader();
    } else {
      CAFEVDB.PME.collapsePageHeader();
    }

    return false;
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
