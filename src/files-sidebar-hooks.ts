/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { ISidebarContext } from '@nextcloud/files';
import type { FilesInitialState } from '../build/ts-types/php-modules/Controller/DTO.ts';

import { FileType, registerSidebarTab } from '@nextcloud/files';
import { translate as t } from '@nextcloud/l10n';
import { defineAsyncComponent, defineCustomElement } from 'vue';
import logoSvg from '../img/cafevdb.svg?raw';
import { appName } from './config.ts';
import getInitialState from './toolkit/util/initial-state.ts';

const sidebarTabTag = `${appName}-mailmerge-files-sidebar-tab` as const;

const initialState = getInitialState<FilesInitialState>({ section: 'files' });

// @todo: we can of course support much more ...
const supportedMimeTypes = [
  'application/vnd.oasis.opendocument.text',
];

const acceptableMimeType = function<T extends string>(mimeType: T) {
  return supportedMimeTypes.indexOf(mimeType) >= 0;
};

const validTemplatePath = function<T extends string>(path: T) {
  return initialState && path.startsWith(initialState.sharing.files.folders.templates);
};

const enableTemplateActions = function(context: ISidebarContext) {

  const node = context.node;

  if (node.type === FileType.Folder) {
    return false;
  }

  if (!acceptableMimeType(node.mime)) {
    return false;
  }

  if (!validTemplatePath(node.path)) {
    return false;
  }

  return true; // TODO depend on subdir etc.
};

if (window.customElements.get(sidebarTabTag) === undefined) {
  window.customElements.define(
    sidebarTabTag,
    defineCustomElement(defineAsyncComponent(() => import('./views/FilesTab.vue')), { shadowRoot: false }),
  );

  registerSidebarTab({
    id: `${appName}-mailmerge`,
    displayName: t(appName, 'MailMerge'),
    order: 50,
    iconSvgInline: logoSvg,
    tagName: sidebarTabTag,
    enabled: enableTemplateActions,
  });
}
