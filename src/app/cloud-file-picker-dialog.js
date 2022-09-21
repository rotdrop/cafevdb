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

import $ from './jquery.js';
import { appName } from './app-info.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as Notification from './notification.js';
import generateUrl from './generate-url.js';
import { parse as pathParse } from 'path';
import escapeHtml from 'escape-html';

const defaultOptions = {
  setup() {},
  cleanup() {},
  handlePickedFiles(files, paths, cleanup) {
    cleanup();
  },
  filePickerCaption: t(appName, 'Select a file from the cloud'),
  stashUrl: 'upload/stash',
  multiple: false,
  mimeTypeFilter: undefined,
  modal: undefined,
  initialCloudFolder: '',
};

const cloudFilePickerDialog = function(options) {

  options = {
    ...defaultOptions,
    ...options,
  };

  Dialogs.filePicker(
    options.filePickerCaption,
    function(paths) {
      options.setup();
      if (!paths) {
        Dialogs.alert(t(appName, 'Empty response from file selection!'), t(appName, 'Error'));
        options.cleanup();
        return;
      }
      if (!Array.isArray(paths)) {
        paths = [paths];
      }
      $.post(generateUrl(options.stashUrl), {
        cloudPaths: paths,
        uploadMode: 'test',
      })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, options.cleanup);
        })
        .done(function(data) {

          const performUpload = function(uploadMode) {
            $.post(generateUrl(options.stashUrl), {
              cloudPaths: paths,
              uploadMode,
            })
              .fail(function(xhr, status, errorThrown) {
                Ajax.handleError(xhr, status, errorThrown, options.cleanup);
              })
              .done(function(files) {
                if (!Array.isArray(files) || (!options.multiple && files.length !== 1)) {
                  Dialogs.alert(
                    t(appName, 'Unable to copy selected file(s) {file}.', { file: paths.join(', ') }),
                    t(appName, 'Error'),
                    options.cleanup
                  );
                  return;
                }
                options.handlePickedFiles(files, paths, options.cleanup);
              });
          };

          const uploadFiles = [];
          const allUploadModes = ['copy', 'move', 'link'];
          let uploadModes = allUploadModes;
          for (const uploadInfo of data) {
            uploadModes = uploadModes.filter(value => uploadInfo.upload_mode.includes(value));
            uploadFiles.push(pathParse(uploadInfo.original_name));
          }
          const templateParameters = {
            operations: uploadModes.join(' '),
            files: uploadFiles.map(
              (info) => `<span class="file-node tooltip-auto tooltip-wide"
      title="${escapeHtml(info.dir + '/' + info.base)}"
>
  <span class="dirname">${escapeHtml(info.dir)}/</span>
  <span class="basename">${escapeHtml(info.base)}</span>
</span>`).join(''),
            widgetCssClass: 'cloud-file-system-operations',
            widgetRadioName: 'cloudFileSystemOperations',
          };
          for (const mode of allUploadModes) {
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
            { escapeFunction: (x) => x }
          );

          let uploadMode = 'copy';
          $('body')
            .off('change', 'input.cloud-file-system-operations-input')
            .on('change', 'input.cloud-file-system-operations-input', function(event) {
              uploadMode = $(this).val();
              console.info('UPLOAD MODE', uploadMode);
            });
          $('body')
            .on('open', '#oc-dialog-0-content', function(event) {
              console.info('DIALOG OPENED', event);
            });

          Dialogs.confirm(
            $fileSystemOps.html(),
            t(appName, 'Select File System Operation'), {
              callback(answer) {
                console.info('UPLOAD MODE', uploadMode);
                if (answer) {
                  performUpload(uploadMode);
                } else {
                  options.cleanup();
                  Notification.messages(t(appName, 'Operation has been cancelled.'));
                }
              },
              buttons: {
                type: OC.dialogs.YES_NO_BUTTONS,
                confirm: t(appName, 'Apply'),
                cancel: t(appName, 'Cancel'),
              },
              modal: true,
              allowHtml: true,
            }
          )
            .then(function() {
              $('.oc-dialog .oc-dialog-content .cloud-file-system-operations-wrapper .tooltip-auto').cafevTooltip();
            });

        });
    },
    options.multiple, // multiselect
    options.mimeTypeFilter, // mimetypeFilter
    options.modal, // modal
    undefined, // type
    options.initialCloudFolder,
  );

};

export default cloudFilePickerDialog;
