<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

use Behat\Transliterator\Transliterator;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository;
use OCA\CAFEVDB\Storage\UserStorage;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo4Embedded\Service\RPC as WebPagesRPC;

use OCA\CAFEVDB\Common\Util;

/**
 * General support service, kind of inconsequent glue between
 * Doctrine\ORM and CAFEVDB\PageRenderer.
 */
class ProjectService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const DBTABLE = 'Projects';

  const PROJECT_FOLDER_KEYS = [
    ConfigService::PROJECTS_FOLDER,
    ConfigService::PROJECT_PARTICIPANTS_FOLDER,
    ConfigService::PROJECT_BALANCE_FOLDER,
  ];

  /** @var UserStorage */
  private $userStorage;

  /** @var OCA\DokuWikiEmedded\Service\AuthDokuWiki */
  private $wikiRPC;

  /** @var OCA\Redaxo4Embedded\Service\RPC */
  private $webPagesRPC;

  /** @var ProjectsRepository */
  private $repository;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
    , UserStorage $userStorage
    , WikiRPC $wikiRPC
    , WebPagesRPC $webPagesRPC
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->userStorage = $userStorage;
    $this->wikiRPC = $wikiRPC;
    $this->wikiRPC->errorReporting(WikiRPC::ON_ERROR_THROW);
    $this->webPagesRPC = $webPagesRPC;
    $this->webPagesRPC->errorReporting(WebPagesRPC::ON_ERROR_THROW);
    //$this->logInfo(print_r($this->webPagesRPC->getCategories(), true));
    //$this->logInfo(print_r($this->webPagesRPC->getTemplates(), true));
    //$this->logInfo(print_r($this->webPagesRPC->getModules(), true));
    try {
      $this->repository = $this->getDatabaseRepository(Entities\Project::class);
    } catch (\Throwable $t) {
      $this->logException($t);
      $request = \OC::$server->query(OCP\IRequest::class);
      $userId = $this->userId();
      if ($request) {
        $this->logError('User "'.$userId.'" request uri "'.$request->getRequestUri().'"');
      } else {
        $this->logError('User "'.$userId.'", no request?!');
      }
      $this->repository = null;
    }
    $this->l = $this->l10n();
  }

  /**
   * Generate an option table with all participants, suitable to be
   * staffed into Navigation::selectOptions(). This is a single
   * select, only one musician may be preselected. The key is the
   * musician id. The options are meant for a single-choice select
   * box.
   *
   * @param $projectId The id of the project to fetch the musician options from
   *
   * @param $projectName Optional project name, will be queried from
   * DB if not specified.
   *
   * @param $musicianId A pre-selected musician, defaults to null.
   */
  public function participantOptions($projectId, $projectName = null, $selectedMusicianId = -1)
  {
    $participants = $this->getDatabaseRepository(Entities\ProjectParticipant::class)->fetchParticipantNames($projectId);
    $options = [];
    foreach ($participants as $participant) {
      $musicianId = $participant['musicianId'];
      $flags = ($musicianId == $selectedMusicianId) ? Navigation::SELECTED : 0;
      $options[] = [
        'value' => $musicianId,
        'name' => $participant['firstName'].' '.$participant['surName'],
        'flags' => $flags,
      ];
    }
    return $options;
  }

  public function findParticipant($projectId, $musicianId)
  {
    return $this->getDatabaseRepository(Entities\ProjectParticipant::class)
                ->find([ 'project' => $projectId, 'musician' => $musicianId]);
  }

  public function projectOptions($criteria = [], $selectedProject = -1)
  {
    $projects = $this->repository->findBy($criteria, [ 'year' => 'DESC', 'name' => 'ASC' ]);
    $options = [];
    foreach ($projects as $project) {
      $flags = ($project['id'] == $selectedProject) ? Navigation::SELECTED : 0;
      $name = $project['name'];
      $year = $project['year'];
      $shortName = str_replace($year, '', $name);
      $options[] = [
        'value' => $project['id'],
        'name' => $name,
        'label' => $shortName,
        'flags' => $flags,
        'group' => $year,
        'type' => $project['type'],
      ];
    }
    return $options;
  }

  /**
   * Fetch the instrumentation balance for the given project, @see \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectInstrumentationNumbersRepository::fetchInstrumentationBalance().
   *
   * @param int|Entities\Project $projectOrId
   *
   * @param bool $sumVoices Sum-up all voices into a single instrument field
   *
   * @return array<string, array>
   * ```
   * [
   *   INSTRUMENT:VOICE => [
   *     'instrument' => INSTRUMENT,
   *     'voice' => VOICE,
   *     'registered' => (REQUIRED - REGISTERED),
   *     'confirmed' => (REQUIRED - CONFIRMED),
   *   ]
   * ]
   * ```
   *
   * If $sumVoices == true, the voice field is not present and the key
   * consists of the instrument only.
   */
  public function instrumentationBalance($projectOrId, $sumVoices = false)
  {
    $projectId = ($projectOrId instanceof Entities\Project) ? $projectOrId['id'] : $projectOrId;


    $balanceData = $this->getDatabaseRepository(Entities\ProjectInstrumentationNumber::class)
                        ->fetchInstrumentationBalance($projectId);

    $balance = [];
    foreach ($balanceData as $row) {
      $instrument = $row['instrument'];
      if ($sumVoices) {
        $key = $instrument;
      } else {
        $voice = $row['voice'] > 0 ? $row['voice'] : 0;
        $key = $row['instrument'].':'.$voice;
      }
      if (empty($balance[$key])) {
        $balance[$key] = [
          'instrument' => $instrument,
          'required' => 0,
          'registered' => 0,
          'confirmed' => 0,
        ];
      }
      $required = $row['required'];
      foreach (['registered', 'confirmed'] as $field) {
        $balance[$key][$field] += $required - $row[$field];
      }
      $balance[$key]['required'] += $required;
      if (!$sumVoices) {
        $balance[$key]['voice'] = $voice;
      }
    }
    return $balance;
  }

  /**
   * Find a project by its name.
   *
   * @param string $projectName
   *
   * @return null|Entities\Project
   */
  public function findByName(string $projectName):?Entities\Project
  {
    return $this->repository->findOneBy([
      'name' => $projectName,
      'disabled' => false,
    ]);
  }

  public function persistProject(Entities\Project $project)
  {
    $this->persist($project);
    $this->flush($project);
  }

  /**
   * Fetch the project-name name corresponding to $projectId.
   */
  public function fetchName($projectId)
  {
    $project = $this->repository->find($projectId);
    if ($project == null) {
      return null;
    }
    return $project->getName();
  }

  /**
   * Just return the collection of all projects.
   */
  public function fetchAll() {
    return $this->repository->findAll();
  }

  /**
   * Get the configured name of the all or the specified folder.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @param string|null $only Get the name of only this folder if not
   * null. $only can be one of the PROJECTS_..._FOLDER constants of
   * the ConfigService class, @see ConfigService.
   *
   * @return mixed Either the array of all paths or the path requested by $only.
   */
  public function getProjectFolder($projectOrId, ?string $only = null)
  {
    $project = $this->repository->ensureProject($projectOrId);
    $pathSep = UserStorage::PATH_SEP;
    $yearName = $pathSep.$project['year'].$pathSep.$project['name'];
    $sharedFolder = $pathSep.$this->getConfigValue(ConfigService::SHARED_FOLDER);
    $folders = $only ? [ $only ] : self::PROJECT_FOLDER_KEYS;

    $paths = [];
    foreach ($folders as $key) {
      switch ($key) {
      case ConfigService::PROJECT_PARTICIPANTS_FOLDER:
        $projectsFolder = $sharedFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECTS_FOLDER).$yearName;
      case ConfigService::PROJECTS_FOLDER:
        if ($key == ConfigService::PROJECTS_FOLDER) {
          $paths[$key] = $projectsFolder;
          break;
        }
        $paths[$key] = $projectsFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
        break;
      case ConfigService::PROJECT_BALANCE_FOLDER:
        $paths[$key] = $sharedFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECT_BALANCE_FOLDER).$yearName;
        break;
      }
    }
    return empty($only) ? $paths : $paths[$only];
  }

  /**
   * Check for the existence of the project folders. Returns an array
   * of folders (balance and general files).
   *
   * @param int|Entities\Project $projectOrId
   *
   * @param string $projectName Name of the project.
   *
   * @param bool|string If a string create only this folder.
   *
   * @return array Array of created folders.
   *
   */
  public function ensureProjectFolders($projectOrId, $projectName = null, $only = null)
  {
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($projectName)) {
      $projectName = $project['name'];
    } else if ($projectName !== $project['name']) {
      return false;
    }

    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $participantsFolder = $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
    $balanceFolder  = $this->getConfigValue(ConfigService::PROJECT_BALANCE_FOLDER);

    $projectPaths = [
      'project' => [
        $sharedFolder,
        $projectsFolder,
        $project['year'],
        $project['name'],
      ],
      'balance' => [
        $sharedFolder,
        $balanceFolder,
        $projectsFolder,
        $project['year'],
        $project['name'],
      ],
      'participants' => [
        $sharedFolder,
        $projectsFolder,
        $project['year'],
        $project['name'],
        $participantsFolder,
      ],
    ];

    $returnPaths = [];
    foreach ($projectPaths as $key => $chain) {
      if (!empty($only) && $key != $only) {
        continue;
      }
      try {
        $this->userStorage->ensureFolderChain($chain);
        $returnPaths[$key] = UserStorage::PATH_SEP.implode(UserStorage::PATH_SEP, $chain);
      } catch (\Throwable $t) {
        if (!empty($only)) {
          throw \Exception(
            $this->l->t('Unable to ensure existence of folder "%s".',
                        UserStorage::PATH_SEP.implode(UserStorage::PATH_SEP, $chain)),
            $t->getCode(),
            $t);
        } else {
          $this->logException($t);
        }
      }
    }
    return $returnPaths;
  }

  /**
   * Remove the folder for the given project.
   *
   * @param Project|array Project entity or plain query result.
   *
   * @return bool Status
   */
  public function removeProjectFolders($oldProject)
  {
    $pathSep = UserStorage::PATH_SEP;
    $yearName = $pathSep.$oldProject['year'].$pathSep.$oldProject['name'];

    $sharedFolder   = $pathSep.$this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $sharedFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECTS_FOLDER).$yearName;
    $participantsFolder = $projectsFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
    $balanceFolder  = $sharedFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECT_BALANCE_FOLDER).$yearName;

    $projectPaths = [
      'participants' => $participantsFolder,
      'project' => $projectsFolder,
      'balance' => $balanceFolder,
    ];

    foreach($projectPaths as $key => $path) {
      $this->userStorage->delete($path);
    }

    return true;
  }

  /**
   * Rename the associated project folders after project rename.
   *
   * @param array|Entities\Project $newProject Array-like object,
   * "id", "name" and "year" keys need to be present.
   *
   * @param array|Entities\Project $oldProject Array-like object,
   * "id", "name" and "year" keys need to be present.
   *
   * @return array Array with new paths.
   */
  public function renameProjectFolder($newProject, $oldProject)
  {
    if (!isset($newProject['id'])) {
      $newProject['id'] = $oldProject['id'];
    }
    if (!isset($newProject['year'])) {
      $newProject['year'] = $oldProject['year'];
    }

    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $balanceFolder  = $this->getConfigValue(ConfigService::PROJECT_BALANCE_FOLDER);

    $prefixPath = [
      'project' => '/'.$sharedFolder.'/'.$projectsFolder.'/',
      'balance' => '/'.$sharedFolder.'/'.$balanceFolder.'/'.$projectsFolder."/",
    ];

    $returnPaths = [];
    foreach ($prefixPath as $key => $prefix) {

      $oldPath = $prefix.$oldProject['year']."/".$oldProject['name'];
      $newPrefixPath = $prefix.$newProject['year'];

      $newPath = $newPrefixPath.'/'.$newProject['name'];

      $oldDir = $this->userStorage->get($oldPath);
      if (!empty($oldDir)) {
        // If the year has changed it may be necessary to create a new
        // directory.
        $this->userStorage->ensureFolder($newPrefixPath);
        $this->userStorage->rename($oldPath, $newPath);
        $returnPaths[$key] = $newPath;
      } else {
        try {
          // Otherwise there is nothing to move; we simply create the new directory.
          $returnPaths = array_merge(
            $returnPaths,
            $this->ensureProjectFolders($newProject, null, $key /* only */));
        } catch (\Throwable $t) {
          $this->logException($t);
        }
      }
    }

    return $returnPaths;
  }

  public function ensureParticipantFolder(Entities\Project $project, $musician)
  {
    $parentPath = array_shift($this->ensureProjectFolders($project, null, 'participants'));
    $participantFolder = $parentPath.UserStorage::PATH_SEP.$musician['userIdSlug'];
    $this->userStorage->ensureFolder($participantFolder);
    return $participantFolder;
  }

  /**
   * e.g. passport-clausjustusheine.pdf
   * e.g. passport-claus-justus-heine.pdf
   */
  public function participantFilename(string $base, $project, $musician)
  {
    $userIdSlug = $musician['userIdSlug']?:$this->defaultUserIdSlug($musician['surName'], $musician['firstName'], $musician['nickName']);
    return $base.'-'.Util::dashesToCamelCase($userIdSlug, true, '_-.');
  }

  public function projectWikiLink($pageName)
  {
    $orchestra = $this->getConfigValue('orchestra');

    return $orchestra.":projects:".$pageName;
  }

  /**
   * Generate an automated overview
   */
  public function generateWikiOverview()
  {
/*
  ====== Projekte der Camerata Academica Freiburg e.V. ======

  ==== 2011 ====
  * [[Auvergne2011|Auvergne]]
  * [[Weihnachten2011]]

  ==== 2012 ====
  * [[Listenpunkt]]
  * [[Blah]]

  ==== 2013 ====
  * [[Listenpunkt]]
  */
    $orchestra = $this->getConfigValue('orchestra');
    $orchestra = $this->getConfigValue('streetAddressName01', $orchestra);

    $projects = $this->repository->findAll();

    $page = "====== ".($this->l->t('Projects of %s', [$orchestra]))."======\n\n";

    $year = -1;
    foreach ($projects as $project) {
      if ($project['year'] != $year) {
        $year = $project['year'];
        $page .= "\n==== ".$year."====\n";
      }
      $name = $project['name'];

      $matches = false;
      if (preg_match('/^(.*\D)?(\d{4})$/', $name, $matches) == 1) {
        $bareName = $matches[1];
        //$projectYear = $matches[2];
      } else {
        $bareName = $name;
      }

      // A page is tagged with the project name; if this ever should
      // be changed (which is possible), then change-trigger should
      // create a new page as copy from the old one and change the
      // text of the old one to contain a link to the new page.

      $page .= "  * [[".$this->projectWikiLink($name)."|".$bareName."]]\n";
    }

    $pageName = $this->projectWikiLink('projects');

    $this->wikiRPC->putPage($pageName, $page,
                            [ "sum" => "Automatic CAFEVDB synchronization",
                              "minor" => true ]);
  }

  /**Generate an almost empty project page. This spares people the
   * need to click on "new page".
   *
   * - We insert a proper title heading
   *
   * - We insert a sub-title "Contacts"
   *
   * - We insert a sub-title "Financial Arrangements"
   *
   * - We insert a sub-title "Location"
   */
  public function generateProjectWikiPage($projectId, $projectName)
  {
    $page = $this->l->t('====== Project %s ======

===== Forword =====

This wiki-page is useful to store selected project related
informations in comfortable and structured form. This can be useful
for "permant information" like details about supplementary fees,
contact informations and the like. In particular, this page could be
helpful to reduce unnecessary data-digging in our email box.

===== Contacts =====
Please add any relevant email and mail-adresses here. Please use the wiki-syntax
* [[foobar@important.com|Mister Universe]]

===== Financial Arrangements =====
Please add any special financial arrangements here. For example:
single-room fees, double-roome fees. Please consider using an
unordered list for this like so:
  * single room fee: 3000€
  * double room fee: 6000€
  * supplemenrary fee for Cello-players: 1500€

===== Location =====
Whatever.',
                 [ $projectName ]);

      $pagename = $this->projectWikiLink($projectName);
      $this->wikiRPC->putPage($pagename, $page,
                              [ "sum" => "Automatic CAFEVDB synchronization",
                                "minor" => true ]);
  }

  /**
   * Rename the associated wiki-pages after project rename.
   *
   * @param array|Entities\Project $newProject Array-like object,
   * "name" and "year" keys need to be present.
   *
   * @param array|Entities\Project $oldProject Array-like object,
   * "name" and "year" keys need to be present.
   */
  public function renameProjectWikiPage($newProject, $oldProject)
  {
    $oldName = $oldProject['name'];
    $newName = $newProject['name'];
    $oldPageName = $this->projectWikiLink($oldName);
    $newPageName = $this->projectWikiLink($newName);

    $oldPage = " *  ".$oldvals['name']." wurde zu [[".$newPageName."]] umbenant\n";
    $newPage = $this->wikiRPC->getPage($oldPageName);

    $this->logInfo('OLD '.$oldPageName.' / '.$oldPage);
    $this->logInfo('NEW '.$newPageName.' / '.$newPage);

    if ($newPage) {
      $this->wikiRPC->putPage(
        $newPageName, $newPage,
        [ "sum" => "Automatic CAFEVDB page renaming", "minor" => false ]);
      // Generate stuff if there is an old page
      $this->wikiRPC->putPage(
        $oldPageName, $oldPage,
        [ "sum" => "Automatic CAFEVDB page renaming", "minor" => false ]);
    }

    $this->generateWikiOverview();
  }

  /**
   */
  public function webPageCMSURL($articleId, $editMode = false)
  {
    return $this->webPagesRPC->redaxoURL($articleId, $editMode);
  }

  /**
   * Fetch fetch the article entities from the database
   *
   * @return ArrayCollection
   */
  public function fetchProjectWebPages($projectId)
  {
    $project = $this->repository->find($projectId);
    if (empty($project)) {
      return null;
    }

    return $project->getWebPages();
  }

  /**
   * Fetch all articles known to the system.
   *
   * @return array
   * ```
   * [
   *   'projectPages' => WEBPAGES,
   *   'otherPages' => WEBPAGES,
   * ]
   * ```
   */
  public function projectWebPages($projectId)
  {
    $project = $this->repository->find($projectId);
    if (empty($project)) {
      return false;
    }

    $articleIds = [];
    foreach ($project->getWebPages() as $idx => $article) {
      $articleIds[$article['articleId']] = $idx;
    }

    $categories = [ [ 'id' => $this->getConfigValue('redaxoPreview'),
                      'name' => $this->l->t('Preview') ],
                    [ 'id' => $this->getConfigValue('redaxoRehearsals'),
                      'name' => $this->l->t('Rehearsals') ],
                    [ 'id' => $this->getConfigValue('redaxoArchive'),
                      'name' => $this->l->t('Archive') ],
                    [ 'id' => $this->getConfigValue('redaxoTrashbin'),
                      'name' => $this->l->t('Trashbin')] ];
    $projectPages = [];
    $otherPages = [];
    foreach ($categories as $category) {
      // Fetch all articles and remove those already registered
      $pages = $this->webPagesRPC->articlesByName('.*', $category['id']);
      $this->logDebug("Projects: ".$category['id']);
      if (is_array($pages)) {
        foreach ($pages as $idx => $article) {
          $article['categoryName'] = $category['name'];
          if (isset($articleIds[$article['articleId']])) {
            $projectPages[] = $article;
          } else {
            $otherPages[] = $article;
          }
          $this->logDebug("Projects: ".print_r($article, true));
        }
      }
    }
    //$this->logInfo('PROJECT '.print_r($projectPages, true));
    //$this->logInfo('OTHER '.print_r($otherPages, true));
    return [
      'projectPages' => $projectPages,
      'otherPages' => $otherPages,
    ];
  }

  /**
   * Create and add a new web-page. The first one will have the name
   * of the project, subsequent one have a number attached like
   * Tango2014-5.
   *
   * @param $projectId Id of the project
   *
   * @param $kind One of 'concert' or 'rehearsals'
   *
   * @param $handle Optional active data-base handle.
   */
  public function createProjectWebPage($projectId, $kind = 'concert')
  {
    $project = $this->repository->find($projectId);
    if (empty($project)) {
      throw new \Exception($this->l->t('Empty project-id'));
    }
    $projectName = $project->getName();

    switch ($kind) {
    case 'rehearsals':
      $prefix = $this->l->t('Rehearsals').' ';
      $category = $this->getConfigValue('redaxoRehearsals');
      $module = $this->getConfigValue('redaxoRehearsalsModule');
      break;
    default:
      // Don't care about the archive, new pages go to preview, and the
      // id will be unique even in case of a name clash
      $prefix = '';
      $category = $this->getConfigValue('redaxoPreview');
      $module = $this->getConfigValue('redaxoConcertModule');
      break;
    }

    // General page template
    $pageTemplate = $this->getConfigValue('redaxoTemplate');

    $pageName = $prefix.$projectName;
    try {
      $articles = $this->webPagesRPC->articlesByName($pageName.'(-[0-9]+)?', $category);
    } catch (\Throwable $t)  {
      throw new \Exception(
        $this->l->t('Unable to fetch web-pages like "%s".', [ $pageName ]),
        $t->getCode(),
        $t);
    }

    $names = array();
    foreach ($articles as $article) {
      $names[] = $article['articleName'];
    }
    if (array_search($pageName, $names) !== false) {
      for ($i = 1; ; ++$i) {
        if (array_search($pageName.'-'.$i, $names) === false) {
          // this will teminate ;)
          $pageName = $pageName.'-'.$i;
          break;
        }
      }
    }

    try {
      $article = $this->webPagesRPC->addArticle($pageName, $category, $pageTemplate);
    } catch (\Throwable $t) {
      throw new \Exception(
        $this->l->t('Unable to create web-page "%s".', [ $pageName ]),
        $t->getCode(),
        $t);
    }

    // just forget about the rest, we can't help it anyway if the
    // names are not unique
    $article = $article[0];

    $this->entityManager->beginTransaction();
    try {
      // insert into the db table to form the link
      $this->attachProjectWebPage($projectId, $article);
      $this->webPagesRPC->addArticleBlock($article['articleId'], $module);

      $this->flush();
      $this->entityManager->commit();
    } catch (\Throwable $t)  {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception(
        $this->l->t('Unable to attach article "%s".', [ $pageName ]),
        $t->getCode(),
        $t);
    }

    return $article;
  }

  /**
   * Delete a web page. This is implemented by moving the page to the
   * Trashbin category, leaving the real cleanup to a human being.
   */
  public function deleteProjectWebPage($projectId, array $article)
  {
    $articleId = $article['articleId'];
    $categoryId = $article['categoryId'];

    $this->entityManager->beginTransaction();
    try {
      $this->detachProjectWebPage($projectId, $articleId);
      $trashCategory = $this->getConfigValue('redaxoTrashbin');

      // try moving to tash if the article exists in its category.
      if (!empty($this->webPagesRPC->articlesById([ $articleId ], $categoryId))) {
        $result = $this->webPagesRPC->moveArticle($articleId, $trashCategory);
      }

      $this->flush();
      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception($this->l->t('Failed removing web-page %d from project %d', [ $articleId, $projectId ]), $t->getCode(), $t);
    }
  }

  /**
   * Detach a web page, but do not delete it. Meant as utility routine
   * for the UI (in order to correct wrong associations).
   */
  public function detachProjectWebPage($projectId, $articleId)
  {
    $this->entityManager->beginTransaction();
    try {
      $this->setDatabaseRepository(Entities\ProjectWebPage::class);
      $this->remove([ 'project' => $projectId, 'articleId' => $articleId  ]);
      $this->flush();

      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception($this->l->t('Failed detaching web-page %d from project %d', [ $articleId, $projectId ]), $t->getCode(), $t);
    }
  }

  /**
   * Attach an existing web page to the project.
   *
   * @param $projectId Project Id.
   *
   * @param $article Article array as returned from articlesByName().
   *
   */
  public function attachProjectWebPage($projectId, $article)
  {
    // Try to remove from trashbin, if appropriate.
    $trashCategory = $this->getConfigValue('redaxoTrashbin');
    if ($article['categoryId'] == $trashCategory) {
      if (stristr($article['articleName'], $this->l->t('Rehearsals')) !== false) {
        $destinationCategory = $this->getConfigValue('redaxoRehearsals');
      } else {
        $destinationCategory = $this->getConfigValue('redaxoPreview');
      }
      $articleId = $article['articleId'];
      $result = $this->webPagesRPC->moveArticle($articleId, $destinationCategory);
      if ($result === false) {
        $this->logDebug("Failed moving ".$articleId." to ".$destinationCategory);
      } else {
        $article['categoryId'] = $destinationCategory;
      }
    }

    $webPagesRepository = $this->entityManager->getRepository(Entities\ProjectWebPage::class);
    try {
      $projectWebPage = $webPagesRepository->attachProjectWebPage($projectId, $article);
    } catch (\Throwable $t) {
      throw new \Exception("Unable to attach web-page ".$articleId." for ".$projectId, $t->getCode(), $t);
    }
  }

  /**
   * Set the name of all registered web-pages to the canonical name,
   * project name given.
   */
  public function nameProjectWebPages($projectId, $projectName = null)
  {
    $project = $this->repository->find($projectId);
    if (empty($project)) {
      return false;
    }
    if (empty($projectName)) {
      $projectName = $project->getName(); // @todo check if already set
    }

    $webPages = $project->getWebPages();

    $rehearsalsName = $this->l->t('Rehearsals');
    $webPagesRepository = $this->entityManager->getRepository(Entities\ProjectWebpage::class);

    $concertNr = 0;
    $rehearsalNr = 0; // should stay at zero
    foreach ($webPages as $article) {
      if (stristr($article['articleName'], $rehearsalsName) !== false) {
        $newName = $rehearsalsName.' '.$projectName;
        if ($rehearsalNr > 0) {
          $newName .= '-'.$rehearsalNr;
        }
        ++$rehearsalNr;
      } else {
        $newName = $projectName;
        if ($concertNr > 0) {
          $newName .= '-'.$concertNr;
        }
        ++$concertNr;
      }
      if ($this->webPagesRPC->setArticleName($article['articleId'], $newName)) {
        // if successful then also update the data-base entry
        $webPagesRepository->mergeAttributes(
          [ 'articleId' => $article['articleId'] ],
          [ 'articleName' => newName ]);
      }
    }

    return true;
  }

  /**
   * Search through the list of all projects and attach those with a
   * matching name. Something which should go to the "expert"
   * controls.
   *
   * @param int|Entities\Project $projectOrId
   */
  public function attachMatchingWebPages($projectOrId)
  {
    $project = $this->repository->ensureProject($projectOrId);
    $projectId = $project->getId();
    $projectName = $project->getName();

    $previewCat    = $this->getConfigtValue('redaxoPreview');
    $archiveCat    = $this->getConfigValue('redaxoArchive');
    $rehearsalsCat = $this->getConfigValue('redaxoRehearsals');

    $cntRe = '(?:-[0-9]+)?';

    $preview = $this->webPagesRPC->articlesByName($projectName.$cntRe, $previewCat);
    if (!is_array($preview)) {
      return false;
    }
    $archive = $this->webPagesRPC->articlesByName($projectName.$cntRe, $archiveCat);
    if (!is_array($archive)) {
      return false;
    }
    $rehearsals = $this->webPagesRPC->articlesByName($this->l->t('Rehearsals').' '.$projectName.$cntRe, $rehearsalsCat);
    if (!is_array($rehearsals)) {
      return false;
    }

    $articles = array_merge($preview, $archive, $rehearsals);

    //\OCP\Util::writeLog(Config::APP_NAME, "Web pages for ".$projectName.": ".print_r($articles, true), \OCP\Util::DEBUG);

    foreach ($articles as $article) {
      // ignore any error
      $this->attachProjectWebPage($projectId, $article);
    }

    return true;
  }

  /**
   * Soft delete this project.
   */
  public function disable($projectId, $disable = true)
  {
    $this->repository->disable($projectId);
  }

  /**
   * Undo soft-deletion.
   */
  public function enable($projectId, $disable = true)
  {
    $this->repository->enable($projectId);
  }

  /**
   * Add the given musicians to the project.
   *
   * @param array $musicianIds Flat array of the data-base keys for Entities\Musician.
   *
   * @param int $projectId The project-id for the destination project.
   *
   * @todo Perhaps allow project-entities, i.e. "mixed $project"
   * instead of "int $projectId".
   *
   * @return array
   * ```
   * [
   *   'failed' => [
   *     [ 'id' => ID, 'notice' => REMARK, ... ],
   *     ...
   *   ],
   *   'added'  => [
   *      [ id' => ID, 'notice' => REMARK, 'sqlerror' => OPTIONAL ],
   *   ],
   * ]
   * ```
   */
  public function addMusicians(array $musicianIds, $projectId)
  {
    $project = $this->repository->find($projectId);
    if (empty($project)) {
      throw new \Exception($this->l->t('Unabled to retrieve project with id %d', $projectId));
    }

    $statusReport = [
      'added' => [],
      'failed' => [],
    ];
    foreach ($musicianIds as $id) {
      if ($this->addOneMusician($id, $project, $status)) {
        $statusReport['added'][$id] = $status;
      } else {
        $statusReport['failed'][$id] = $status;
      }
    }

    return $statusReport;
  }

  /**
   * Add one given musician to the project.
   *
   * @param mixed $id Id of the musician, something understood by
   * MusiciansRepository::find().
   */
  private function addOneMusician($id, Entities\Project $project, &$status)
  {
    $musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);

    $status = [];

    $musician = $musiciansRepository->find($id);
    if (empty($musician)) {
      if (!empty($status)) {
        $status[] = [
          'id' => $id,
          'notice' => $this->l->t(
            'Unable to fetch musician\'s personal information for id %d.', $id),
        ];
      }
      return false;
    }

    $musicianName = $musician['firstName'].' '.$musician['surName'];

    // check for already registered
    $exists = $project['participants']->exists(function($key, $participant) use ($musician) {
      return $participant['musician']['id'] == $musicion['id'];
    });
    if ($exists) {
      $status[$id][] = [
        'id' => $id,
        'notice' => $this->l->t(
          'The musician %s is already registered with project %s.',
          [ $musicianName, $project['name'] ]),
      ];
      return false;
    }

    $this->entityManager->beginTransaction();
    try {

      // The musician exists and is not already registered, so add it.
      $participant = Entities\ProjectParticipant::create();
      $participant['project'] = $project;
      $participant['musician'] = $musician;
      $this->persist($participant);

      // Try to make a likely default choice for the project instrument.
      $instrumentationNumbers = $project['instrumentationNumbers'];

      if ($musician['instruments']->isEmpty()) {
        $status[] = [
          'id' => $id,
          'notice' => $this->l->t('The musician %s does not play any instrument.', $musicianName),
        ];
      } else {

        // first find one instrument with best ranking
        $bestInstrument = null;
        $ranking = PHP_INT_MIN;
        foreach ($musician['instruments'] as $musicianInstrument) {
          $instrumentId = $musicianInstrument['instrument']['id'];
          $numbers =  $instrumentationNumbers->filter(function($number) use ($instrumentId) {
            return $number['instrument']['id'] == $instrumentId;
          });
          if ($numbers->isEmpty()) {
            continue;
          }
          if ($musicianInstrument['ranking'] <= $ranking) {
            continue;
          }
          $ranking = $musicianInstrument['ranking'];

          // if voice == -1 exist, use it (no voice), otherwise use
          // the one with the lest registerd musicians or the
          // potentially less demanding (highest numbered) voice.
          $voice = 0;
          $neededMost = PHP_INT_MIN;
          foreach ($numbers as $number) {
            if ($number['voice'] == -1) {
              $voice = -1;
              break;
            }
            $needed = $number['quantity'] - count($number['instruments']);
            if ($needed > $neededMost ||
                ($needed == $neededMost &&  $number['voice'] > $voice)) {
              $neededMost = $needed;
              $voice = $number['voice'];
            }
          }

          $bestInstrument = [
            'instrument' => $musicianInstrument['instrument'],
            'voice' => $voice,
          ];
        }

        if (empty($bestInstrument)) {
          $status[] = [
            'id' => $id,
            'notice' => $this->l->t(
              'The musician %s does not play any instrument registered in the instrumentation list for the project %s.',
              [ $musicianName, $project['name'], ]),
          ];
        } else {
          $projectInstrument = Entities\ProjectInstrument::create();
          $projectInstrument['project'] = $project;
          $projectInstrument['musician'] = $musician;
          $projectInstrument['instrument'] = $bestInstrument['instrument'];
          $projectInstrument['voice'] = $bestInstrument['voice'];
          $this->persist($projectInstrument);
        }
      }

      $musician['updated'] = $project['updated'] = new \DateTime;

      $this->flush();

      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }

      $status[] = [
        'id' => $id,
        'notice' => $this->l->t(
          'Adding the musician with id %d failed with exception %s',
          [ $id, $t->getMessage(), ]),
      ];
      return false;
    }

    $status[] = [
      'id' => $id,
      'notice' => $this->l->t('The musician %s has been added to project %s.',
                              [ $musicianName, $project['name'], ]),
    ];

    return true;
  }

  /**
   * Create the infra-structure to the given project.
   *
   * @param array|Entities\Project $project Either the project entity
   * or an array with at least the entries for the id and name fields.
   *
   */
  public function createProjectInfraStructure($project)
  {
    // $newvals contains the new values
    $projectId   = $project['id'];
    $projectName = $project['name'];

    // Also create the project folders.
    $projectPaths = $this->ensureProjectFolders($projectId, $projectName);

    $this->generateWikiOverview();
    $this->generateProjectWikiPage($projectId, $projectName);

    // Generate an empty offline page template in the public web-space
    $this->createProjectWebPage($projectId, 'concert');
    $this->createProjectWebPage($projectId, 'rehearsals');
  }

  /**
   * Create a new project with the given name and optional year.
   *
   * @param string $name The name of the new project.
   *
   * @param int|null $year Optional year for "temporary" projects.
   *
   * @param mixed $type Type of the project, @see Types\EnumProjectTemporalType
   */
  public function createProject(string $name, ?int $year = null, $type = Types\EnumProjectTemporalType::TEMPORARY):?Entities\Project
  {
    $project = null;

    $this->entityManager->beginTransaction();
    try {

      $year = $year?:\date('Y');

      $project = Entities\Project::create()
               ->setName($name)
               ->setYear($year)
               ->setType($type);

      $this->persist($project);

      $this->flush($project);

      $this->createProjectInfraStructure($project);

      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception(
        $this->l->t('Unable to create new project with name "%s".', $name),
        $t->getCode(),
        $t);
    }

    return $project;
  }

  /**
   * Delete the given project or id.
   *
   * @param int|Entities\Project $project
   *
   * @return null|Entities\Project Returns null if project was
   * actually deleted, else the updated "soft-deleted" project instance.
   */
  public function deleteProject($projectOrId):? Entities\Project
  {
    $project = $this->repository->ensureProject($projectOrId);
    $projectId = $project['id'];

    $softDelete  = count($project['payments']) > 0;

    $this->entityManager->beginTransaction();
    try {

      // Remove the project folder ... OC has undelete
      // functionality and we have a long-ranging backup.
      $this->removeProjectFolders($project);

      // Regenerate the TOC page in the wiki.
      $this->generateWikiOverview();

      // Delete the page template from the public web-space. However,
      // here we only move it to the trashbin.
      $webPages = $project->getWebPages();
      foreach ($webPages as $page) {
        // ignore errors
        $this->deleteProjectWebPage($projectId, $page);
      }

      if ($softDelete) {
        $project['disabled'] = true;
        $this->persistProject($project);
      } else {

        $this->remove($project, true);

        // @todo: use cascading to remove
        $deleteTables = [
          Entities\ProjectParticipant::class,
          Entities\ProjectInstrument::class,
          Entities\ProjectWebPage::class,
          Entities\ProjectParticipantField::class, // needs cascading
          // [ 'table' => 'ProjectEvents', 'column' => 'project_id' ], handled above
        ];

        $triggerResult = true;
        foreach ($deleteTables as $table) {
          $this->entityManager
            ->createQueryBuilder()
            ->delete($table, 't')
            ->where('t.project = :projectId')
            ->setParameter('projectId', $projectId)
            ->getQuery()
            ->execute();
        }
      }

      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception(
        $this->l->t('Failed to remove project "%s", id "%d".',
                    [ $project['name'], $project['id'] ]),
        $t->getCode(),
        $t);
    }

    // if ($this->entityManager->isTransactionActive()) {
    //   throw new \Exception('Transaction still active '.$this->entityManager->getTransactionNestingLevel());
    // }

    return $softDelete ? $project : null;
  }

  /**
   * Rename the given project or id.
   *
   * @param int|Entities\Project $project
   *
   * @param string|array|Entities\Project $newData
   *
   * @return Entities\Project Returns the renamed and persisted
   * entity.
   */
  public function renameProject($projectOrId, $newData)
  {
    $project = $this->repository->ensureProject($projectOrId);
    $projectId = $project['id'];

    if (is_string($newData)) {
      list('name' => $newName, 'year' => $newYear) = $this->yearFromName($newData);
      $newName = $newName.($newYear?:'');
    } else {
      $newName = $newData['name'];
      $newYear = $newData['year'];
    }
    $newYear = $newYear?:$project['year'];
    $newProject = [ 'id' => $projectId, 'name' => $newName, 'year' => $newYear ];

    $stages = [
      [
        'method' => 'renameProjectFolder',
        'forward' => [ $newProject, $project ],
        'backwards' => [ $project, $newProject ],
        'done' => false,
      ],
      [
        'method' => 'renameProjectWikiPage',
        'forward' => [ $newProject, $project ],
        'backwards' => [ $project, $newProject ],
        'done' => false,
      ],
      [
        'method' => 'nameProjectWebPages',
        'forward' => [ $projectId, $newName ],
        'backwards' => [ $projectId, $project['name'] ],
        'done' => false,
      ],
    ];
    $this->entityManager->beginTransaction();
    try {

      // stages should throw on error
      foreach ($stages as &$stage) {
        \call_user_func_array([ $this, $stage['method'] ], $stage['forward']);
        $stage['done'] = true;
      }

      // // Now that we link events to projects using their short name as
      // // category, we also need to updated all linke events in case the
      // // short-name has changed.
      // $events = Events::events($pme->rec, $pme->dbh);

      // foreach ($events as $event) {
      //   // Last parameter "true" means to also perform string substitution
      //   // in the summary field of the event.
      //   Events::replaceCategory($event, $oldvals['name'], $newvals['name'], true);
      // }

      // // Now, we should also rename the project folder. We simply can
      // // pass $newvals and $oldvals
      // $this->renameProjectFolder($newProject, $project);

      // // Same for the Wiki
      // $this->renameProjectWikiPage($newProject, $project);

      // // Rename titles of all public project web pages
      // $this->nameProjectWebPages($projectId, $newName);

      $project['name'] = $newName;
      $project['year'] = $newYear;
      $this->persistProject($project);

      $this->entityManager->commit();

    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }

      foreach ($stages as $stage) {
        try {
          if ($stage['done']) {
            \call_user_func_array([ $this, $stage['method'] ], $stage['backwards']);
            $stage['done'] = false;
          }
        } catch (\Throwable $t2)  {
          $this->logException($t2);
        }
      }

      throw new \Exception(
        $this->l->t('Failed to rename project "%s", id "%d" to new name "%s".',
                    [ $project['name'], $project['id'], $newName ]),
        $t->getCode(),
        $t);
    }

    return $project;
  }


  /**
   * Extract the year from the project name if present.
   *
   * @param string $name
   *
   * @return array
   *
   * ```
   * [ 'name' => NAME_WITHOUT_YEAR, 'year' => NULL_OR_YEAR ]
   * ```
   */
  public function yearFromName($projectName)
  {
    $projectYear = substr($projectName, -4);
    if (preg_match('/^\d{4}$/', $projectYear) !== 1) {
      $projectYear = null;
    } else {
      $projectName = substr($projectName, 0, -4);
    }
    return [ 'name' => $projectName, 'year' => $projectYear ];
  }

  /**
   * Validate the name, no spaces, camel case, last four characters
   * may be digits of the form 20XX.
   *
   * @param string $projectName The name to validate.
   *
   * @param boolean $requireYear Year in last four characters is
   * mandatory.
   */
  public function sanitizeName($projectName, $requireYear = false)
  {
    list('name' => $projectName, 'year' => $projectYear) = $this->yearFromName($projectName);
    if ($requireYear && !$projectYear) {
      return false;
    }

    if ($projectName ==  strtoupper($projectName)) {
      $projectName = strtolower($projectName);
    }
    $projectName = ucwords($projectName);
    $projectName = preg_replace("/[^[:alnum:]]?[[:space:]]?/u", '', $projectName);

    if ($projectYear) {
      $projectName .= $projectYear;
    }
    return $projectName;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
