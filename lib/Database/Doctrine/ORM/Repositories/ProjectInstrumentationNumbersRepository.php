<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query;

class ProjectInstrumentationNumbersRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

  // SELECT
  //   i.Instrument,
  //   pi.Quantity AS Required,
  //   COUNT(mi.Id) AS Registered,
  //   COUNT(b.Id) AS Confirmed
  //   FROM `".self::INSTRUMENTATION."` pi
  // LEFT JOIN `".self::REGISTERED."` mi
  //   ON mi.ProjectId = pi.ProjectId AND mi.InstrumentId = pi.InstrumentId
  // LEFT JOIN `Besetzungen` b
  //   ON b.Id = mi.InstrumentationId AND b.Anmeldung = 1
  // LEFT JOIN `".self::INSTRUMENTS."` i
  //   ON i.Id = pi.InstrumentId
  // WHERE pi.ProjectId = $projectId
  // GROUP BY pi.ProjectId, pi.InstrumentId
  // ORDER BY i.Sortierung ASC;

  /**
   * Fetch the instrumentation balance for the given project-id, that
   * is, for each instrument and voice compute the number of required
   * instruments vs. pro-forma registered instrument vs. confirmed
   * instruments (ideally confirmed by written confirmation by
   * musician).
   *
   * @param int $projectId The projectId to fetch the balance for.
   *
   * @return array<int, array>
   * ```
   * [
   *   [
   *     'instrument' => INSTRUMENT,
   *     'voice' => VOICE,
   *     'required' => NUM_REQUIRED,
   *     'registered' => NUM_PRO_FORMA_REGISTERED,
   *   ],
   *   ...
   * ]
   * ```
   */
  public function fetchInstrumentationBalance(int $projectId):array
  {
    $em = $this->getEntityManager();

    $qb = $this->createQueryBuilder('pin');
    $qb->select([
      'i.name AS instrument',
      'pin.voice AS voice',
      'pin.quantity AS required',
      'count(pi.instrument) AS registered',
      'count(pp.registration) AS confirmed' ])
       ->leftJoin('pin.instrument', 'i')
       ->leftJoin('pin.instruments', 'pi')
       ->leftJoin(Entities\ProjectParticipant::class, 'pp',
                  Query\Expr\Join::WITH,
                  'pi.project = pp.project'
                  .' AND '
                  .'pi.musician = pp.musician'
                  .' AND '
                  .'pp.registration = 1')
       ->where($qb->expr()->eq('identity(pin.project)', ':projectId'))
       ->setParameter('projectId', $projectId)
       ->addGroupBy('pin.project')
       ->addGroupBy('pin.instrument')
       ->addGroupBy('pin.voice')
       ->orderBy('i.sortOrder', 'ASC')
       ->addOrderBy('pin.voice', 'ASC');

    // $this->log($qb->getQuery()->getSql());

    return $qb->getQuery()->getResult();
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
