var PHPMYEDIT = PHPMYEDIT || {
  filterSelectPlaceholder: 'Select a filter Option',
  filterSelectChosen: true,
  filterHandler:function(theForm, theEvent) {
    var pressed_key = null;
    if (theEvent.which) {
      pressed_key = theEvent.which;
    } else {
      pressed_key = theEvent.keyCode;
    }
    if (pressed_key == 13) { // enter pressed
      theForm.submit();
      return false;
    }
    return true;
  },
  init:function(pmepfx) {
    $("input[type='checkbox']."+pmepfx+"-sort").change(function(event) {
      return this.form.submit();
    });
    
    $("select."+pmepfx+"-goto").change(function(event) {
      return this.form.submit();
    });
    
    $("select."+pmepfx+"-pagerows").change(function(event) {
      return this.form.submit();
    });

    $("select[class^='"+pmepfx+"-filter']").change(function(event) {
      return this.form.submit();
    });

    $("input[class^='"+pmepfx+"-filter']").keypress(function(event) {
      return PHPMYEDIT.filterHandler(this.form, event);
    });

    $("input[type='submit'].pme-save").click(function(event) {
      return PHPMYEDIT.filterHandler(this.form, event);
    });

    $("input[type='submit'].pme-more").click(function(event) {
      return PHPMYEDIT.filterHandler(this.form, event);
    });

    if (PHPMYEDIT.filterSelectChosen) {
      $("select[class^='"+pmepfx+"-comp-filter']").chosen({width:"auto",  disable_search_threshold: 10});

      // Provide a data-placeholder and also remove the match-all
      // filter, which is not needed when using chosen.
      $("select[class^='"+pmepfx+"-filter']").attr("data-placeholder", PHPMYEDIT.filterSelectPlaceholder);
      $("select[class^='"+pmepfx+"-filter']").unbind('change');
      $("select[class^='"+pmepfx+"-filter'] option[value='*']").remove();
      $("select[class^='"+pmepfx+"-filter']").chosen({width:"100%"});
    }
  }
};

$(document).ready(function(){

  PHPMYEDIT.init('pme');

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
