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

class ParticipantFolders extends Command
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var IL10N */
  private $l;

  /** @var IUserManager */
  private $userManager;

  /** @var IUserSession */
  private $userSession;

  public function __construct(
    $appName
    , IL10N $l10n
    , IUserManager $userManager
    , IUserSession $userSession
  ) {
    parent::__construct();
    $this->l = $l10n;
    $this->userManager = $userManager;
    $this->userSession = $userSession;
    $this->appName = $appName;
    if (empty($l10n)) {
      throw new \RuntimeException('No IL10N :(');
    }
  }

  protected function configure()
  {
    $this
      ->setName('cafevdb:projects:participans:generate-folders')
      ->setDescription('Ensure all participant-folders exists')
      ;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $helper = $this->getHelper('question');
    $question = new Question('User: ', '');
    $userId = $helper->ask($input, $output, $question);
    $question = (new Question('Password: ', ''))->setHidden(true);
    $password = $helper->ask($input, $output, $question);

    // $output->writeln($this->l->t('Your Answers: "%s:%s"', [ $userId, $password ]));
    $user = $this->userManager->get($userId);
    $this->userSession->setUser($user);

    // Login event-handler binds encryption-service and entity-manager
    if ($this->userSession->login($userId, $password)) {
      $output->writeln($this->l->t('Login succeeded.'));
    } else {
      $output->writeln($this->l->t('Login failed.'));
      return 1;
    }

    $this->entityManager = \OC::$server->query(EntityManager::class);
    $this->disableFilter('soft-deleteable');

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
