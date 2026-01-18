/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.ts';
import * as Ajax from './ajax.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import type { ProgressResponse } from '../../build/ts-types/php-modules/Controller/DTO.ts';
import { GET_URL, POST_URL } from '../../build/ts-types/php-modules/Controller/ProgressStatusController.ts';
import type { EnumProgressStatusOperation } from '../../build/ts-types/php-modules/Controller.ts';

require('progressbar.scss');

let progressTimer: undefined|NodeJS.Timeout;
let progressTimerStopped = true;

type ProgressResponseData = ProgressResponse['data'];

/**
 * Generate a progress-status token with an Ajax-callback.
 *
 * @param target TBD.
 *
 * @param current TBD.
 *
 * @param data TBD.
 */
const createProgressStatus = (target: number, current: number, data?: ProgressResponseData) => {
  const operation: EnumProgressStatusOperation = 'create';
  return $.post(
    generateAppUrl(POST_URL, { operation }),
    { target: target || 100, current: current || 0, data });
};

export interface PollOptions{
  interval: number,
  fail: typeof Ajax.handleError,
  update(id: string|number, current: number, target: number, data?: ProgressResponseData): boolean,
}

/**
 * Poll the given progress-status-id.
 *
 * @param id TBD.
 *
 * @param parameters TBD.
 */
const pollProgressStatus = (id: string, parameters: Partial<PollOptions>) => {
  const defaultPollOptions: PollOptions = {
    update() { return false; },
    fail: Ajax.handleError,
    interval: 800,
  };

  const options: PollOptions = { ...defaultPollOptions, ...parameters };
  const interval = options.interval;

  const poll = function() {
    if (progressTimerStopped) {
      clearTimeout(progressTimer);
      progressTimer = undefined;
      console.info('PROGRESS STOPPED');
      return;
    }
    $.get(generateAppUrl(GET_URL, { id }))
      .done(function(data: ProgressResponse) {
        clearTimeout(progressTimer);
        progressTimer = undefined;
        if (progressTimerStopped) {
          return;
        }
        if (!Ajax.validateResponse(data, ['id', 'current', 'target', 'data'])) {
          return;
        }
        if (!options.update(data.id, data.current, data.target, data.data)) {
          return;
        }
        if (!progressTimerStopped) {
          progressTimer = setTimeout(poll, interval);
          console.log('FIRED PROGRESS TIMER', progressTimer);
        }
      })
      .fail(function(xhr, status, errorThrown) {
        clearTimeout(progressTimer);
        progressTimer = undefined;
        if (!progressTimerStopped) {
          options.fail(xhr, status, errorThrown);
        }
      });
  };
  progressTimerStopped = false;
  console.info('INIT POLL', id);
  poll();
};

const deleteProgressStatus = function(id: string) {
  const operation: EnumProgressStatusOperation = 'delete';
  return $.post(
    generateAppUrl(POST_URL, { operation }), { id });
};

pollProgressStatus.stop = function() {
  progressTimerStopped = true;
};

pollProgressStatus.active = function() {
  return !!progressTimer;
};

export {
  createProgressStatus as create,
  pollProgressStatus as poll,
  deleteProgressStatus as delete,
};
