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

import type { IFolder, INode, NewMenuEntry } from '@nextcloud/files';
import type { FilesInitialState, UploadFileData } from '../build/ts-types/php-modules/Controller/DTO.ts';
import type {
  MailMergePayload,
  MailMergeResponse,
} from './types/ajax/mail-merge.ts';

import { getCurrentUser } from '@nextcloud/auth';
import axios from '@nextcloud/axios';
import { showError, showInfo, showSuccess, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs';
import { emit, subscribe } from '@nextcloud/event-bus';
import {
  addNewFileMenuEntry,
  FileType,
  Folder,
  getFileActions,
  getNewFileMenuEntries,
  Permission,
  registerFileAction,
} from '@nextcloud/files';
import { translate as t } from '@nextcloud/l10n';
import { basename } from 'path';
import { nextTick as vueNextTick } from 'vue';
import { EnumAddDocumentConflictAction, EnumFileUploadMode, EnumPersonalSettingsKey } from '../build/ts-types/php-modules/Controller.ts';
import {
  DOCUMENT_ACTION_UPLOAD,
  FINANCE_TOPIC_INVOICES,
  SECTION_FINANCE,
  END_POINT as uploadEndPoint,
} from '../build/ts-types/php-modules/Controller/DocumentStorageUploadController.ts';
import { END_POINT as mailMergeEndPoint } from '../build/ts-types/php-modules/Controller/MailMergeController.ts';
import { END_POINT_PAGE } from '../build/ts-types/php-modules/Controller/VueAppController.ts';
import { DEBUG_VUE } from '../build/ts-types/php-modules/Settings/ConfigConstants.ts';
import logoSvg from '../img/cafevdb.svg?raw';
import { appName } from './config.ts';
import dialogAlert from './toolkit/util/dialog-alert.ts';
import { generateUrl as generateAppUrl } from './toolkit/util/generate-url.ts';
import getInitialState from './toolkit/util/initial-state.ts';
import { vueDevTools } from './toolkit/util/vue-devtools.ts';
import { MailMergeCloud } from './types/ajax/mail-merge.ts';
import Console from './util/console.ts';

type Toast = ReturnType<typeof showError>;

const COMPONENT_NAME = 'CAFEVDB-FILES-HOOKS';
const logger = new Console(COMPONENT_NAME);

f (!window.OCA.CAFEVDB) {
  window.OCA.CAFEVDB = {};
}

const initialState = getInitialState<FilesInitialState>({ section: 'files' });

vueDevTools({ enabled: !!((initialState?.[EnumPersonalSettingsKey.DEBUG_MODE] ?? 0) & DEBUG_VUE) });

const projectBalancesFolder = initialState?.sharing.files.folders.projectBalances;
const projectManagementFolder = initialState?.sharing.files.folders.projectManagement;
const supportingDocumentsFolder = initialState?.sharing.files.subFolders.supportingDocuments;
const projectParticipantsFolder = initialState?.sharing.files.subFolders.projectParticipants;

// @todo: we can of course support much more ...
const supportedMimeTypes = [
  'application/vnd.oasis.opendocument.text',
];

const acceptableMimeType = (mimeType: string|undefined) => {
  return mimeType !== undefined && supportedMimeTypes.indexOf(mimeType) >= 0;
};

const validTemplatePath = (path: string) => {
  return initialState && path.startsWith(initialState?.sharing.files.folders.templates);
};

const getProjectNameFromProjectFolder = (folder: IFolder, prefixPath?: string) => {
  let dirName = folder.dirname;
  if (!prefixPath || !dirName.startsWith(prefixPath)) {
    return null;
  }
  dirName = dirName.substring(prefixPath.length); // strip prefix
  dirName = dirName.replace(/^\/?(\d{4}|)\/?/, ''); // get rid of optional year subfolder
  const slashPos = dirName.indexOf('/');
  const projectName = slashPos >= 0 ? dirName.substring(0, dirName.indexOf('/')) : dirName;
  return projectName;
};

const getProjectNameFromProjectBalancesFolder = (folder: IFolder) => {
  return getProjectNameFromProjectFolder(folder, projectBalancesFolder);
};

const getProjectYearFromProjectName = function(projectName: string|null) {
  if (!projectName) {
    return null;
  }
  const yearMatch = projectName.match(/\d{4}$/);
  if (Array.isArray(yearMatch) && yearMatch.length === 1) {
    return yearMatch[0];
  }
  return null;
};

const isProjectManagementParentFolder = (folder: IFolder) => (
  folder.path === projectManagementFolder
    || (folder.dirname === projectManagementFolder
      && (/^\d{4}$/.test(folder.basename) || folder.basename === t(appName, 'templates'))));

const isProjectParticipantsFolder = (folder: IFolder) => {
  if (!projectManagementFolder || !folder.dirname.startsWith(projectManagementFolder)) {
    return false;
  }
  return folder.basename === projectParticipantsFolder;
};

const isProjectBalanceSupportingDocumentsTopFolder = (folder: IFolder, projectName: string|null) => {
  projectName = projectName || getProjectNameFromProjectBalancesFolder(folder);
  if (!projectName || !projectBalancesFolder) {
    return false;
  }
  const dirName = folder.dirname;
  const baseName = folder.basename;
  return dirName.startsWith(projectBalancesFolder)
    && baseName === supportingDocumentsFolder;
};

const isProjectBalanceSupportingDocumentsFolder = (folder: IFolder, projectName: string|null, projectYear: string|null) => {
  projectName = projectName || getProjectNameFromProjectBalancesFolder(folder);
  if (!projectName) {
    return false;
  }
  projectYear = projectYear || getProjectYearFromProjectName(projectName);
  const dirName = folder.dirname;
  const baseName = folder.basename;
  if (projectYear) {
    return isProjectBalanceSupportingDocumentsTopFolder(folder, projectName);
  } else {
    const result = dirName.startsWith(projectBalancesFolder + '/' + projectName + '/' + supportingDocumentsFolder)
      && baseName.match(/\d{4}$/);
    return result;
  }
};

const isInvoicesFolder = (folder: IFolder) =>
  initialState && folder.path.startsWith(initialState?.sharing.files.folders.invoices);

const getDataFromInvoiceFolder = (folder: IFolder) => {
  let path = folder.path;
  logger.debug('TEST INVOICE FOLDER', { folder, path });
  if (!initialState || !path.startsWith(initialState?.sharing.files.folders.invoices)) {
    return null;
  }
  path = path.substring(initialState?.sharing.files.folders.invoices.length);
  // '/2025/Test-2024-002-2-EineFirmaBlah-AddressbookIntegrationTester'
  // -> [ "/2025/Test-2024-002-2-EineFirmaBlah-AddressbookIntegrationTester", "Test-2024-002", "Test", undefined, "2024", "2", "EineFirmaBlah", "AddressbookIntegrationTester" ]
  // '/2025/Test2024-002-2-EineFirmaBlah-AddressbookIntegrationTester'
  // -> [ "/2025/Test2024-002-2-EineFirmaBlah-AddressbookIntegrationTester", "Test2024-002", "Test2024", "2024", undefined, "2", "EineFirmaBlah", "AddressbookIntegrationTester" ]
  const matches = path.match(/^\/?\d{4}\/(([^0-9-\s]+(\d{4})?)(?:-(\d{4}))?-(?:\d|X){3})-(\d+)-([^-]+)-([^-]+)?$/);
  if (!matches) {
    return null;
  }
  const invoice = {
    invoiceNumber: matches[1] + '/' + matches[5],
    projectName: matches[2],
    projectYear: matches[3] || matches[4],
    projectType: matches[3] ? 'temporary' : 'permanent',
    organization: matches[6],
    person: matches[7],
  };
  logger.debug('INVOICE DATA', { path, invoice });

  return invoice;
};

const enableTemplateActions = function(node: INode) {

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

registerFileAction({
  id: appName + '-mailmerge',
  displayName(_context) {
    return '';
  },
  title(_context) {
    return t(appName, 'Perform mail-merge operation with this template file.');
  },
  iconSvgInline(_context) {
    return logoSvg;
  },
  enabled(context) {
    return context.nodes.length === 1 && enableTemplateActions(context.nodes[0]);
  },
  async exec(context) {
    const node = context.nodes[0];
    const view = context.view;
    const folder = context.folder;
    // You need read permissions to see the sidebar
    if ((node.permissions & Permission.READ) !== 0) {
      window.OCA?.Files?.Sidebar?.setActiveTab?.(appName + '-mailmerge');

      // borrowed from ../files/src/actions/sidebarAction.ts
      try {
        // If the sidebar is already open for the current file, do nothing
        if (window.OCA.Files.Sidebar.file === node.path) {
          logger.debug('Sidebar already open for this file', { node });
          return null;
        }
        // Open sidebar and set active tab to our mailmerge tool
        window.OCA.Files.Sidebar.setActiveTab(appName + '-mailmerge');

        // TODO: migrate Sidebar to use a Node instead
        await window.OCA.Files.Sidebar.open(node.path);

        // Silently update current fileid
        window.OCP?.Files?.Router?.goToRoute(
          null,
          { view: view.id, fileid: node.id },
          { ...window.OCP.Files.Router.query, folder, opendetails: 'true' },
          true,
        );

        return null;
      } catch (error) {
        logger.error('Error while opening sidebar', { error });
        return false;
      }
    }
    return null;
  },
  inline: () => true,
  order: -1000000,
});

type createFolderResponse = {
  fileid: number;
  source: string;
};

const createNewFolder = async (root: IFolder, name: string): Promise<createFolderResponse> => {
  const source = root.source + '/' + name;
  const encodedSource = root.encodedSource + '/' + encodeURIComponent(name);

  const response = await axios({
    method: 'MKCOL',
    url: encodedSource,
    headers: {
      Overwrite: 'F',
    },
  });
  return {
    fileid: parseInt(response.headers['oc-fileid']),
    source,
  };
};

/**
 * Menu-entry for generating either a new year-folder or a new
   supporting document folder for a project. Replace the general "new
   directory" entry.
 */
class SupportingDocumentEntry implements NewMenuEntry {

  private projectName: string|null = null;
  private projectYear: string|null = null;
  private isTopFolder: boolean = false;

  public id: string;
  public displayName: string;
  public iconClass: string = 'icon-folder';
  public order: number = 1000000;

  public constructor(appName: string) {
    this.id = appName + '-project-supporting-document-folder';
    this.displayName = t(appName, 'New Supporting Document');
  }

  public enabled(folder: IFolder) {
    // tweak further?
    // class="action upload-picker__menu-entry" data-cy-upload-picker-menu-entry="cafevdb-project-supporting-document-folder"><
    logger.debug(
      'MENU ENTRY',
      {
        folder,
        el: document.querySelector('[data-cy-upload-picker-menu-entry="' + this.id + '"]'),
      },
    );

    const projectName = getProjectNameFromProjectBalancesFolder(folder);
    const projectYear = getProjectYearFromProjectName(projectName);

    const isTopFolder = !!projectName && isProjectBalanceSupportingDocumentsTopFolder(folder, projectName);
    const isDocumentsFolder = projectName && isProjectBalanceSupportingDocumentsFolder(folder, projectName, projectYear);

    if (!isTopFolder && !isDocumentsFolder) {
      this.projectName = null;
      this.projectYear = null;
      return false;
    }

    this.projectName = projectName;
    this.projectYear = projectYear;
    this.isTopFolder = isTopFolder;

    if (!projectYear && isTopFolder) {
      this.displayName = t(appName, 'New Year Folder');
    } else {
      this.displayName = t(appName, 'New Supporting Document');
    }

    return true;
  }

  public async handler(folder: IFolder, content: INode[]) {
    if (!this.projectYear && this.isTopFolder) {
      await this.yearFolderHandler(folder, content);
    } else {
      await this.supportingDocumentHandler(folder, content);
    }
  }

  private async yearFolderHandler(folder: IFolder, content: INode[]) {
    const year = '' + new Date().getFullYear();
    let dirName = '' + year;
    const yearFolders = content.filter((node: INode) => node.basename.match(/^\d{4}$/));
    const existing = yearFolders.find((node: INode) => node.basename === dirName);
    if (existing) {
      const maxYear = yearFolders.reduce((accumulator: number, currentValue: INode) => Math.max(accumulator, +currentValue.basename), +yearFolders[0].basename);
      dirName = '' + (maxYear + 1);
    }
    showInfo(t(appName, 'Year determined as {year}.', { year: dirName }));
    const { fileid, source } = await createNewFolder(folder, dirName);

    // Create the folder in the store
    const newFolder = new Folder({
      source,
      id: fileid,
      mtime: new Date(),
      owner: getCurrentUser()?.uid || null,
      permissions: Permission.ALL & ~Permission.SHARE,
      root: folder?.root || 'this must not happen',
      attributes: {
        'mount-type': 'cafevdb-database',
      },
    });

    showSuccess(t('files', 'Created new folder "{name}"', { name: basename(source) }));
    emit('files:node:created', newFolder);
    // emit('files:node:rename', newFolder);
    // emit('files:node:renamed', newFolder);
  }

  private async supportingDocumentHandler(folder: IFolder, content: INode[]) {
    const folderPrefix = this.projectYear ? this.projectName : this.projectName + '-' + folder.basename;
    const nameRegExp = new RegExp('^(?:' + folderPrefix + '-?)?\\d{3}$');
    const sequenceFolders = content.filter((node: INode) => node.basename.match(nameRegExp));
    const sequences = sequenceFolders.map((node: INode) => +node.basename.substring(node.basename.length - 3));
    sequences.sort((a, b) => a - b);
    let sequence: number;
    if (sequences[sequences.length - 1] !== sequences.length) {
      // find first hole, inefficiently
      let previous = 0;
      for (const current of sequences) {
        if (current - previous !== 1) {
          break;
        }
        previous = current;
      }
      sequence = previous + 1;
    } else {
      sequence = sequences.length + 1;
    }
    const sequenceString = String(sequence).padStart(3, '0');
    showInfo(t(appName, 'Document sequence determined as {sequence}.', { sequence: sequenceString }));
    const dirName = folderPrefix + '-' + sequenceString;
    const { fileid, source } = await createNewFolder(folder, dirName);

    // Create the folder in the store
    const newFolder = new Folder({
      source,
      id: fileid,
      mtime: new Date(),
      owner: getCurrentUser()?.uid || null,
      permissions: Permission.ALL & ~Permission.SHARE,
      root: folder?.root || 'this must not happen',
      attributes: {
        'mount-type': 'cafevdb-database',
      },
    });

    showSuccess(t('files', 'Created new folder "{name}"', { name: basename(source) }));
    emit('files:node:created', newFolder);
    // emit('files:node:rename', newFolder);
    // emit('files:node:renamed', newFolder);
  }

}

const supportingDocumentsEntry = new SupportingDocumentEntry(appName);

addNewFileMenuEntry(supportingDocumentsEntry);

/**
 * Replace "new directory" by a suitable "new project" menu entry.
 */
class ProjectManagementFolderEntry implements NewMenuEntry {

  private isManagementFolder: boolean = false;

  public id: string;
  public displayName: string;
  public iconClass: string = 'icon-folder';
  public order: number = 1000000;

  public constructor(appName: string) {
    this.id = appName + '-project-management-folder';
    this.displayName = t(appName, 'New Project');
  }

  public enabled(folder: IFolder) {
    this.isManagementFolder = isProjectManagementParentFolder(folder);

    return this.isManagementFolder;
  }

  public async handler(_folder: IFolder, _content: INode[]) {
    const route = generateAppUrl(`${END_POINT_PAGE}/projects`, {

      PME_sys_qfyear: (new Date()).getFullYear() - 1,

      PME_sys_qfyear_comp: '>=',
    });
    // <a target="_blank" style="text-decoration: revert; font-style: italic;" href="{route}">project overview</a> page.', {
    await dialogAlert({
      title: t(appName, 'Please use the "{appName}" app!', { appName }),
      text: t(
        appName,
        'New projects have to be created using the "{buttonName}" button on the {pageName} page.',
        {
          pageName: `@ANCHOR@${t(appName, 'project overview')}@ROHCNA@`,
          buttonName: t(appName, 'New Project'),
        },
      )
        .replace('@ANCHOR@', `<a target="_blank" style="text-decoration: revert; font-style: italic;" href="${route}">`)
        .replace('@ROHCNA@', '</a>'),
      allowHtml: true,
    });
  }

}

const projectManagementFolderEntry = new ProjectManagementFolderEntry(appName);

addNewFileMenuEntry(projectManagementFolderEntry);

/**
 * Replace "new directory" by a suitable "new participant" menu entry.
 */
class ProjectParticipantFolderEntry implements NewMenuEntry {

  public id: string;
  public displayName: string;
  public iconClass: string = 'icon-folder';
  public order: number = 1000000;

  public constructor(appName: string) {
    this.id = appName + '-project-participant-folder';
    this.displayName = t(appName, 'New Project-Participant');
  }

  public enabled(folder: IFolder) {
    return isProjectParticipantsFolder(folder);
  }

  public async handler(folder: IFolder, _content: INode[]) {
    // the project-name is the basename of folder.dirname
    const projectName = folder.dirname.substring(folder.dirname.lastIndexOf('/') + 1);
    const route = generateAppUrl(`${END_POINT_PAGE}/project-participants/{projectName}`, {
      projectName,
    });
    // <a target="_blank" style="text-decoration: revert; font-style: italic;" href="{route}">project overview</a> page.', {
    await dialogAlert({
      title: t(appName, 'Please use the "{appName}" app!', { appName }),
      text: t(
        appName,
        'Participants have to be managed on the {pageName} page.',
        {
          pageName: `@ANCHOR@${t(appName, 'project participants')}@ROHCNA@`,
        },
      )
        .replace('@ANCHOR@', `<a target="_blank" style="text-decoration: revert; font-style: italic;" href="${route}">`)
        .replace('@ROHCNA@', '</a>'),
      allowHtml: true,
    });
  }

}

const projectParticipantFolderEntry = new ProjectParticipantFolderEntry(appName);

addNewFileMenuEntry(projectParticipantFolderEntry);

// invoices are also special, and perhaps later on contracts

class InvoicesEntry implements NewMenuEntry {

  public id: string;
  public displayName: string;
  public iconClass: string = 'icon-folder';
  public order: number = 1000000;

  public constructor(appName: string) {
    this.id = appName + '-invoices-folder';
    this.displayName = t(appName, 'New Invoice');
  }

  public enabled(folder: IFolder) {
    logger.debug('FOLDER', {
      folder,
    });
    if (!isInvoicesFolder(folder)) {
      return false;
    }

    const invoiceData = getDataFromInvoiceFolder(folder);
    if (!invoiceData) {
      this.displayName = t(appName, 'New Invoice');
    } else {
      this.displayName = t(appName, 'Generate Invoice Document');
    }

    return true;
  }

  public async handler(folder: IFolder, content: INode[]) {
    logger.debug('FOLDER', { folder, content });
    // fixup later

    const invoiceData = getDataFromInvoiceFolder(folder);
    if (!invoiceData) {
      logger.debug('NO INVOICE DATA', { folder, content });
    } else {
      const postData: MailMergePayload = {
        templateName: 'invoice',
        operation: MailMergeCloud,
        invoiceIds: [invoiceData.invoiceNumber],
      };
      const mailMergeUrl = generateAppUrl(mailMergeEndPoint);
      let mailMergeToast: Toast|null = showInfo(t(appName, 'Starting mail-merge, this may take some time …'), { timeout: TOAST_PERMANENT_TIMEOUT });
      try {
        const response = await axios.post<MailMergeResponse>(mailMergeUrl, postData);
        const cloudFile = response.data.cloudFolder + '/' + response.data.cloudFiles[0];

        mailMergeToast.hideToast();
        mailMergeToast = null;
        showInfo(t(appName, 'Mail-merge completed, moving document into the proper place …'));

        const moveUrl = generateAppUrl(
          `${uploadEndPoint}/${SECTION_FINANCE}/${FINANCE_TOPIC_INVOICES}/${DOCUMENT_ACTION_UPLOAD}`,
        );

        const moveData = {
          data: {
            optionKey: invoiceData.invoiceNumber,
            filesAppPath: folder.path,
          },
          cloudFile,
          uploadMode: EnumFileUploadMode.MOVE,
          conflict: EnumAddDocumentConflictAction.RENAME,
        };

        const moveResponse = await axios.post<UploadFileData[]>(moveUrl, moveData);

        logger.debug('MAIL MERGE RESPONSES', {
          moveResponse,
          response,
        });

        if (moveResponse.data[0].message) {
          showInfo(moveResponse.data[0].message);
        }
        showInfo(t(appName, 'Reloading file list.'));

        emit('files:config:updated', {});

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
      } catch (e: any) {
        if (mailMergeToast) {
          mailMergeToast.hideToast();
          // mailMergeToast = null;
        }
        // @todo: better diagnostics
        showError(t(appName, 'Mail-merge operation has failed'));
        logger.error('MAIL MERGE ERROR', { error: e });
      }
    }
  }

}

const invoicesEntry = new InvoicesEntry(appName);

addNewFileMenuEntry(invoicesEntry);

const newFileMenuEntryNeedsTweak = (entry: NewMenuEntry) => (
  entry !== supportingDocumentsEntry
    && entry !== invoicesEntry
    && entry !== projectManagementFolderEntry
    && entry !== projectParticipantFolderEntry
    && entry.id !== 'rich-workspace-init');

const isSpecialEntryEnabled = (folder: IFolder) => (
  projectManagementFolderEntry.enabled(folder)
    || projectParticipantFolderEntry.enabled(folder)
    || supportingDocumentsEntry.enabled(folder)
    || invoicesEntry.enabled(folder)
);

// At the time of this writing the standard uploads actions are
// unconditionally enabled if the underlying mount is writable. So we
// remove the upload-items by a brute-force approach. This is
// unfortunate and error prone but works.
const observer = new MutationObserver(async (mutationList, _observer) => {
  for (const mutationRecord of mutationList) {
    for (const element of mutationRecord.addedNodes) {
      if (!(element instanceof HTMLElement)) {
        continue;
      }
      if (!element.classList.contains('v-popper__popper')) {
        continue;
      }
      await vueNextTick(); // wait for the child-nodes to appear ...
      const uploadAddItem = element.querySelector('li.action[data-cy-upload-picker-add]');
      if (!uploadAddItem) {
        logger.error('No upload menu item', element);
      }
      uploadAddItem?.previousElementSibling?.remove();
      uploadAddItem?.nextElementSibling?.nextElementSibling?.remove();
      uploadAddItem?.nextElementSibling?.remove();
      uploadAddItem?.remove();
      // observer.disconnect(); // one-time action when the files-list is updated.
    }
  }
});

let currentFolder: IFolder|undefined;

subscribe('files:list:updated', ({ folder }) => {
  currentFolder = folder;
  if (isSpecialEntryEnabled(folder)) {
    observer.observe(document.body, { childList: true });
  } else {
    observer.disconnect();
  }
});

/*
 * In special locations generic "new file" action should be very restricted.
 */
window.addEventListener('DOMContentLoaded', () => {
  const newFileMenuEntries = getNewFileMenuEntries();
  for (const entry of newFileMenuEntries) {
    if (newFileMenuEntryNeedsTweak(entry)) {
      const enabledMethod = entry.enabled;
      entry.enabled = (folder: IFolder) => !isSpecialEntryEnabled(folder) && (enabledMethod ? enabledMethod.call(entry, folder) : true);
    }
  }
  const fileActionEntries = getFileActions();
  for (const fileAction of fileActionEntries) {
    switch (fileAction.id) {
      case 'delete':
      case 'move-copy':
      case 'rename': {
        const enabledMethod = fileAction.enabled;
        fileAction.enabled = (context) =>
          (currentFolder && isSpecialEntryEnabled(currentFolder)
            ? false
            : (enabledMethod ? enabledMethod.call(fileAction, context) : true));
        break;
      }
      default:
        break;
    }
  }
});
