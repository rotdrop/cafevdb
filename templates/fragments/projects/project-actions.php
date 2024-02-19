<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024 Claus-Justus Heine
 * @license GNU AGPL version 3 or any later version
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
 * You should have received a copy of the GNU Affero General Public
 * License alogng with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @param int $projectId
 * @param string $projectName
 * @param \OCP\IURLGenerator $urlGenerator
 * @param \OCA\CAFEVDB\Service\ToolTipsService $toolTips
 * @param bool $isOverview
 * @param array $projectFolders
 * @param \OCA\CAFEVDB\Service\ProjectService $projectService
 * @param \OCA\CAFEVDB\Service\OrganizationalRolesService $rolesService
 * @param string $direction 'left' or 'right'
 * @param string $dropDirection 'up' or 'down'
 */

// provide URLs for all cases where the user may want to open this in another tab or window
$routes = [
  'project-files' => $urlGenerator->linkToRoute('files.view.index', [
    'dir' => $projectFolders['project'],
  ]),
  'financial-balance' => $urlGenerator->linkToRoute('files.view.index', [
    'dir' => $projectFolders['balance'],
  ]),
  'project-participants' => $urlGenerator->linkToRoute($appName . '.page.index', [
    'template' => 'project-participants',
    'projectId' => $projectId,
    'projectName' => $projectName,
  ]),
  'instrumentation-numbers' => $urlGenerator->linkToRoute($appName . '.page.index', [
    'template' => 'project-instrumentation-numbers',
    'projectId' => $projectId,
    'projectName' => $projectName,
  ]),
  'participant-fields' => $urlGenerator->linkToRoute($appName . '.page.index', [
    'template' => 'project-participant-fields',
    'projectId' => $projectId,
    'projectName' => $projectName,
  ]),
  'wiki' => $urlGenerator->linkToRoute('dokuwiki.page.index', [
    'wikiPage' => $wikiPage,
  ]),
  'sepa-bank-accounts' => $urlGenerator->linkToRoute($appName . '.page.index', [
    'template' => 'sepa-bank-accounts',
    'projectId' => $projectId,
    'projectName' => $projectName,
  ]),
  'project-payments' => $urlGenerator->linkToRoute($appName . '.page.index', [
    'template' => 'project-payments',
    'projectId' => $projectId,
    'projectName' => $projectName,
  ]),
];

/** @var \OCP\IURLGenerator $urlGenerator */
/** @var \OCA\CAFEVDB\Service\OrganizationalRolesService $rolesService */
echo $this->inc('fragments/action-menu/menu', [
  'cssClasses' => ['project-actions'],
  'menuData' => ['project-id' => $projectId, 'project-name' => $projectName],
  'toolTipPrefix' => 'project-actions',
  'menuItemTemplate' => 'fragments/projects/action-items',
  'routes' => $routes,
  'projectFolders' => $projectService->ensureProjectFolders($projectId, dry: true),
  'wikiPage' => $projectService->projectWikiLink($projectName),
  'wikiTitle' => $l->t('Project Wiki for %s', [ $projectName ]),
]);
