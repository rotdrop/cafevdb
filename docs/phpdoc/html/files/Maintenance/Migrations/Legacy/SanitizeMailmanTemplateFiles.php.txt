<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations\Legacy;

use OCP\Files\Node;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Avoid characters which are illegal on certain operating systems,
 * i.e. replace colons by dashes. The MailingListAutoResponsesListener in
 * principle should reconfigure the lists automatically after the files got
 * renamed.
 */
class SanitizeMailmanTemplateFiles implements IMigration
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected IL10N $l,
    protected LoggerInterface $logger,
    protected MailingListsService $mailingListsService,
    protected UserStorage $userStorage,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t(
      'Replace "%1$s" by "%2$s" in the filenames for the mailing list auto-response templates.',
      [ MailingListsService::TEMPLATE_MAILMAN_SEPARATOR, MailingListsService::TEMPLATE_FILE_SEPARATOR ],
    );
  }

   /** {@inheritdoc} */
  public function execute():bool
  {
    $templateTypes = [
      MailingListsService::TEMPLATE_TYPE_ANNOUNCEMENTS,
      MailingListsService::TEMPLATE_TYPE_PROJECTS,
    ];
    foreach ($templateTypes as $templateType) {
      $templateFolderpath = $this->mailingListsService->templateFolderPath($templateType);
      $templatesFolder = $this->userStorage->getFolder($templateFolderpath);
      /** @var Node $node */
      foreach ($templatesFolder->getDirectoryListing() as $node) {
        if ($node->getType() != \OCP\Files\FileInfo::TYPE_FILE) {
          continue;
        }
        $mimeType = $node->getMimetype();
        if ($mimeType != 'text/plain' && $mimeType != 'text/markdown') {
          continue;
        }
        $pathInfo = pathinfo($node->getPath());
        $oldBaseName = $pathInfo['basename'];
        $newBaseName = str_replace(
          MailingListsService::TEMPLATE_MAILMAN_SEPARATOR,
          MailingListsService::TEMPLATE_FILE_SEPARATOR,
          $oldBaseName,
        );
        if ($oldBaseName != $newBaseName) {
          $node->move($pathInfo['dirname'] . Constants::PATH_SEP . $newBaseName);
        }
      }
    }

    return true;
  }
}
