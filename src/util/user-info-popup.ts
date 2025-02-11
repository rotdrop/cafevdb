/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2023, 2023, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import { translate as t, translatePlural as n } from '@nextcloud/l10n';
import type { CloudUser, CloudGroup } from '../stores/cloud-users-groups.ts'

export type UserOption = CloudUser | (Pick<CloudUser, 'id'|'displayname'> & Pick<Partial<CloudUser>, 'email'|'backend'>)

export const userInfoPopup = (userOption: UserOption) => {
  let name = userOption.displayname || userOption.id;
  if (userOption.id !== name) {
    name += ` (${userOption.id})`;
  }
  const additionalInfo: string[] = [];
  if (userOption.email) {
    additionalInfo.push(`${userOption.email}`);
  }
  if (userOption.backend) {
    additionalInfo.push(`${t(appName, 'Provider: {backend}', { backend: userOption.backend })}`);
  }
  const content = `<h4>${name}</h4>` + additionalInfo.join('<br/>');
  return {
    content,
    // placement: 'bottom',
    preventOverflow: false,
        boundariesElement: 'viewport',
    html: true,
    classes: ['vue-tooltip-user-info-popup'],
  };
};

export type GroupOption = CloudGroup | (Pick<CloudGroup, 'id'|'displayname'> & Pick<Partial<CloudGroup>, 'backends'|'usercount'>)

export const groupInfoPopup = (groupOption: GroupOption) => {
  let name = groupOption.displayname || groupOption.id;
  if (groupOption.id !== name) {
    name += ` (${groupOption.id})`;
  }
  const additionalInfo: string[] = [];
  if (groupOption.usercount) {
    additionalInfo.push(t(appName, '#Users: {usercount}', { usercount: groupOption.usercount }));
  }
  if (groupOption.backends) {
    const providers = groupOption.backends;
    additionalInfo.push(
      n(
        appName,
        'Provider: {providers}',
        'Providers: {providers}',
        providers.length,
        { providers: providers.join(', ') },
      ),
    );
  }
  const content = `<h4>${name}</h4>` + additionalInfo.join('<br/>');
  return {
    content,
    // placement: 'bottom',
    preventOverflow: false,
    boundariesElement: 'viewport',
    html: true,
    csstag: ['vue-tooltip-data-popup'],
  };
};
