<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository;
use OCA\CAFEVDB\Storage\UserStorage;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo4Embedded\Service\RPC as WebPagesRPC;

/**
 * General support service, kind of inconsequent glue between
 * Doctrine\ORM and CAFEVDB\PageRenderer.
 */
class ProjectService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const DBTABLE = 'Projects';

  /** @var EntityManager */
  protected $entityManager;

  /** @var ProjectsRepository */
  private $repository;

  /** @var OCA\CAFEVDB\Storage\UserStorage */
  private $userStorage;

  /** @var OCA\DokuWikiEmedded\Service\AuthDokuWiki */
  private $wikiRPC;

  /** @var OCA\Redaxo4Embedded\Service\RPC */
  private $webPagesRPC;

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
    //$this->logInfo(print_r($this->webPagesRPC->getCategories(), true));
    $this->repository = $this->getDatabaseRepository(Entities\Project::class);
    $this->l = $this->l10n();
  }

  /**Generate an option table with all participants, suitable to be
   * staffed into Navigation::selectOptions(). This is a single
   * select, only one musician may be preselected. The key is the
   * musician id. The options are meant for a single-choice select box.
   *
   * @param $projectId The id of the project to fetch the musician options from
   *
   * @param $projectName Optional project name, will be queried from
   * DB if not specified.
   *
   * @param $musicianId A pre-selected musician, defaults to null.
   */
  public function participantOptions($projectId, $projectName = null, $musicianId = -1)
  {
    if (empty($projectName)) {
      $projectName = $this->fetchName($projectId);
    }

    $table = $projectName.'View';

    $options = [];

    // simply fetch all participants
    $query = "SELECT Name,Vorname,MusikerId FROM ".$table." WHERE 1";
    $stmt = $this->entityManager->getConnction()->query($query);
    while ($row = $stmt->fetch()) {
      $key = $row['MusikerId'];
      $name = $row['Vorname'].' '.$row['Name'];
      $flags = ($key == $musicianId) ? Navigation::SELECTED : 0;
      $options[] = [ 'value' => $key,
                     'name' => $name,
                     'flags' => $flags ];
    }

    return $options;
  }

  /** Fetch the project-name name corresponding to $projectId.
   */
  public function fetchName($projectId)
  {
    $project = $this->find($projectid);
    if ($project == null) {
      return null;
    }
    return $project->getName();
  }

  public function fetchAll() {
    return $this->findAll();
  }

  /**
   * Check for the existence of the project folders. Returns an array
   * of folders (balance and general files).
   *
   * @param int $projectId Id of the project.
   *
   * @param string $projectName Name of the project.
   *
   * @param bool|string If a string create only this folder.
   *
   * @return array Array of created folders.
   *
   */
  public function ensureProjectFolders($projectId, $projectName = false, $only = false)
  {
    $project = $this->repository->find($projectId);
    if (!$projectName) {
      $projectName = $project['Name'];
    } else if ($projectName != $project['Name']) {
      return false;
    }

    $sharedFolder   = $this->getConfigValue('sharedfolder');
    $projectsFolder = $this->getConfigValue('projectsfolder');
    $balanceFolder  = $this->getConfigValue('projectsbalancefolder');

    $paths = [ 'project' => '/'.$sharedFolder.'/'.$projectsFolder,
               'balance' => '/'.$sharedFolder.'/'.$balanceFolder.'/'.$projectsFolder ];
    $returnPaths = [];
    foreach ($paths as $key => $path) {
      if ($only && $key != $only) {
        continue;
      }
      try {
        $this->userStorage->ensureFolder($path);
        $path .= "/".$project['Jahr'];
        $this->userStorage->ensureFolder($path);
        $path .= "/".$project['Name'];
        $this->userStorage->ensureFolder($path);
        $returnPaths[$key] = $path;
      } catch (\Throwable $t) {
        $this->logException($t);
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
    $sharedFolder   = $this->getConfigValue('sharedfolder');
    $projectsFolder = $this->getConfigValue('projectsfolder');
    $balanceFolder  = $this->getConfigValue('projectsbalancefolder');

    $prefixPath = [
      'project' => '/'.$sharedFolder.'/'.$projectsFolder.'/',
      'balance' => '/'.$sharedFolder.'/'.$balanceFolder.'/'.$projectsFolder."/",
    ];

    $userFolder = $this->rootFolder->getUserFolder($this->userID());

    $fileView = \OC\Files\Filesystem::getView();

    foreach($prefixPath as $key => $prefix) {

      $oldPath = $prefix.$oldProject['Jahr']."/".$oldProject['Name'];
      $this->userStorage->delete($oldPath);
    }

    return true;
  }

  /**
   * Remove the folders for the given projectgs
   *
   * @param Project|array $newProject Project entity or plain query result.
   *
   * @param Project|array $oldProject Project entity or plain query result.
   *
   * @return array Array with newly renamed or created folders.
   */
  public function renameProjectFolder($newProject, $oldProject)
  {
    $sharedFolder   = $this->getConfigValue('sharedfolder');
    $projectsFolder = $this->getConfigValue('projectsfolder');
    $balanceFolder  = $this->getConfigValue('projectsbalancefolder');

    $prefixPath = [
      'project' => '/'.$sharedFolder.'/'.$projectsFolder.'/',
      'balance' => '/'.$sharedFolder.'/'.$balanceFolder.'/'.$projectsFolder."/",
    ];

    $returnPaths = [];
    foreach ($prefixPath as $key => $prefix) {

      $oldPath = $prefix.$oldProject['Jahr']."/".$oldProject['Name'];
      $newPrefixPath = $prefix.$newProject['Jahr'];

      $newPath = $newPrefixPath.'/'.$newProject['Name'];

      if ($fileView->is_dir($oldPath)) {

        // If the year has changed it may be necessary to create a new
        // directory.
        $this->userStorage->ensureFolder($newPrefixPath);
        $this->userStorage->rename($oldPath, $newPath);

        $returnPaths[$key] = $newPath;
      } else {
        // Otherwise there is nothing to move; we simply create the new directory.
        $returnPaths = array_merge($returnPaths,
                                   $this->ensureProjectFolders($projectId, $projectName, $only = $key));
      }
    }

    return $returnPaths;
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
    $orchestra = $this->getConfigValue('streetAddressName=1', $orchestra);

    $projects = $this->findAll();

    $page = "====== ".($this->l->t('Projects of %s', [$orchestra]))."======\n\n";

    $year = -1;
    foreach ($projects as $project) {
      if ($project['Jahr'] != $year) {
        $year = $row['Jahr'];
        $page .= "\n==== ".$year."====\n";
      }
      $name = $row['Name'];

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

    $this->wikiRPC->putPage($pagename, $page,
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

  public function renameProjectWikiPage($newProject, $oldProject)
  {
    $oldName = $oldProject['Name'];
    $newName = $newProject['Name'];
    $oldPageName = $this->projectWikiLink($oldName);
    $newPageName = $this->projectWikiLink($newName);

    $oldPage = " *  ".$oldvals['Name']." wurde zu [[".$newPageName."]] umbenant\n";
    $newPage = $this->wikiRPC->getPage($oldPageName);
    if ($newPage) {
      // Geneate stuff if there is an old page
      $this->wikiRPC->putPage($oldPageName, $oldPage, [ "sum" => "Automatic CAFEVDB page renaming",
                                                   "minor" => false ]);
      $this->wikiRPC->putPage($newPageName, $newPage, [ "sum" => "Automatic CAFEVDB page renaming",
                                                   "minor" => false ]);
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
    $project = $this->find($projectId);
    if (empty($project)) {
      return false;
    }

    $articleIds = [];
    foreach ($project->getWebPages() as $idx => $article) {
      $articleIds[$article['ArticleId']] = $idx;
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
          $article['CategoryName'] = $category['name'];
          if (isset($articleIds[$article['ArticleId']])) {
            $projectPages[] = $article;
          } else {
            $otherPages[] = $article;
          }
          $this->logDebug("Projects: ".print_r($article, true));
        }
      }
    }
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
    $project = $this->find($projectId);
    if (empty($project)) {
      return false;
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
    $articles = $this->webPagesRPC->articlesByName($pageName.'(-[0-9]+)?', $category);
    if (!is_array($articles)) {
      return false;
    }

    $names = array();
    foreach ($articles as $article) {
      $names[] = $article['ArticleName'];
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

    $article = $this->webPagesRPC->addArticle($pageName, $category, $pageTemplate);

    if ($article === false) {
      $this->logError("Error generating web page template");
      return false;
    }

    // just forget about the rest, we can't help it anyway if the
    // names are not unique
    $article = $article[0];

    // insert into the db table to form the link
    if ($this->attachProjectWebPage($projectId, $article) === false) {
      $this->logError("Error attaching web page template");
      return false;
    }

    $this->webPagesRPC->addArticleBlock($article['ArticleId'], $module);

    return $article;
  }

  /**
   * Delete a web page. This is implemented by moving the page to the
   * Trashbin category, leaving the real cleanup to a human being.
   */
  public function deleteProjectWebPage($projectId, $articleId)
  {
    if ($this->detachProjectWebPage($projectId, $articleId) === false) {
      return false;
    }
    $trashCategory = $this->getConfigValue('redaxoTrashbin');
    $result = $this->webPagesRPC->moveArticle($articleId, $trashCategory);
    if ($result === false) {
      $this->logError("Failed moving ".$articleId." to ".$trashCategory);
    }
    return $result;
  }

  /**
   * Detach a web page, but do not delete it. Meant as utility routine
   * for the UI (in order to correct wrong associations).
   */
  public function detachProjectWebPage($projectId, $articleId)
  {
    try {
      $this->remove([ 'projectId' => $projectId, 'articleId' => $articleId  ]);
      $this->flush();
    } catch (\Throwable $t) {
      $this->logException($t);
      return false;
    }

    return true;
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
    if ($article['CategoryId'] == $trashCategory) {
      if (stristr($article['ArticleName'], $this->l->t('Rehearsals')) !== false) {
        $destinationCategory = $this->getConfigValue('redaxoRehearsals');
      } else {
        $destinationCategory = $this->getConfigValue('redaxoPreview');
      }
      $articleId = $article['ArticleId'];
      $result = $this->webPagesRPC->moveArticle($articleId, $destinationCategory);
      if ($result === false) {
        $this->logDebug("Failed moving ".$articleId." to ".$destinationCategory);
      } else {
        $artical['CategoryId'] = $destinationCategory;
      }
      // @TODO Shouldn't this be recorded in the data-base as well, as the
      // category has changed?
    }

    $webPagesRepository = $this->entityManager->getRepository(Entities\ProjectWebpage::class);
    try {
      $projectWebPage = $webPagesRepository->attachProjectWebPage($projectid, $articleId);
    } catch (\Throwable $t) {
      throw new \Exception("Unable to attach web-page ".$articleId." for ".$projectId);
    }
  }

  /**
   * Set the name of all registered web-pages to the canonical name,
   * project name given.
   */
  public function nameProjectWebPages($projectId, $projectName = null)
  {
    $project = $this->find($projectId);
    if (empty($project)) {
      return false;
    }
    if (empty($projectName)) {
      $projectName = $project->getName(); // @TODO check if already set
    }

    $webPages = $project->getWebPages();

    $rehearsalsName = $this->l->t('Rehearsals');
    $webPagesRepository = $this->entityManger->getRepository(Entities\ProjectWebpage::class);

    $concertNr = 0;
    $rehearsalNr = 0; // should stay at zero
    foreach ($webPages as $article) {
      if (stristr($article['ArticleName'], $rehearsalsName) !== false) {
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
      if ($this->webPagesRPC->setArticleName($article['ArticleId'], $newName)) {
        // if successful then also update the data-base entry
        $webPagesRepository->mergeAttributes(
          [ 'articleId' => $article['ArticleId'] ],
          [ 'articleName' => newName ]);
      }
    }

    return true;
  }

  /**
   * Search through the list of all projects and attach those with a
   * matching name. Something which should go to the "expert"
   * controls.
   */
  public function attachMatchingWebPages($projectId)
  {
    $project = $this->find($projectId);
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

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
