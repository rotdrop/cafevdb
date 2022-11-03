<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

$projectFolders = $projectService->ensureProjectFolders($projectId, dry: true);
$wikiPage = $projectService->projectWikiLink($projectName);
$wikiTitle = $l->t('Project Wiki for %s', [ $projectName ]);

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
  'wiki' => $urlGenerator->linkToRoute('dokuwikiembedded.page.index', [
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

?>
<span class="actions project-actions dropdown-container dropdown-no-hover tooltip-right"
      data-project-id="<?php p($projectId); ?>"
      data-project-name="<?php p($projectName); ?>"
>
  <button class="menu-title action-menu-toggle tooltip-auto"
          title="<?php echo $toolTips['project-actions']; ?>"
  >...</button>
  <nav class="dropdown-content dropdown-align-<?php p($direction); ?> dropdown-drop<?php p($dropDirection); ?>">
    <ul>
      <li class="project-name context-menu"
          data-operation="none"
      >
        <a href="#">
          <span class="context-menu-title"><?php p($projectName); ?></span>
        </a>
      </li>
      <li class="separator show-if-context-menu"><span class="rule"></span></li>
      <?php if ($isOverview) { ?>
        <li class="project-action project-infopage tooltip-auto"
            data-operation="infopage"
            title="<?php echo $toolTips['project-infopage']; ?>"
        >
          <a href="#">
            <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/info.svg'); ?>">
            <?php p($l->t('Project Overview')) ?>
          </a>
        </li>
        <li class="separator"><span class="rule"></span></li>
      <?php } ?>
      <li class="project-action project-participants tooltip-auto"
          data-operation="project-participants"
          title="<?php echo $toolTips['project-action:project-participants']; ?>"
      >
        <a href="<?php p($routes['project-participants']) ?>">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/group.svg'); ?>">
          <?php p($l->t('Participants')); ?>
        </a>
      </li>
      <li class="project-action project-instrumentation-numbers tooltip-auto"
          data-operation="instrumentation-numbers"
          title="<?php echo $toolTips['project-action:project-instrumentation-numbers']; ?>"
      >
        <a href="<?php p($routes['instrumentation-numbers']) ?>">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/quota.svg'); ?>">
          <?php p($l->t('Instrumentation Numbers')); ?>
        </a>
      </li>
      <li class="project-action project-participant-fields tooltip-auto"
          data-operation="participant-fields"
          title="<?php echo $toolTips['project-action:participant-fields']; ?>"
      >
        <a href="<?php p($routes['participant-fields']) ?>">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/edit.svg'); ?>">
          <?php p($l->t('Extra Member Data')); ?>
        </a>
      </li>
      <li class="separator"><span class="rule"></span></li>
      <li class="project-action project-files tooltip-auto"
          data-operation="files"
          data-project-files="<?php p($projectFolders['project']); ?>"
          title="<?php echo $toolTips['project-action:files']; ?>"
      >
        <a href="<?php p($routes['project-files']); ?>">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'categories/files.svg'); ?>">
          <?php p($l->t('Project Files')); ?>
        </a>
      </li>
      <li class="project-action project-wiki tooltip-auto"
          data-operation="wiki"
          title="<?php echo $toolTips['project-action:wiki']; ?>"
          data-wiki-page="<?php p($wikiPage); ?>"
          data-wiki-title="<?php p($wikiTitle); ?>"
      >
        <a href="<?php p($routes['wiki']); ?>">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/comment.svg'); ?>">
          <?php p($l->t('Project Notes')); ?>
        </a>
      </li>
      <li class="project-action project-events tooltip-auto"
          data-operation="events"
          title="<?php echo $toolTips['project-action:events']; ?>"
      >
        <a href="#">
          <img alt="" src="<?php echo $urlGenerator->imagePath($appName, 'time.svg'); ?>">
          <?php p($l->t('Events')); ?>
        </a>
      </li>
      <li class="project-action project-email tooltip-auto"
          data-operation="email"
          title="<?php echo $toolTips['project-action:email']; ?>"
      >
        <a href="#">
          <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/mail.svg'); ?>">
          <?php p($l->t('Em@il')); ?>
        </a>
      </li>
      <?php if ($rolesService->inTreasurerGroup()) { ?>
        <li class="separator"><span class="rule"></span></li>
        <li class="project-action project-sepa-bank-accounts tooltip-auto"
            data-operation="sepa-bank-accounts"
            title="<?php echo $toolTips['project-action:sepa-bank-accounts']; ?>"
        >
          <a href="<?php p($routes['sepa-bank-accounts']); ?>">
            <img alt="" src="<?php echo $urlGenerator->imagePath($appName, 'bank-transfer.svg'); ?>">
            <?php p($l->t('Debit Mandates')); ?>
          </a>
        </li>
        <li class="project-action project-payments tooltip-auto"
            data-operation="project-payments"
            title="<?php echo $toolTips['project-action:payments']; ?>"
        >
          <a href="<?php p($routes['project-payments']); ?>">
            <span class="menu-icon"><?php p($currencySymbol); ?></span>
            <?php p($l->t('Payments')); ?>
          </a>
        </li>
        <li class="project-action project-financial-balance tooltip-auto"
            data-operation="financial-balance"
            data-project-files="<?php p($projectFolders['balance']); ?>"
            title="<?php echo $toolTips['project-action:financial-balance']; ?>"
        >
          <a href="<?php p($routes['financial-balance']); ?>">
            <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'categories/files.svg'); ?>">
            <?php p($l->t('Financial Balance')); ?>
          </a>
        </li>
      <?php } ?>
    </ul>
  </nav>
</span>
