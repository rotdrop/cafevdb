<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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
?>
<li class="context-menu-heading context-menu dropdown-item dropdown-no-close"
    data-operation="none"
>
  <a href="#">
    <span class="context-menu-title"><?php p($contextMenuTitle); ?></span>
  </a>
</li>
<li class="separator context-menu dropdown-item dropdown-no-close"><span class="rule"></span></li>
<li class="project-payment-action dropdown-item tooltip-auto <?php !$isDonation && p('disabled'); ?>"
    data-operation="donation-receipt:download"
    title="<?php echo $toolTips['project-payment-action:donation-receipt:download']; ?>"
>
  <a href="<?php p($routes['donation-receipt:download']); ?>">
    <span class="menu-icon"></span>
    <?php p($l->t('Download Donation Receipt')); ?>
  </a>
</li>
<li class="project-payment-action dropdown-item tooltip-auto <?php (true || !$isDonation) && p('disabled'); ?>"
    data-operation="donation-receipt:email"
    title="<?php echo $toolTips['project-payment-action:donation-receipt:email']; ?>"
>
  <a href="<?php p($routes['donation-receipt:email']); ?>">
    <span class="menu-icon"></span>
    <?php p($l->t('Email Donation Receipt')); ?>
  </a>
</li>
<li class="project-payment-action dropdown-item tooltip-auto <?php $isDonation && p('disabled'); ?>"
    data-operation="standard-receipt:download"
    title="<?php echo $toolTips['project-payment-action:standard-receipt:download']; ?>"
>
  <a href="<?php p($routes['standard-receipt:download']); ?>">
    <span class="menu-icon"></span>
    <?php p($l->t('Download Standard Receipt')); ?>
  </a>
</li>
<li class="project-payment-action dropdown-item tooltip-auto <?php $isDonation && p('disabled'); ?>"
    data-operation="standard-receipt:email"
    title="<?php echo $toolTips['project-payment-action:standard-receipt:send']; ?>"
>
  <a href="<?php p($routes['standard-receipt:email']); ?>">
    <span class="menu-icon"></span>
    <?php p($l->t('Email Standard Receipt')); ?>
  </a>
</li>
<li class="project-payment-action dropdown-item tooltip-auto"
    data-operation="payment:download-data"
    title="<?php echo $toolTips['project-payment-action:payment:download-data']; ?>"
>
  <a href="<?php p($routes['payment:download-data'] ?? '#'); ?>">
    <span class="menu-icon"></span>
    <?php p($l->t('Download Substitution Data')); ?>
  </a>
</li>
