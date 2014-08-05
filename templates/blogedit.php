<?php
use CAFEVDB\L;
use CAFEVDB\Config;
?>
<div id="blogedit">
  <form id="blogeditform">
    <textarea class="<?php echo Config::$opts['editor'];?>" id="blogtextarea" rows="15" cols="66"></textarea>
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
    <?php if ($_['popup'] === false) { ?>
      <input
        type="checkbox"
        name="popupset"
        title="<?php echo Config::toolTips('blog-popup-set'); ?>"
        id="blogpopupset"
      />
      <label for="blogpopupset"
        title="<?php echo Config::toolTips('blog-popup-set'); ?>"><?php echo L::t('Set Blog Popup') ?></label>
    <?php } else { ?>
      <input
        type="checkbox"
        name="popupclear"
        title="<?php echo Config::toolTips('blog-popup-clear'); ?>"
        id="blogpopupclear"
      />
      <label for="blogpopupclear"
        title="<?php echo Config::toolTips('blog-popup-clear'); ?>"><?php echo L::t('Clear Blog Popup') ?></label>
    <?php } ?>      
      <input
        type="checkbox"
        name="readerclear"
        title="<?php echo Config::toolTips('blog-reader-clear'); ?>"
        id="blogreaderclear"
      />
      <label for="blogreaderclear"
        title="<?php echo Config::toolTips('blog-reader-clear'); ?>"><?php echo L::t('Clear Reader List') ?></label>
  </form>
</div>
