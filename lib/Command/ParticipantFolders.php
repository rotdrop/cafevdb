<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\DescriptorHelper;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Storage\UserStorage;

/** Create all participant sub-folder for each project. */
class ParticipantFolders extends Command
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected IAppContainer $appContainer,
  ) {
    parent::__construct();
  }

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName('cafevdb:projects:participants:generate-folders')
      ->setDescription('Ensure all or selected participant-folders exist.')
      ->addOption(
        'user',
        'u',
        InputOption::VALUE_REQUIRED,
        'Restrict the operation to the given user-id',
      )
      ->addOption(
        'all',
        'a',
        InputOption::VALUE_NONE,
        'Work on all folders of all participants',
      )
      ->addOption(
        'project',
        'p',
        InputOption::VALUE_REQUIRED,
        'Restrict the operation to the given project. Can be combined with --user=USER',
      )
      ->addOption(
        'dry',
        null,
        InputOption::VALUE_NONE,
        'Just simulate, do not generate any folders.',
      )
      ->addOption(
        'check',
        'c',
        InputOption::VALUE_NONE,
        'Check if the folders exist, exit with non-zero status if any is missing, print warnings.',
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $memberUserId = $input->getOption('user');
    $projectName = $input->getOption('project');
    $all = $input->getOption('all');
    $dry = $input->getOption('dry');
    $check = $input->getOption('check');
    if ($check) {
      $dry = true;
    }

    if (empty($memberUserId) && empty($projectName) && empty($all)) {
      $output->writeln('<error>' . $this->l->t('One of the options "--user=USER", "--project=PROJECT" or "--all" has to be specified.') . '</error>');
      $output->writeln('');
      (new DescriptorHelper)->describe($output, $this);
      return 1;
    }
    if (!empty($all) && (!empty($memberUserId) || !empty($projectName))) {
      $output->writeln('<error>' . $this->l->t('"--all" cannot be compbined with "--user=USER" or "--project=PROJECT".') . '</error>');
      $output->writeln('');
      return 1;
    }

    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /** @var ProjectService $projectService */
    $projectService = $this->appContainer->get(ProjectService::class);

    /** @var UserStorage $userStorage */
    $userStorage = $this->appContainer->get(UserStorage::class);

    $totals = 0;

    if (!empty($projectName)) {
      $projectEntity = $projectService->findByName($projectName);
      if (empty($projectEntity)) {
        $output->writeln('<error>' . $this->l->t('Unable to find the project with name "%s".', $projectName) . '</error>');
        return 1;
      }
      $projects = [ $projectEntity ];
    } else {
      $projects = $projectService->fetchAll();
    }

    $projectParticipants = [];

    /** @var Entities\Project $project */
    foreach ($projects as $project) {
      /** @var Collection $participants */
      $participants = $project->getParticipants();
      if (!empty($memberUserId)) {
        $participants = $participants->filter(fn(Entities\ProjectParticipant $participant) => $participant->getMusician()->getUserIdSlug() == $memberUserId);
      }
      $projectParticipants[$project->getId()] = $participants;
      $totals += count($participants);
    }

    $section0 = $output->section();
    $section1 = $output->section();
    $section2 = $output->section();
    $section3 = $output->section();
    $section4 = $output->section();

    $progress0 = new ProgressBar($section0);
    $progress1 = new ProgressBar($section1);
    $progress2 = new ProgressBar($section2);

    $errorCount = 0;

    $progress0->start(count($projects));
    $progress2->start($totals);
    foreach ($projects as $project) {
      $participants = $projectParticipants[$project->getId()];
      $progress1->start($participants->count());
      /** @var Entities\ProjectParticipant $participant */
      foreach ($participants as $participant) {
        if ($participant->isDeleted() || $participant->getMusician()->isDeleted()) {
          continue;
        }
        $folder = $projectService->ensureParticipantFolder($project, $participant->getMusician(), dry: $dry);
        if ($check) {
          if (empty($userStorage->get($folder))) {
            $section4->writeln('<error>' . $this->l->t('Folder "%s" does not exist.', $folder) . '</error>');
            ++$errorCount;
          } else {
            $section3->overwrite($this->l->t('Folder "%s" exists.', $folder), OutputInterface::VERBOSITY_VERBOSE);
          }
        } else {
          $section3->overwrite($this->l->t('Ensured existence of folder "%s".', $folder), OutputInterface::VERBOSITY_VERBOSE);
        }
        $progress1->advance();
        $progress2->advance();
      }
      $progress1->finish();
      $progress0->advance();
    }
    $progress2->finish();
    $progress0->finish();

    return $errorCount > 0 ? 1 : 0;
  }
}
