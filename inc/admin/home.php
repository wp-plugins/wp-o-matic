<?php $this->adminHeader() ?>
        
  <div class="wrap">
    <h2><?php _e('Dashboard', 'wpomatic') ?></h2>                            
                                          
    <div id="sidebar">  
      <div id="sidebar_logging">                     
        <a href="<?php echo $this->helpurl ?>logging" class="help_link"><?php _e('Help', 'wpomatic') ?></a>                                                                        
        <h3>&rsaquo; <?php _e('Latest log entries', 'wpomatic') ?> <a href="<?php echo $this->adminurl ?>&amp;s=logs"><?php _e('(view all)', 'wpomatic')?></a></h3>
        <?php if(!$logs): ?>
        <p class="none"><?php echo _e('No actions to display', 'wpomatic') ?></p>
        <?php else: ?>
        <ul id="logs">
          <?php foreach($logs as $log): ?>                         
          <li><?php echo mysql2date('m/d h:i:s', $log->created_on) . ' &mdash; <strong>' . attribute_escape($log->message) ?></strong></li>
          <?php endforeach; ?>
        </ul>         
        <?php endif; ?>                                                                               

        <p id="log_status"><?php _e(sprintf('Logging is currently <strong>%s</strong>', __($logging ? 'enabled' : 'disabled')), 'wpomatic') ?> (<a title="<?php _e('We recommend keeping logging on only when experimenting with new feeds.', 'wpomatic') ?>" href="<?php echo $this->adminurl ?>&amp;s=options"><?php _e('change', 'wpomatic')?></a>).</p>
      </div>
      
      <?php if(get_option('wpo_unixcron')): ?>
      <div id="sidebar_cron"> 
        <a href="<?php echo $this->helpurl ?>cron" class="help_link"><?php _e('Help', 'wpomatic') ?></a>                                                                        
        <h3>&rsaquo; <?php _e('Cron configuration', 'wpomatic') ?></h3>
        <p><?php _e('Make sure this line is loaded in your crontab:', 'wpomatic') ?></p>
        <p id="command">
          <a href="#" id="command_select">Select</a>
          <input type="text" value="<?php echo $command ?>" id="command_input" />
        </p>
      </div>
      <?php endif ?>
    </div>  
    
    <div id="main">             

      <p><?php _e('Welcome to WP-o-Matic Dashboard! This is the place where you can quickly watch WP-o-Matic activity, and keep track of your top campaigns and feeds.', 'wpomatic') ?></p>       
    
      <h3>Next campaigns to process</h3>
      <?php if(count($nextcampaigns) == 0): ?>
      <p class="none"><?php _e('No campaigns to display', 'wpomatic') ?>
      <?php else: ?>
        <ol class="campaignlist">
          <?php foreach($nextcampaigns as $campaign): 
            $cl = (mysql2date('U', $campaign->lastactive) + $campaign->frequency) - current_time('timestamp');
            $cl = WPOTools::calcTime($cl, 0, 'd', false);
            
            $timestr = '';
            if($cl['days']) $timestr .= $cl['days'] . __('d', 'wpomatic') . ' ';
            if($cl['hours']) $timestr .= $cl['hours'] . __('h', 'wpomatic') . ' ';
            if($cl['minutes']) $timestr .= $cl['minutes'] . __('m', 'wpomatic') . ' ';      
            if($cl['seconds']) $timestr .= $cl['seconds'] . __('s', 'wpomatic');     
          ?>                         
          <li>
            <span class="details"><?php echo ($timestr) ? $timestr : __('Next!', 'wpomatic') ?></span>
            <a href="<?php echo $this->adminurl ?>&amp;s=list&amp;id=<?php echo $campaign->id ?>"><?php echo attribute_escape($campaign->title) ?></a></li>
          <?php endforeach; ?>  
        </ol>
      <?php endif; ?>
      
      <h3>Latest processed campaigns</h3>
      <?php if(count($lastcampaigns) == 0): ?>
      <p class="none"><?php _e('No campaigns to display', 'wpomatic') ?>
      <?php else: ?>
        <ol class="campaignlist">
          <?php foreach($lastcampaigns as $campaign): ?>                         
          <li>
            <span class="details"><?php echo mysql2date('m/d h:i', $campaign->lastactive) ?></span>
            <a href="<?php echo $this->adminurl ?>&amp;s=list&amp;id=<?php echo $campaign->id ?>"><?php echo attribute_escape($campaign->title) ?></a></li>
          <?php endforeach; ?>  
        </ol>
      <?php endif; ?>
    
      <h3><?php echo _e('Your top campaigns', 'wpomatic') ?></h3>
      <?php if(count($campaigns) == 0): ?>
      <p class="none"><?php _e('No campaigns to display', 'wpomatic') ?></p>
      <?php else: ?>
      <ol class="campaignlist">
        <?php foreach($campaigns as $campaign): ?>                         
        <li>
          <span class="details"><?php echo $campaign->count ?></span>
          <a href="<?php echo $this->adminurl ?>&amp;s=list&amp;id=<?php echo $campaign->id ?>"><?php echo attribute_escape($campaign->title) ?></a></li>
        <?php endforeach; ?>  
      </ol>       
      <?php endif; ?>
                
      <div id="versioncheck">
        <h3><?php _e('WP-o-Matic Version', 'wpomatic') ?></h3>
        <p><?php _e('Your WP-o-Matic version is...', 'wpomatic') ?> <strong id="version"><?php echo (isset($_COOKIE['wpo_version'])) ? $_COOKIE['wpo_version'] : '' ?></strong></p>
      </div>  
          
    </div>
  </div>
  
<?php $this->adminFooter() ?>