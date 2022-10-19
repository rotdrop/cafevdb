<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Controller\MailingListsController;

/**
 * @param string $mailingListActionName The name of the radio control
 */

?>

<input class="radio"
       id="mailing-list-action-invite"
       type="radio" value="invite"
       name="<?php echo $mailingListActionName; ?>"
       checked
/>
<label for="mailing-list-action-invite"><?php p($l->t('invite')); ?></label>
<input class="radio"
       type="radio"
       id="mailing-list-action-subscribe"
       value="subscribe"
       name="<?php echo $mailingListActionName; ?>"
/>
<label for="mailing-list-action-subscribe"><?php p($l->t('subscribe')); ?></label>
<input class="radio"
       type="radio"
       id="mailing-list-action-noop"
       value="subscribe"
       name="<?php echo $mailingListActionName; ?>"
/>
<label for="mailing-list-action-noop"><?php p($l->t('no action')); ?></label>
