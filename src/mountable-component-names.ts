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

import type { LegacySqlQueryLogItem } from './types/legacy-query-log.d.ts';

export const DOKU_WIKI_WRAPPER = 'DokuWikiWrapper';
export const LEGACY_QUERY_LOG = 'LegacyQueryLog';
export const PROJECT_ACTIONS_MENU = 'ProjectActionsMenu';
export const SEPA_BULK_TRANSACTION_ACTIONS_MENU = 'SepaBulkTransactionActionsMenu';

export interface ComponentProps {
  [DOKU_WIKI_WRAPPER]: {
    wikiPage?: string,
    query?: Record<string, string>,
    iFrameAttributes?: Record<string, string>,
    fullScreen?: boolean,
  },
  [LEGACY_QUERY_LOG]: {
    queryLog: LegacySqlQueryLogItem[],
  },
  [PROJECT_ACTIONS_MENU]: {
    projectId: number,
    projectName: string,
    forceProjectName?: boolean,
    enableOverviewItem?: boolean,
    testOpen?: boolean,
  },
  [SEPA_BULK_TRANSACTION_ACTIONS_MENU]: {
    projectId: number,
    projectName: string,
    forceMenuCaption?: boolean,
    enableOverviewItem?: boolean,
    testOpen?: boolean,
  },
}

export type PropsData<C extends keyof ComponentProps> = ComponentProps[C];
