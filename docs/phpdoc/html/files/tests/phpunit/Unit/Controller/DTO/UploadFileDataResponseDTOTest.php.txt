<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Controller\DTO;

use DateTime;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Controller\EnumFileUploadOrigin;
use OCA\CAFEVDB\Controller\EnumFileUploadMode;

use OCA\CAFEVDB\Controller\DTO\UploadFileData as TestedDTO;

/** Consistency test for ValidatePhoneResponse DTO. */
#[Attributes\CoversClass(TestedDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\UploadFileMetaData::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
class UploadFileDataResponseDTOTest extends TestCase
{
  use TestResponseDTOTrait;
  use UploadFileMetaDataTrait;

  private const DTO_CLASS = TestedDTO::class;

  private TestedDTO $dto;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup(): void
  {
    $this->dto = new TestedDTO(
      name: 'name',
      error: 0,
      str_error: 'str_error',
      message: 'message',
      tmp_name: 'tmp_name',
      type: 'type',
      size: 47,
      original_name: 'original_name',
      upload_max_file_size: 15,
      max_human_file_size: 'fifteen bytes',
      meta: $this->getUploadFileMetaData(),
      origin: EnumFileUploadOrigin::UPLOAD,
      upload_mode: EnumFileUploadMode::COPY,
    );
  }
}
