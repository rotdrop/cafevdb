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

if (!is_array($blog[0])) {
  echo '<div class="statusmessage">'.$blog[0].'</div>';
} else {
  echo '<ul id="bloglist">'."\n";
  $level = 0;
  $savedblog = array();
  while (!empty($blog)) {
    $entry    = array_shift($blog);
    $msg      = $entry['head'];
    $id       = $msg['id'];
    $author   = $msg['author'];
    $created  = $msg['created'];
    $created  = Util::strftime('%x, %H:%M', $created, $_['locale']);
    $editor   = $msg['editor'];
    $modified = $msg['modified'];
    $sticky   = $msg['sticky'];
    $deleted  = $msg['deleted'];

    if ($deleted > 0) {
      $deleted = Util::strftime('%x, %H:%M', $deleted, $_['locale']);
      continue;
    }

    $edittxt = '';
    if ($modified > 0) {
      $modified = Util::strftime('%x, %H:%M', $modified, $_['locale']);
      $edittxt = L::t('(latest change by `%s\', %s)', array($editor,$modified));  
    }

    $stickytext = '';
    if ($sticky == 1) {
      $stickytext = '<span class="blogentrysticky">sticky</span>';
    }

    $text  = $msg['message'];
    echo '  <li class="blogentry level'.$level.'"><div class="blogentry level'.$level.'">
    <span class="photo"><img class="photo" src="'.Blog::fetchPhoto($author).'" /></span>
    <span id="blogentryactions">
      <button class="blogbutton reply" id="blogreply'.$id.'" name="blogreply'.$id.'" value="'.$id.'" title="'.Config::toolTips('blogentry-reply').'">
        <img class="png blogbutton reply" src="'.\OCP\Util::imagePath('cafevdb', 'reply.png').'" alt="'.L::t('Reply').'"/>
      </button>
      <button class="blogbutton edit" id="blogedit'.$id.'" name="blogedit'.$id.'" value="'.$id.'" title="'.Config::toolTips('blogentry-edit').'">
        <img class="png blogbutton edit" src="'.\OCP\Util::imagePath('cafevdb', 'edit.png').'" alt="'.L::t('Edit').'"/>
      </button>
      <button class="blogbutton sticky" id="blogsticky'.$id.'" name="blogsticky'.($sticky == 1 ? 'off' : 'on').'" value="'.$id.'" title="'.Config::toolTips('blogentry-sticky').'">
        <img class="png blogbutton sticky" src="'.\OCP\Util::imagePath('cafevdb', 'sticky.png').'" alt="'.L::t('Sticky').'"/>
      </button>
      <button class="blogbutton delete" id="blogdelete'.$id.'" name="blogdelete'.$id.'" value="'.$id.'" title="'.Config::toolTips('blogentry-delete').'">
        <img class="png blogbutton delete" src="'.\OCP\Util::imagePath('cafevdb', 'delete.png').'" alt="'.L::t('Delete').'"/>
      </button>
    </span> 
    <span class="blogentrycenter">
      <span class="blogentrytitle">'.$author.' -- '.$created.' '.$stickytext.' '.$edittxt.'</span><br/>
      <span class="blogentrytext">'.$text.'</span>
    </span>
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

