/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import * as Ajax from './ajax.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import type { AutocompleteGnuCashAccountsResponse, GnuCashAccountsAutocompleteData } from '../../build/ts-types/php-modules/Controller/DTO.ts';
import type { ResponseData } from '../types/ajax/response-data.d.ts';
import { END_POINT as autocompleteEndPoint } from '../../build/ts-types/php-modules/Controller/AccountingController.ts';

type AccountsAutocompleteData = {
  [key: string|number]: GnuCashAccountsAutocompleteData,
};

const accountsAutocomplete: AccountsAutocompleteData = {};

// 'url' => '/projects/accounting/autocomplete/gnucash-accounts/{project}',

/**
 * Fetch the GnuCash accounts autocompletion data. If none is available, return null on error.
 *
 * @param projectIdentifier The project id or the project name.
 */
const getGnuCashAccountsAutcompleteData = async (projectIdentifier: string|number) => {
  if (+projectIdentifier <= 0) {
    console.trace('Non-positive project id, bailing out.', { projectIdentifier });
    return null;
  }
  const autocompleteData = accountsAutocomplete[projectIdentifier];
  if (autocompleteData) {
    return autocompleteData;
  }
  const url = generateAppUrl(`${autocompleteEndPoint}/${projectIdentifier}`);
  try {
    const data: ResponseData<AutocompleteGnuCashAccountsResponse> = await $.get(url);
    if (!Ajax.validateResponse(data, ['projectName', 'accounts'])) {
      return null;
    }
    accountsAutocomplete[data.projectName] = data.accounts;
    if (!isNaN(+projectIdentifier)) {
      const projectId = +projectIdentifier;
      accountsAutocomplete[projectId] = accountsAutocomplete[data.projectName];
    }
    return accountsAutocomplete[data.projectName];
  } catch (xhr) {
    await new Promise((resolve) => Ajax.handleError(xhr as JQuery.jqXHR, 'error', (xhr as JQuery.jqXHR).statusText, resolve));
    return null;
  }
};

export default getGnuCashAccountsAutcompleteData;
