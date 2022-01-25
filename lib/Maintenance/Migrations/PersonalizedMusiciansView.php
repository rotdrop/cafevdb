<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\IConfig;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Statement as DBALStatement;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/**
 * Remove user field from attachments table, use Gedmo "blamable" and
 * "timestampable".
 */
class PersonalizedMusiciansView extends AbstractMigration
{
  const MUSICIAN_ID_TABLES = [
    'SepaBankAcounts' => 'musician_id',
    'SepaDebitMandates' => 'musician_id',
    'ProjectParticipants' => 'musician_id',
    'MusicianInstruments' => 'musician_id',
    'ProjectInstruments' => 'musician_id',
    'ProjectParticipantFieldsData' => 'musician_id',
    'ProjectPayments' => 'musician_id',
    'CompositePayments' => 'musician_id',
  ];
  const PROJECT_ID_TABLES = [
    'Projects' => 'id',
  ];
  const PARTICIPANT_FIELD_ID_TABLES = [
    'ProjectParticipantFields' => 'id',
    'ProjectParticpantFieldsDataOptions' => 'field_id',
  ];
  const UNRESTRICTED_TABLES = [
    'TableFieldTranslations',
  ];

  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE OR REPLACE FUNCTION MUSICIAN_USER_ID() RETURNS VARCHAR(256) CHARSET ascii
    NO SQL
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
  RETURN COALESCE(@CLOUD_USER_ID, SUBSTRING_INDEX(USER(), '@', 1));
END",
    ],
  ];

  /** @var IConfig */
  private $cloudConfig;

  /** @var EncryptionService */
  private $encryptionService;

  public function description():string
  {
    return $this->l->t('Add a view which only allows access to the row referring to the currently logged in cloud-user.');
  }

  private function restrictedViewName($table)
  {
    return 'Personalized' . $table . 'View';
  }

  public function __construct(
    ILogger $logger
    , IL10N $l10n
    , EntityManager $entityManager
    , IConfig $cloudConfig
    , EncryptionService $encryptionService
  ) {
    parent::__construct($logger, $l10n, $entityManager);

    $this->cloudConfig = $cloudConfig;
    $this->encryptionService = $encryptionService;

    $cloudDbHost = $this->cloudConfig->getSystemValue('dbhost');
    $cloudDbUser = $this->cloudConfig->getSystemValue('dbuser');

    $appDbHost = $this->encryptionService->getConfigValue('dbserver');

    if ($cloudDbHost !== $appDbHost) {
      throw new \RuntimeException($this->l->t('Cloud database server "%s" and app database server "%s" must coincide.',  [ $cloudDbHost, $appDbHost ]));
    }

    self::$sql[self::STRUCTURAL][] = [
      "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $this->restrictedViewName('Musicians') . "
AS
SELECT *
FROM Musicians m
WHERE m.user_id_slug = MUSICIAN_USER_ID()",
    ];

    foreach (self::MUSICIAN_ID_TABLES as $table => $column) {
      $viewName = $this->restrictedViewName($table);
      self::$sql[self::STRUCTURAL][] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " . $this->restrictedViewName('Musicians') . " pmv
  INNER JOIN " . $table . " t
    ON t." . $column . " = pmv.id";
    }

    foreach (self::PROJECT_ID_TABLES as $table => $column) {
      $viewName = $this->restrictedViewName($table);
      self::$sql[self::STRUCTURAL][] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " . $this->restrictedViewName('ProjectParticipants') . " pppv
  INNER JOIN " . $table . " t
    ON t." . $column . " = pppv.project_id";
    }

    foreach (self::PARTICIPANT_FIELD_ID_TABLES as $table => $column) {
      $viewName = $this->restrictedViewName($table);
      self::$sql[self::STRUCTURAL][] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.*
  FROM " . $this->restrictedViewName('ProjectParticipantFieldsData'). " pppfdv
  INNER JOIN " . $table . " t
    ON t." . $column . " = pppfdv.field_id";
    }

    foreach (self::UNRESTRICTED_TABLES as $table) {
      $viewName = $this->restrictedViewName($table);
      self::$sql[self::STRUCTURAL][] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $viewName . "
AS
SELECT t.* " . $table . " t";
    }

    $grantTables = array_merge(
      [ 'Musicians' ],
      array_keys(MUSICIAN_ID_TABLES),
      array_keys(PROJECT_ID_TABLES),
      array_keys(PARTICIPANT_FIELDS_TABLES),
      UNRESTRICTED_TABLES,
    );
    foreach ($grantTables as $table) {
      $viewName = $this->restrictedViewName($table);
      self::$sql[self::STRUCTURAL][] = [
        'statement' => "GRANT SELECT ON " . $viewName . " TO  ?@'localhost'",
        'bind' => function(DBALStatement $statement) use ($cloudDbUser) {
          $statement->bindParam(1, $cloudDbUser);
        },
      ];
    }
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
