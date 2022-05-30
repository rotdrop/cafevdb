<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 */

$projectFolders = $projectService->ensureProjectFolders($projectId, $projectName, null, true);
$wikiPage = $projectService->projectWikiLink($projectName);
$wikiTitle = $l->t('Project Wiki for %s', [ $projectName ]);

?>
<span class="actions dropdown-container dropdown-no-hover"
      title="<?php echo $toolTips['project-actions']; ?>"
      data-project-id="<?php p($projectId); ?>"
      data-project-name="<?php p($projectName); ?>"
>
  <button class="menu-title action-menu-toggle">...</button>
  <nav class="dropdown-content dropdown-align-right">
   <ul>
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
         data-operation="participants"
         title="<?php echo $toolTips['project-action:project-participants']; ?>"
     >
       <a href="#">
         <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/group.svg'); ?>">
         <?php p($l->t('Participants')); ?>
       </a>
     </li>
     <li class="project-action project-instrumentation-numbers tooltip-auto"
         data-operation="instrumentation-numbers"
         title="<?php echo $toolTips['project-action:project-instrumentation-numbers']; ?>"
     >
       <a href="#">
         <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/quota.svg'); ?>">
         <?php p($l->t('Instrumentation Numbers')); ?>
       </a>
     </li>
     <li class="project-action project-participant-fields tooltip-auto"
         data-operation="instrumentation-numbers"
         title="<?php echo $toolTips['project-action:participant-fields']; ?>"
     >
       <a href="#">
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
       <a href="#">
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
       <a href="#">
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
     <li class="separator"><span class="rule"></span></li>
     <li class="project-action project-sepa-bank-accounts tooltip-auto"
         data-operation="sepa-bank-accounts"
         title="<?php echo $toolTips['project-action:sepa-bank-accounts']; ?>"
     >
       <a href="#">
         <?php p($l->t('Debit Mandates')); ?>
       </a>
     </li>
     <li class="project-action project-payments tooltip-auto"
         data-operation="payments"
         title="<?php echo $toolTips['project-action:payments']; ?>"
     >
       <a href="#">
         <?php p($l->t('Payments')); ?>
       </a>
     </li>
     <li class="project-action project-financial-balance tooltip-auto"
         data-operation="email"
         title="<?php echo $toolTips['project-action:financial-balance']; ?>"
     >
       <a href="#">
         <?php p($l->t('Financial Balance')); ?>
       </a>
     </li>
   </ul>
  </nav>
</span>
