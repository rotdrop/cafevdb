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
<li class="context-menu-heading show-if-context-menu"
    data-operation="none"
>
  <a href="#">
    <span class="context-menu-title"><?php p($contextMenuTitle); ?></span>
  </a>
</li>
<li class="separator show-if-context-menu"><span class="rule"></span></li>
<?php if ($isDonation) { ?>
<li class=project-payment-action tooltip-auto"
    data-operation="donation-receipt"
    title="<?php echo $toolTips['project-payment-action:donation-receipt']; ?>"
>
  <a href="<?php p($routes['donation-receipt']); ?>">
    <?php p($l->t('Donation Receipt')); ?>
  </a>
</li>
<?php } elseif ($amount >= 0) { ?>
<li class=project-payment-action tooltip-auto"
    data-operation="standard-receipt"
    title="<?php echo $toolTips['project-payment-action:standard-receipt']; ?>"
>
  <a href="<?php p($routes['standard-receipt']); ?>">
    <?php p($l->t('Receipt')); ?>
  </a>
</li>
<?php } ?>
