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

export interface AddressBook {
  key: string|number,
  uid: string,
  uri?: string,
  FN?: string,
  displayName: string,
  isSystemAddressBook?: boolean,
  $isDisabled?: boolean,
}

export interface Contact {
  key: number|string,
  name?: string|{ value: string },
  label: string,
  addressBookName?: string,
  $isDisabled?: boolean,
  UID: string,
  URI?: string,
  FN?: string
  EMAIL?: string[] | { value: string }[],
  informalDisplayName?: string,
  ADR?: string[] | { value: string }[],
}

export interface Musician {
  id: number,
  formalDisplayName: string,
  informalDisplayName?: string,
  userIdSlug?: string,
  email?: string,
  street?: string,
  city?: string,
  streetNumber?: string,
  postalCode?: string,
  countryName?: string,
  country?: string,
}
