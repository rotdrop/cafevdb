<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB;

use Common\Util;

$blog = $_['renderer']->findThreadDisplay();

echo '<ul id="bloglist" class="bloglist">'."\n";
$level = 0;
$savedblog = [];
while (!empty($blog)) {
  $entry    = array_shift($blog);
  $msg      = $entry['head'];
  $id       = $msg->getId();
  $author   = $msg->getAuthor();
  $created  = $msg->getCreated();
  $created  = Util::strftime('%x, %H:%M', $created, $_['timezone'], $_['locale']);
  $editor   = $msg->getEditor();
  $modified = $msg->getModified();
  $priority = $msg->getPriority();
  $popup    = $msg->getPopup() != 0;
  $reader   = $msg->getReader();
  $re = '/(^|[,])+'.$_['user'].'($|[,])+/';
  if (preg_match($re, $reader) === 1) {
    $popup = false;
  }
  $deleted  = $msg->getDeleted();
  $reply    = $msg->getInReplyTo();
  $avatar   = $urlGenerator->linkToRoute('core.avatar.getAvatar',
                                         ["userId" => $author, "size" => 64]);
  $avatar  .= "?requesttoken=".$_['requesttoken'];
  $imgtitle = $l->t("Avatar pictures can be uploaded through the personal settings page.");
  $imgtitle = 'title="'.$imgtitle.'" ';
  if ($deleted > 0) {
    $deleted = Util::strftime('%x, %H:%M', $deleted, $_['timezone'], $_['locale']);
    continue;
  }

  $prioritytext = '';
  if ($priority != 0) {
    $prioritytext = '<span class="blogentrypriority">'.$l->t(', priority %d', [$priority]).'</span>';
  }

  $edittxt = '';
  if ($modified > 0) {
    $modified = Util::strftime('%x, %H:%M', $modified, $_['timezone'], $_['locale']);
    $edittxt = $l->t(', latest change by `%s\', %s', [$editor,$modified]);
  }

  $text  = $msg->getMessage();
  echo '  <li class="blogentry level'.$level.'"><div class="blogentry level'.$level.'">
    <!-- <span class="photo"><img class="photo" src="'.$avatar.'" '.$imgtitle.'/></span> -->
    <span class="avatar photo" data-author="'.$author.'" data-size="64"></span>
    <span id="blogentryactions">
      <button class="blogbutton reply" id="blogreply'.$id.'" name="blogreply'.$id.'" value="'.$id.'" title="'.$toolTips['blogentry-reply'].'">
        <img class="png blogbutton reply" src="'.$urlGenerator->imagePath('cafevdb', 'reply.png').'" alt="'.$l->t('Reply').'"/>
      </button>
      <button class="blogbutton edit" id="blogedit'.$id.'" name="blogedit'.$id.'" value="'.$id.'" title="'.$toolTips['blogentry-edit'].'">
        <img class="png blogbutton edit" src="'.$urlGenerator->imagePath('cafevdb', 'edit.png').'" alt="'.$l->t('Edit').'"/>
      </button>
      '.($reply >= 0 || $priority == 0 ? '<!-- ' : '').'
      <input type="hidden" id="blogpriority'.$id.'" name="blogpriority'.$id.'" value="'.$priority.'" />
      <button class="blogbutton raise" id="blograise'.$id.'" name="blograise'.$id.'" value="'.$id.'" title="'.$toolTips['blogentry-raise'].'">
        <img class="svg blogbutton raise" src="'.$urlGenerator->imagePath('cafevdb', 'up.svg').'" alt="'.$l->t('Raise priority').'"/>
      </button>
      <button class="blogbutton lower" id="bloglower'.$id.'" name="bloglower'.$id.'" value="'.$id.'" title="'.$toolTips['blogentry-lower'].'">
        <img class="svg blogbutton lower" src="'.$urlGenerator->imagePath('cafevdb', 'down.svg').'" alt="'.$l->t('Lower priority').'"/>
      </button>
      '.($reply >= 0 || $priority == 0 ? ' -->' : '').'
      <button class="blogbutton delete" id="blogdelete'.$id.'" name="blogdelete'.$id.'" value="'.$id.'" title="'.$toolTips['blogentry-delete'].'">
        <img class="png blogbutton delete" src="'.$urlGenerator->imagePath('cafevdb', 'delete.png').'" alt="'.$l->t('Delete').'"/>
      </button>
    </span>
    <span class="blogentrycenter">
      <span class="blogentrytitle">'.$author.' -- '.$created.$prioritytext.$edittxt.'</span><br/>
      <span class="blogentrytext">'.$text.'</span>
    </span>'.
                                         ($popup === false ? '' : '
      <div class="blogentrypopup bloglist" id="blogentrypopup'.$id.'" style="display:none;">
	<input type="hidden" class="blogentrypopupid" value="'.$id.'"/>
	<span class="avatar" data-user="' . \OCP\Util::sanitizeHTML($author) . '"></span>
        <span class="blogentrycenter">
          <span class="blogentrytitle">'.$author.' -- '.$created.$prioritytext.$edittxt.'</span><br/>
          <span class="blogentrytext">'.$text.'</span>
        </span>
      </div>').'
  </div></li>
';
  if (!empty($entry['children'])) {
    array_push($savedblog, $blog);
    $blog = $entry['children'];
    $level ++;
  } else while (empty($blog) && $level > 0) {
      $blog = array_pop($savedblog);
      $level --;
    }
}
echo "</ul>\n";
