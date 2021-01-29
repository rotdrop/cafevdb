/* Orchestra member, musicion and project management application.
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

import { globalState, appName } from './globals.js';
import * as Email from './email.js';

/**
 * Some general PME tweaks.
 *
 * @param container
 */
const pmeTweaks = function(container) {
  if (typeof container == 'undefined') {
    container = $('body');
  }

  container.find('input.date').datepicker({
    dateFormat : 'dd.mm.yy', // this is 4-digit year
    minDate: '01.01.1940'
  });

  container.find('input.datetime').datepicker({
    dateFormat : 'dd.mm.yy', // this is 4-digit year
    minDate: '01.01.1990'
  });

  container.find('td.money, td.signed-number').filter(function() {
    return $.trim($(this).text()).indexOf('-') == 0;
  }).addClass('negative');


  $(PHPMYEDIT.defaultSelector + ' input.pme-email').
    off('click').
    on('click', function(event) {
      event.stopImmediatePropagation();
      Email.emailFormPopup($(this.form).serialize());
      return false;
    });

  const form = container.find('form.pme-form').first();
  form.find('a.email').off('click').on('click', function(event) {
    event.preventDefault();
    const href = $(this).attr('href');
    const recordId = href.match(/[?]recordId=(\d+)$/);
    if (typeof recordId[1] != 'undefined') {
      recordId = recordId[1];
    } else {
      return false; // Mmmh, echo error diagnostics to the user?
    }
    const post = form.serialize();
    post += '&PME_sys_mrecs[]=' + recordId;
    post += '&emailRecipients[MemberStatusFilter][0]=regular';
    post += '&emailRecipients[MemberStatusFilter][1]=passive';
    post += '&emailRecipients[MemberStatusFilter][2]=soloist';
    post += '&emailRecipients[MemberStatusFilter][3]=conductor';
    Email.emailFormPopup(post, true, true);
    return false;
  });

  // This could also be wrapped into a popup maybe, and lead back to
  // the brief-instrumentation table on success.
  // $(PHPMYEDIT.defaultSelector + ' input.pme-bulkcommit').addClass('formsubmit');
};

export default pmeTweaks;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
