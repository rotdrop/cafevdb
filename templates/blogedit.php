<?php
use CAFEVDB\L;
use CAFEVDB\Config;
?>
<div id="blogedit">
  <form id="blogeditform">
    <textarea id="blogtextarea" rows="15" cols="66"></textarea>
    <br/>
    <input
      type="button"
      title="<?php echo Config::toolTips('blog-acceptentry');?>"
      value="<?php echo L::t('Submit'); ?>"
      id="blogsubmit"
    />
    <input
      type="button"
      title="<?php echo Config::toolTips('blog-cancelentry');?>"
      value="<?php echo L::t('Cancel'); ?>"
      id="blogcancel"
    />
  </form>
</div>