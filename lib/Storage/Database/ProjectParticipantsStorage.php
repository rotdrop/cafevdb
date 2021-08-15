<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage\Database;

// FIXME: those are not public, but ...
use OC\Files\Storage\Common as AbstractStorage;
use OC\Files\Storage\PolyFill\CopyDirectory;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Common\Util;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class ProjectParticipantsStorage extends Storage
{
  /** @var Entities\Project */
  private $project;

  /** @var Entities\ProjectParticipant */
  private $participant;

  /** @var array */
  private $files = [];

  public function __construct($params)
  {
    parent::__construct($params);
    $this->participant = $params['participant'];
    $this->project = $this->participant->getProject();
  }

  /**
   * {@inheritdoc}
   */
  protected function fileNameFromEntity(Entities\File $file):string
  {
    $fileName = $file->getFileName();
    if (empty($fileName)) {
      $fileName = parent::fileNameFromEntity($file);
    }
    return $fileName;
  }

  /**
   * {@inheritdoc}
   */
  protected function fileFromFileName(string $name):?Entities\File
  {
    $name = $this->buildPath($name);
    $name = pathinfo($name, PATHINFO_BASENAME);

    if (empty($this->files)) {
      $this->findFiles();
    }

    return $this->files[$name] ?? null;
  }

  /**
   * {@inheritdoc}
   */
  protected function findFiles()
  {
    $this->files = [];
    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
    foreach ($this->participant->getParticipantFieldsData() as $fieldDatum) {
      $dataType = $fieldDatum->getField()->getDataType();
      switch ($dataType) {
      case FieldType::DB_FILE:
        $fileId = (int)$fieldDatum->getOptionValue();
        $file = $this->filesRepository->find($fileId);
        if (!empty($file)) {
          $this->files[$file->getFileName()] = $file;
        }
        break;
      case FieldType::SERVICE_FEE:
        $file = $fieldDatum->getSupportingDocument();
        if (!empty($file)) {
          $this->files[$file->getFileName()] = $file;
        }
        break;
      default:
        continue 2;
      }
    }
    return array_values($this->files);
  }

  /**
   * {@inheritdoc}
   */
  protected function getStorageModificationTime()
  {
    $date = (new \DateTimeImmutable)->setTimestamp(0);
    /** @var Entities\File $file */
    foreach ($this->findFiles() as $file) {
      $updated = $file->getUpdated();
      if ($updated > $date) {
        $date = $updated;
      }
    }
    return $date->getTimestamp();
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return $this->appName()
      . '::'
      . 'database-storage/'
      . $this->project->getName()
      . 'participants/'
      . $this->participant->getMusician()->getUserIdSlug()
      . $this->root;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
