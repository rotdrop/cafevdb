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
import { showError, showInfo } from '@nextcloud/dialogs';
import { addNewFileMenuEntry, registerFileAction, FileAction, Node, FileType, Permission, View } from '@nextcloud/files';
import { action as sidebarAction } from '../../files/src/actions/sidebarAction.ts';
import { translate as t, translatePlural as n } from '@nextcloud/l10n';
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

import logoSvg from '../img/cafevdb.svg?raw';

console.info('LOGO SVG', logoSvg);

declare global {
  interface Window {
    OCA: any;
    OCP: any;
  }
  // var __webpack_public_path__: string;
}

Vue.directive('tooltip', Tooltip);

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath(appName, '', 'js');
Vue.mixin({ data() { return { appName }; }, methods: { t, n } });

if (!window.OCA.CAFEVDB) {
  window.OCA.CAFEVDB = {};
}

const initialState = getInitialState();

const projectBalancesFolder = initialState.sharing.files.folders.projectBalances;
const supportingDocumentsFolder = initialState.sharing.files.subFolders.supportingDocuments;

// @todo: we can of course support much more ...
const supportedMimeTypes = [
  'application/vnd.oasis.opendocument.text',
];

const acceptableMimeType = function(mimeType: string|undefined) {
  return mimeType !== undefined && supportedMimeTypes.indexOf(mimeType) >= 0;
};

const validTemplatePath = function(path: string) {
  return path.startsWith(initialState.sharing.files.folders.templates);
};

const getProjectNameFromProjectBalancesFolders = function(dirInfo) {
  let dirName = dirInfo.path;
  if (!dirName.startsWith(initialState.sharing.files.folders.projectBalances)) {
    return null;
  }
  dirName = dirName.substring(initialState.sharing.files.folders.projectBalances.length);
  dirName = dirName.replace(/^\/?(\d{4}|)\/?/, '');
  const slashPos = dirName.indexOf('/');
  const projectName = slashPos >= 0 ? dirName.substring(0, dirName.indexOf('/')) : dirName;
  return projectName;
};

const getProjectYearFromProjectName = function(projectName) {
  if (!projectName) {
    return null;
  }
  const yearMatch = projectName.match(/\d{4}$/);
  if (Array.isArray(yearMatch) && yearMatch.length === 1) {
    return yearMatch[0];
  }
  return null;
};

const isProjectBalanceSupportingDocumentsTopFolder = function(dirInfo, projectName) {
  projectName = projectName || getProjectNameFromProjectBalancesFolders(dirInfo);
  if (!projectName) {
    return false;
  }
  const dirName = dirInfo.path;
  const baseName = dirInfo.name;
  return dirName.startsWith(projectBalancesFolder)
    && baseName === supportingDocumentsFolder;
};

const isProjectBalanceSupportingDocumentsFolder = function(dirInfo, projectName, projectYear) {
  projectName = projectName || getProjectNameFromProjectBalancesFolders(dirInfo);
  if (!projectName) {
    return false;
  }
  projectYear = projectYear || getProjectYearFromProjectName(projectName);
  const dirName = dirInfo.path;
  const baseName = dirInfo.name;
  if (projectYear) {
    return isProjectBalanceSupportingDocumentsTopFolder(dirInfo, projectName);
  } else {
    const result = dirName.startsWith(projectBalancesFolder + '/' + projectName + '/' + supportingDocumentsFolder)
      && baseName.match(/\d{4}$/);
    return result;
  }
};

const enableTemplateActions = function(node: Node) {

  if (node && node.type === FileType.Folder) {
    return false;
  }

  if (!acceptableMimeType(node.mime)) {
    return false;
  }

  if (!validTemplatePath(node.path)) {
    return false;
  }

  window.OCA.CAFEVDB.node = node;

  return true; // TODO depend on subdir etc.
};

registerFileAction(new FileAction({
  id: appName + '-mailmerge',
  displayName(/*nodes: Node[], view: View*/) {
    return '';
  },
  title(/* files: Node[], view: View */) {
    return t(appName, 'Perform mail-merge operation with this template file.');
  },
  iconSvgInline(/* files: Node[], view: View) */) {
    return logoSvg;
  },
  enabled(nodes: Node[]/* , view: View) */) {
    return nodes.length === 1 && enableTemplateActions(nodes[0]);
  },
  async exec(node: Node, view: View, dir: string) {
    // You need read permissions to see the sidebar
    if ((node.permissions & Permission.READ) !== 0) {
      window.OCA?.Files?.Sidebar?.setActiveTab?.(appName + '-mailmerge');

      return sidebarAction.exec(node, view, dir);
    }
    return null;
  },
  inline: () => true,
}));

addNewFileMenuEntry({
  id: appName + '-project-supporting-document-folder',
  displayName: t(appName, 'New Supporting Document'),
  enabled() {
  },
});

addNewFileMenuEntry({
  id: appName + '-project-supporting-document-year-folder',
  displayName: t(appName, 'New Supporting Document '),
  enabled() {
  },
});

window.addEventListener('DOMContentLoaded', () => {

  // menu file-actions can only depend on the literal local file-name,
  // the type and the mime-type.
  //
  // inline file-actions are probably a relict from earlier days and
  // can conditionally be enabled via the "shouldRender()" hook. The
  // provided context give access to several interesting data like the
  // directory and owner etc.

  if (OC.Plugins) {
    OC.Plugins.register('OCA.Files.NewFileMenu', {
      menuData: {
        id: 'project-supporting-document',
        displayName: t(appName, 'New Supporting Document'),
        templateName: t(appName, 'PROJECTNAME-XXX'),
        folderPrefix: 'PROJECTNAME',
        projectYear: 'PROJECTYEAR',
        isTopFolder: true,
        fileList: null,
        iconClass: 'icon-folder',
        fileType: 'httpd/unix-directory',
        async supportingDocumentHandler(name) {
          const nameRegExp = new RegExp('^(?:' + this.folderPrefix + '-?)?' + '(\\d{3}|XXX)$');
          const sequenceMatch = name.match(nameRegExp);
          if (!sequenceMatch) {
            showError(t(appName, 'The name of the new document in support must match the format "{projectName}-XXX" where "XXX" is a placeholder for 3 decimal digits or a literal "XXX" in which case the next available sequence number is chosen automatically.', this));
          }
          let sequence = sequenceMatch[1];
          if (sequence === 'XXX') {
            const sequences = [];
            for (const file of this.fileList.files) {
              if (file.mimetype !== 'httpd/unix-directory') {
                continue;
              }
              if (!file.name.match(nameRegExp)) {
                continue;
              }
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
          const dirName = this.folderPrefix + '-' + sequence;
          await this.fileList.createDirectory(dirName);
        },
        async yearFolderHandler(name) {
          const nameRegExp = /^(\d{4}|YYYY)$/;
          const yearMatch = name.match(nameRegExp);
          if (!yearMatch) {
            showError(t(appName, 'The name of the new folder must match the format "YYYY" where "YYYY" is a placeholder for 4 decimal digits or a literal "YYYY" in which case a folder for the current year is created if it does not exist already.'));
          }
          let year = yearMatch[1];
          if (year === 'YYYY') {
            year = new Date().getFullYear();
          }
          showInfo(t(appName, 'Year determined as {year}.', { year }));
          const dirName = '' + year;
          const existing = this.fileList.files.find(file => file.name === dirName);
          if (existing) {
            showError(t(appName, 'There is already a directory or file with the name "{dirName}".', { dirName }));
            return false;
          }
          await this.fileList.createDirectory(dirName);
        },
        async actionHandler(name) {
          if (!this.projectYear && this.isTopFolder) {
            await this.yearFolderHandler(name);
          } else {
            await this.supportingDocumentHandler(name);
          }
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
        const projectYear = getProjectYearFromProjectName(projectName);

        const isTopFolder = projectName && isProjectBalanceSupportingDocumentsTopFolder(dirInfo, projectName);
        const isDocumentsFolder = projectName && isProjectBalanceSupportingDocumentsFolder(dirInfo, projectName, projectYear);

        if (!isTopFolder && !isDocumentsFolder) {
          if (this.savedMenuItems) {
            // the WOPI requests sent to the richdocuments do not
            // contain enough authentication to access our data-base.
            const menuItems = dirInfo.mountType === 'cafevdb-database'
              ? this.savedMenuItems.filter((item) => /* !item.id.match('richdocuments') && */ (!projectYear || item.id !== 'folder'))
              : this.savedMenuItems;
            menu._menuItems = menuItems;
          }
          return;
        }

        console.info('DIRINFO', dirInfo);

        this.menuData.folderPrefix = projectName;
        this.menuData.projectYear = projectYear;
        this.menuData.isTopFolder = isTopFolder;

        if (!projectYear && isTopFolder) {
          this.menuData.templateName = 'YYYY';
          this.menuData.displayName = t(appName, 'New Year Folder');
        } else {
          if (!projectYear) {
            this.menuData.folderPrefix = projectName + '-' + dirInfo.name;
          }
          this.menuData.templateName = this.menuData.folderPrefix + '-XXX';
          this.menuData.displayName = t(appName, 'New Supporting Document');
        }
        this.menuData.actionHandler = this.menuData.actionHandler.bind(this.menuData);
        this.menuData.fileList = menu.fileList;

        const menuItems = this.savedMenuItems
          ? this.savedMenuItems.filter((item) => /* !item.id.match('richdocuments') && */ item.id !== 'folder')
          : [];
        menu._menuItems = menuItems;

        menu.addMenuEntry(this.menuData);
      },
      attach(menu) {
        if (!this.savedMenuItems) {

          this.savedMenuItems = menu._menuItems;

          const menuRender = menu.render;
          menu.render = function() {
            menuRender.apply(this);
            if (isProjectBalanceSupportingDocumentsFolder(this.fileList.dirInfo)) {
              // this.$el.find('ul li:first').remove();
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
