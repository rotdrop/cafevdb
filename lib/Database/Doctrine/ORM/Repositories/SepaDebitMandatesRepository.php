<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use DateTime;
use Exception;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * @method Entities\SepaDebitMandate find($id)
 */
class SepaDebitMandatesRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\PerMusicianSequenceTrait;

  /**
   * Find a SEPA-mandate by either its primary key or its mandate
   * reference; no-op if $idOrReference already is a SEPA-mandate
   * entity.
   *
   * @param string|array|Entities\SepaDebitMandate $idOrReference
   * Mandate-reference or primary key or entity instance.
   *
   * @param null|array $orderBy
   *
   * @return null|Entities\SepaDebitMandate
   */
  public function findOneBy(mixed $idOrReference, ?array $orderBy = null):?Entities\SepaDebitMandate
  {
    if ($idOrReference instanceof Entities\SepaDebitMandate) {
      return $idOrReference;
    } elseif (is_string($idOrReference)) {
      return parent::findOneBy([ 'mandateReference' => $idOrReference ], null);
    } elseif (is_array($idOrReference)) {
      return parent::findOneBy($idOrReference, $orderBy);
    } else {
      return null;
    }
  }

  /**
   * Try to persist the given bank-account by first fetching the
   * current sequence for its musician and then increasing it.
   *
   * @param Entities\SepaDebitMandate $mandate
   *
   * @return Entities\SepaDebitMandate
   *
   * @throws Doctrine\DBAL\Exception\UniqueConstraintViolationException
   */
  public function persist(Entities\SepaDebitMandate $mandate):Entities\SepaDebitMandate
  {
    return $this->persistEntity($mandate);
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
      $this->setDeletedAt(new DataTime());
      $this->getEntityManager()->flush($mandate);
    }
    return $mandate;
  }

  /**
   * Delete the given mandate if it is not used.
   *
   * @param int|Entities\SepaDebitMandate $idOrReference
   *
   * @return ?Entities\SepaDebitMandate
   */
  public function remove($idOrReference):Entities\SepaDebitMandate
  {
    $entityManager = $this->getEntityManager();
    $filterState = $entityManager->getFilters()->isEnabled(EntityManager::SOFT_DELETEABLE_FILTER);
    $entityManager->getFilters()->disable(EntityManager::SOFT_DELETEABLE_FILTER);
    $mandate = $this->findOneBy($idOrReference);
    if (!empty($mandate)) {
      $usage = $this->usage($mandate, true);
      $this->ban($mandate);
      if (empty($usage['lastUsed'])) {
        // second removal should really remove it
        $this->getEntityManager()->remove($mandate);
        $this->getEntityManager()->flush($mandate);
      }
    }
    if ($filterState) {
      $entityManager->getFilters()->enable(EntityManager::SOFT_DELETEABLE_FILTER);
    }
    return $mandate;
  }

  /**
   * Find the sepa-mandate with the highest sequence number, if
   * any. Deactivated mandates are ignored.
   *
   * @param int|Entities\Project $project
   *
   * @param int|Entities\Musician $musician
   *
   * @return ?SepaDebitMandate
   *
   * @todo Check that soft-delete behavior actually works as filter.
   */
  public function findNewest(mixed $project, mixed $musician):?Entities\SepaDebitMandate
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
   * @param bool $brief Omit detailed usage time-stamps.
   *
   * @return null|array
   */
  public function usage(mixed $identifier, bool $brief = false):?array
  {
    $selects = [
      'm.mandateReference',
      'm.deleted',
      "GREATEST(COALESCE(MAX(t.dueDate), ''), COALESCE(MAX(m.lastUsedDate), '')) AS lastUsed",
      'm.mandateDate AS mandateIssued',
    ];
    if (!$brief) {
      $selects = array_merge($selects, [
        'm.lastUsedDate AS mandateLastUsed',
        'MAX(p.dateOfReceipt) AS dateOfLastReceipt',
        'MAX(t.created) AS dateOfLastCreatedTransaction',
        'MAX(t.submitDate) AS dateOfLastSubmittedDebitNote',
        // 'IF(p.dateOfReceipt = MAX(t.dueDate), p.debitMessageId, NULL) AS debitNoteLastNotified',
        'CASE WHEN p.dateOfReceipt = MAX(t.dueDate) THEN p.notificationMessage ELSE \'\' END AS debitNoteLastNotified',
      ]);
    }

    // the what part ...
    $qb = $this->createQueryBuilder('m')
               ->select(implode(',', $selects))
               ->leftJoin('m.payments', 'p')
               ->leftJoin('p.sepaTransaction', 't')
               ->groupBy('m.mandateReference');

    // the where part ...
    if (is_string($identifier)) { // assume it is the mandate-reference
      $qb->where('m.mandateReference = :reference')
         ->setParameter('reference', $identifier);
    } elseif (is_array($identifier) || ($identifier instanceof Entities\SepaDebitMandate)) {
      $qb->where('m.project = :project')
         ->andWhere('m.musician = :musician')
         ->andWhere('m.sequence = :sequence')
         ->setParameter('project', $identifier['project'])
         ->setParameter('musician', $identifier['musician'])
         ->setParameter('sequence', $identifier['sequence']);
    } else {
      throw new Exception('Mandate identifier is '.(empty($identifier) ? 'empty' : 'unsupported'));
    }
    return $qb->getQuery()->getOneOrNullResult();
  }
}
