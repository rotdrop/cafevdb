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

use OCP\IConfig;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Generate views in order to forward musician information as user-ids
 * to Nextcloud.
 */
class Version00000000000002 implements IMigration
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public function description():string
  {
    return $this->l->t('Create views for user_sql auth backend.');
  }  
  
  private const SQL = [
    "CREATE OR REPLACE VIEW NextcloudGroupView AS
select p.id AS gid,
       p.name AS display_name,
       0 AS is_admin
from Projects p
where p.type = 'permanent'",
    "CREATE OR REPLACE VIEW NextcloudUserGroupView AS
select m.user_id_slug AS uid,
       pp.project_id AS gid
from ProjectParticipants pp
left join Musicians m on m.id = pp.musician_id
left join Projects p on p.id = pp.project_id
where p.type = 'permanent'",
    "CREATE OR REPLACE VIEW NextcloudUserView AS
select m.id AS id,
       m.user_id_slug AS uid,
       m.user_id_slug AS username,
       m.user_passphrase AS password,
       concat_ws(' ', if(m.nick_name is null
                         or m.nick_name = '', m.first_name, m.nick_name), m.sur_name) AS name,
       m.email AS email,
       NULL AS quota,
       NULL AS home,
       if(m.deleted is null, 1, 0) AS active,
       if(m.deleted is null, 0, 1) AS disabled,
       0 AS avatar,
       NULL AS salt
from Musicians m
where m.id in
    (select pp.musician_id
     from ProjectParticipants pp
     left join Projects p on pp.project_id = p.id
     where p.type = 'permanent')",
  ];

  private const GRANTS = [
    "GRANT SELECT ON NextcloudUserView TO ?@'localhost'",
    "GRANT SELECT ON NextcloudGroupView TO ?@'localhost'",
    "GRANT SELECT ON NextcloudUserGroupView TO ?@'localhost'",
    "GRANT UPDATE (password) ON NextcloudUserView TO ?@'localhost'",
  ];

  /** @var IConfig */
  private $cloudConfig;

  /** @var EncryptionService */
  private $encryptionService;

  public function __construct(
    IConfig $cloudConfig
    , ILogger $logger
    , IL10N $l10n
    , EntityManager $entityManager
    , EncryptionService $encryptionService
  ) {
    $this->cloudConfig = $cloudConfig;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->entityManager = $entityManager;
    $this->encryptionService = $encryptionService;
  }

  public function execute():bool
  {
    $connection = $this->entityManager->getConnection();

    foreach (self::SQL as $sql) {
      $statement = $connection->prepare($sql);
      $statement->execute();
    }

    $cloudDbHost = $this->cloudConfig->getSystemValue('dbhost');
    $cloudDbUser = $this->cloudConfig->getSystemValue('dbuser');

    $appDbHost = $this->encryptionService->getConfigValue('dbserver');

    if ($cloudDbHost !== $appDbHost) {
      throw new \RuntimeException($this->l->t('Cloud database server "%s" and app database server "%s" must coincide.',  [ $cloudDbHost, $appDbHost ]));
    }

    foreach (self::GRANTS as $grant) {
      $stmt = $connection->prepare($grant);
      $stmt->bindValue(1, $cloudDbUser);
      $stmt->execute();
    }

    return true;
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
