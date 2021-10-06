<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Documents;

use clsTinyButStrong as OpenDocumentFillerBackend;

use OCP\IL10N;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Exceptions;

class OpenDocumentFiller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var OpenDocumentFillerBackend */
  private $backend;

  /** @var UserStorage */
  private $userStorage;

  /** @var TemplateService */
  private $templateService;

  public function __construct(
    ConfigService $configService
    , UserStorage $userStorage
    , TemplateService $templateService
    , OpenDocumentFillerBackend $backend
  ) {
    $this->configService = $configService;
    $this->userStorage = $userStorage;
    $this->templateService = $templateService;
    $this->backend = $backend;
    $this->di(\clsOpenTBS::class);
    ob_start();
    $this->backend->NoErr = true;
    $this->backend->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
    $output = ob_get_contents();
    ob_end_clean();
    if (!empty($output)) {
      throw new Exceptions\Exception($output);
    }
    $this->l = $this->l10n();
  }

  /**
   * Fill the given template file which must exist in the cloud
   * file-system with the given template-data.
   *
   * @param string $templateFileName Name of the template file in the
   * user-storage of the current user.
   *
   * @param array $templateData The template-data to substitute.
   */
  public function fill($templateFileName, $templateData)
  {
    ob_start();

    $this->logInfo('TEMPLATE ' . $templateFileName);

    $this->backend->ResetVarRef(false);
    $this->backend->VarRef = $this->fillData($templateData);
    $templateFile = $this->userStorage->getFile($templateFileName);
    if (empty($templateFile)) {
      throw new \RuntimeException($this->l->t('Unable to obtain file-handle for path "%s"', $templateFileName));
    }

    $this->backend->LoadTemplate($templateFile->fopen('r'), OPENTBS_ALREADY_UTF8);

    // OpenTBS does not support DateTimeInterface or time-zones, so
    // convert everything to a time-stamp and add the timezone-offset
    // to get correct dates and times.
    array_walk_recursive($this->backend->VarRef, function(&$value, $key) {
      if ($value instanceof \DateTimeInterface) {
        $stamp = $value->getTimestamp();
        $stamp -= $value->getOffset();
        $value = $stamp;
      }
    });

    // do a serialize - unserialize
    $this->backend->VarRef = json_decode(json_encode($this->backend->VarRef), true);

    // Do an opportunistic block-merge for every key with is an array

    foreach ($this->backend->VarRef as $key => $value) {
      if (is_array($value)) {
        $this->backend->MergeBlock($key, $value);
      }
    }

    $this->backend->show(OPENTBS_STRING);

    $output = ob_get_contents();
    ob_end_clean();

    if (!empty($output)) {
      throw new Exceptions\Exception($output);
    }

    return [
      $this->backend->Source,
      $templateFile->getMimeType(),
      basename($templateFileName),
    ];
  }

  /**
   * Return the data which would be filled into the template. This is
   * a merge of the $templateData with a couple of orchestra things.
   *
   * @param array $templateData The given template data to augment.
   *
   * @return array A merge of $templateData with global information
   * like orchestra address, logs etc.
   */
  public function fillData($templateData)
  {
    $fillData = array_merge(
      $this->getOrchestraSubstitutions(),
      $templateData);
    $fillData['now'] = (new \DateTimeImmutable())->setTimezone($this->getDateTimeZone());

    $fillData['test'] = 'Test Replacement Value';

    return $fillData;
  }

  /**
   * Generate a set of substitutions variables, taking some values
   * from the config-space, like logo, addresses, bank account etc.
   */
  public function getOrchestraSubstitutions()
  {
    $substitutions = [];

    $substitutions['orchestra:name1'] = $this->getConfigValue('streetAddressName01');
    $substitutions['orchestra:name2'] = $this->getConfigValue('streetAddressName02');
    $substitutions['orchestra:street'] = $this->getConfigValue('streetAddressStreet');
    $substitutions['orchestra:streetNumber'] = $this->getConfigValue('streetAddressHouseNumber');
    $substitutions['orchestra:city'] = $this->getConfigValue('streetAddressCity');
    $substitutions['orchestra:postalCode'] = $this->getConfigValue('streetAddressZIP');
    $substitutions['orchestra:country'] = $this->getConfigValue('streetAddressCountry');

    // Logo
    $logo = $this->templateService->getDocumentTemplate(ConfigService::DOCUMENT_TEMPLATE_LOGO);

    $substitutions['orchestra:'.ConfigService::DOCUMENT_TEMPLATE_LOGO] = $this->userStorage->createDataUri($logo);

    // $logoData = $logo->getContent();
    // $logoImage = ImagesService::rasterize($logoData, 1200);
    // $substitutions['orchestra:'.ConfigService::DOCUMENT_TEMPLATE_LOGO] = 'data:'.$logoImage->mimeType().';base64,' . base64_encode($logoImage->data());

    $this->logInfo('SUBSTITUTIONS ' . print_r(array_keys($substitutions), true));

    /** @var OrganizationalRolesService $rolesService */
    $rolesService = $this->di(OrganizationalRolesService::class);

    foreach (OrganizationalRolesService::BOARD_MEMBERS as $boardMember) {

      $contact = $rolesService->{$boardMember . 'Contact'}();
      foreach ($contact as $tag => $value) {
        $substitutions['orchestra:'.$boardMember.':'.$tag] = $value??$this->l->t('unknown');
      }

      /** @var \OCP\Image $signature */
      $signature = $rolesService->{$boardMember . 'Signature'}();

      if (!empty($signature)) {
        // if ($signature->mimeType() != 'image/png') {
        //   $signature = ImagesService::rasterize($signature, 1200, 1200);
        // }
        $signature = 'data:'.$signature->mimeType().';base64,' . base64_encode($signature->data());
      }
      $substitutions['orchestra:'.$boardMember.':signature'] = $signature;
    }

    return $substitutions;
  }

}
