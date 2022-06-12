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
import $ from './app/jquery.js';
import { getInitialState } from './services/initial-state-service.js';
import { generateFilePath, imagePath } from '@nextcloud/router';
import { translate as t, translatePlural as n } from '@nextcloud/l10n';
import FilesTab from './views/FilesTab.vue';
import { createPinia, PiniaVuePlugin } from 'pinia';

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

let initialState;

// @todo: we can of course support much more ...
const supportedMimeTypes = [
  'application/vnd.oasis.opendocument.text',
];

const acceptableMimeType = function(mimeType) {
  return supportedMimeTypes.indexOf(mimeType) >= 0;
};

const validTemplatePath = function(path) {
  return path.startsWith(initialState.sharing.files.templates);
};

const isEnabled = function(fileInfo) {

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

  initialState = getInitialState();

  console.info('INITIAL STATE', initialState);

  // menu file-actions can only depend on the literal local file-name,
  // the type and the mime-type.
  //
  // inline file-actions are probably a relict from earlier days and
  // can conditionally be enabled via the "shouldRender()" hook. The
  // provided context give access to several interesting data like the
  // directory and owner etc.

  if (OCA.Files && OCA.Files.fileActions) {
    const fileActions = OCA.Files.fileActions;
    fileActions.registerAction({
      name: appName,
      displayName: false,
      altText: t(appName, 'Template'),
      mime: 'all',
      type: OCA.Files.FileActions.TYPE_INLINE,
      // mime: 'application/pdf',
      permissions: OC.PERMISSION_READ,
      shouldRender(context) {
        const $file = $(context.$file);

        // 0: <tr class="" data-id="17874" data-type="file" data-size="40187" data-file="AdressAktualisierung-GeneralLastschrift.odt" data-mime="application/vnd.oasis.opendocument.text" data-mtime="1653855228000" data-etag="8c41b9a493acf47033cc070e137a8a88" data-quota="-1" data-permissions="27" data-has-preview="true" data-e2eencrypted="false" data-mounttype="shared" data-path="/camerata/templates/finance" data-share-permissions="19" data-share-owner="cameratashareholder" data-share-owner-id="cameratashareholder">

        // mock a file-info object
        const fileInfo = {
          isDirectory() {
            return $file.data('type') !== 'file';
          },
          mimetype: $file.data('mime'),
          path: $file.data('path'),
        };

        return isEnabled(fileInfo);
      },
      foobar: imagePath(appName, appName),
      iconClass() {
        return 'cafevdb-template';
      },
      render(actionSpec, isDefault, context) {
        const size = 32;
        const $html = fileActions._defaultRenderAction(actionSpec, isDefault, context);
        const $icon = $html.find('.icon');
        $icon.append('<img alt="' + actionSpec.altText + '" width="' + size + '" height="' + size + '" src="' + imagePath(appName, appName) + '">');
        $icon.css({ width: size + 'px', height: size + 'px' });
        $icon.attr('title', t(appName, 'Fill the template with substitutions. Opens the "Details" view for further options.'));
        $icon.tooltip({ placement: 'top' });
        return $html;
      },
      /**
       * @param {string} fileName TBD.
       *
       * @param {object} context TBD.
       * @param {jQuery} context.$file jQuery row corresponding to selected file.
       * @param {object} context.fileActions OCA.Files.fileActions instance.
       * @param {object} context.fileInfoModel Don't know.
       * @param {object} context.fileList File-list instance we are attached to.
       */
      actionHandler(fileName, context) {
        context.fileList.showDetailsView(fileName, 'cafevdb');
      },
    });
  }

  /**
   * Register a new tab in the sidebar
   */
  if (OCA.Files && OCA.Files.Sidebar) {
    OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab({
      id: appName,
      name: t(appName, 'Template'),
      icon: 'icon-rename',
      enabled: isEnabled,

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
        const $tabHeader = $(context.$el.closest('.app-sidebar-tabs'));
        $tabHeader.find('#cafevdb .app-sidebar-tabs__tab-icon span').css({
          'background-image': 'url(' + imagePath(appName, appName) + ')',
          'background-size': '16px',
        });
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
