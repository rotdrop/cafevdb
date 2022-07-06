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
import { getInitialState } from './services/initial-state-service.js';
import { generateFilePath, imagePath, generateUrl } from '@nextcloud/router';
import { showError, showInfo } from '@nextcloud/dialogs';
import { translate as t, translatePlural as n } from '@nextcloud/l10n';
import FilesTab from './views/FilesTab.vue';
import { createPinia, PiniaVuePlugin } from 'pinia';
import { Tooltip } from '@nextcloud/vue';
import fileDownload from './app/file-download.js';

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

let initialState = getInitialState();

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

const getProjectNameFromProjectBalancesFolders = function(dirInfo) {
  let dirName = dirInfo.path;
  if (!dirName.startsWith(initialState.sharing.files.folders.projectBalances)) {
    return false;
  }
  dirName = dirName.substring(initialState.sharing.files.folders.projectBalances.length);
  dirName = dirName.replace(/^\/?\d{4}\/?/, '');
  const slashPos = dirName.indexOf('/');
  const projectName = slashPos >= 0 ? dirName.substring(0, dirName.indexOf('/')) : dirName;
  return projectName;
};

const isProjectBalanceSupportingDocumentsFolder = function(dirInfo, projectName) {
  projectName = projectName || getProjectNameFromProjectBalancesFolders(dirInfo);
  const dirName = dirInfo.path;
  const baseName = dirInfo.name;
  return dirName.startsWith(initialState.sharing.files.folders.projectBalances)
    && baseName === initialState.sharing.files.subFolders.supportingDocuments;
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

  initialState = initialState || getInitialState();

  // menu file-actions can only depend on the literal local file-name,
  // the type and the mime-type.
  //
  // inline file-actions are probably a relict from earlier days and
  // can conditionally be enabled via the "shouldRender()" hook. The
  // provided context give access to several interesting data like the
  // directory and owner etc.

  if (OCA.Files && OCA.Files.fileActions) {
    const fileActions = OCA.Files.fileActions;

    // a menu entry in order to download a folder as multi-page pdf
    fileActions.registerAction({
      name: 'download-pdf',
      displayName: t(appName, 'Download PDF'),
      altText: t(appName, 'Download PDF'),
      mime: 'httpd/unix-directory',
      type: OCA.Files.FileActions.TYPE_DROPDOWN,
      permissions: OC.PERMISSION_READ,
      // shouldRender(context) {}, is not invoked for TYPE_DROPDOWN
      icon() {
        return imagePath('core', 'filetypes/application-pdf');
      },
      // render(actionSpec, isDefault, context) {}, is not invoked for TYPE_DROPDOWN
      /**
       * Handle multi-page PDF download request. Stolen from the
       * files-app download action handler.
       *
       * @param {string} dirName TBD.
       * @param {object} context TBD.
       */
      actionHandler(dirName, context) {
        console.info('DOWNLOAD PDF ACTION INVOKED', dirName, context);
        dirName = encodeURIComponent([
          context.fileList.dirInfo.path,
          context.fileList.dirInfo.name,
          dirName,
        ].join('/'));

        const url = generateUrl('/apps/' + appName + '/download/pdf/{dirName}', { dirName });

        // $file is a jQuery object, change that if the files-app gets overhauled
        const downloadFileaction = context.$file.find('.fileactions .action-download-pdf');

        // don't allow a second click on the download action
        if (downloadFileaction.hasClass('disabled')) {
          return;
        }

        if (url) {
          const disableLoadingState = function() {
            context.fileList.showFileBusyState(dirName, false);
          };

          context.fileList.showFileBusyState(dirName, true);
          // OCA.Files.Files.handleDownload(url, disableLoadingState);
          fileDownload(url, false, { always: disableLoadingState });
        }
      },
    });

    // an extra button which just will open the side-bar
    fileActions.registerAction({
      name: appName,
      displayName: false,
      altText: t(appName, 'MailMerge'),
      mime: 'all',
      type: OCA.Files.FileActions.TYPE_INLINE,
      // mime: 'application/pdf',
      permissions: OC.PERMISSION_READ,
      shouldRender(context) {
        // context.$file is a jQuery object
        const $file = context.$file[0];

        // 0: <tr class="" data-id="17874" data-type="file" data-size="40187" data-file="AdressAktualisierung-GeneralLastschrift.odt" data-mime="application/vnd.oasis.opendocument.text" data-mtime="1653855228000" data-etag="8c41b9a493acf47033cc070e137a8a88" data-quota="-1" data-permissions="27" data-has-preview="true" data-e2eencrypted="false" data-mounttype="shared" data-path="/camerata/templates/finance" data-share-permissions="19" data-share-owner="cameratashareholder" data-share-owner-id="cameratashareholder">

        // mock a file-info object
        const fileInfo = {
          isDirectory() {
            return $file.dataset.type !== 'file';
          },
          mimetype: $file.dataset.mime,
          path: $file.dataset.path,
        };

        return enableTemplateActions(fileInfo);
      },
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
      name: t(appName, 'MailMerge'),
      icon: 'icon-rename',
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
        const $tabHeader = context.$el.closest('.app-sidebar-tabs');
        const $iconSpan = $tabHeader.querySelector('#cafevdb .app-sidebar-tabs__tab-icon span');
        $iconSpan.style.backgroundImage = 'url(' + imagePath(appName, appName) + ')';
        $iconSpan.style.backgroundSize = '16px';
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

  if (OC.Plugins) {
    OC.Plugins.register('OCA.Files.NewFileMenu', {
      menuData: {
        id: 'project-supporting-document',
        displayName: t(appName, 'New Supporting Document'),
        templateName: t(appName, 'PROJECTNAME-XXX'),
        projectName: 'PROJECTNAME',
        fileList: null,
        iconClass: 'icon-folder',
        fileType: 'httpd/unix-directory',
        async actionHandler(name) {
          const nameRegExp = new RegExp('^(?:' + this.projectName + '-?)?' + '(\\d{3}|XXX)$');
          const sequenceMatch = name.match(nameRegExp);
          if (!sequenceMatch) {
            showError(t(appName, 'The name of the new document in support must match the format "{projectName}-XXX" where "XXX" is a placeholder for 3 decimal digits or a literatl "XXX" in which case the next available sequence number is chosen automatically.', this));
          }
          let sequence = sequenceMatch[1];
          if (sequence === 'XXX') {
            const sequences = [];
            for (const file of this.fileList.files) {
              sequences.push(+file.name.substr(file.name.length - 3));
            }
            sequences.sort((a, b) => a - b);
            let previous = 0;
            for (const current of sequences) {
              if (current - previous !== 1) {
                break;
              }
              previous = current;
            }
            sequence = previous + 1;
          }
          sequence = String(sequence).padStart(3, '0');
          showInfo(t(appName, 'Document sequence determined as {sequence}.', { sequence }));
          const dirName = this.projectName + '-' + sequence;
          await this.fileList.createDirectory(dirName);
        },
        checkFilename() {
          // this seems to be unused
          console.trace('CHECK FILENAME', arguments);
        },
      },
      savedMenuItems: null,
      installMenuNew(menu) {
        const fileList = menu.fileList;
        const dirInfo = fileList.dirInfo;
        const projectName = getProjectNameFromProjectBalancesFolders(dirInfo);

        if (!isProjectBalanceSupportingDocumentsFolder(dirInfo, projectName)) {
          if (this.savedMenuItems) {
            // the WOPI requests send to the richdocuments do not
            // contain enough authentication to access our data-base.
            const menuItems = dirInfo.mountType === 'cafevdb-database'
              ? this.savedMenuItems.filter((item) => !item.id.match('richdocuments') && item.id !== 'folder')
              : this.savedMenuItems;
            menu._menuItems = menuItems;
          }
          return;
        }

        this.menuData.projectName = projectName;
        this.menuData.templateName = projectName + '-XXX';
        this.menuData.actionHandler = this.menuData.actionHandler.bind(this.menuData);
        this.menuData.fileList = menu.fileList;

        menu._menuItems = [];
        menu.addMenuEntry(this.menuData);
      },
      attach(menu) {
        if (!this.savedMenuItems) {

          this.savedMenuItems = menu._menuItems;

          const menuRender = menu.render;
          menu.render = function() {
            menuRender.apply(this);
            if (isProjectBalanceSupportingDocumentsFolder(this.fileList.dirInfo)) {
              this.$el.find('ul li:first').remove();
            }
          }.bind(menu);

          menu.fileList.$el.on('afterChangeDirectory', (params) => {
            this.installMenuNew(menu);
          });
        }

        this.installMenuNew(menu);
      },
    });
  }
});
