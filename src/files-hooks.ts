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

import Vue from 'vue';
import { appName } from './config.ts';
import getInitialState from './toolkit/util/initial-state.ts';
import { basename } from 'path';
import { getCurrentUser } from '@nextcloud/auth';
import { generateFilePath } from '@nextcloud/router';
import { generateUrl as generateAppUrl } from './toolkit/util/generate-url.ts'
import { emit } from '@nextcloud/event-bus';
import { showError, showInfo, showSuccess, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs';
import {
  FileAction,
  FileType,
  Folder,
  Node,
  Permission,
  View,
  addNewFileMenuEntry,
  getNewFileMenuEntries,
  registerFileAction,
} from '@nextcloud/files';
import type { NewMenuEntry } from '@nextcloud/files';
import { translate as t, translatePlural as n } from '@nextcloud/l10n';
import axios from '@nextcloud/axios';
import logoSvg from '../img/cafevdb.svg?raw';
import type { FilesInitialState } from './types/initial-state.d.ts';
import Console from './util/console.ts'
import { MailMergeCloud } from './types/ajax/mail-merge.ts';
import type {
  MailMergePayload,
  MailMergeResponse,
} from './types/ajax/mail-merge.ts';

type Toast = ReturnType<typeof showError>;

const COMPONENT_NAME = 'CAFEVDB-FILES-HOOKS';
const logger = new Console(COMPONENT_NAME)

declare global {
  interface Window {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    OCA: any;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    OCP: any;
  }
  // var __webpack_public_path__: string;
}

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath(appName, '', 'js');
Vue.mixin({ data() { return { appName }; }, methods: { t, n } });

if (!window.OCA.CAFEVDB) {
  window.OCA.CAFEVDB = {};
}

const initialState = getInitialState<FilesInitialState>({ section: 'files' });

const projectBalancesFolder = initialState?.sharing.files.folders.projectBalances;
const supportingDocumentsFolder = initialState?.sharing.files.subFolders.supportingDocuments;

// @todo: we can of course support much more ...
const supportedMimeTypes = [
  'application/vnd.oasis.opendocument.text',
];

const acceptableMimeType = function(mimeType: string|undefined) {
  return mimeType !== undefined && supportedMimeTypes.indexOf(mimeType) >= 0;
};

const validTemplatePath = function(path: string) {
  return initialState && path.startsWith(initialState?.sharing.files.folders.templates);
};

const getProjectNameFromProjectBalancesFolders = function(folder: Folder) {
  let dirName = folder.dirname;
  if (!initialState || !dirName.startsWith(initialState?.sharing.files.folders.projectBalances)) {
    return null;
  }
  dirName = dirName.substring(initialState?.sharing.files.folders.projectBalances.length);
  dirName = dirName.replace(/^\/?(\d{4}|)\/?/, '');
  const slashPos = dirName.indexOf('/');
  const projectName = slashPos >= 0 ? dirName.substring(0, dirName.indexOf('/')) : dirName;
  return projectName;
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

const isProjectBalanceSupportingDocumentsTopFolder = function(folder: Folder, projectName: string|null) {
  projectName = projectName || getProjectNameFromProjectBalancesFolders(folder);
  if (!projectName || !projectBalancesFolder) {
    return false;
  }
  const dirName = folder.dirname;
  const baseName = folder.basename;
  return dirName.startsWith(projectBalancesFolder)
    && baseName === supportingDocumentsFolder;
};

const isProjectBalanceSupportingDocumentsFolder = function(folder: Folder, projectName: string|null, projectYear: string|null) {
  projectName = projectName || getProjectNameFromProjectBalancesFolders(folder);
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

const isInvoicesFolder = (folder: Folder) =>
  initialState && folder.path.startsWith(initialState?.sharing.files.folders.invoices);

const getDataFromInvoiceFolder = (folder: Folder) => {
  let path = folder.path
  logger.info('TEST INVOICE FOLDER', { folder, path });
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
  logger.info('INVOICE DATA', { path, invoice });

  return invoice;
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
  displayName(/* nodes: Node[], view: View */) {
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

      // borrowed from ../files/src/actions/sidebarAction.ts
      try {
        // If the sidebar is already open for the current file, do nothing
        if (window.OCA.Files.Sidebar.file === node.path) {
          logger.debug('Sidebar already open for this file', { node })
          return null
        }
        // Open sidebar and set active tab to our mailmerge tool
        window.OCA.Files.Sidebar.setActiveTab(appName + '-mailmerge')

        // TODO: migrate Sidebar to use a Node instead
        await window.OCA.Files.Sidebar.open(node.path)

        // Silently update current fileid
        window.OCP?.Files?.Router?.goToRoute(
          null,
          { view: view.id, fileid: String(node.fileid) },
          { ...window.OCP.Files.Router.query, dir, opendetails: 'true' },
          true,
        )

        return null
      } catch (error) {
        logger.error('Error while opening sidebar', { error })
        return false
      }
    }
    return null;
  },
  inline: () => true,
  order: -1000000,
}));

type createFolderResponse = {
  fileid: number
  source: string
}

const createNewFolder = async (root: Folder, name: string): Promise<createFolderResponse> => {
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

  public enabled(folder: Folder) {
    // tweak further?
    // class="action upload-picker__menu-entry" data-cy-upload-picker-menu-entry="cafevdb-project-supporting-document-folder"><
    logger.info('MENU ENTRY', { el: document.querySelector('[data-cy-upload-picker-menu-entry="' + supportingDocumentsEntry.id + '"]') });

    const projectName = getProjectNameFromProjectBalancesFolders(folder);
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

  public async handler(folder: Folder, content: Node[]) {
    if (!this.projectYear && this.isTopFolder) {
      await this.yearFolderHandler(folder, content);
    } else {
      await this.supportingDocumentHandler(folder, content);
    }
  }

  private async yearFolderHandler(folder: Folder, content: Node[]) {
    const year = '' + new Date().getFullYear();
    let dirName = '' + year;
    const yearFolders = content.filter((node: Node) => node.basename.match(/^\d{4}$/));
    const existing = yearFolders.find((node: Node) => node.basename === dirName);
    if (existing) {
      const maxYear = yearFolders.reduce((accumulator: number, currentValue: Node) => Math.max(accumulator, +currentValue.basename), +yearFolders[0].basename);
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

  private async supportingDocumentHandler(folder: Folder, content: Node[]) {
    const folderPrefix = this.projectYear ? this.projectName : this.projectName + '-' + folder.basename;
    const nameRegExp = new RegExp('^(?:' + folderPrefix + '-?)?' + '\\d{3}$');
    const sequenceFolders = content.filter((node: Node) => node.basename.match(nameRegExp));
    const sequences = sequenceFolders.map((node: Node) => +node.basename.substring(node.basename.length - 3));
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

  public enabled(folder: Folder) {
    logger.info('FOLDER', { folder });
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

  public async handler(folder: Folder, content: Node[]) {
    logger.info('FOLDER', { folder, content });
    // fixup later

    const invoiceData = getDataFromInvoiceFolder(folder);
    if (!invoiceData) {

    } else {
      const postData: MailMergePayload = {
        templateName: 'invoice',
        operation: MailMergeCloud,
        invoiceIds: [ invoiceData.invoiceNumber ],
      };
      const mailMergeUrl = generateAppUrl('documents/mail-merge');
      let mailMergeToast: Toast|null = showInfo(t(appName, 'Starting mail-merge, this may take some time ...'), { timeout: TOAST_PERMANENT_TIMEOUT });
      try {
        const response = await axios.post<MailMergeResponse>(mailMergeUrl, postData);
        const cloudFile = response.data.cloudFolder + '/' + response.data.cloudFiles[0];

        mailMergeToast.hideToast();
        mailMergeToast = null;
        showInfo(t(appName, 'Mail-merge completed, moving document into the proper place ...'));

        const moveUrl = generateAppUrl('documents/finance/invoices/upload');

        const moveData = {
          data: {
            optionKey: invoiceData.invoiceNumber,
            filesAppPath: folder.path,
          },
          cloudFile,
          uploadMode: 'move',
          conflict: 'rename',
        };

        const moveResponse = await axios.post(moveUrl, moveData);

        logger.info('MAIL MERGE RESPONSES', {
          moveResponse,
          response,
        });

        showInfo(moveResponse.data[0].message);
        showInfo(t(appName, 'Reloading file list.'));

        emit('files:config:updated', {});

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
      } catch (e: any) {
        if (mailMergeToast) {
          mailMergeToast.hideToast();
          mailMergeToast = null;
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

/*
 * In special locations generic "new file" action should be very restricted.
 */
window.addEventListener('DOMContentLoaded', () => {
  const newFileMenuEntries = getNewFileMenuEntries();
  logger.info('NEW FILE MENU ENTRIES', newFileMenuEntries);
  for (const entry of newFileMenuEntries) {
    if (entry !== supportingDocumentsEntry && entry !== invoicesEntry && entry.id !== 'rich-workspace-init') {
      const enabledMethod = entry.enabled;
      entry.enabled = (folder: Folder) => {
        return !invoicesEntry.enabled(folder) && !supportingDocumentsEntry.enabled(folder) && (enabledMethod ? enabledMethod(folder) : true);
      };
    }
  }
});
