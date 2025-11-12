/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.ts';
import * as Ajax from './ajax.js';
import generateAppUrl from './generate-url.js';

const accountsAutocomplete = {
};

// b'url' => '/projects/accounting/autocomplete/gnucash-accounts/{project}',

/**
 * @param {(number|string)} projectIdentifier The project id or the project name.
 *
 * @returns {(Array|null)}
 */
const getGnuCashAccountsAutcompleteData = async (projectIdentifier) => {
  const autocompleteData = accountsAutocomplete[projectIdentifier];
  if (autocompleteData) {
    return autocompleteData;
  }
  const url = generateAppUrl('accounting/autocomplete/gnucash-accounts/' + projectIdentifier);
  try {
    const data = await $.get(url);
    accountsAutocomplete[data.projectName] = data.accounts;
    if (!isNaN(+projectIdentifier)) {
      const projectId = +projectIdentifier;
      accountsAutocomplete[projectId] = accountsAutocomplete[data.projectName];
    }
    return accountsAutocomplete[data.projectName];
  } catch (xhr) {
    await new Promise((resolve) => Ajax.handleError(xhr, 'error', xhr.statusText, resolve));
    return null;
  }
};

export default getGnuCashAccountsAutcompleteData;
