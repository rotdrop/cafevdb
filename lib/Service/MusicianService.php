<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumMemberStatus as MemberStatus;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;

use OCA\CAFEVDB\Common\Util;

/**
 * General support service, kind of inconsequent glue between
 * Doctrine\ORM and CAFEVDB\PageRenderer.
 */
class MusicianService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const DBTABLE = 'Musicians';

  /** @var MailingListsService */
  private $listsService;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
    , MailingListsService $listsService
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->listsService = $listsService;
    $this->l = $this->l10n();
  }

  /**
   * Ensure that the musicain indeed has a user-id-slug. In principle
   * this should never happen when the app runs in production mode ...
   *
   * @return string The user-id slug.
   */
  public function ensureUserIdSlug(Entities\Musician $musician)
  {
    if (empty($musician->getUserIdSlug())) {
      $musician->setUserIdSlug(\Gedmo\Sluggable\SluggableListener::PLACEHOLDER_SLUG);
      $this->persist($musician);
      $this->flush();
    }
    return $musician->getUserIdSlug();
  }

  /**
   * Convert a user-id to a file-name-part avoiding "duplicate" extensions,
   * i.e. use ClausJustusHeine.pdf instead of claus-justus.heine.pdf
   */
  public static function userIdSlugToFileName(string $userIdSlug):string
  {
    return Util::dashesToCamelCase($userIdSlug, true, '_-.');
  }

  public static function getSlugPostfix(string $userIdSlug):string
  {
    return '-' . self::userIdSlugToFileName($userIdSlug);
  }

  public static function slugifyFileName(string $base, string $userIdSlug, bool $ignoreExtension = false):string
  {
    if ($ignoreExtension) {
      $fileName = $base;
      $extension = '';
    } else {
      $pathInfo = pathinfo($base);
      $fileName = $pathInfo['filename'];
      $extension = empty($pathInfo['extension']) ? '' : '.' . $pathInfo['extension'];
    }
    $fileName = str_replace('.', '-', $fileName);
    return $fileName . self::getSlugPostfix($userIdSlug) . $extension;
  }

  public static function isSlugifiedFileName(string $path, string $userIdSlug):bool
  {
    $fileName = pathinfo($path, PATHINFO_FILENAME);
    return str_ends_with($fileName, self::getSlugPostfix($userIdSlug));
  }

  public static function unSlugifyFileName(string $path, string $userIdSlug, bool $keepExtension = true):string
  {
    $pathInfo = pathinfo($path);
    $fileName = $pathInfo['filename'];
    $postfix = self::getSlugPostfix($userIdSlug);
    $extension = empty($pathInfo['extension']) || !$keepExtension ? '' : '.' . $pathInfo['extension'];
    if (str_ends_with($fileName, $postfix)) {
      return substr($fileName, 0, -strlen($postfix)) . $extension;
    }
    return $fileName . $extension;
  }

  /**
   * As a last resort generate a "disabled" email address pointing back to the
   * orchestra from the user-id and optionally an existing (broken) email
   * address.
   *
   * @param Entities\Musician $musician
   *
   * @param null|string $email
   *
   * @return string
   */
  public function generateDisabledEmailAddress(Entities\Musician $musician, ?string $email = null):string
  {
    $orchestraAddress = $this->getConfigValue('emailfromaddress');
    if (!empty($email)) {
      $email = '_' . preg_replace('|[@+!./]|', '_', $email);
    }
    $email = str_replace('@', '+' . $musician->getUserIdSlug() . ($email ?? '') . '@', $orchestraAddress);

    return $email;
  }

  /**
   * Remove all personal data from the record in order to keep
   * project-structures intact but still honour privacy regulations.
   * This function then would have to cleanup data-base storage and
   * cloud-storage as well.
   *
   * Musicians with remaining open obligations are not impersonated.
   *
   * @param Entities\Musician $musician
   *
   * @todo Musicians without OPEN payments can safely be remove, worst case
   * after a couple of years. ATM we just block.
   */
  protected function impersonateMusician(Entities\Musician $musician):void
  {
    $financialArtifacts = ProjectParticipantFieldsService::participantMonetaryObligations($musician);
    if ($financialArtifacts['sum'] !== $financialArtifacts['received']) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t(
          'Musician "%1$s" cannot be removed because there are unbalanced financial obligations totals/payed/remaining = %$2d/%3d/%3d. A negative remaining amount means the musician still needs to be refund by the orchestra.', [
            $musician->getPublicName(),
            $financialArtifacts['sum'],
            $financialArtifacts['received'],
            $financialArtifacts['sum'] - $financialArtifacts['received'],
          ]
        )
      );
    }
    if ($musician->getPayments()->count() > 0) {
      throw new Exceptions\EndUserNotificationException(
        $this->l->t('Musician "%s" cannot be removed because there are financial records which depend on this musician.', $musician->getPublicName()));
    }

    /** @var ProjectParticipantFieldsService $participantFieldsService */
    $participantFieldsService = $this->di(ProjectParticipantFieldsService::class);

    /** @var Entities\ProjectParticipantFieldDatum $participantDatum */
    foreach ($musician->getProjectParticipantFieldsData() as $participantDatum) {
      // cleanup as required, in particular the file-system
      $removeEntity = false;
      /** @var Entities\ProjectParticipantField $participantField */
      $participantField = $participantDatum->getField();
      switch ($participantField->getDataType()) {
        case FieldDataType::CLOUD_FILE:
          /** @var OCP\Files\File $cloudFile */
          $cloudFile = $participantFieldsService->getEffectiveFieldDatum($participantDatum);
          $cloudFile->delete();
          $removeEntity = true;
          break;
        case FieldDataType::CLOUD_FOLDER:
          /** @var OCP\Files\Folder $cloudFolder */
          $cloudFolder = $participantFieldsService->getEffectiveFieldDatum($participantDatum);
          $cloudFolder->delete();
          $removeEntity = true;
          break;
        case FieldDataType::DB_FILE:
          /** @var Entities\File $dbFile */
          $dbFile = $participantFieldsService->getEffectiveFieldDatum($participantDatum);
          $this->remove($dbFile, true);
          $removeEntity = true;
          break;
        case FieldDataType::SERVICE_FEE:
          $dbFile = $participantDatum->getSupportingDocument();
          $this->remove($dbFile, true);
          $participantDatum->setSupportingDocument(null);
          $this->persist($participantDatum); // ??? needed ???
          break;
        default:
          break;
      }
      switch ($participantField->getMultiplicity()) {
        case FieldMultiplicity::SIMPLE:
          $removeEntity = true;
          break;
        case FieldMultiplicity::RECURRING:
          $removeEntity = true;
          break;
        default:
          break;
      }
      if ($removeEntity) {
        $musician->getProjectParticipantFieldsData()->removeElement($participantDatum);
        $participantDatum->getDataOption()->getFieldData()->removeElement($participantDatum);
        $participantDatum->getField()->getFieldData()->removeElement($participantDatum);
        $this->remove($participantDatum, true);
      }
    }

    /** @var Entities\SepaDebitMandate $debitMandate */
    foreach ($musician->getSepaDebitMandates() as $debitMandate) {
      $this->remove($debitMandate, true);
    }
    $musician->setSepaDebitMandates(new Collections\ArrayCollection);

    /** @var Entities\SepaBankAccount $bankAccount */
    foreach ($musician->getSepaBankAccounts() as $bankAccount) {
      $this->remove($bankAccount, true);
    }
    $musician->setSepaBankAccounts(new Collections\ArrayCollection);

    $musician->setBirthday(null);
    $musician->setCity(null);
    $musician->setCountry(null);
    $musician->setStreet(null);
    $musician->setPostalCode(null);
    $musician->setRemarks(null);
    $musician->setMobilePhone(null);
    $musician->setFixedLinePhone(null);
    $musician->setMemberStatus(MemberStatus::PASSIVE);
    $musician->setLanguage(null);

    if (!empty($musician->getPhoto())) {
      $this->remove($musician->getPhoto(), true);
      $musician->setPhoto(null);
    }

    $musician->setFirstName($this->l->t('Dummy'));
    $musician->setSurName((string)$musician->getUuid());
    $musician->setUserPassphrase(null);
    $this->persist($musician);
    $this->flush();

    $emailAddress = $this->generateDisabledEmailAddress($musician);
    $musician->setEmailAddresses(new Collections\ArrayCollection);
    $musician->setEmail($emailAddress);

    $this->persist($musician);
    $this->flush();
  }

  public function deleteMusician(Entities\Musician $musician)
  {
    // Unsubscribe the musician from the mailing-list
    $list = $this->getConfigValue('announcementsMailingList');
    try {
      $this->listsService->unsubscribe($list, $musician->getEmail());
    } catch (\Throwable $t) {
      // for now we ignore any mailing list related errors in order not to
      // annoy the persons who are desperately trying to add persons to
      // the data-base. We should, however, find a notification channel
      // for end-user messages.
      $this->logException(
        $t,
        'Failed to unsubscribe' . $musician->getPublicName(firstNameFirst: true)
        . ' from the mailing-list ' . $list);
    }

    if (empty($musician->getDeleted())) {
      // $this->remove($musician, true); // this should be soft-delete
      // for now skip the soft-deleteable cascade and just set the deleted-data manually
      $musician->setDeleted('now');
    }
    if ($musician->getPayments()->count() == 0) {
      if ($musician->getProjectParticipation()->isEmpty()) {
        $this->logInfo($musician->getPublicName() . ' is unused, issuing hard-delete');
        foreach ($musician->getInstruments() as $instrument) {
          $this->remove($instrument, true);
          $this->remove($instrument, true);
        }
        $this->remove($musician, true); // this should be hard-delete
      } else {
        $this->logInfo($musician->getPublicName() . ' is used, trying to impersonate');
        // Perhaps: remove all personal data and keep a dummy record as
        // project-participant, until finally all projects have been deleted and
        // cleaned up.
        $this->impersonateMusician($musician);
      }
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
