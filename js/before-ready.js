/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

$(function() {

  // ???? needed ????
  $.widget("ui.dialog", $.ui.dialog, {
    _allowInteraction: function(event) {
      return !!$(event.target).closest(".mce-container").length || this._super( event );
    }
  });

  if (false) {
    // should somehow depend on debug mode.
    $(document).on('ajaxError', function(event, xhr, settings, error) {
      OC.dialogs.alert(t('cafevdb', 'Unhandled internal AJAX error:')+
                       '<br/>'+
                       t('cafevdb', 'Error')+': '+error+
                       '<br/>'+
                       t('cafevdb', 'URL')+': '+settings.url,
                       t('cafevdb', 'Error'),
                       undefined, true, true);
      return false;
    });
  }

  const content = $('#content');

  content.on('cafevdb:content-update', function(event) {
    $.fn.cafevTooltip.remove(); // remove any left-over items.
  });

  content.on('chosen:showing_dropdown', 'select', function(event, params)   {
    const results = params.chosen.search_results;
    const menuItems = results.find('li');
    menuItems.cafevTooltip({placement:'right'});
    if (!CAFEVDB.toolTipsEnabled) {
      menuItems.cafevTooltip('disable');
    }
    $.fn.cafevTooltip.remove(); // remove any left-over items.
  });

  // Any pending form-submit which has not been caught otherwise is
  // here intercepted and redirected to the page-loader in order to
  // reduce load-time and to record usable history information.
  content.on('submit', 'form', function(event) {
    console.info('Catchall form submit', event);
    const form = $(this);
    const action = form.attr('action');
    if (action != '') {
      // not for us, external target.
      return true;
    }
    const post = form.serialize();
    //alert('post: '+post);
    CAFEVDB.Page.loadPage(post);
    return false;
  });

  // Any pending form-submit which has not been caught otherwise is
  // here intercepted and redirected to the page-loader in order to
  // reduce load-time and to record usable history information.
  content.on('click', ':submit', function(event) {
    console.info('Catchall form submit input', event);
    const form = $(this.form);
    const action = self.attr('formaction');
    if (action != '') {
      return true; // not for us
    }
    const post = form.serialize();
    const self = $(this);
    if (self.attr('name')) {
      var obj = {};
      obj[self.attr('name')] = self.val();
      post += '&' + $.param(obj);
    }
    //alert('post: '+post);
    CAFEVDB.Page.loadPage(post);
    return false;
  });

  // Intercept app-navigation events here and redirect to the page
  // loader
  content.on('click', 'ul#navigation-list li a', function(event) {
    const target = $(event.target);
    if (target.is('.nav-heading a')) {
      // don't recurse on nav-heading which just should close the sidebar.
      return true;
    }
    const post = $(this).data('post');
    CAFEVDB.Page.loadPage(post);
    //alert('post: '+post);
    return false;
  });


  const musiciansCallback = {
    callback: function(selector, parameters, resizeCB) {

      if (parameters.reason == 'tabChange') {
        resizeCB();
        return;
      }

      const container = $(selector);
      CAFEVDB.exportMenu(selector);

      container.find('div.photo, #cafevdb_inline_image_wrapper').
        off('click', 'img.zoomable').
        on('click', 'img.zoomable', function(event) {
          event.preventDefault();
          CAFEVDB.Photo.popup(this);
          return false;
        });

      CAFEVDB.Musicians.ready(container);

      $(':button.musician-instrument-insurance').click(function(event) {
        event.preventDefault();
        const values = $(this).attr('name');

        CAFEVDB.Page.loadPage($(this).attr('name'));

        return false;
      });

      if (container.find('#contact_photo_upload').length > 0) {
        const idField = container.find('input[name="PME_data_id"]');
        var recordId = -1;
        if (idField.length > 0) {
          recordId = idField.val();
        }
        CAFEVDB.Photo.ready(recordId, 'MusicianPhoto', resizeCB);
      } else {
        container.find('div.photo, span.photo').imagesLoaded(resizeCB);
      }
    },
    context: CAFEVDB,
    parameters: []
  };

  PHPMYEDIT.addTableLoadCallback('all-musicians', musiciansCallback);
  PHPMYEDIT.addTableLoadCallback('add-musicians', musiciansCallback);

  PHPMYEDIT.addTableLoadCallback('project-extra-fields', {
    callback: function(selector, parameters, resizeCB) {

      if (parameters.reason != 'dialogOpen') {
        resizeCB();
        return;
      }

      CAFEVDB.ProjectExtra.ready(selector, resizeCB);
    },
    context: CAFEVDB,
    parameters: []
  });


  PHPMYEDIT.addTableLoadCallback('instruments', {
    callback: function(selector, parameters, resizeCB) {
      resizeCB();
    },
    context: CAFEVDB,
    parameters: []
  });

  PHPMYEDIT.addTableLoadCallback('instrument-families', {
    callback: function(selector, parameters, resizeCB) {
      resizeCB();
    },
    context: CAFEVDB,
    parameters: []
  });

  PHPMYEDIT.addTableLoadCallback('project-payments', {
    callback: function(selector, parameters, resizeCB) {
      resizeCB();
    },
    context: CAFEVDB,
    parameters: []
  });

  PHPMYEDIT.addTableLoadCallback('debit-notes', {
    callback: function(selector, parameters, resizeCB) {

      if (parameters.reason != 'dialogOpen') {
        resizeCB();
        return;
      }

      CAFEVDB.DebitNotes.ready(selector, resizeCB);
    },
    context: CAFEVDB,
    parameters: []
  });

  CAFEVDB.addReadyCallback(function() {
    CAFEVDB.exportMenu();

    CAFEVDB.pmeTweaks();

    CAFEVDB.toolTipsInit();

    // Prevent drag&drop outside allowed areas.
    window.addEventListener("dragover", function(e) {
      e = e || event;
      e.preventDefault();
      console.info("Prevented dragover event");
    }, false);
    window.addEventListener("drop", function(e) {
      e = e || event;
      e.preventDefault();
      console.info("Prevented drop event");
    }, false);

  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
