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
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

use OCA\CAFEVDB\Service\GeoCodingService;

/** Selectively update the GeoCoding cache from various remote sources. */
class UpdateGeoCodingCache extends Command
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    IL10N $l10n,
    IUserManager $userManager,
    IUserSession $userSession,
    IAppContainer $appContainer
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
      ->setName('cafevdb:geo-coding:update')
      ->setDescription('Update geo-coding cache')
      ->addOption(
        'country',
        null,
        InputOption::VALUE_REQUIRED,
        'Only update the cache for the given country/countries, may be a comma-separated list.',
      )
      ->addOption(
        'city',
        null,
        InputOption::VALUE_REQUIRED,
        'Only update the cache for the given city/cities, may be a comma-separated list.',
      )
      ->addOption(
        'continent',
        null,
        InputOption::VALUE_REQUIRED,
        'Only update the cache for the given continent(s), may be a comma-separated list.',
      )
      ->addOption(
        'language',
        null,
        InputOption::VALUE_REQUIRED,
        'Only update the cache for the given language(s), may be a comma-separated list.',
      )
      ->addOption(
        'list',
        null,
        InputOption::VALUE_REQUIRED,
        "Arguemnt is one of 'countries', 'cities', 'continents', 'languages' in order to trigger printing the available objects in the data-base",
      )
      ->addOption(
        'sanitize',
        null,
        InputOption::VALUE_REQUIRED,
        "Argument is one of 'countries', 'cities', 'continents', 'languages' in order to sanitize the respetive data in the data-base",
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    /** @var GeoCodingService $geoCodingService */
    $geoCodingService = $this->appContainer->get(GeoCodingService::class);

    $countries = explode(',', $input->getOption('country'));
    // $cities = explode(',', $input->getOption('city'));
    // $continents = explode(',',  $input->getOption('continent'));
    // $languages = explode(',', $input->getOption('language'));

    $list = $input->getOption('list');
    switch ($list) {
      case 'languages':
        $output->writeln(implode(', ', $geoCodingService->getLanguages()));
        break;
      default:
        break;
    }

    $sanitize = $input->getOption('sanitize');
    if (!empty($sanitize)) {
      switch ($sanitize) {
        case 'continents':
        case 'countries':
          $countries = $this->entityManager->getRepository(Entities\GeoCountry::class)->findAll();
          $numOk =
            $numFailed =
            $numDeleted =
            $numUpdated =
            $numTodo = 0;
          /** @var Entities\GeoCountry $country */
          foreach ($countries as $country) {
            if (empty($country->getContinent())) {
              ++$numTodo;
              $output->writeln('No continent for ' . $country->getIso() . ' / ' . $country->getTarget() . ' / ' . $country->getL10nName());
              try {
                $geoCodingService->updateCountriesForLanguage($country->getTarget(), force: true);
                $output->writeln(
                  'Updated data for '
                  . $country->getIso() . ' / '
                  . $country->getTarget() . ' / '
                  . $country->getL10nName() . ' continent: ' . $country->getContinent()->getCode() . ' / '
                  . $country->getContinent()->getL10nName());
              } catch (\Throwable $t) {
                $output->writeln('Error updating country ' . $country->getIso() . ' / ' . $country->getTarget() . ' failed with error: ' . $t->getMessage());
                ++$numFailed;
              }
            } elseif (empty($country->getL10nName())) {
              ++$numTodo;
              try {
                $this->entityManager->remove($country);
                $this->entityManager->flush();
                $output->writeln('Deleted country ' . $country->getIso() . ' / ' . $country->getTarget() . ' because of missing translation.');
                ++$numDeleted;
              } catch (\Throwabled $t) {
                $output->writeln('Error deleting country ' . $country->getIso() . ' / ' . $country->getTarget() . ' failed with error: ' . $t->getMessage());
                ++$numFailed;
              }
            } else {
              ++$numOk;
              if ($output->isVerbose()) {
                $output->writeln(
                  'Data ok for ' . $country->getIso() . ' / '
                  . $country->getTarget() . ' / '
                  . $country->getL10nName() . ' continent: ' . $country->getContinent()->getL10nName());
              }
            }
          }
          $output->writeln('Checked ' . count($countries) . ' countires, found ' . $numTodo . ' inconsistencies, updated  ' . $numUpdated . ', deleted ' . $numDeleted . '.');
          break;
        default:
          break;
      }
    }

    // $totals = 0;

    // $projects = $projectService->fetchAll();

    // /** @var Entities\Project $project */
    // foreach ($projects as $project) {
    //   /** @var Entities\ProjectParticipant $participant */
    //   $totals += $project->getParticipants()->count();
    // }

    // $section0 = $output->section();
    // $section1 = $output->section();
    // $section2 = $output->section();

    // $progress0 = new ProgressBar($section0);
    // $progress1 = new ProgressBar($section1);
    // $progress2 = new ProgressBar($section2);

    // $progress0->start(count($projects));
    // $progress2->start($totals);
    // foreach ($projects as $project) {
    //   $participants = $project->getParticipants();
    //   $progress1->start($participants->count());
    //   foreach ($participants as $participant) {
    //     if ($participant->isDeleted() || $participant->getMusician()->isDeleted()) {
    //       continue;
    //     }
    //     $projectService->ensureParticipantFolder($project, $participant->getMusician(), dry: false);
    //     $progress1->advance();
    //     $progress2->advance();
    //   }
    //   $progress1->finish();
    //   $progress0->advance();
    // }
    // $progress2->finish();
    // $progress0->finish();

    return 0;
  }
}
