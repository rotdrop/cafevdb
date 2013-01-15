<?php
use CAFEVDB\L;
use CAFEVDB\Config;
?>
<div id="blogedit">
  <form id="blogeditform" onsubmit="return false;">
    <textarea id="blogtextarea" rows="15" cols="66"></textarea>
    <br/>
    <input
      type="button"
      title="<?php echo Config::toolTips('blog-acceptentry'); ?>"
      value="<?php echo L::t('Submit'); ?>"
      id="blogsubmit"
    />
    <input
      type="button"
      title="<?php echo Config::toolTips('blog-cancelentry'); ?>"
      value="<?php echo L::t('Cancel'); ?>"
      id="blogcancel"
    />
    <?php if ($_['priority'] !== false) { ?>
    <input
      type="text"
      title="<?php echo Config::toolTips('blog-priority'); ?>"
      value="<?php echo $_['priority']; ?>"
      name="priority"
      id="blogpriority"
    />
    <?php } ?>
  </form>
</div>