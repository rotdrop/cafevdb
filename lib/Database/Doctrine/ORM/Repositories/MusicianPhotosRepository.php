<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;
use Doctrine\DBAL\Logging\DebugStack;

class MusicianPhotosRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

//   SELECT m.mac AS mac, vi.id AS viid, m.id AS id,
//     m.firstseen AS firstseen, m.lastseen AS lastseen,
//     c.id AS customerid, c.abbreviatedName AS customer,
//     s.name AS switchname,
//     GROUP_CONCAT( sp.name ) AS switchport,
//     GROUP_CONCAT( DISTINCT ipv4.address ) AS ip4,
//     GROUP_CONCAT( DISTINCT ipv6.address ) AS ip6,
//     COALESCE( o.organisation, 'Unknown' ) AS organisation

// FROM Entities\\MACAddress m
//     JOIN m.VirtualInterface vi
//     JOIN vi.VlanInterfaces vli
//     LEFT JOIN vli.IPv4Address ipv4
//     LEFT JOIN vli.IPv6Address ipv6
//     JOIN vi.Customer c
//     LEFT JOIN vi.PhysicalInterfaces pi
//     LEFT JOIN pi.SwitchPort sp
//     LEFT JOIN sp.Switcher s
//     LEFT JOIN Entities\\OUI o WITH SUBSTRING( m.mac, 1, 6 ) = o.oui

// GROUP BY m.mac, vi.id, m.id, m.firstseen, m.lastseen,
//     c.id, c.abbreviatedName, s.name, o.organisation

  // ORDER BY c.abbreviatedName ASC

  public function joinTable()
  {
    $em = $this->getEntityManager();
    $logger = new DebugStack();
    $em->getConfiguration()->setSQLLogger($logger);

    $qb = $em->createQueryBuilder()
             ->select([ 'mp.ownerId AS musicianId', 'i.id AS imageId' ])
             ->from($this->getEntityName(), 'mp')
             ->leftJoin('mp.image', 'i');
    //->leftJoin('i.imageData', 'id');
    $query = $qb->getQuery();
    $result = $query->getResult();

    self::log(print_r($logger->queries, true));
    $this->getEntityManager()->getConfiguration()->setSQLLogger(null);
    self::log(print_r($result, true));

    $view = new Doctrine_View($query, 'TestView');
    $view->create();

    return $result;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
