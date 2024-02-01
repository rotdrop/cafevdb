/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import { getInitialState } from './services/initial-state-service.js';
import { generateFilePath } from '@nextcloud/router';
import { translate as t, translatePlural as n } from '@nextcloud/l10n';
import FilesTab from './views/FilesTab.vue';
import { createPinia, PiniaVuePlugin } from 'pinia';
import { Tooltip } from '@nextcloud/vue';
// eslint-disable-next-line
import logoSvg from '../img/cafevdb.svg?raw';

Vue.directive('tooltip', Tooltip);

Vue.use(PiniaVuePlugin);
const pinia = createPinia();

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath(appName, '', 'js');
Vue.mixin({ data() { return { appName }; }, methods: { t, n } });

const View = Vue.extend(FilesTab);
let TabInstance = null;

if (!window.OCA.CAFEVDB) {
  window.OCA.CAFEVDB = {};
}

const initialState = getInitialState();

// @todo: we can of course support much more ...
const supportedMimeTypes = [
  'application/vnd.oasis.opendocument.text',
];

const acceptableMimeType = function(mimeType) {
  return supportedMimeTypes.indexOf(mimeType) >= 0;
};

const validTemplatePath = function(path) {
  return path.startsWith(initialState.sharing.files.folders.templates);
};

const enableTemplateActions = function(fileInfo) {

  if (fileInfo && fileInfo.isDirectory()) {
    return false;
  }

  if (!acceptableMimeType(fileInfo.mimetype)) {
    return false;
  }

  if (!validTemplatePath(fileInfo.path)) {
    return false;
  }

  window.OCA.CAFEVDB.fileInfo = fileInfo;

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

      async mount(el, fileInfo, context) {

        if (TabInstance) {
          TabInstance.$destroy();
        }

        TabInstance = new View({
          // Better integration with vue parent component
          parent: context,
          pinia,
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
