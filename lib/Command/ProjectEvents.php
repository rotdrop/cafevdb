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

use DateTimeImmutable;

use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;
use OCP\Calendar\IManager as CalendarManager;
use OCP\Calendar\ICalendar;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagAlreadyExistsException;
use OCP\SystemTag\TagNotFoundException;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\DescriptorHelper;

use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\VCalendarService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Storage\UserStorage;

/** Create all participant sub-folder for each project. */
class ProjectEvents extends Command
{
  use AuthenticatedCommandTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IL10N $l10n,
    ILogger $logger,
    IUserManager $userManager,
    IUserSession $userSession,
    IAppContainer $appContainer,
  ) {
    parent::__construct();
    $this->appName = $appName;
    $this->l = $l10n;
    $this->logger = $logger;
    $this->userManager = $userManager;
    $this->userSession = $userSession;
    $this->appContainer = $appContainer;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName('cafevdb:projects:events')
      ->setDescription('Ensure consistency of the registered project events.')
      ->addOption(
        'calendar',
        'c',
        InputOption::VALUE_REQUIRED,
        'Restrict the operation to the given calendar, specified by its internal URI ' . implode(', ', array_keys(ConfigService::CALENDARS)) . '. CALENDAR may also consist of a comma separated list of several calendar-uris. If not given the act on all calendars.',
      )
      ->addOption(
        'all',
        'a',
        InputOption::VALUE_NONE,
        'Work on all events of all projects',
      )
      ->addOption(
        'project',
        'p',
        InputOption::VALUE_REQUIRED,
        'Restrict the operation to the given project, specified by its name. PROJECT may be a comma separated list of several projects. Can be combined with --calendar=URI',
      )
      ->addOption(
        'years',
        'y',
        InputOption::VALUE_REQUIRED,
        'Restrict the operation to projects of the given years. Single years and incomplete ranges are allowed. Can be combined with --calendar=CALENDAR. Note that events for a project for a given year may not belong to that year.',
      )
      ->addOption(
        'event-uri',
        'e',
        InputOption::VALUE_REQUIRED,
        'Restrict the operation to the event with the given URI.',
      )
      ->addOption(
        'dry',
        null,
        InputOption::VALUE_NONE,
        'Just simulate, do not change anything. Can be used in addition to "--fix" and "--tidy".',
      )
      ->addOption(
        'fix',
        'f',
        InputOption::VALUE_OPTIONAL,
        'Try to fix inconsistencies. FIX can be one of "unregister", "reattach", "category", "split" or "delete". '
        . '"unregister" will resolve the conflicts by breaking the link between a project and an inconsistent event. '
        . '"reattach" will add events which have the project-name category back to the list of project events. '
        . '"split" will convert non-repeating multi-day events into repeating event series. '
        . '"category" will add the project-name category to events which are registered but do not have this category '
        . 'and will remove the project-name category from events which are not registered with the project. '
        . '"delete" will delete events which have the project-name category but are  not registered with the event. '
        . 'In any case "fix" will cleanup orphaned events, i.e. links to events which do not exist.',
      )
      ->addOption(
        'purge',
        null,
        InputOption::VALUE_NONE,
        'Remove "soft-deleted" from the project-events table.',
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $calendars = $input->getOption('calendar');
    $projectName = $input->getOption('project');
    $years = $input->getOption('years');
    $all = $input->getOption('all');
    $dry = $input->getOption('dry');
    $fix = $input->getOption('fix');
    $purge = $input->getOption('purge');
    $onlyUri = $input->getOption('event-uri');
    if ($fix === false) {
      $dry = true;
    }

    if (empty($years) && empty($calendars) && empty($projectName) && empty($all)) {
      $output->writeln('<error>' . $this->l->t('One of the options "years=RANGE", "--calendar=CALENDAR", "--project=PROJECT" or "--all" has to be specified.') . '</error>');
      $output->writeln('');
      (new DescriptorHelper)->describe($output, $this);
      return 1;
    }
    if (!empty($all) && (!empty($calendars) || !empty($projectName))) {
      $output->writeln('<error>' . $this->l->t('"--all" cannot be compbined with "years=RANGE", "--calendar=CALENDAR" or "--project=PROJECT".') . '</error>');
      $output->writeln('');
      return 1;
    }
    if (!empty($fix) && !in_array($fix, ['unregister', 'reattach', 'category', 'split', 'delete'])) {
      $output->writeln('<error>' . $this->l->t('FIX must be one of "unregister", "reattach" or "category".'));
      $output->writeln('');
      return 1;
    }
    if ($fix === null) {
      $fix = 'orphans';
    }
    if (!empty($calendars)) {
      $calendars = explode(',', $calendars);
      foreach ($calendars as $calendar) {
        if (!in_array($calendar, array_keys(ConfigService::CALENDARS))) {
          $output->writeln(
            '<error>'
            . $this->l->t('CALENDAR "%1$s" must be one of "%2$s"', [
              $calendar,
              implode('", "', array_keys(ConfigService::CALENDARS)),
            ])
            . '</error>'
          );
          $output->writeln('');
          return 1;
        }
      }
    } else {
      $calendars = array_keys(ConfigService::CALENDARS);
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

    /** @var EventsService $eventsService */
    $eventsService = $this->appContainer->get(EventsService::class);

    /** @var CalDavService $calDavService */
    $calDavService = $this->appContainer->get(CalDavService::class);

    /** @var CalendarManager $calendarManager */
    $calendarManager = $this->appContainer->get(CalendarManager::class);

    /** @var ISystemTagManager $systemTagManager */
    $systemTagManager = $this->appContainer->get(ISystemTagManager::class);

    /** @var Repositories\ProjectsRepository $projectsRepository */
    $projectsRepository = $this->getDatabaseRepository(Entities\Project::class);

    /** @var ConfigService $configService */
    $configService = $this->appContainer->get(ConfigService::class);

    $projectCriteria = [
      '>=year' => $firstYear,
      '<=year' => $lastYear,
    ];
    if (!empty($projectName)) {
      $projectName = explode(',', $projectName);
      $projectCriteria['name'] = $projectName;
    }
    // $output->writeln('PROJECT CRIT ' . print_r($projectCriteria, true));
    $projects = $projectsRepository->findBy($projectCriteria, [ 'year' => 'DESC', 'name' => 'ASC' ]);

    $calendarCriteria = [];
    if (!empty($calendars)) {
      $calendarCriteria['calendarUri'] = $calendars;
    }

    $orphans = 0;
    $purged = 0;
    $missingCategory = 0;
    $removedCategory = 0;
    $unregister = 0;
    $reattach = 0;
    $split = 0;
    $deleted = 0;
    $systemTags = 0;

    $appL10n = $configService->getAppL10n();
    $systemCategories = [
      EventsService::getAbsenceCategory($appL10n),
    ];
    foreach ($calendars as $calendarUri) {
      $systemCategories[] = $appL10n->t($calendarUri);
    }
    foreach ($systemCategories as $category) {
      $output->writeln('Checking for system category "' . $category . '".', OutputInterface::VERBOSITY_VERBOSE);
      try {
        $systemTagManager->getTag($category, userVisible: true, userAssignable: true);
      } catch (TagNotFoundException $e) {
        try {
          if (!$dry) {
            $output->writeln('Adding system category "' . $category . '".', OutputInterface::VERBOSITY_VERBOSE);
            $systemTagManager->createTag($category, userVisible: true, userAssignable: true);
          } else {
            $output->writeln('Would add system category "' . $category . '" (dry-run).', OutputInterface::VERBOSITY_VERBOSE);
          }
          ++$systemTags;
        } catch (TagAlreadyExistsException $e) {
          // ignore
        }
      }
    }

    /** @var Entities\Project $project */
    foreach ($projects as $project) {
      $projectName = $project->getName();

      $output->writeln('Working on project ' . $projectName, OutputInterface::VERBOSITY_VERBOSE);

      $category = $projectName;
      try {
        $systemTagManager->getTag($category, userVisible: true, userAssignable: true);
      } catch (TagNotFoundException $e) {
        try {
          if (!$dry) {
            $output->writeln('Adding system category "' . $category . '".', OutputInterface::VERBOSITY_VERBOSE);
            $systemTagManager->createTag($category, userVisible: true, userAssignable: true);
          } else {
            $output->writeln('Would add system category "' . $category . '" (dry-run).', OutputInterface::VERBOSITY_VERBOSE);
          }
          ++$systemTags;
        } catch (TagAlreadyExistsException $e) {
          // ignore
        }
      }

      /** @var Collection $projectEvents */
      $projectEvents = $project->getCalendarEvents();
      if (!empty($calendarCriteria)) {
        $projectEvents = $projectEvents->matching(DBUtil::criteriaWhere($calendarCriteria));
      }

       /** @var Entities\ProjectEvent $projectEvent */
      foreach ($projectEvents as $projectEvent) {
        $calendarUri = $projectEvent->getCalendarUri();
        $calendarId = $projectEvent->getCalendarId();
        $eventUri = $projectEvent->getEventUri();
        if (!empty($onlyUri) && $eventUri != $onlyUri) {
          continue;
        }
        if ($projectEvent->isDeleted()) {
          if ($purge) {
            ++$purged;
            if (!$dry) {
              $output->writeln(
                $this->l->t('Hard-removing soft-deleted event "%1$s@%2$s" from project "%3$s"', [
                  $eventUri, $calendarUri, $projectName
                ]),
                OutputInterface::VERBOSITY_VERBOSE
              );
              $this->remove($projectEvent, hard: true, flush: true);
            } else {
              $output->writeln(
                $this->l->t('Would hard-remove soft-deleted event "%1$s@%2$s" from project "%3$s" (dry-run)', [
                  $eventUri, $calendarUri, $projectName
                ]),
                OutputInterface::VERBOSITY_VERBOSE
              );
            }
          }
          continue;
        }
        if ($projectEvent->getType() != 'VEVENT') {
          continue;
        }

        $recurrenceId = $projectEvent->getRecurrenceId();
        $event = $eventsService->fetchEvent($project, $eventUri, $recurrenceId);
        if (empty($event)) {
          // Try to fetch the event directly from the calendar. This can
          // happen if a repeating event had been added without recording its
          // siblings.
          $eventData = $calDavService->getCalendarObject($calendarId, $eventUri);
          if (!empty($eventData)) {
            $eventData['calendaruri'] = $calendarUri;
            $this->logInfo('EVENT DATA ' . print_r($eventData, true));
            if (!$dry) {
              $eventsService->syncCalendarObject($eventData);
              $output->writeln($this->l->t('Synchronizing event "%s".', $eventUri));
            } else {
              $output->writeln($this->l->t('Would synchronize event "%s" (dry run).', $eventUri));
            }
            continue;
          }
        }

        if (empty($event)) {
          ++$orphans;
          if (!empty($recurrenceId)) {
            $output->writeln(
              $this->l->t('Recurrence-id "%1$s" of event "%2$s" not found', [
                (new DateTimeImmutable)->setTimestamp($recurrenceId)->format('Y-m-d'),
                $eventUri,
              ]),
              OutputInterface::VERBOSITY_VERBOSE
            );
          } else {
            $output->writeln(
              $this->l->t('Event "%1$s" not found', [
                $eventUri,
              ]),
              OutputInterface::VERBOSITY_VERBOSE
            );
          }
          if (!empty($fix)) {
            if (!$dry) {
              $output->writeln(
                $this->l->t('Removing orphan event "%1$s@%2$s" from project "%3$s"', [
                  $eventUri, $calendarUri, $projectName
                ]),
                OutputInterface::VERBOSITY_VERBOSE
              );
              $this->remove($projectEvent, hard: true, flush: true);
            } else {
              $output->writeln(
                $this->l->t('Would remove orphan event "%1$s@%2$s" from project "%3$s" (dry-run)', [
                  $eventUri, $calendarUri, $projectName
                ]),
                OutputInterface::VERBOSITY_VERBOSE
              );
            }
          }
        } else {
          // check categories
          $categories = $event['categories'];
          if (!in_array($projectName, $categories)) {
            $output->writeln(
              $this->l->t('Event "%1$s@%2$s" is missing the project-category "%3$s".', [
                  $eventUri, $calendarUri, $projectName
              ]),
              OutputInterface::VERBOSITY_VERBOSE
            );
            switch ($fix) {
              case 'category':
                ++$missingCategory;
                if ($dry) {
                  $output->writeln(
                    $this->l->t('Would add the category "%3$s" to the event "%1$s@%2$s" (dry-run).', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                } else {
                  $output->writeln(
                    $this->l->t('Adding the category "%3$s" to the event "%1$s@%2$s".', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                  $eventsService->changeCategories($project, $calendarId, $eventUri, recurrenceId: null, additions: [ $projectName ]);
                }
                break;
              case 'unregister':
                ++$unregister;
                if ($dry) {
                  $output->writeln(
                    $this->l->t('Would remove uncategorized event "%1$s@%2$s" from project "%3$s" (dry-run).', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                } else {
                  $output->writeln(
                    $this->l->t('Removing uncategorized event "%1$s@%2$s" from project "%3$s".', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                  $this->remove($projectEvent, hard: true, flush: true);
                }
                break;
            }
          } // categories check

          // check for non-repeating multi-day events and potentially reattach them
          $hours = ($event['end']->getTimestamp() - $event['start']->getTimestamp()) / 3600;
          if (empty($event['recurrenceId']) && (($event['allday'] && $hours > 36) || $hours > 48)) {
            if ($fix == 'split') {
              ++$split;
              if ($dry) {
                $output->writeln(
                  $this->l->t('Would split non-repeating multi-day event "%1$s@%2$s" of project "%3$s" (dry-run).', [
                    $eventUri, $calendarUri, $projectName
                  ]),
                  OutputInterface::VERBOSITY_VERBOSE
                );
              } else {
                $output->writeln(
                  $this->l->t('Will split non-repeating multi-day event "%1$s@%2$s" of project "%3$s".', [
                    $eventUri, $calendarUri, $projectName
                  ]),
                  OutputInterface::VERBOSITY_VERBOSE
                );
                // "reattach" is performed using a dummy do-nothing update
                $calendarObject = $calDavService->getCalendarObject($calendarId, $eventUri);
                $vCalendar = VCalendarService::getVCalendar($calendarObject);
                $calDavService->updateCalendarObject($calendarId, $eventUri, $vCalendar);
              }
            }
          }
        }
      } // loop over registered events

      /** @var ICalendar $calendar */
      foreach ($calendarManager->getCalendars() as $calendar) {
        $calendarId = $calendar->getKey();
        $calendarUri = $calendar->getUri();
        if (!str_contains($calendarUri, '_shared_by_')) {
          continue;
        }
        list($calendarUri,) = explode(':', str_replace('_shared_by_', ':', $calendarUri));
        if (!in_array($calendarUri, $calendars)) {
          continue;
        }
        $events = $calendar->search($projectName, ['CATEGORIES']);
        foreach ($events as $event) {
          $eventUri = $event['uri'];
          if (!empty($onlyUri) && $eventUri != $onlyUri) {
            continue;
          }
          // Unfortunately, there is no way to make the search an exact
          // match. In addition: the search result structure is really by far
          // too complicated. Instead, fetch the underlying calendar object
          // and work on it directly.
          $calendarObject = $calDavService->getCalendarObject($calendarId, $eventUri);
          $vCalendar = VCalendarService::getVCalendar($calendarObject);
          $exactMatch = false;
          foreach (VCalendarService::getAllVObjects($vCalendar) as $vObject) {
            $categories = VCalendarService::getCategories($vObject);
            if (in_array($projectName, $categories)) {
              $exactMatch = true;
            }
          }
          if (!$exactMatch) {
            continue;
          }
          $criteria = [
            'calendarUri' => $calendarUri,
            'eventUri' => $eventUri,
          ];
          $projectEvents = $project->getCalendarEvents()->matching(DBUtil::criteriaWhere($criteria));
          if (count($projectEvents) == 0) {
            switch ($fix) {
              case 'category':
                ++$removedCategory;
                if ($dry) {
                  $output->writeln(
                    $this->l->t('Would remove the category "%3$s" from the event "%1$s@%2$s" (dry-run).', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                } else {
                  $output->writeln(
                    $this->l->t('Removing the category "%3$s" from the event "%1$s@%2$s".', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                  $eventsService->changeCategories($project, $calendarId, $eventUri, recurrenceId: null, removals: [ $projectName ]);
                }
                break;
              case 'reattach':
                ++$reattach;
                if ($dry) {
                  $output->writeln(
                    $this->l->t('Would reattach categorized event "%1$s@%2$s" to project "%3$s" (dry-run).', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                } else {
                  $output->writeln(
                    $this->l->t('Reattaching categorized event "%1$s@%2$s" to project "%3$s".', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                  // "reattach" is performed using a dummy do-nothing update
                  $calendarObject = $calDavService->getCalendarObject($calendarId, $eventUri);
                  $vCalendar = VCalendarService::getVCalendar($calendarObject);
                  $calDavService->updateCalendarObject($calendarId, $eventUri, $vCalendar);
                }
                break;
              case 'delete':
                ++$deleted;
                if ($dry) {
                  $output->writeln(
                    $this->l->t('Would delete unregistered event "%1$s@%2$s" with project-category "%3$s" (dry-run).', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                } else {
                  $output->writeln(
                    $this->l->t('Will delete unregistered event "%1$s@%2$s" with project-category "%3$s" (dry-run).', [
                      $eventUri, $calendarUri, $projectName
                    ]),
                    OutputInterface::VERBOSITY_VERBOSE
                  );
                  // "reattach" is performed using a dummy do-nothing update
                  $calDavService->deleteCalendarObject($calendarId, $eventUri);
                }
                break;
            }
          }
        }
      }

    } // loop over projects

    if ($purged > 0) {
      if ($dry) {
        $output->writeln($this->l->t('Would have hard-removed %d soft-deleted events (dry-run).', $purged));
      } else {
        $output->writeln($this->l->t('Hard-removed %d soft-deleted events.', $purged));
      }
    }

    if (!empty($fix) && $orphans > 0) {
      if ($dry) {
        $output->writeln($this->l->t('Would have removed %d orphaned events (dry-run).', $orphans));
      } else {
        $output->writeln($this->l->t('Removed %d orphaned events.', $orphans));
      }
    }

    if ($missingCategory > 0) {
      if ($dry) {
        $output->writeln($this->l->t('Would have added %d missing categories (dry-run).', $missingCategory));
      } else {
        $output->writeln($this->l->t('Added %d missing categories.', $missingCategory));
      }
    }

    if ($unregister > 0) {
      if ($dry) {
        $output->writeln($this->l->t('Would have unregistered %d events with missing project-name category (dry-run).', $unregister));
      } else {
        $output->writeln($this->l->t('Unregistered %d events with missing project-name category.', $unregister));
      }
    }

    if ($removedCategory > 0) {
      if ($dry) {
        $output->writeln($this->l->t('Would have removed %d erroneous categories (dry-run).', $removedCategory));
      } else {
        $output->writeln($this->l->t('Removed %d erroneous categories.', $removedCategory));
      }
    }

    if ($reattach > 0) {
      if ($dry) {
        $output->writeln($this->l->t('Would have reattached %d events with set project-name category (dry-run).', $reattach));
      } else {
        $output->writeln($this->l->t('Reattached %d events with set project-name category.', $reattach));
      }
    }

    if ($deleted > 0) {
      if ($dry) {
        $output->writeln($this->l->t('Would have deleted %d unregistered events with set project-name category (dry-run).', $deleted));
      } else {
        $output->writeln($this->l->t('Deleted %d unregistered events with set project-name category.', $deleted));
      }
    }

    if ($split > 0) {
      if ($dry) {
        $output->writeln($this->l->t('Would have splitted %d multi-day events into repeating event series (dry-run).', $split));
      } else {
        $output->writeln($this->l->t('Splitted %d multi-day events into repeating event series.', $split));
      }
    }

    if ($systemTags > 0) {
      if ($dry) {
        $output->writeln($this->l->t('Would have created %d missing system-tags (dry-run).', $systemTags));
      } else {
        $output->writeln($this->l->t('Created %d missing system-tags.', $systemTags));
      }
    }

    return 0;
  }
}
