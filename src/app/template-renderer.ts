/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// silly support function for stripping template: from a string

import { DataConstants } from '../../build/ts-types/php-modules/PageRenderer.ts';

const RENDERER_PREFIX = DataConstants.RENDERER_PREFIX_TAG;
export type RendererPrefixType = typeof DataConstants.RENDERER_PREFIX_TAG;
export type TemplateRenderer<S extends string, T extends RendererPrefixType = RendererPrefixType> = `${T}${S}`;

export const templateRenderer = <S extends string>(template: S): TemplateRenderer<S> => `${RENDERER_PREFIX}${template}`;

export const templateFromRenderer = <S extends string>(templateRenderer: TemplateRenderer<S>): S => templateRenderer.replace(RENDERER_PREFIX, '') as S;
