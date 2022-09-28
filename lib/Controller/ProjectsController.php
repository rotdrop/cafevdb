<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\PageRenderer\Projects as Renderer;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;

class ProjectsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\FlattenEntityTrait;

  const LIST_OPERATION_CREATE = 'create';
  const LIST_OPERATION_SUBSCRIBE = 'subscribe';
  const LIST_OPERATION_CLOSE = 'close';
  const LIST_OPERATION_REOPEN = 'reopen';
  const LIST_OPERATION_DELETE = 'delete';

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var EntityManager */
  protected $entityManager;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function validate($topic)
  {
    $projectValues = $this->parameterService->getPrefixParams($this->pme->cgiDataName());
    switch ($topic) {
      case 'name':
        $required = [
          'year' => $this->l->t("project-year"),
          'name' => $this->l->t("project-name"),
          'type'  => $this->l->t("project-kind"),
        ];
        foreach ($required as $key => $subject) {
          if (empty($projectValues[$key])) {
            return self::grumble($this->l->t("The %s must not be empty.", [$subject]));
          }
        }
        $control = $this->parameterService->getParam('control', 'name');
        $record = $this->pme->getCGIRecordId();
        $projectId = $record['id']??null;
        $projectName = $projectValues['name'];
        $projectYear = $projectValues['year'];
        $attachYear  = !empty($projectValues['type']) && $projectValues['type'] == ProjectType::TEMPORARY;

        $infoMessage = "";
        switch ($control) {
          case "submit":
          case "name":
            // No whitespace, s.v.p., and CamelCase
            $origName = $projectName;
            if (strtoupper($projectName) == $projectName) {
              $projectName = strtolower($projectName);
            }
            $projectName = ucwords($projectName);
            $projectName = preg_replace("/[^[:alnum:]]?[[:space:]]?/u", '', $projectName);
            //$projectName = preg_replace('/\s+/', '', $projectName);
            if ($origName != $projectName) {
              $infoMessage .= $this->l->t('The project name has been simplified from "%s" to "%s".',
                                          [ $origName, $projectName ]);
            }
            $matches = [];
            // Get the year from the name, if set
            if (preg_match('/^(.*\D)?(\d{4})$/', $projectName, $matches) == 1) {
              $projectName = $matches[1];
              if ($control != "submit" && $attachYear) {
                // the year-control wins when submitting the form
                $projectYear = $matches[2];
              }
              if ($projectName == "") {
                return self::grumble($this->l->t("The project-name must not only consist of the year-number."));
              }
            } else if ($projectName == "") {
              return self::grumble($this->l->t("No project-name given."));
            }
            if (mb_strlen($projectName) > Renderer::NAME_LENGTH_MAX) {
              return self::grumble($this->l->t("The project-name is too long, ".
                                               "please use something less than %d characters ".
                                               "(excluding the attached year). Thanks",
                                               [ Renderer::NAME_LENGTH_MAX ]));
            }
            // fallthrough
          case "year":
            if ($projectYear == "") {
              return self::grumble($this->l->t("No project-year given."));
            }
            if (preg_match('/^\d{4}$/', $projectYear) !== 1) {
              return self::grumble($this->l->t("The project-year has to consist of four digits, e.g. ``1984''."));
            }

            // Strip the year from the name and replace with the given year
            $origName = $origName??$projectName;
            $strippedName = preg_replace('/\d+$/', '', $projectName);
            if ($attachYear) {
              $projectName = $strippedName . $projectYear;
              if ($projectName != $origName) {
                $infoMessage .= $this->l->t("The year %s has been appended to the project-slug %s.", [ $projectYear, $strippedName ]);
              }
            }
            // Project name may be empty at this point. Why not
            break;
          default:
            return self::grumble($this->l->t('Unknown Request'));
        }

        $repository = $this->getDatabaseRepository(Entities\Project::class);
        $projects = $repository->shortDescription();
        foreach ($projects['projects'] as $id => $nameYear) {
          if ($id != $projectId && $nameYear['name'] == $projectName && $nameYear['year'] == $projectYear) {
            return self::grumble($this->l->t('A project with the name "%1$s" already exists in the year %2$s with the id %3$d (new: %4$d). Please choose a different name or year.',
                                             [ $projectName, $projectYear, $id, $projectId ]));
          }
        }
        return self::dataResponse(['projectYear' => $projectYear,
                                   'projectName' => $projectName,
                                   'message' => $infoMessage ]);
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

  /**
   * @NoAdminRequired
   *
   * @param string $instrumentsKey The name of the instruments select.
   *
   * @param string $voicesKey The name of the voices select.
   */
  public function changeInstrumentation(string $instruments, string $voices)
  {
    $instrumentsKey = str_replace('[]', '', $instruments);
    $instruments = array_filter($this->parameterService[$instrumentsKey]??[]);

    $voicesKey = str_replace('[]', '', $voices);
    $voices = array_filter($this->parameterService[$voicesKey]??[]);

    $instrumentInfo =
      $this->getDatabaseRepository(Entities\Instrument::class)->describeALL();

    // convert voices array to instrumentId => [ voices ]
    $voicesByInstrument = Util::explodeIndexedMulti(implode(',', $voices));

    $voicesSelectArray = [];
    foreach ($instruments as $instrument) {
      $highestConfigured = empty($voicesByInstrument[$instrument]) ? 0 : max($voicesByInstrument[$instrument]);
      $highest = max(Renderer::NUM_VOICES_MIN, $highestConfigured + Renderer::NUM_VOICES_EXTRA);
      for ($i = 1; $i <= $highest+1; ++$i) {
        $voiceIndicator = ($i <= $highest) ? $i : '?';
        $value = $instrument . Renderer::JOIN_KEY_SEP . $voiceIndicator;
        $voiceOption = [
          'value' => $value,
          'name' => $instrumentInfo['byId'][$instrument] . ' ' . $voiceIndicator,
          'group' => $instrumentInfo['idGroups'][$instrument],
        ];
        $voicesSelectArray[] = $voiceOption;
        // make sure there are no holes, all voices up to the maximum are
        // selected
        if ($i <= $highestConfigured) {
          $voices[] = $value;
        }
      }
    }

    $voicesSelectOptions = PageNavigation::selectOptions($voicesSelectArray, $voices);

    return self::dataResponse([ 'voices' => $voicesSelectOptions ]);
  }

  /**
   * @NoAdminRequired
   *
   * @param string $operation One of create, close, delete
   */
  public function mailingLists(string $operation, int $projectId, bool $force = false)
  {
    switch ($operation) {
      case self::LIST_OPERATION_CREATE:
        /** @var ProjectService $projectService */
        $projectService = $this->di(ProjectService::class);
        $listInfo = $projectService->createProjectMailingList($projectId);
        $listId = $listInfo['list_id'];
        /** @var MailingListsService $listsService */
        $listsService = $this->di(MailingListsService::class);
        if (empty($listsService->getListConfig($listId, 'allow_list_posts'))) {
          $l10nStatus = $this->l->t($status = 'closed');
        } else {
          $l10nStatus = $this->l->t($status = 'active');
        }
        $listInfo['message'] = $this->l->t('Mailing-list "%s" successfully created.', $listInfo['fqdn_listname']);
        $listInfo['status'] = $status;
        $listInfo['l10nStatus'] = $l10nStatus;
        return self::dataResponse($listInfo);

      case self::LIST_OPERATION_REOPEN:
      case self::LIST_OPERATION_CLOSE:
        /** @var ProjectService $projectService */
        $projectService = $this->di(ProjectService::class);
        /** @var Entities\Project $project */
        $project = $projectService->findById($projectId);
        $listId = $project->getMailingListId();
        /** @var MailingListsService $listsService */
        $listsService = $this->di(MailingListsService::class);
        $listsService->setListConfig($listId, 'emergency', $operation === 'close');
        $listInfo = $listsService->getListInfo($listId);
        if ($operation == 'close') {
          $l10nStatus = $this->l->t($status = 'closed');
          $listInfo['message'] = $this->l->t('Successfully closed "%s".', $listId);
        } else {
          $l10nStatus = $this->l->t($status = 'active');
          $listInfo['message'] = $this->l->t('Successfully re-opened "%s".', $listId);
        }
        $listInfo['status'] = $status;
        $listInfo['l10nStatus'] = $l10nStatus;
        return self::dataResponse($listInfo);

      case self::LIST_OPERATION_DELETE:
        /** @var ProjectService $projectService */
        $projectService = $this->di(ProjectService::class);
        /** @var Entities\Project $project */
        $project = $projectService->findById($projectId);
        $listId = $project->getMailingListId();
        if (!$force) {
          return new DataResponse([
              'status' => 'unconfirmed',
              'feedback' => $this->l->t('Really delete the mailing list "%1$s" for the project "%2$s"?', [ $listId, $project->getName() ]),
          ]);
        }
        $projectService->deleteProjectMailingList($project);
        return self::dataResponse([
          'message' => $this->l->t('Successfully deleted "%s".', $listId),
          'list_id' => $listId,
          'fqdn_listname' => preg_replace('/\./', '@', $listId, 1),
          'status' => 'unset',
          'l10nStatus' => $this->l->t('unset'),
        ]);

      case self::LIST_OPERATION_SUBSCRIBE:
        /** @var MailingListsService $listsService */
        $listsService = $this->di(MailingListsService::class);
        /** @var ProjectService $projectService */
        $projectService = $this->di(ProjectService::class);
        /** @var Entities\Project $project */
        $project = $projectService->findById($projectId);
        $listId = $project->getMailingListId();

        $failures = [];
        $newCount = 0;
        $keptCount = 0;
        $noEmailCount = 0;
        $notConfirmedCount = 0;
        /** @var Entities\ProjectParticipant $participant */
        foreach ($project->getParticipants() as $participant) {
          if (!$participant->getRegistration()) {
            ++$notConfirmedCount;
            continue; // only subscribe confirmed participants
          }
          $email = $participant->getMusician()->getEmailAddress();
          if (empty($email)) {
            ++$noEmailCount;
            continue; // after all, it is a mailing list ...
          }
          $displayName = $participant->getMusician()->getPublicName(firstNameFirst: true);
          try {
            $result = $projectService->ensureMailingListSubscription($participant);
            $newCount += (int)$result;
            $keptCount += (int)!$result;
          } catch (\Throwable $t) {
            $failures[] = [
              'email' => $email,
              'displayName' => $displayName,
              'message' => $t->getMessage(),
            ];
          }
        }

        $messages = [
          $this->l->t('%1$d new subscriptions, %2$d existing subscriptions have been kept.', [ $newCount, $keptCount ]),
        ];
        if ($notConfirmedCount > 0) {
          $messages[] = $this->l->n(
            '%n person has not been subscribed because of unconfirmed participation.',
            '%n persons have not been subscribed because their participation is not confirmed.',
            $notConfirmedCount
          );
        }
        if ($noEmailCount > 0) {
          $messages[] = $this->l->t('%d persons have not been subscribed because we do not have their email-address.', $noEmailCount);
        }
        if (count($failures) > 0) {
          $messages[] = $this->l->t('%d subscription requests have failed.', [ count($failures) ]);
        }
        foreach ($failures as $failure) {
          $messages[] = $this->l->t('Error for "%1$s <%2$s>": %3$s', [
            $failure['displayName'], $failure['email'], $failure['message'],
          ]);
        }
        return self::dataResponse([
          'message' => $messages,
          'listId' => $listId,
          'status' => 'unchanged',
        ]);
    }
    return self::grumble($this->l->t('Unknown Request: "%s".', $operation));
  }

  const GET_PROJECT_FOLDER = 'folder';
  const FOLDER_TYPES = [
    ProjectService::FOLDER_TYPE_PROJECT => ConfigService::PROJECTS_FOLDER,
    ProjectService::FOLDER_TYPE_PARTICIPANTS => ConfigService::PROJECT_PARTICIPANTS_FOLDER,
    ProjectService::FOLDER_TYPE_POSTERS => ConfigService::PROJECT_POSTERS_FOLDER,
    ProjectService::FOLDER_TYPE_BALANCE => ConfigService::BALANCES_FOLDER,
  ];

  /**
   * @NoAdminRequired
   *
   * $param int $projectId
   *
   * $param string $topic
   *
   * $param string $subTopic
   */
  public function get(int $projectId, string $topic = '', string $subTopic = '')
  {
    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);
    $project = $projectService->findById($projectId);
    if (empty($project)) {
      return self::grumble($this->l->t('Unable to find project with id "%d".', $projectId));
    }
    switch ($topic) {
      case '':
        return self::dataResponse($this->flattenProject($project));
      case self::GET_PROJECT_FOLDER:
        switch ($subTopic) {
          case '':
          case 'all':
            return self::dataResponse($projectService->getProjectFolder($project));
          case ProjectService::FOLDER_TYPE_PROJECT:
          case ProjectService::FOLDER_TYPE_BALANCE:
          case ProjectService::FOLDER_TYPE_PARTICIPANTS:
          case ProjectService::FOLDER_TYPE_POSTERS:
            $configKey = self::FOLDER_TYPES[$subTopic];
            return self::dataResponse([
              'folder' => $projectService->getProjectFolder($project, only: $configKey),
            ]);
          default:
            return self::grumble($this->l->t('Unknown folder type "%s".', $subTopic));
        }
      default:
    }
    return self::grumble($this->l->t('Unknown request: "%1$s / %2$s".', [ $topic, $subTopic ]));
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
