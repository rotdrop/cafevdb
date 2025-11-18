/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import {
  formSelector as pmeFormSelector,
  token as pmeToken,
} from './pme-selectors.ts';
import {
  emit as asyncEmit,
  getEmitResult,
} from '../services/async-event-bus.ts';
import {
  PAGE_TEMPLATE_ACTION_MENU,
  GET_VUE_COMPONENT,
} from '../event-bus-events.ts';
import type { ComponentProps } from '../mountable-component-names.ts';
import type { LegacyPageActionsMenu } from '../types/components.d.ts';

const actionMenu = async function(
  $container: JQuery,
  template: string,
  vueMenuName: keyof ComponentProps,
) {

  const generateVueMenu = async ($actionMenu: JQuery) => {
    const propsData = { template, ...$actionMenu.data('actionMenu') };
    propsData.enableOverviewItem = $container.find(pmeFormSelector).hasClass(pmeToken('list'));
    const vueMenu: LegacyPageActionsMenu = await getEmitResult(
      asyncEmit(GET_VUE_COMPONENT, {
        name: vueMenuName,
        propsData,
      }),
    );
    const vueComponents = $container.data('vueComponents') || [];
    if (vueComponents.length === 0) {
      $container.data('vueComponents', vueComponents);
    }
    vueComponents.push(vueMenu);

    $actionMenu.data('vueMenu', vueMenu);
    vueMenu.$mount($actionMenu.find('.vue-mount-point')[0]);
    return vueMenu;
  };

  const actionTriggerSelector = `.vue-action-menu-placeholder.${template} button.vue-mount-point`;
  $container
    .off('click', actionTriggerSelector)
    .on('click', actionTriggerSelector, async function(event) {

      // @ts-expect-error: 2339
      $.fn.cafevTooltip.hide();

      const $actionMenu = $(this).parent();
      if ($actionMenu.data('vueMenu')) {
        // the menu already exists, just let it do its work
        return;
      }

      // otherwise intercept the event and mount the menu
      event.preventDefault();
      event.stopImmediatePropagation();

      const vueMenu = await generateVueMenu($actionMenu);
      const entityId = $actionMenu.data('actionMenu').entityId;

      asyncEmit(PAGE_TEMPLATE_ACTION_MENU, {
        template,
        open: false,
        entityId: -entityId,
      });
      vueMenu.openMenu();

      return false;
    });

  $container
    .off('pme:contextmenu', 'tr.' + pmeToken('row'))
    .on('pme:contextmenu', 'tr.' + pmeToken('row'), async function(event, originalEvent, databaseIdentifier) {
      console.debug('CONTEXTMENU EVENT', $(this), event, originalEvent, databaseIdentifier);

      const $row = $(this);
      const $form = $row.closest(pmeFormSelector);
      let $actionMenuContainer: JQuery;
      if ($form.is('.' + pmeToken('list'))) {
        $actionMenuContainer = $row.hasClass('following') ? $row.prevAll('.first').first() : $row;
      } else {
        $actionMenuContainer = $row.closest(pmeFormSelector);
      }
      const $actionMenu = $actionMenuContainer.find('.vue-action-menu-placeholder.' + template).first();

      if ($actionMenu.length === 0) {
        return;
      }

      originalEvent.preventDefault();
      originalEvent.stopImmediatePropagation();

      const vueMenu = $actionMenu.data('vueMenu') || await generateVueMenu($actionMenu);
      const entityId = $actionMenu.data('actionMenu').entityId;

      if (vueMenu.isOpen()) {
        vueMenu.closeMenu();
      } else {
        asyncEmit(PAGE_TEMPLATE_ACTION_MENU, {
          template,
          open: false,
          entityId: -entityId,
        });
        vueMenu.openMenu(
          originalEvent.originalEvent.clientX,
          originalEvent.originalEvent.clientY,
        );
      }

      return false;
    });
};

export default actionMenu;
