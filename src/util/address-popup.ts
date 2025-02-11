/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from '../config.ts';
import { translate as t } from '@nextcloud/l10n';
import type { Contact, Musician } from '../types/address-book.d.ts';

const addressItemUnknownLabel = (item: string) =>
  t(appName, '{item}: unknown', { item: t(appName, item) });

export const musicianAddressPopup = (option: Musician) => {
  if (option.id === 0) {
    return addressPopup(t(appName, 'selects all musicians'));
  }
  const name = option.informalDisplayName || '';
  const userId = option.userIdSlug ? ` (${option.userIdSlug})` : '';
  const email = option.email || addressItemUnknownLabel('email');
  const street = option.street || addressItemUnknownLabel('street');
  const streetNumber = option.streetNumber ? ' ' + option.streetNumber : '';
  const postalCode = option.postalCode && option.postalCode !== '0' ? option.postalCode + ' ' : '';
  const city = option.city || addressItemUnknownLabel('city');
  const additionalInfo = [email, street + streetNumber, postalCode + city];
  if (option.countryName) {
    let country = option.countryName;
    if (option.country) {
      country += ` (${option.country})`;
    }
    additionalInfo.push(country);
  }
  const content = `<h4>${name}${userId}</h4>` + additionalInfo.join('<br/>');
  return addressPopup(content);
};

export const contactNameFromContact = (option: Contact) => {
  const nameValue = option?.name as any;
  let name = nameValue?.value || nameValue;
  if (typeof name !== 'string') {
    name = t(appName, '[empty name]');
  }
  return name;
};

export const contactAddressPopup = (option: Contact) => {
  const name = contactNameFromContact(option);
  const additionalInfo: string[] = [];
  let emails: string[] = [];
  if (option.EMAIL) {
    for (const email of option.EMAIL as any[]) {
      const emailValue = (email.value || email) as string;
      if (typeof emailValue === 'string') {
        emails.push(`${emailValue}`);
      }
    }
  }
  if (emails.length > 0) {
    additionalInfo.push(emails.join('<br/>'));
  }
  let address: string[] = [];
  if (option.ADR && option.ADR.length > 0) {
    const  adrValue = option.ADR[0] as any;
    address = (adrValue.value || adrValue).split(';');
  }
  const street = address[2] || addressItemUnknownLabel('street');
  const postalCode = (address[5] + ' ') || '';
  const city = address[3] || addressItemUnknownLabel('city');
  const country = address[6] || addressItemUnknownLabel('country');
  additionalInfo.splice(additionalInfo.length, 0, street, postalCode + city, country);
  const book = option.addressBookName;
  if (book) {
    additionalInfo.push('[' + book + ']');
  }
  const content = `<h4>${name}</h4>`
    + additionalInfo.join('<br/>');
  return addressPopup(content);
};

export const addressPopup = (content: string) => {
  return {
    content,
    // placement: 'bottom',
    preventOverflow: false,
    boundariesElement: 'viewport',
    // shown: true,
    // triggers: [],
    html: true,
    csstag: ['vue-tooltip-data-popup'],
  };
};
