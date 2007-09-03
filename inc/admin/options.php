<?php require_once(WPOTPL . '/helper/form.helper.php' ) ?>
<?php $this->adminHeader() ?>
      
  <?php if(isset($updated)): ?>    
  <div id="added-warning" class="updated"><p><?php _e('Options saved.', 'wpomatic') ?></p></div>
  <?php endif ?>
  
  <?php if(isset($not_writable)): ?>
    <div class="error"><p><?php _e('Image cache path ' . WPODIR . get_option('wpo_cachepath') . ' is not writable!' ) ?></p></div>
  <?php endif ?>
      
  <div class="wrap">
    <h2>Options</h2>     
    
    <form action="" method="post" accept-charset="utf-8">      
      <input type="hidden" name="update" value="1" />
      
      <ul id="options">
        <li>
          <?php echo label_for('option_logging', __('Enable logging', 'wpomatic')) ?>
          <?php echo checkbox_tag('option_logging', 1, get_option('wpo_log')) ?>
        
          <p class="note"><?php _e('Enable database-driven logging of events.', 'wpomatic') ?> <a href="<?php echo $this->helpurl ?>logging" class="help_link"><?php _e('More', 'wpomatic') ?></a></p>
        </li>
      
        <li>
          <?php echo label_for('option_caching', __('Cache images', 'wpomatic')) ?>
          <?php echo checkbox_tag('option_caching', 1, get_option('wpo_cacheimages')) ?>
        
          <p class="note"><?php _e('This option overrides all campaign-specific settings', 'wpomatic') ?> <a href="<?php echo $this->helpurl ?>image_caching" class="help_link"><?php _e('More', 'wpomatic') ?></a></p>
        </li>
        
        <li>
          <?php echo label_for('option_cachepath', __('Image cache path')) ?>
          <?php echo input_tag('option_cachepath', get_option('wpo_cachepath')) ?>           
          
          <p class="note"><?php printf(__('The path %s must exist, be writable by the server and accessible through browser.', 'wpomatic'), '<span id="cachepath">'. WPODIR . '<span id="cachepath_input">' . get_option('wpo_cachepath') . '</span></span>') ?></p>                 
        </li>
      </ul>     
    
      <?php echo submit_tag(__('Save', 'wpomatic')) ?>
    </form>
  </div>
  
<?php $this->adminFooter() ?>