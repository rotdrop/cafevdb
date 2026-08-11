/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020,-2022, 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

// const qs = require('qs');
// require('qs/lib/index.js');
import { parse as qsParse } from 'qs';
import { EnumParticipationStatus } from '../../build/ts-types/php-modules/Database/Doctrine/DBAL/Types.ts';
import { EnumPostTag as EmailPostTag } from '../../build/ts-types/php-modules/EmailForm.ts';
import { PARTICIPATION_STATUS_FILTER } from '../../build/ts-types/php-modules/EmailForm/RecipientsFilterCgiKeys.ts';
import { MRECS_KEY } from '../../build/ts-types/php-modules/PageRenderer/DataConstants.ts';
import pageBusyIcon from './busy-icon.ts';
import { emailFormPopup } from './email.ts';
import { globalState } from './globals.ts';
import $ from './jquery.ts';
import { sys as PMEsys, token as pmeToken } from './pme-selectors.ts';

import './jquery-datetimepicker.ts';

/**
 * Cleanup-function to call before replacing HTML content
 *
 * @param $container TBD.
 */
const pmeUnTweak = function($container: JQuery) {
  if (typeof $container === 'undefined') {
    $container = $('body');
  }

  $container.find('input.date').each(function() {
    const $this = $(this);
    if ($this.hasClass('hasDatepicker')) {
      $this.datepicker('hide').datepicker('destroy');
    }
  });

  $container.find('input.datetime').each(function() {
    const $this = $(this);
    // @ts-expect-error 2345 do not know wtf
    $this.datetimepicker('hide').datetimepicker('destroy');
  });
};

/**
 * Some general PME tweaks.
 *
 * @param $container TBD.
 */
const pmeTweaks = function($container?: JQuery) {
  if (typeof $container === 'undefined') {
    $container = $('body');
  }

  const $dateInputs = $container.find('input.date');
  $dateInputs.each(function() {
    const $this = $(this);
    if ($this.hasClass('hasDatepicker')) {
      $this.datepicker('destroy');
    }
  });
  $dateInputs.datepicker({
    minDate: '01.01.1940', // birthday limit
  });

  const $dateTimeInputs = $container.find('input.datetime');
  $dateTimeInputs.datetimepicker('destroy');
  $dateTimeInputs.datetimepicker({
    minDate: '01.01.1990',
    step: 5,
  });

  $container.find('td.money, td.signed-number').filter(function() {
    return $(this).text().trim().indexOf('-') === 0;
  }).addClass('negative');

  // Email-popup after clicking on global email button
  $(globalState.PHPMyEdit.defaultSelector + ' input.email.' + pmeToken('misc') + '.' + pmeToken('commit'))
    .off('click')
    .on('click', function(/* this: HTMLInputElement */) {
      pageBusyIcon(true);
      emailFormPopup($((this as HTMLInputElement).form as HTMLFormElement).serialize(), true, false, () => pageBusyIcon(false));
      return false;
    });

  const form = $container.find('form.' + pmeToken('form')).first();

  // open the email-form when clicking on a musician's or project
  // participant's email address.
  form.find('a.email').off('click').on('click', function(event) {
    event.preventDefault();
    const href = $(this).attr('href')!.split('?');
    if (href.length !== 2) {
      return false;
    }
    const recordKey = PMEsys('rec');
    const params = qsParse(href[1]);
    if (params[recordKey] === undefined) {
      return false;
    }
    let post = form.serialize();
    post += `&${PMEsys(MRECS_KEY)}[]=${JSON.stringify(params[recordKey])}`;
    for (const status of Object.values(EnumParticipationStatus)) {
      post += `&${EmailPostTag.RECIPIENTS_FILTER}[${PARTICIPATION_STATUS_FILTER}][]=${status}`;
    }

    pageBusyIcon(true);
    emailFormPopup(post, true, true, () => pageBusyIcon(false));

    return false;
  });

  // This could also be wrapped into a popup maybe, and lead back to
  // the brief-instrumentation table on success.
  // $(PHPMyEdit.defaultSelector + ' input.' + pmeToken('bulkcommit')).addClass('formsubmit');
};

export {
  pmeTweaks as tweaks,
  pmeUnTweak as unTweak,
};
