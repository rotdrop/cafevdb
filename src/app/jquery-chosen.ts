/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Compatibility for the Chosen calls used by the legacy pages. Rendering and
 * state are owned by the independent mountable Vue component.
 */

import { translate as t } from '@nextcloud/l10n';
import { appName } from '../config.ts';
import { ChosenService, getMountedChosen } from '../services/mountable-chosen.ts';
import $ from './jquery.ts';
import * as Notification from './notification.ts';

$.fn.chosen = function(options: Chosen.Options|'destroy' = {}) {
  return this.each(function() {
    if (!(this instanceof HTMLSelectElement)) {
      return;
    }
    const select = $(this)[0];
    const $select = $(select);
    if (options === 'destroy') {
      getMountedChosen(select)?.destroy();
      return;
    }
    if (getMountedChosen(select)) {
      return;
    }
    const service = ChosenService.create(select, {
      ...options,
      onOpen: () => {
        $select.trigger('chosen:before_showing_dropdown');
        $select.trigger('chosen:showing_dropdown');
      },
      onClose: () => $select.trigger('chosen:hiding_dropdown'),
      onUpdate: () => $select.trigger('chosen:update'),
      onDestroy: () => {
        $select.off('.chosenVue').removeData('chosen');
      },
    });
    $select.data('chosen', service);
    $select.on('chosen:updated.chosenVue', () => service.refresh());
    $select.on('chosen:open.chosenVue chosen:activate.chosenVue', () => service.open());
    $select.on('chosen:close.chosenVue', () => service.close());
    void service.ready.then(() => {
      if (getMountedChosen(select) === service) {
        $select.trigger('chosen:ready');
      }
    }).catch((error: unknown) => {
      console.error('Unable to mount Chosen select controls.', error);
      Notification.show(t(appName, 'Unable to initialize the select controls.'), { timeout: 15 });
    });
  });
};
