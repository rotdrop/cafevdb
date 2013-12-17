<?php
use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Blog;
use CAFEVDB\Navigation;
use CAFEVDB\Util;

$css_pfx = 'cafevdb-page';
$hdr_vis = $_['headervisibility'];

$nav = '';
$nav .= Navigation::button('projects');
$nav .= Navigation::button('all');
$nav .= Navigation::button('projectinstruments');
$nav .= Navigation::button('instruments');

$header = ''
  .'<div class="'.$css_pfx.'-blog" id="'.$css_pfx.'-blog-header">
'.L::t('Camerata DB meets web 2.0 - the data-base operations can be accessed
through the respective navigation buttons at the top of the page. The
page below may be used as a bulletin-board. Configuration options are
accessible through the gear- and pencil-shaped buttons in the
bottom-left and top-right edge of the screen')
  .'</div>
';


$blog = Blog::fetchThreadDisplay();

echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'header' => $header));
?>

<div id="blogframe">
  <form id="blogform" method="post">
    <input type="hidden" name="app" value="<?php echo Config::APP_NAME; ?>" />
    <input type="hidden" name="headervisibility" value="<?php echo $hdr_vis; ?>" />
    <input
      type="submit"
      title="<?php echo Config::toolTips('blog-newentry');?>"
      value="<?php echo L::t('New note'); ?>"
      id="blognewentry"
    />
  </form>

<?php

if ($blog['status'] == 'error') {
  echo '<div class="statusmessage">'.$blog['data'].'</div>';
} else {
  $blog = $blog['data'];
  echo '<ul id="bloglist" class="bloglist">'."\n";
  $level = 0;
  $savedblog = array();
  while (!empty($blog)) {
    $entry    = array_shift($blog);
    $msg      = $entry['head'];
    $id       = $msg['id'];
    $author   = $msg['author'];
    $created  = $msg['created'];
    $created  = Util::strftime('%x, %H:%M', $created, $_['timezone'], $_['locale']);
    $editor   = $msg['editor'];
    $modified = $msg['modified'];
    $priority = $msg['priority'];
    $popup    = $msg['popup'] != 0;
    $reader   = $msg['reader'];
    $re = '/(^|[,])+'.$_['user'].'($|[,])+/';
    if (preg_match($re, $reader) === 1) {
      $popup = false;
    }
    $deleted  = $msg['deleted'];
    $reply    = $msg['inreplyto'];
    $avatar   = \OCP\Util::linkToRoute('core_avatar_get',
                                       array("user" => $author, "size" => 64));
    $avatar  .= "?requesttoken=".\OCP\Util::callRegister();
    $imgtitle = L::t("Avatar pictures can be uploaded through the personal settings page.");
    $imgtitle = 'title="'.$imgtitle.'" ';
    if ($deleted > 0) {
      $deleted = Util::strftime('%x, %H:%M', $deleted, $_['timezone'], $_['locale']);
      continue;
    }

    $prioritytext = '';
    if ($priority != 0) {
      $prioritytext = '<span class="blogentrypriority">'.L::t(', priority %d', array($priority)).'</span>';
    }

    $edittxt = '';
    if ($modified > 0) {
      $modified = Util::strftime('%x, %H:%M', $modified, $_['timezone'], $_['locale']);
      $edittxt = L::t(', latest change by `%s\', %s', array($editor,$modified));  
    }

    $text  = $msg['message'];
    echo '  <li class="blogentry level'.$level.'"><div class="blogentry level'.$level.'">
    <span class="photo"><img class="photo" src="'.$avatar.'" '.$imgtitle.'/></span>
    <span id="blogentryactions">
      <button class="blogbutton reply" id="blogreply'.$id.'" name="blogreply'.$id.'" value="'.$id.'" title="'.Config::toolTips('blogentry-reply').'">
        <img class="png blogbutton reply" src="'.\OCP\Util::imagePath('cafevdb', 'reply.png').'" alt="'.L::t('Reply').'"/>
      </button>
      <button class="blogbutton edit" id="blogedit'.$id.'" name="blogedit'.$id.'" value="'.$id.'" title="'.Config::toolTips('blogentry-edit').'">
        <img class="png blogbutton edit" src="'.\OCP\Util::imagePath('cafevdb', 'edit.png').'" alt="'.L::t('Edit').'"/>
      </button>
      '.($reply >= 0 || $priority == 0 ? '<!-- ' : '').'
      <input type="hidden" id="blogpriority'.$id.'" name="blogpriority'.$id.'" value="'.$priority.'" />
      <button class="blogbutton raise" id="blograise'.$id.'" name="blograise'.$id.'" value="'.$id.'" title="'.Config::toolTips('blogentry-raise').'">
        <img class="svg blogbutton raise" src="'.\OCP\Util::imagePath('cafevdb', 'up.svg').'" alt="'.L::t('Raise priority').'"/>
      </button>
      <button class="blogbutton lower" id="bloglower'.$id.'" name="bloglower'.$id.'" value="'.$id.'" title="'.Config::toolTips('blogentry-lower').'">
        <img class="svg blogbutton lower" src="'.\OCP\Util::imagePath('cafevdb', 'down.svg').'" alt="'.L::t('Lower priority').'"/>
      </button>
      '.($reply >= 0 || $priority == 0 ? ' -->' : '').'
      <button class="blogbutton delete" id="blogdelete'.$id.'" name="blogdelete'.$id.'" value="'.$id.'" title="'.Config::toolTips('blogentry-delete').'">
        <img class="png blogbutton delete" src="'.\OCP\Util::imagePath('cafevdb', 'delete.png').'" alt="'.L::t('Delete').'"/>
      </button>
    </span> 
    <span class="blogentrycenter">
      <span class="blogentrytitle">'.$author.' -- '.$created.$prioritytext.$edittxt.'</span><br/>
      <span class="blogentrytext">'.$text.'</span>
    </span>'.
    ($popup === false ? '' : '
      <div class="blogentrypopup bloglist" id="blogentrypopup'.$id.'" style="display:none;">
	<input type="hidden" class="blogentrypopupid" value="'.$id.'"/>
        <span class="photo"><img class="photo" src="'.MYSELF\Export::photo($author).'" '.$imgtitle.'/></span>
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
}

?>

</div>

<?php
// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

