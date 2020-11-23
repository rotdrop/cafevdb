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

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
    , UserStorage $userStorage
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->userStorage = $userStorage;
    $this->repository = $this->getDatabaseRepository(Entities\Project::class);
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
  public static function renameProjectFolder($newProject, $oldProject)
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
    $orchestra = $this>getConfigValue('orchestra');

    return $orchestra.":projects:".$pageName;
  }

  /** Generate an automated overview. Actually, the orchestra-title
   * should be made configurable.
   */
  public function generateWikiOverview($handle = false)
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

      $page .= "  * [[".self::projectWikiLink($name)."|".$bareName."]]\n";
    }

    $pageName = self::projectWikiLink('projects');

    // $wikiLocation = \OCP\Config::GetAppValue("dokuwikiembed", 'wikilocation', '');
    // $dwembed = new \DWEMBED\App($wikiLocation);
    // $dwembed->putPage($pagename, $page,
    //                   [ "sum" => "Automatic CAFEVDB synchronization",
    //                     "minor" => true ]);

  }

//     /**Generate an almost empty project page. This spares people the
//      * need to click on "new page".
//      *
//      * - We insert a proper title heading
//      *
//      * - We insert a sub-title "Contacts"
//      *
//      * - We insert a sub-title "Financial Arrangements"
//      *
//      * - We insert a sub-title "Location"
//      */
//     public static function generateProjectWikiPage($projectId, $projectName, $handle)
//     {
//       $orchestra = Config::$opts['orchestra']; // for the name-space

//       $page = L::t('====== Project %s ======

// ===== Forword =====

// This wiki-page is useful to store selected project related
// informations in comfortable and structured form. This can be useful
// for "permant information" like details about supplementary fees,
// contact informations and the like. In particular, this page could be
// helpful to reduce unnecessary data-digging in our email box.

// ===== Contacts =====
// Please add any relevant email and mail-adresses here. Please use the wiki-syntax
// * [[foobar@important.com|Mister Universe]]

// ===== Financial Arrangements =====
// Please add any special financial arrangements here. For example:
// single-room fees, double-roome fees. Please consider using an
// unordered list for this like so:
//   * single room fee: 3000€
//   * double room fee: 6000€
//   * supplemenrary fee for Cello-players: 1500€

// ===== Location =====
// Whatever.',
//                    array($projectName));

//       $pagename = self::projectWikiLink($projectName);

//       $wikiLocation = \OCP\Config::GetAppValue("dokuwikiembed", 'wikilocation', '');
//       $dwembed = new \DWEMBED\App($wikiLocation);
//       $dwembed->putPage($pagename, $page,
//                         array("sum" => "Automatic CAFEVDB synchronization",
//                               "minor" => true));

//     }

//   }; // class Projects

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
