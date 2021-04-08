<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use MyCLabs\Enum\Enum as EnumType;

/**
 * Enum for "participant-fields" data-types.
 *
 * @see \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantField
 *
 * @method static EnumParticipantFieldDataType TEXT()
 *   Plain UTF-8 text field.
 * @method static EnumParticipantFieldDataType HTML()
 *   HTML text field.
 * @method static EnumParticipantFieldDataType BOOLEAN()
 *   Yes/no value.
 * @method static EnumParticipantFieldDataType INTEGER()
 *   Integral number.
 * @method static EnumParticipantFieldDataType FLOAT()
 *   Floating point number.
 * @method static EnumParticipantFieldDataType DATE()
 *   A date without time.
 * @method static EnumParticipantFieldDataType DATETIME()
 *   A date with time information.
 *
 * @method static EnumParticipantFieldDataType SERVICE_FEE()
 * A service-fee value with the convention that positive values denote
 * receivables and negative values denote liabilities (from the view
 * of the orchestra.
 *
 * @method static EnumParticipantFieldDataType DEPOSIT()
 * A financial deposit which may be charged in advance to the total
 * amount of receivables.
 *
 * @method static EnumParticipantFieldDataType FILE_DATA()
 * Single-file upload data which is stored as inline URI in the
 * database. The total encoded size is limited by the used database
 * backend and its associated data-type.
 *
 * @method static self UPLOAD_AREA()
 * A file-upload area. Files can be uploaded to a dedicated file-space
 * in the cloud which is tagged by the participants UUID and the UUID
 * of the field.
 */
class EnumParticipantFieldDataType extends EnumType
{
  private const TEXT = 'text';
  private const HTML = 'html';
  private const BOOLEAN = 'boolean';
  private const INTEGER = 'integer';
  private const FLOAT = 'float';
  private const DATE = 'date';
  private const DATETIME = 'datetime';
  private const SERVICE_FEE = 'service-fee';
  private const DEPOSIT = 'deposit';
  private const FILE_DATA = 'file-data';

  /**
   * {@see EnumParticipantFieldDataType::UPLOAD_AREA()}
   */
  private const UPLOAD_AREA = 'upload-area';
};
