<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 */
class EncryptedFile extends File
{
  /**
   * @var FileData
   *
   * @ORM\OneToOne(targetEntity="EncryptedFileData", mappedBy="file", cascade="all", orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $fileData;

  public function __construct($fileName = null, $data = null, $mimeType = null) {
    parent::__construct($fileName, null, $mimeType);
    if (!empty($data)) {
      $fileData = new EncryptedFileData;
      $fileData->setData($data);
      $fileData->setFile($this);
      $this->setFileData($fileData);
    }
  }
}
