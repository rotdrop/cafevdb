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

  public function fill($templateFileName, $templateData)
  {
    ob_start();

    $this->logInfo('TEMPLATE ' . $templateFileName);

    $this->backend->ResetVarRef(false);
    $this->backend->VarRef = array_merge(
      $this->getOrchestraSubstitutions(),
      $templateData);
    $this->backend->VarRef['test'] = 'Test Replacement Value';

    $this->logInfo('REPLACEMENTS ' . print_r(array_keys($this->backend->VarRef), true));
    $this->backend->LoadTemplate($template, OPENTBS_ALREADY_UTF8);

    $templateFile = $this->userStorage->getFile($templateFileName);

    $this->backend->LoadTemplate($templateFile->fopen('r'), OPENTBS_ALREADY_UTF8);
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
   * Generate a set of substitutions variables, taking some values
   * from the config-space, like logo, addresses, bank account etc.
   */
  public function getOrchestraSubstitutions()
  {
    $substitutions = [];

    // Logo
    $logo = $this->templateService->getDocumentTemplate(ConfigService::DOCUMENT_TEMPLATE_LOGO);

    $logoData = $logo->getContent();
    $logoImage = ImagesService::rasterize($logoData, 1200);
    // $substitutions['orchestra:'.ConfigService::DOCUMENT_TEMPLATE_LOGO] = $this->userStorage->createDataUri($logo);
    $substitutions['orchestra:'.ConfigService::DOCUMENT_TEMPLATE_LOGO] = 'data:'.$logoImage->mimeType().';base64,' . base64_encode($logoImage->data());

    $this->logInfo('SUBSTITUTIONS ' . print_r(array_keys($substitutions), true));

    /** @var OrganizationalRolesService $rolesService */
    $rolesService = $this->di(OrganizationalRolesService::class);

    foreach (OrganizationalRolesService::BOARD_MEMBERS as $boardMember) {

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
