/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { AsyncNextcloudEvents } from '@rotdrop/async-nextcloud-event-bus';
import type { WIKI_POPUP } from '../event-bus-events.ts';
import type { MountableComponent } from '../services/mountable-components.ts';

import { awaitEmit } from '@rotdrop/async-nextcloud-event-bus';
import { appName } from '../config.ts';
import { GET_VUE_COMPONENT } from '../event-bus-events.ts';
import { DOKU_WIKI_WRAPPER } from '../mountable-component-names.ts';
import { toBackButton as dialogToBackButton } from './dialog-utils.ts';
import $ from './jquery.js';
import modalizer from './modalizer.ts';

require('dokuwiki-jquery-popup.scss');

// declare global {
//   interface JQuery<TElement extends HTMLElement> {
//     cafevDialog: JQuery<TElement>['dialog'],
//     chosen: (options?: string|Record<string, unknown>) => JQuery<TElement>,
//     tooltip(options: Record<string, unknown>): JQuery<TElement>,
//     tooltip(method: string): JQuery<TElement>,
//     cafevTooltip: JQuery<TElement>['tooltip'],
//     hasVerticalScrollbar(): boolean,
//   }
// }

let dokuWikiWrapper: undefined|MountableComponent<typeof DOKU_WIKI_WRAPPER>;

let wikiContentHeight = -1;

const popupPosition = {
  my: 'left top',
  at: 'left+5% top+5%',
  // of: window
  of: '#app-content, #app-content-vue', // main?
};

/**
 * Generate a popup-dialog with a wiki-page. Not to much project
 * related, rather general. Page and page-title are assumed to be
 * attached to the "post"-object
 *
 * @param post Arguments
 *
 * @param [reopen] If true, close any already dialog and re-open it
 * (the default). If false, only raise an existing dialog to top.
 */
const wikiPopup = async (post: AsyncNextcloudEvents[typeof WIKI_POPUP]['arg'], reopen?: boolean) => {
  if (typeof reopen === 'undefined') {
    reopen = false;
  }
  let $dialogHolder: undefined|JQuery = $('#dokuwiki_popup');
  if ($dialogHolder.dialog('isOpen') === true) {
    if (reopen === false) {
      $dialogHolder.dialog('moveToTop');
      return;
    }
    $dialogHolder.dialog('close').remove();
    $dialogHolder = undefined;
    dokuWikiWrapper?.destroy();
    dokuWikiWrapper = undefined;
  }
  if (!dokuWikiWrapper) {
    dokuWikiWrapper = await awaitEmit(GET_VUE_COMPONENT, {
      name: DOKU_WIKI_WRAPPER,
      propsData: {
        wikiPage: post.wikiPage,
        fullScreen: false,
      },
    }) as MountableComponent<'DokuWikiWrapper'>;
    if (!dokuWikiWrapper) {
      return;
    }
  } else {
    // this is supposedly illegal and also skips the consistency
    // checks, but maybe it just works ... ;)
    dokuWikiWrapper.props!.fullScreen = false;
    dokuWikiWrapper.props!.wikiPage = post.wikiPage;
  }
  if (!$dialogHolder || $dialogHolder.length === 0) {
    $dialogHolder = $('<div id="dokuwiki_popup" style="overflow:hidden;"><div></div></div>');
    dokuWikiWrapper.mount($dialogHolder.find('div')[0]);
  }

  $dialogHolder.cafevDialog({
    title: post.popupTitle,
    dialogClass: [
      'dokuwiki-page-popup',
      appName,
    ].join(' '),
    modal: false,
    position: popupPosition,
    width: 'auto',
    height: 'auto',
    closeOnEscape: false,
    resizable: false,
    draggable: true,
    open() {
      dialogToBackButton($dialogHolder);
      const $dialogWidget = $dialogHolder.dialog('widget');
      const titleHeight = $dialogWidget.find('.ui-dialog-titlebar').outerHeight()!;
      dokuWikiWrapper!.props.onIframeLoaded = (...args) => {
        console.debug('WIKI POPUP LOADED LISTENER', { ...args });
        const newHeight = $dialogWidget.height()! - titleHeight;
        $dialogHolder.height(newHeight);
      };
      dokuWikiWrapper!.props.onIframeResize = (event: ResizeObserverEntry) => {
        console.debug('WIKI POPUP RESIZE LISTENER', { event });
        const height = event.contentRect.height;
        if (height === wikiContentHeight || height === 0) {
          return;
        }
        wikiContentHeight = height;
        const wikiIFrame = dokuWikiWrapper!.wikiIFrame as HTMLIFrameElement;
        console.debug('new height', {
          height,
          contentHeight: wikiContentHeight,
          frameHeight: wikiIFrame.style.height,
        });
        // $dialogHolder.contentHeight = height;
        wikiIFrame.style.height = height + 'px';
        $dialogHolder.height(height);
        $dialogWidget.height(height + titleHeight);
        const widgetHeight = $dialogWidget.outerHeight()!;
        const maxHeight = widgetHeight - titleHeight;
        wikiIFrame.style.maxHeight = maxHeight + 'px';
        $dialogHolder.height(maxHeight);
      };
      console.info('DW WRAPPER', { dokuWikiWrapper });
    },
    close() {
      modalizer(false);
      dokuWikiWrapper!.props.onIframeLoaded =
        dokuWikiWrapper!.props.onIframeResize = undefined;
    },
  });
};

export default wikiPopup;
