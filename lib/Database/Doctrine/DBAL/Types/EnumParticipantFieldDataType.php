<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2023, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

/**
 * Enum for "participant-fields" data-types.
 */
enum EnumParticipantFieldDataType: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait;

  /** A yes/no value. */
  case BOOLEAN = 'boolean';
  /**
   * Single-file upload data which is stored in the storage of the ambient
   * cloud software.
   */
  case CLOUD_FILE = 'cloud-file';
  /**
   * Multi-file upload data which is stored in the storage of the ambient
   * cloud software under a folder with the configured name.
   */
  case CLOUD_FOLDER = 'cloud-folder';
  /** A date without time. */
  case DATE = 'date';
  /** A date including time information. */
  case DATETIME = 'datetime';
  /**
   * Single-file upload data which is stored as blob in the database. The
   * total encoded size is limited by the used database backend and its
   * associated data-type.
   */
  case DB_FILE = 'db-file';
  /** Floating point number. */
  case FLOAT = 'float';
  /** HTML text field. */
  case HTML = 'html';
  /** Integral number. */
  case INTEGER = 'integer';
  /**
   * A monetary value with the convention that positive values denote
   * liabilities and negative values denote receivables (from the view of the
   * orchestra.
   */
  case LIABILITIES = 'liabilities';
  /**
   * A monetary value with the convention that positive values denote
   * receivables and negative values denote liabilities (from the view of the
   * orchestra.
   */
  case RECEIVABLES = 'receivables';
  /** Plain UTF-8 text field. */
  case TEXT  = 'text';
}
