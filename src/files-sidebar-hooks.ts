/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from './config.ts';
import { getInitialState } from './services/initial-state-service.ts';
import { generateFilePath } from '@nextcloud/router';
import { getRequestToken } from '@nextcloud/auth';
import { translate as t } from '@nextcloud/l10n';
import logoSvg from '../img/cafevdb.svg?raw';
import type { LegacyFileInfo } from '@nextcloud/files'

// eslint-disable-next-line camelcase
__webpack_nonce__ = btoa(getRequestToken() || '');

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath(appName, '', '');

interface FilesTab extends Vue {
  update(fileInfo: LegacyFileInfo): Promise<unknown>,
}

let OCA = window.OCA;

let TabInstance: undefined|FilesTab = undefined;

if (!OCA.CAFEVDB) {
  OCA.CAFEVDB = {};
}

const initialState = getInitialState();

// @todo: we can of course support much more ...
const supportedMimeTypes = [
  'application/vnd.oasis.opendocument.text',
];

const acceptableMimeType = function<T extends string>(mimeType: T) {
  return supportedMimeTypes.indexOf(mimeType) >= 0;
};

const validTemplatePath = function<T extends string>(path: T) {
  return path.startsWith(initialState.sharing.files.folders.templates);
};

const enableTemplateActions = function(fileInfo: LegacyFileInfo) {

  if (fileInfo && fileInfo.isDirectory()) {
    return false;
  }

  if (!acceptableMimeType(fileInfo.mimetype)) {
    return false;
  }

  if (!validTemplatePath(fileInfo.path)) {
    return false;
  }

  OCA.CAFEVDB.fileInfo = fileInfo;

  return true; // TODO depend on subdir etc.
};

window.addEventListener('DOMContentLoaded', () => {

  /**
   * Register a new tab in the sidebar
   */
  if (OCA.Files && OCA.Files.Sidebar) {
    OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab({
      id: appName + '-mailmerge',
      name: t(appName, 'MailMerge'),
      iconSvg: logoSvg,
      enabled: enableTemplateActions,
      async mount<VueType extends Vue>(el: HTMLElement, fileInfo: LegacyFileInfo, context: VueType) {
        const FilesTabAsset = (await import('./files-tab.ts'));
        const factory = FilesTabAsset.default;

        if (TabInstance) {
          TabInstance.$destroy();
        }

        TabInstance = factory(context)

        // Only mount after we hahve all the info we need
        await TabInstance.update(fileInfo);
        TabInstance.$mount(el);
      },
      update(fileInfo: LegacyFileInfo) {
        TabInstance!.update(fileInfo);
      },
      destroy() {
        if (TabInstance !== undefined) {
          TabInstance.$destroy();
        }
        TabInstance = undefined;
      },
    }));
  }
});
