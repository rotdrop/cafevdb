<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Enums;

/**
 * Enum for "participant-fields" data-types.
 *
 * @see \OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipantField
 */
enum EnumParticipantFieldDataType: string
{
  /**
   * @var string
   * Plain UTF-8 text field.
   */
  case TEXT = 'text';
  /**
   * @var string
   * HTML text field.
   */
  case HTML = 'html';
  /**
   * @var string
   * Yes/no value.
   */
  case BOOLEAN = 'boolean';
  /**
   * @var string
   * Integral number.
   */
  case INTEGER = 'integer';
  /**
   * @var string
   * Floating point number.
   */
  case FLOAT = 'float';
  /**
   * @var string
   * A date without time.
   */
  case DATE = 'date';
  /**
   * @var string
   * A date with time information.
   */
  case DATETIME = 'datetime';
  /**
   * @var string
   * A service-fee value with the convention that positive values denote
   * receivables and negative values denote liabilities (from the view of the
   * orchestra.
   */
  case SERVICE_FEE = 'service-fee';
  /**
   * @var string
   * Single-file upload data which is stored in the storage of the ambient
   * cloud software.
   */
  case CLOUD_FILE = 'cloud-file';
  /**
   * @var string
   * Multi-file upload data which is stored in the storage of the ambient
   * cloud software under a folder with the configured name.
   */
  case CLOUD_FOLDER = 'cloud-folder';
  /**
   * @var string
   * Single-file upload data which is stored as blob in the database. The
   * total encoded size is limited by the used database backend and its
   * associated data-type.
   */
  case DB_FILE = 'db-file';
}
