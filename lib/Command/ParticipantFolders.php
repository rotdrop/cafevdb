<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine
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
use Symfony\Component\Console\Helper\ProgressBar;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

/** Create all participant sub-folder for each project. */
class ParticipantFolders extends Command
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
      ->setName('cafevdb:projects:participans:generate-folders')
      ->setDescription('Ensure all participant-folders exists')
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /** @var ProjectService $projectService */
    $projectService = \OC::$server->query(ProjectService::class);

    $totals = 0;

    $projects = $projectService->fetchAll();

    /** @var Entities\Project $project */
    foreach ($projects as $project) {
      /** @var Entities\ProjectParticipant $participant */
      $totals += $project->getParticipants()->count();
    }

    $section0 = $output->section();
    $section1 = $output->section();
    $section2 = $output->section();

    $progress0 = new ProgressBar($section0);
    $progress1 = new ProgressBar($section1);
    $progress2 = new ProgressBar($section2);

    $progress0->start(count($projects));
    $progress2->start($totals);
    foreach ($projects as $project) {
      $participants = $project->getParticipants();
      $progress1->start($participants->count());
      foreach ($participants as $participant) {
        if ($participant->isDeleted() || $participant->getMusician()->isDeleted()) {
          continue;
        }
        $projectService->ensureParticipantFolder($project, $participant->getMusician(), dry: false);
        $progress1->advance();
        $progress2->advance();
      }
      $progress1->finish();
      $progress0->advance();
    }
    $progress2->finish();
    $progress0->finish();

    return 0;
  }
}
