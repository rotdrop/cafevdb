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

import { EnumFileUploadMode } from '../../../build/ts-types/php-modules/Controller.ts';

export type TemplateFileUploadMode = Exclude<EnumFileUploadMode, EnumFileUploadMode.TEST>;

export type TemplateParameters = {
  cloudFileSystemOperations: {
    files: string;
    operations: string;
    widgetCssClass: string;
    widgetRadioName: string;
  } & {
    [ḳ in `${TemplateFileUploadMode}CssClass`]?: string;
  } & {
    [ḳ in `${TemplateFileUploadMode}Selected`]?: ''|'checked';
  } & {
    [ḳ in `${TemplateFileUploadMode}Disabled`]?: ''|'disabled';
  };
  fileUploadTemplate: {
    wrapperId: string;
    formClass: string;
    accept: string;
    uploadName: string;
    requestToken: string;
    uploadData?: string;
    projectId?: number;
    musicianId?: number;
  };
  imageFileUploadTemplate: {
    formId: string;
    ownerId: number;
    imageId: number;
    joinTable: string;
    requestToken: string;
    imageSize: number;
  };
  musicianAddressViewTemplate: {
    id: number;
    personalPublicName: string;
    email?: string;
    fixedLinePhone?: string;
    mobilePhone?: string;
    addressSupplement?: string;
    streetAndNumber: string;
    postalCode?: string;
    country?: string;
    duplicatesProbability: number;
    reasons: string;
  };
  progressWrapperTemplate: {
    wrapperId: strin;g
    caption: string;
    label: string;
  };
};
