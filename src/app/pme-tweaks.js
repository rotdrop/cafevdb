/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, $ } from './globals.js';
import * as Email from './email.js';
import { busyIcon as pageBusyIcon } from './page.js';
import { token as pmeToken, sys as PMEsys } from './pme-selectors.js';

// const qs = require('qs');
// require('qs/lib/index.js');
import * as qs from 'qs';

/**
 * Some general PME tweaks.
 *
 * @param {jQuery} container TBD.
 */
const pmeTweaks = function(container) {
  if (typeof container === 'undefined') {
    container = $('body');
  }

  container.find('input.date').datepicker({
    minDate: '01.01.1940', // birthday limit
  });

  // @todo this should be some sort of date-time picker
  container.find('input.datetime').datetimepicker({
    minDate: '01.01.1990',
  });

  container.find('td.money, td.signed-number').filter(function() {
    return $.trim($(this).text()).indexOf('-') === 0;
  }).addClass('negative');

  $(globalState.PHPMyEdit.defaultSelector + ' input.email.' + pmeToken('misc') + '.' + pmeToken('commit'))
    .off('click')
    .on('click', function(event) {
      pageBusyIcon(true);
      Email.emailFormPopup($(this.form).serialize(), true, false, () => pageBusyIcon(false));
      return false;
    });

  const form = container.find('form.' + pmeToken('form')).first();

  // open the email-form when clicking on a musician's or project
  // participant's email address.
  form.find('a.email').off('click').on('click', function(event) {
    event.preventDefault();
    const href = $(this).attr('href').split('?');
    if (href.length != 2) {
      return false;
    }
    const recordKey = PMEsys('rec');
    const params = qs.parse(href[1]);
    console.info('QUERY DATA', params);
    if (params[recordKey] === undefined) {
      return false;
    }
    let post = form.serialize();
    post += '&' + PMEsys('mrecs') + '[]=' + JSON.stringify(params[recordKey]);
    post += '&emailRecipients[MemberStatusFilter][0]=regular';
    post += '&emailRecipients[MemberStatusFilter][1]=passive';
    post += '&emailRecipients[MemberStatusFilter][2]=soloist';
    post += '&emailRecipients[MemberStatusFilter][3]=conductor';

    pageBusyIcon(true);
    Email.emailFormPopup(post, true, true, () => pageBusyIcon(false));

    return false;
  });

  // This could also be wrapped into a popup maybe, and lead back to
  // the brief-instrumentation table on success.
  // $(PHPMyEdit.defaultSelector + ' input.' + pmeToken('bulkcommit')).addClass('formsubmit');
};

export default pmeTweaks;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
