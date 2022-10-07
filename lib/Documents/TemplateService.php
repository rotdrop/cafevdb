<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Documents;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Storage\UserStorage;

class TemplateService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public function __construct(
    ConfigService $configService
    , UserStorage $userStorage
  ) {
    $this->configService = $configService;
    $this->userStorage = $userStorage;
    $this->l = $this->l10n();
  }

  /**
   * Return the file-system path to the given template file. The
   * allowed keys are defined in
   *
   * ConfigService::DOCUMENT_TEMPLATES
   *
   * @param string Configuration key of the template
   *
   * @return null|string
   */
  public function getTemplatePath(string $templateName):?string
  {
    $templateFileName = $this->getConfigValue($templateName);

    if (empty($templateFileName)) {
      return null;
    }

    $templatesFolder = $this->getDocumentTemplatesPath();
    if (empty($templatesFolder)) {
      return null;
    }
    $templateFileName = UserStorage::pathCat($templatesFolder, $templateFileName);

    return $templateFileName;
  }

  /**
   * Return the given template as a file-node or null if not
   * found. The allowed keys are defined in
   *
   * ConfigService::DOCUMENT_TEMPLATES
   *
   * @param string Configuration key of the template
   *
   * @return null|\OCP\Files\File null on error or the associated
   * file-node.
   */
  public function getDocumentTemplate(string $templateName):?\OCP\Files\File
  {
    $templateFileName = $this->getTemplatePath($templateName);
    if (empty($templateFileName)) {
      return null;
    }
    return $this->userStorage->getFile($templateFileName);
  }
}
