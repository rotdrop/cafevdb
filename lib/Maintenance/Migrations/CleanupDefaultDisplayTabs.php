<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as Multiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as DataType;

/**
 * Replace a legacy one-table solution by a clean join-table.
 */
class CleanupDefaultDisplayTabs extends AbstractMigration
{
  private const DATA_TABLE = 'ProjectParticipantFields';
  private const TRANSLATIONS_TABLE = 'TableFieldTranslations';

  private const PROJECT_EXCLUDE = [
    DataType::SERVICE_FEE,
    DataType::CLOUD_FILE,
    DataType::CLOUD_FOLDER,
    DataType::DB_FILE,
  ];
  private const ATTACHMENT_TYPES = [
    DataType::CLOUD_FILE,
    DataType::CLOUD_FOLDER,
    DataType::DB_FILE,
  ];

  public function __construct(
    ILogger $logger
    , IL10N $l10n
    , EntityManager $entityManager
  ) {
    parent::__construct($logger, $l10n, $entityManager);
    self::$sql = [
      self::TRANSACTIONAL => [
        "UPDATE `".self::DATA_TABLE."` SET tab = NULL WHERE
  tab = 'project'
  AND NOT data_type IN ('".implode("','", self::PROJECT_EXCLUDE)."')",
        "UPDATE `".self::DATA_TABLE."` SET tab = NULL
WHERE
  tab = 'finance' AND data_type = '".DataType::SERVICE_FEE."'",
        "UPDATE `".self::DATA_TABLE."` SET tab = NULL
WHERE
  tab = 'file-attachments' AND data_type in ('".implode("','", self::ATTACHMENT_TYPES)."')",
        "UPDATE `".self::DATA_TABLE."` SET tab = NULL WHERE tab = ''",
        // clean up translations table
        "DELETE t FROM `".self::TRANSLATIONS_TABLE."` t
LEFT JOIN `".self::DATA_TABLE."` f
ON t.foreign_key = f.id
WHERE t.field = 'tab'
  AND t.object_class = '".str_replace('\\', '\\\\', Entities\ProjectParticipantField::class)."'
  AND t.locale = 'de_DE'
  AND f.tab IS NULL
  AND ((f.data_type IN ('".implode("','", self::ATTACHMENT_TYPES)."')
        AND t.content LIKE '".str_replace([' ', '-'], ['_','_'], $this->l->t('file-attachments'))."'
       OR
       (f.data_type NOT IN ('".implode("','", self::PROJECT_EXCLUDE)."')
        AND t.content = 'project')))",
        "DELETE FROM `".self::TRANSLATIONS_TABLE."` WHERE content IS NULL",
      ],
    ];
  }

  public function description():string
  {
    return $this->l->t('Remove some default display-tab definitions from the participant-fields tables.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
