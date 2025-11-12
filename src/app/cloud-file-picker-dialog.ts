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

import $ from './jquery.js';
import { appName } from '../config.ts';
import * as Ajax from './ajax.js';
import {
  YES_NO_BUTTONS,
  alert as alertDialog,
  confirm as confirmDialog,
  filePicker as filePickerDialog,
} from './dialogs.ts';
import * as Notification from './notification.js';
import generateAppUrl from './generate-url.js';
import { parse as pathParse } from './path.js';
import { translate as t } from '@nextcloud/l10n';
import escapeHtml from 'escape-html';
import { UPLOAD_MODES } from '../../build/ts-types/php-modules/Controller/UploadsController.ts';

type UploadMode = typeof UPLOAD_MODES[number];

export interface CloudFilePickerParameters {
  setup?: () => void,
  cleanup?: () => void,
  handlePickedFiles?: (files: Record<string, unknown>[], paths: string[], cleanup: () => void) => void,
  filePickerCaption?: string,
  stashUrl?: string,
  multiple?: boolean,
  modal?: boolean,
  initialCloudFolder?: string,
  allowDirectories?: boolean,
}

const defaultOptions: Required<CloudFilePickerParameters> = {
  setup() {},
  cleanup() {},
  handlePickedFiles(_files, _paths, cleanup) {
    cleanup();
  },
  filePickerCaption: t(appName, 'Select a file from the cloud'),
  stashUrl: 'upload/stash',
  multiple: false,
  modal: false,
  initialCloudFolder: '',
  allowDirectories: false,
};

const cloudFilePickerDialog = function(options: CloudFilePickerParameters) {

  const parameters: typeof defaultOptions = {
    ...defaultOptions,
    ...options,
  };

  filePickerDialog({
    title: parameters.filePickerCaption,
    callback(paths) {
      parameters.setup();
      if (!paths) {
        alertDialog(t(appName, 'Empty response from file selection!'), t(appName, 'Error'));
        parameters.cleanup();
        return;
      }
      if (!Array.isArray(paths)) {
        paths = [paths];
      }
      $.post(generateAppUrl(parameters.stashUrl), {
        cloudPaths: paths,
        uploadMode: 'test',
      })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, parameters.cleanup);
        })
        .done(function(data) {

          const performUpload = function(uploadMode: UploadMode) {
            $.post(generateAppUrl(parameters.stashUrl), {
              cloudPaths: paths,
              uploadMode,
            })
              .fail(function(xhr, status, errorThrown) {
                Ajax.handleError(xhr, status, errorThrown, parameters.cleanup);
              })
              .done(function(files) {
                if (!Array.isArray(files) || (!parameters.multiple && files.length !== 1)) {
                  alertDialog(
                    t(appName, 'Unable to copy selected file(s) {file}.', { file: paths.join(', ') }),
                    t(appName, 'Error'),
                    parameters.cleanup,
                  );
                  return;
                }
                parameters.handlePickedFiles(files, paths, parameters.cleanup);
              });
          };

          const uploadFiles = [];
          let uploadModes: string[] = UPLOAD_MODES;
          for (const uploadInfo of data) {
            uploadModes = uploadModes.filter(value => uploadInfo.upload_mode.includes(value));
            uploadFiles.push(pathParse(uploadInfo.original_name));
          }
          const templateParameters = {
            operations: uploadModes.join(' '),
            files: uploadFiles.map(
              (info) => {
                const dir = escapeHtml(info.dir).replace(/\/+$/, '');
                const base = escapeHtml(info.base).replace(/^\/+/, '');
                return `
<span class="file-node tooltip-auto tooltip-wide flex-container"
      title="${dir + '/' + base}"
>
  <span class="dirname">${dir}</span>
  <span class="separator">/</span>
  <span class="basename">${base}</span>
  </span>`;
              }).join(''),
            widgetCssClass: 'cloud-file-system-operations',
            widgetRadioName: 'cloudFileSystemOperations',
          };
          for (const mode of UPLOAD_MODES) {
            templateParameters[mode + 'Selected'] = '';
            templateParameters[mode + 'CssClass'] = mode + '-control';
            if (uploadModes.includes(mode)) {
              templateParameters[mode + 'Disabled'] = '';
              templateParameters[mode + 'CssClass'] += ' enabled';
            } else {
              templateParameters[mode + 'Disabled'] = 'disabled';
              templateParameters[mode + 'CssClass'] += ' disabled';
            }
          }
          templateParameters.copySelected = 'checked';

          const $fileSystemOps = $('#cloudFileSystemOperations').octemplate(
            templateParameters,
            { escapeFunction: (x) => x },
          );

          let uploadMode = 'copy';
          $('body')
            .off('change', 'input.cloud-file-system-operations-input')
            .on('change', 'input.cloud-file-system-operations-input', function(_event) {
              uploadMode = $(this).val();
              console.info('UPLOAD MODE', uploadMode);
            });
          $('body')
            .on('open', '#oc-dialog-0-content', function(event) {
              console.info('DIALOG OPENED', event);
            });

          confirmDialog(
            $fileSystemOps.html(),
            t(appName, 'Select File System Operation'), {
              callback(answer) {
                console.info('UPLOAD MODE', uploadMode);
                if (answer) {
                  performUpload(uploadMode);
                } else {
                  parameters.cleanup();
                  Notification.messages(t(appName, 'Operation has been cancelled.'));
                }
              },
              buttons: {
                type: YES_NO_BUTTONS,
                confirm: t(appName, 'Apply'),
                cancel: t(appName, 'Cancel'),
              },
              modal: true,
              allowHtml: true,
              dialogClasses: 'fit-content-width',
            },
          )
            .then(function() {
              $('.oc-dialog .oc-dialog-content .cloud-file-system-operations-wrapper .tooltip-auto').cafevTooltip();
            });

        });
    },
    multiple: parameters.multiple, // multiselect
    modal: parameters.modal, // modal
    startPath: parameters.initialCloudFolder,
    allowDirectories: parameters.allowDirectories,
  });

};

export default cloudFilePickerDialog;
