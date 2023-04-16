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
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Storage\UserStorage;

/** Create all participant sub-folder for each project. */
class ProjectFolders extends Command
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
      ->setName('cafevdb:projects:folders')
      ->setDescription('Ensure all or selected project-folders exist.')
      ->addOption(
        'folder',
        'f',
        InputOption::VALUE_REQUIRED,
        'Restrict the operation to the given (sub-)folder, specified by its configuration key ' . implode(', ', ProjectService::PROJECT_FOLDER_CONFIG_KEYS),
      )
      ->addOption(
        'all',
        'a',
        InputOption::VALUE_NONE,
        'Work on all folders of all projects',
      )
      ->addOption(
        'project',
        'p',
        InputOption::VALUE_REQUIRED,
        'Restrict the operation to the given project. Can be combined with --folder=FOLDER',
      )
      ->addOption(
        'years',
        'y',
        InputOption::VALUE_REQUIRED,
        'Restrict the operation to projects of the given years. Single years and incomplete ranges are allowed. Can be combined with --folder=FOLDER',
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
      ->addOption(
        'skeleton',
        's',
        InputOption::VALUE_REQUIRED,
        'Modify the handling of the skeleton structure, if any has been provided. SKELETON is one of "omit", "overwrite", "clean", in order to not copy any skeleton file, overwrite all existing files with the skeleton files or clean existing files which are not present in the skeleton structure. The default is to copy over the skeleton files but to not overwrite existing files with skeleton files.',
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $folder = $input->getOption('folder');
    $projectName = $input->getOption('project');
    $years = $input->getOption('years');
    $all = $input->getOption('all');
    $skel = $input->getOption('skeleton');
    $dry = $input->getOption('dry');
    $check = $input->getOption('check');
    if ($check) {
      $dry = true;
    }

    if (empty($years) && empty($folder) && empty($projectName) && empty($all)) {
      $output->writeln('<error>' . $this->l->t('One of the options "years=RANGE", "--folder=FOLDER", "--project=PROJECT" or "--all" has to be specified.') . '</error>');
      $output->writeln('');
      (new DescriptorHelper)->describe($output, $this);
      return 1;
    }
    if (!empty($all) && (!empty($folder) || !empty($projectName))) {
      $output->writeln('<error>' . $this->l->t('"--all" cannot be compbined with "years=RANGE", "--folder=FOLDER" or "--project=PROJECT".') . '</error>');
      $output->writeln('');
      return 1;
    }
    if (!empty($skel) && !in_array($skel, ['omit', 'overwrite', 'clean'])) {
      $output->writeln('<error>' . $this->l->t('SKELETON must be one of "omit", "overwrite" or "clean".'));
      $output->writeln('');
      return 1;
    }
    if (!empty($folder) && !in_array($folder, ProjectService::PROJECT_FOLDER_CONFIG_KEYS)) {
      $output->writeln('<error>' . $this->l->t('FOLDER must be one of "%s"', implode('", "', ProjectService::PROJECT_FOLDER_CONFIG_KEYS)));
      $output->writeln('');
      return 1;
    }

    if (empty($years)) {
      $firstYear = 1000;
      $lastYear = 9999;
    } elseif (preg_match('/([0-9]{4})?\\s*-?\\s*([0-9]{4})?/', $years, $matches)) {
      $output->writeln('YEARS ' . print_r($matches, true));
      $firstYear = $matches[1] ?? 1000;
      $lastYear = $matches[2] ?? 9999;
    }

    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /** @var ProjectService $projectService */
    $projectService = $this->appContainer->get(ProjectService::class);

    /** @var Repositories\ProjectsRepository $projectsRepository */
    $projectsRepository = $this->getDatabaseRepository(Entities\Project::class);

    $projectCriteria = [
      '>=year' => $firstYear,
      '<=year' => $lastYear,
    ];
    if (!empty($projectName)) {
      $projectCriteria['name'] = $projectName;
    }
    $output->writeln('PROJECT CRIT ' . print_r($projectCriteria, true));
    $projects = $projectsRepository->findBy($projectCriteria, [ 'year' => 'DESC', 'name' => 'ASC' ]);

    foreach ($projects as $project) {
      $folders = $projectService->ensureProjectFolders($project, $folder, $dry);
      $output->writeln('PROJECT ' . $project->getName() . ': ' . implode(', ', $folders));
    }

    return 0;
  }
}
