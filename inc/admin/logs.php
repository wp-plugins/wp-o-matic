<?php $this->adminHeader() ?>

  <div class="wrap">
    <h2><?php _e('Logs', 'wpomatic') ?></h2>     
    
    <div class="logs_bar">
      <form action="">
        <?php wp_nonce_field('clean-logs') ?>
        
        <input type="hidden" name="page" value="wpomatic.php" />
        <input type="hidden" name="s" value="logs" />
        <input type="submit" name="clean_logs" id="clean_logs" value="Clean logs" />
      </form>
    
      <div id="logs_pages">
        <?php echo $paging ?>
      </div>
    </div>
    
    <?php if($logs): ?>
    <ul id="logs">
    <?php foreach($logs as $log): ?>
      <li><?php echo $log->date ?> - <?php echo $log->message ?></li>
    <?php endforeach ?>
    </ul>
    <?php else: ?>
    <p><?php _e('No logs to show', 'wpomatic') ?>
    <?php endif ?>
    
  </div>

<?php $this->adminFooter() ?>