<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller\DTO;

use BackedEnum;
use InvalidArgumentException;

use OCA\CAFEVDB\Controller\EnumFileUploadOrigin;
use OCA\CAFEVDB\Controller\EnumFileUploadMode;

/**
 * DTO upload file data as reported by PHP, a bit enhanced.
 *
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 */
class UploadFileData extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly string $name,
    public readonly ?int $error,
    public readonly ?string $str_error,
    public readonly ?string $message, // why a single message?
    public readonly ?string $tmp_name,
    public readonly string $type,
    public readonly int $size,
    public readonly string $original_name,
    public readonly int $upload_max_file_size,
    public readonly string $max_human_file_size,
    public readonly ?UploadFileMetaData $meta,
    public readonly ?EnumFileUploadOrigin $origin,
    public readonly ?EnumFileUploadMode $upload_mode,
    public readonly ?string $status = null,
  ) {
  }

  /**
   * Create from a data array.
   *
   * @param array $data
   *
   * @return IBANMetatData
   *
   * @throws InvalidArgumentException
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   * @SuppressWarnings(PHPMD.CamelCaseVariableName)
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    if (is_array($meta)) {
      $meta = empty($meta) ? null : UploadFileMetaData::fromArray($meta);
    }
    if (!empty($origin)) {
      try {
        $origin = EnumFileUploadOrigin::get($origin instanceof BackedEnum ? $origin->value : $origin);
      } catch (InvalidArgumentException $e) {
        throw $e;
      }
    }
    if (!empty($upload_mode)) {
      try {
        $upload_mode = EnumFileUploadMode::get($upload_mode);
      } catch (InvalidArgumentException $e) {
        throw $e;
      }
    }
    return new self(
      $name,
      $error ?? null,
      $str_error ?? null,
      $message ?? null,
      $tmp_name ?? null,
      $type,
      $size,
      $original_name,
      $upload_max_file_size,
      $max_human_file_size,
      meta: $meta ?? null,
      origin: $origin ?? null,
      upload_mode: $upload_mode ?? null,
      status: $status ?? null,
    );
  }
}
