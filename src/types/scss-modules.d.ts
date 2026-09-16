/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// declare module '*.scss';
declare module 'variables.module.scss' {
  import type { CSS_PREFIX_POSTFIX } from '../../build/ts-types/php-modules/PageRenderer/CssClasses.ts';
  import type { AppName } from '../config.ts';

  export const appName: AppName;
  export const appNameTag: `app-${AppName}`;
  export const pageTag: CSS_PREFIX_POSTFIX;
  export const cssPrefix: `${AppName}-`;
  export const disabledCssClass: 'disabled';
  export const expandedCssClass: 'expanded';
  export const hiddenCssClass: 'hidden';
  export const loadingCssClass: 'loading';
  export const reallyHiddenCssClass: 'reallyhidden';
  export const showDisabledCssClass: 'show-disabled';
  export const hideDisabledCssClass: 'hide-dsabled';
}

declare module 'emailform.module.scss' {
  export const displayCssClass: 'display';
  export const dropdownOpenCssClass: 'dropdown-open';
  export const editCssClass: 'edit';
  export const emptySelectionCssClass: 'empty-selection';
  export const noAttachmentsCssClass: 'no-attachments';
  export const notProjectModeCssClass: 'not-project-mode';
  export const onlyProjectModeCssClass: 'only-project-mode';
  export const projectModeCssClass: 'project-mode';
  export const projectModeOffCssClass: 'project-mode-off';
  export const showSelectableCssClass: 'show-selectable';
}

declare module 'tooltips.module.scss' {
  export const tooltipWideCssClass = 'tooltip-wide';
  export const tooltipVeryWideCssClass = 'tooltip-verywide';
  export const tooltipMostWideCssClass = 'tooltip-mostwide';
}

declare module 'cafevdb-selectize.scss';
declare module 'config-check.scss';
declare module 'donation-receipts.scss';
declare module 'emailform.scss';
declare module 'invoices.scss';
declare module 'mobile.scss';
declare module 'musicians.scss';
declare module 'oc-fixes.scss';
declare module 'personal-settings-popup.scss';
declare module 'project-participant-fields-display.scss';
declare module 'selectize/dist/css/selectize.bootstrap.css';
declare module 'sepa-bank-accounts.scss';

declare module '*.module.scss';
