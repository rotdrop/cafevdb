<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;

use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Exceptions;

/** Fill the file-owners lookup table by looping over all musicians */
class FillEncryptedFileOwners extends AbstractMigration
{
  /** @var ProjectParticipantFieldsService */
  private $fieldsService;

  public function __construct(
    ILogger $logger
    , IL10N $l10n
    , EntityManager $entityManager
    , ProjectParticipantFieldsService $fieldsService
  ) {
    parent::__construct($logger, $l10n, $entityManager);
    $this->fieldsService = $fieldsService;
  }

  public function description():string
  {
    return $this->l->t('Fill the ownership-information for encrypted files.');
  }

  /**
   * Establish the join-table link between the given musician and the
   * file. $file may be empty.
   *
   * @param Entities\Musician $musician
   *
   * @param null|Entities\EncryptedFile $file
   */
  private function establishOwnership(Entities\Musician $musician, ?Entities\EncryptedFile $file)
  {
    if (empty($file)) {
      return;
    }
    $musician->getEncryptedFiles()->set($file->getId(), $file);
    $file->addOwner($musician);
  }

  public function execute():bool
  {
    $filterState = $this->disableFilter('soft-deleteable');

    $this->entityManager->beginTransaction();
    try {
      $musicians = $this->getDatabaseRepository(Entities\Musician::class)->findAll();
      /** @var Entities\Musician $musician */
      foreach ($musicians as $musician) {

        /** @var Collection $encryptedFiles */
        $encryptedFiles = $musician->getEncryptedFiles();

        /** @var Entities\SepaDebitMandate $debitMandate */
        foreach ($musician->getSepaDebitMandates() as $debitMandate) {
          $this->establishOwnership($musician, $debitMandate->getWrittenMandate());
        }

        /** @var Entities\CompositePayment $compositePayment */
        foreach ($musician->getPayments() as $compositePayment) {
          $this->establishOwnership($musician, $compositePayment->getSupportingDocument());
        }

        /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
        foreach ($musician->getProjectParticipantFieldsData() as $fieldDatum) {
          $this->establishOwnership($musician, $fieldDatum->getSupportingDocument());
          if ($fieldDatum->getField()->getDataType() == FieldDataType::DB_FILE) {
            $this->establishOwnership($musician, $this->fieldsService->getEffectiveFieldDatum($fieldDatum));
          }
        }

      }
      if ($this->entityManager->getTransactionNestingLevel() > 0) {
        $this->entityManager->commit();
      }
    } catch (\Throwable $t) {
      if ($this->entityManager->getTransactionNestingLevel() > 0) {
        try {
          $this->entityManager->rollback();
        } catch (\Throwable $t2) {
          $t = new Exceptions\DatabaseMigrationException($this->l->t('Rollback of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
        }
      }
      throw new Exceptions\DatabaseMigrationException($this->l->t('Transactional part of Migration "%s" failed.', $this->description()), $t->getCode(), $t);
    }

    return true;
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
