<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;
use OCP\IConfig;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Statement as DBALStatement;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Database\EntityManager;

/**
 * Remove user field from attachments table, use Gedmo "blamable" and
 * "timestampable".
 */
class NextcloudUserSqlGroupNames extends AbstractMigration
{
  /** @var IConfig */
  private $cloudConfig;

  /** @var EncryptionService */
  private $encryptionService;

  /** @var string */
  private $appName;

  public function description():string
  {
    return $this->l->t('Make UserSQL group-ids unique by prepending the app-name.');
  }

  private function viewName($table)
  {
    return 'Nextcloud' . $table . 'View';
  }

  private function gidSQL($projectIdField)
  {
    return "CONCAT(_ascii '" . $this->appName . ":', " . $projectIdField . ") COLLATE ascii_bin";
  }

  public function __construct(
    $appName
    , ILogger $logger
    , IL10N $l10n
    , EntityManager $entityManager
    , IConfig $cloudConfig
    , EncryptionService $encryptionService
  ) {
    parent::__construct($logger, $l10n, $entityManager);

    $this->appName = $appName;
    $this->cloudConfig = $cloudConfig;
    $this->encryptionService = $encryptionService;

    $cloudDbHost = $this->cloudConfig->getSystemValue('dbhost');
    $cloudDbUser = $this->cloudConfig->getSystemValue('dbuser');

    $appDbHost = $this->encryptionService->getConfigValue('dbserver');

    if ($cloudDbHost !== $appDbHost) {
      throw new \RuntimeException($this->l->t('Cloud database server "%s" and app database server "%s" must coincide.',  [ $cloudDbHost, $appDbHost ]));
    }

    self::$sql[self::STRUCTURAL][] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $this->viewName('Group'). " AS
SELECT " . $this->gidSQL('p.id') . " AS gid,
       p.name AS display_name,
       0 AS is_admin
from Projects p
where p.type = 'permanent'";
    self::$sql[self::STRUCTURAL][] = "CREATE OR REPLACE
SQL SECURITY DEFINER
VIEW " . $this->viewName('UserGroup') . " AS
select m.user_id_slug AS uid,
       " . $this->gidSQL('pp.project_id') . " AS gid
from ProjectParticipants pp
left join Musicians m on m.id = pp.musician_id
left join Projects p on p.id = pp.project_id
where p.type = 'permanent'";

    $grantTables = [
      'Group',
      'UserGroup'
    ];
    foreach ($grantTables as $table) {
      $viewName = $this->viewName($table);
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
