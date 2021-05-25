/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

import { globalState, $ } from './globals.js';
import * as Ajax from './ajax.js';
import generateUrl from './generate-url.js';

globalState.progressTimer = null;

function createProgressStatus(target, current, data) {
  return $.post(
    generateUrl('foregroundjob/progress/create'),
    { target: target || 100, current: current || 0, data });
}

function pollProgressStatus(id, options) {
  const defaultOptions = {
    update(id, current, target, data) {},
    fail: Ajax.handleError,
    interval: 800,
  };
  options = { ...defaultOptions, ...options };
  const interval = options.interval;

  const poll = function() {
    $.get(generateUrl('foregroundjob/progress/' + id))
      .done(function(data) {
        if (!Ajax.validateResponse(data, ['id', 'current', 'target', 'data'])) {
          clearTimeout(globalState.progressTimer);
          globalState.progressTimer = false;
          return;
        }
        if (!options.update(data.id, data.current, data.target, data.data)) {
          clearTimeout(globalState.progressTimer);
          globalState.progressTimer = false;
          return;
        }
        globalState.progressTimer = setTimeout(poll, interval);
      })
      .fail(function(xhr, status, errorThrown) {
        clearTimeout(globalState.progressTimer);
        globalState.progressTimer = false;
        options.fail(xhr, status, errorThrown);
      });
  };
  poll();
}

const deleteProgressStatus = function(id) {
  return $.post(
    generateUrl('foregroundjob/progress/delete'), { id });
};

pollProgressStatus.stop = function() {
  clearTimeout(globalState.progressTimer);
  globalState.progressTimer = false;
};

pollProgressStatus.active = function() {
  return !!globalState.progressTimer;
};

export {
  createProgressStatus as create,
  pollProgressStatus as poll,
  deleteProgressStatus as delete,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
