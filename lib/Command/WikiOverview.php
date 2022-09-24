<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Command;

use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ProjectService;

/** Recreate the wiki overview pages */
class WikiOverview extends Command
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    IL10N $l10n,
    IUserManager $userManager,
    IUserSession $userSession,
    IAppContainer $appContainer,
  ) {
    parent::__construct();
    $this->appName = $appName;
    $this->l = $l10n;
    $this->userManager = $userManager;
    $this->userSession = $userSession;
    $this->appContainer = $appContainer;
  }

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName('cafevdb:wiki:generate:overview')
      ->setDescription('Generate the wiki overview page')
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    /** @var AuthDokuWiki $wikiRPC */
    $wikiRPC = \OC::$server->query(AuthDokuWiki::class);
    if (!$wikiRPC->login($this->userId, $this->userPassword)) {
      return 1;
    }

    /** @var ProjectService $projectService */
    $projectService = \OC::$server->query(ProjectService::class);
    if (!$projectService->generateWikiOverview()) {
      return 1;
    }

    $output->writeln($this->l->t('Operation successfully completed.'));

    return 0;
  }
}
