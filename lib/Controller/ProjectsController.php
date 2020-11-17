<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Projects as Renderer;

class ProjectsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var \OCA\CAFEVDB\Legacy\PME\PHPMyEdit */
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
          'Jahr' => $this->l->t("project-year"),
          'Name' => $this->l->t("project-name"),
          'Art'  => $this->l->t("project-kind"),
        ];
        foreach ($required as $key => $subject) {
          if (empty($projectValues[$key])) {
            return self::grumble($this->l->t("The %s must not be empty.", [$subject]));
          }
        }
        $control = $this->parameterService->getParam('control', 'name');
        $projectId = $this->pme->getCGIRecordId();
        $projectName = $projectValues['Name'];
        $projectYear = $projectValues['Jahr'];
        $attachYear  = !empty($projectValues['Art']) && $projectValues['Art'] == 'temporary';

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
            $projectName = preg_replace('/\d+$/', "", $projectName);
            if ($attachYear) {
              $origName     = $projectName;
              $projectName .= $projectYear;
              $infoMessage .= $this->l->t("The year %s has been appended to the project-slug %s.", [ $projectYear, $origName ]);
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
            return self::grumble($this->l->t("A project with the name ``%s'' already exists in the year %s. "
                                             . "Please choose a different name or year.",
                                             [ $projectName, $projectYear ]));
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

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
