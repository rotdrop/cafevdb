/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import Vue from 'vue';
import { appName } from './app/app-info.js';
import { generateFilePath, imagePath } from '@nextcloud/router';
import { translate as t, translatePlural as n } from '@nextcloud/l10n';
import FilesTab from './views/FilesTab.vue';

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath(appName, '', 'js');
Vue.mixin({ data() { return { appName }; }, methods: { t, n } });

const View = Vue.extend(FilesTab);
let TabInstance = null;

if (!window.OCA.CAFEVDB) {
  window.OCA.CAFEVDB = {};
}

const isEnabled = function(fileInfo) {
  if (fileInfo && fileInfo.isDirectory()) {
    return false;
  }

  window.OCA.CAFEVDB.fileInfo = fileInfo;

  return true; // TODO depend on subdir etc.
};

window.addEventListener('DOMContentLoaded', () => {

  // menu file-actions can only depend on the literal local file-name,
  // the type and the mime-type.
  //
  // inline file-actions are probably a relict from earlier days and
  // can conditionally enabled via the "shouldRender()" hook. The
  // provided context give access to several interesting data like the
  // directory and owner etc.

  const specialName = 'AdressAktualisierung-GeneralLastschrift.odt';
  if (OCA.Files && OCA.Files.fileActions) {
    console.info('REGISTER FILE ACTION FOR', specialName);
    OCA.Files.fileActions.registerAction({
      name: appName,
      displayName: t(appName, 'Example File Action'),
      filename: specialName,
      mime: 'all',
      type: 1,
      // mime: 'application/pdf',
      permissions: OC.PERMISSION_READ,
      shouldRender(context) {
        console.info('CONTEXT', context);
        return true;
      },
      icon() {
        return imagePath(appName, appName);
      },
      actionHandler(fileName) {
        console.info('CAFEDB ACTION', arguments);
      },
    });
  }

  /**
   * Register a new tab in the sidebar
   */
  if (OCA.Files && OCA.Files.Sidebar) {
    OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab({
      id: appName,
      name: t(appName, appName),
      icon: 'icon-rename',
      enabled: isEnabled,

      async mount(el, fileInfo, context) {
        if (TabInstance) {
          TabInstance.$destroy();
        }

        TabInstance = new View({
          // Better integration with vue parent component
          parent: context,
        });

        // Only mount after we hahve all theh info we need
        await TabInstance.update(fileInfo);

        TabInstance.$mount(el);
      },
      update(fileInfo) {
        TabInstance.update(fileInfo);
      },
      destroy() {
        TabInstance.$destroy();
        TabInstance = null;
      },
    }));
  }
});
