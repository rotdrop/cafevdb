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
 */

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
         data-operation="participants"
         title="<?php echo $toolTips['project-action:project-participants']; ?>"
     >
                 'title' => $this->toolTipsService['project-action:project-instrumentation-numbers'],
                 'value' => 'project-instrumentation-numbers',
                 'name' => $this->l->t('Instrumentation Numbers') ],


       <a href="#">
         <img alt="" src="<?php echo $urlGenerator->imagePath('core', 'actions/group.svg'); ?>">
         <?php p($l->t('Participants')); ?>
       </a>
     </li>
   </ul>
  </nav>
</span>
