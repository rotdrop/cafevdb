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

use Doctrine\ORM\Query;
use Doctrine\ORM\EntityRepository;

class SepaDebitMandatesRepository extends EntityRepository
{
  /**
   * Fetch the mandate reference for the mandate with the highest
   * sequence number.
   */
  public function fetchReference($project, $musician)
  {
    return $this->createQueryBuilder('m')
                ->select('m.mandateReference')
                ->orderBy('m.sequence', 'DESC')
                ->getQuery()
                ->getSingleScalarResult();
  }

  /**
   * Find a SEPA-mandate by either its primary key or its mandate
   * reference; no-op if $idOrReference already is a SEPA-mandate
   * entity.
   *
   * @param string|array|Entities\SepaDebitMandate $idOrReference
   * Mandate-reference or primary key or entity instance.
   */
  public function findOneBy($idOrReference, array $orderBy = null)
  {
    if ($idOrReference instanceof Entities\SepaDebitMandate) {
      return $idOrReference;
    } else if (is_string($idOrRerence)) {
      return parent::findOneBy([ 'mandateReference' => $idOrReference ], null);
    } else if (is_array($idOrReference)) {
      return parent::findOneBy($idOrReference, $orderBy);
    } else {
      return null;
    }
  }

  /**
   * Ban a SEPA-mandate (timeout, withdrawn, erroneous data
   * etc.). This flags the mandate as deleted, but we have to keep
   * the data for the book-keeping.
   *
   * @param string|array|Entities\SepaDebitMandate $idOrReference
   * Mandate-reference or primary key or entity instance.
   *
   * @return ?Entities\SepaDebitMandate
   */
  public function ban($idOrReference):?Entities\SepaDebitMandate
  {
    $mandate = $this->findOneBy($idOrReference);
    if (!empty($mandate) && !$mandate->isDeleted()) {
      $this->setDeletedAt(new \DataTime());
      $this->getEntityManager()->flush($mandate);
    }
    return $mandate;
  }

  /**
   * Delete the given mandate if it is not used.
   *
   * @return ?Entities\SepaDebitMandate
   */
  public function remove($idOrReference)
  {
    $entityManager = $this->getEntityManager();
    $filter = $entityManager->getFilters()->disable('soft-deletable');
    $mandate = $this->findOneBy($idOrReference);
    if (!empty($mandate)) {
      $usage = $this->usage($mandate, true);
      $this->ban($mandate);
      if (!empty($usage['lastUsed'])) {
        // second removal should really remove it
        $this->getEntityManager()->remove($mandate);
        $this->getEntityManager()->flush($mandate);
      }
    }
    $filter = $entityManager->getFilters()->enable('soft-deletable');
    return $mandate;
  }

  /**
   * Find the sepa-mandate with the highest sequence number, if
   * any. Deactivated mandates are ignored.
   *
   * @todo Check that soft-delete behavior actually works as filter.
   *
   * @return ?SepaDebitMandate
   */
  public function findNewest($project, $musician): ?SepaDebitMandate
  {
    return $this->findOneBy(
      [ 'project' => $project, 'musician' => $musician ],
      [ 'sequence' => 'DESC', ]);
  }


  /**
   * Fetch usage information about the given identifier.
   *
   * @param mixed $identifier Primary key(s), or an entity instance or
   * the mandate reference.
   *
   * @param boolean $brief Omit detailed usage time-stamps
   */
  public function usage($identifier, $brief = false)
  {

    // @todo find about multiple incremental selects ...
    $selects = [
      'm.mandateReference',
      'm.disabled',
      'GREATEST(COALESCE(MAX(d.dueDate), ""), COALESCE(MAX(m.lastUsedDate), "")) AS lastUsed',
      'm.mandateDate AS mandateIssued',
    ];
    if (!$brief) {
      $selects = array_merge($selects, [
        'm.lastUsedDate AS mandateLastUsed',
        'MAX(d.dateIssued) AS debitNoteLastIssued',
        'MAX(d.submitDate) AS debitNoteLastSubmitted',
        'IF(p.dateOfReceipt = MAX(d.dueDate), p.debitMessageId, NULL) AS debitNoteLastNotified',
      ]);
    }

    // the what part ...
    $qb = $this->createQueryBuilder('m')
               ->select(implode(',', $selects))
               ->leftJoin('m.projectPayments', 'p')
               ->leftJoin('p.debitNote', 'd')
               ->groupBy('m.mandateReference');

    // the where part ...
    if (is_string($identifier)) { // assume it is the mandate-reference
      $qb->where('m.mandateReference = :reference')
         ->setParamter('reference', $identifier);
    } else if (is_array($identifier) || ($identifier instanceof Entities\SepaDebitMandate)) {
      $qb->where('m.project = :project')
         ->andWhere('m.musician = :musician')
         ->andWhere('m.sequence = :sequence')
         ->setParameter('project', $identifier['project'])
         ->setParameter('musician', $identifier['musician'])
         ->setParameter('sequence', $identifeir['sequence']);
    } else {
      throw new \Exception('Mandate identifier is '.(empty($identifier) ? 'empty' : 'unsupported'));
    }
    return $qb->getQuery()->getOneOrNullResult();
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
