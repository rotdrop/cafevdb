/*
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { translate as t } from '@nextcloud/l10n'
import { appName as appId } from '../../config.ts'

export interface NavigationEntry {
  id: string,
  name: string,
}

export interface TabEntry extends NavigationEntry {
  key: string,
  adminOnly: boolean,
  children: NavigationEntry[],
}

/**
 * Single source of truth for the settings tree: top-level tabs and
 * their sub-sections. Used for rendering as well as for the in-page
 * navigation.
 *
 * @param isGroupAdmin Include the admin-only tabs.
 */
export const getSettingsTree = (isGroupAdmin: boolean): TabEntry[] => {
  const tabs: TabEntry[] = [
    {
      key: 'user',
      id: 'personal-settings-user',
      name: t(appId, 'Personal'),
      adminOnly: false,
      children: [
      { id: 'personal-settings', name: t(appId, 'Personal settings') },
      { id: 'encryption-key', name: t(appId, 'Encryption key') },
      ],
    },
    {
      key: 'general',
      id: 'personal-settings-general',
      name: t(appId, 'General'),
      adminOnly: true,
      children: [
      { id: 'general-settings', name: t(appId, 'General settings') },
      { id: 'encryption-settings', name: t(appId, 'Encryption settings') },
      { id: 'database-settings', name: t(appId, 'Database settings') },
      ],
    },
    {
      key: 'orchestra',
      id: 'personal-settings-orchestra',
      name: t(appId, 'Orchestra'),
      adminOnly: true,
      children: [
      { id: 'street-address', name: t(appId, 'Street Address') },
      { id: 'bank-account', name: t(appId, 'Bank Account') },
      { id: 'document-templates', name: t(appId, 'Document templates') },
      { id: 'executive-board-and-club-members', name: t(appId, 'Executive board and club members') },
      ],
    },
    {
      key: 'sharing',
      id: 'personal-settings-sharing',
      name: t(appId, 'Sharing'),
      adminOnly: true,
      children: [
      { id: 'share-owner', name: t(appId, 'Share owner') },
      { id: 'calendars', name: t(appId, 'Calendars') },
      { id: 'contacts', name: t(appId, 'Contacts') },
      { id: 'shared-folder', name: t(appId, 'Shared folder') },
      { id: 'members-as-cloud-users', name: t(appId, 'Members as Cloud-Users') },
      ],
    },
    {
      key: 'email',
      id: 'personal-settings-email',
      name: t(appId, 'Email'),
      adminOnly: true,
      children: [
      { id: 'server-settings', name: t(appId, 'Server Settings') },
      { id: 'email-account', name: t(appId, 'Email Account') },
      { id: 'bulk-email-composition', name: t(appId, 'Bulk Email Composition') },
      { id: 'bulk-email-privacy-notice', name: t(appId, 'Bulk Email Privacy Notice') },
      { id: 'mailing-list-service', name: t(appId, 'Mailing List Service') },
      { id: 'test-settings', name: t(appId, 'Test Settings') },
      ],
    },
    {
      key: 'cms',
      id: 'personal-settings-cms',
      name: t(appId, 'CMS'),
      adminOnly: true,
      children: [
      { id: 'redaxo-categories', name: t(appId, 'Redaxo Categories') },
      { id: 'redaxo-templates', name: t(appId, 'Redaxo Templates') },
      { id: 'redaxo-modules', name: t(appId, 'Redaxo Modules') },
      ],
    },
    {
      key: 'development',
      id: 'personal-settings-development',
      name: t(appId, 'Development'),
      adminOnly: true,
      children: [
      { id: 'links', name: t(appId, 'Links') },
      ],
    },
    {
      key: 'about',
      id: 'personal-settings-about',
      name: t(appId, 'About'),
      adminOnly: false,
      children: [
      ],
    },
  ]
  return tabs
    .filter((tab) => isGroupAdmin || !tab.adminOnly)
    .map((tab) => ({
      ...tab,
      children: tab.children.map((child) => ({ ...child, id: tab.id + '-' + child.id })),
    }))
}
