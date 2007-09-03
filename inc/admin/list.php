<?php $this->adminHeader() ?>
  
  <?php if(isset($this->forcefetched)): ?>
  <div id="fetched-warning" class="updated">
    <p><?php printf(__("Campaign processed. %s posts fetched", 'wpomatic'), $this->forcefetched) ?></p>
  </div>
  <?php endif; ?>
  
  <div class="wrap">
    <h2>Campaigns</h2> 
  
    <form name="searchform" id="searchform" action="" method="get">
      <fieldset>                                                             
        <input type="hidden" name="page" value="wpomatic.php" />
        <input type="hidden" name="s" value="list" />
        
        <legend><?php _e('Search Campaigns&hellip;', 'wpomatic') ?></legend> 
        <input type="text" name="q" value="<?php if (isset($q)) echo attribute_escape(stripslashes($q)) ?>" size="17" /> 
        <input type="submit" name="submit" value="<?php _e('Search') ?>" class="button" />   
      </fieldset>
    </form>
    
    <br style="clear:both;" />
    
    <table class="widefat"> 
      <thead>
        <tr>
          <th scope="col" style="text-align: center">ID</th>
          <th scope="col"><?php _e('Title', 'wpomatic') ?></th>
          <th style="text-align: center" scope="col"><?php _e('Active', 'wpomatic') ?></th>
      	  <th style="text-align: center" scope="col"><?php _e('Total posts', 'wpomatic') ?></th>
      	  <th scope="col"><?php _e('Last active', 'wpomatic') ?></th>
      	  <th scope="col" colspan="4" style="text-align: center"><?php _e('Actions', 'wpomatic') ?></th>
        </tr>
      </thead>
      
      <tbody id="the-list">            
        <?php if(!$campaigns): ?>
          <tr> 
            <td colspan="5"><?php _e('No campaigns to display', 'wpomatic') ?></td> 
          </tr>  
        <?php else: ?>     
          <?php $class = ''; ?>  
          
          <?php foreach($campaigns as $campaign): ?>
          <?php $class = ('alternate' == $class) ? '' : 'alternate'; ?>             
          <tr id='campaign-<?php echo $campaign->id ?>' class='<?php echo $class ?>'> 
            <th scope="row" style="text-align: center"><?php echo $campaign->id ?></th> 
            <td><?php echo attribute_escape($campaign->title) ?></td>          
            <td style="text-align: center"><?php echo _e($campaign->active ? 'Yes' : 'No', 'wpomatic') ?></td>
            <td style="text-align: center"><?php echo $campaign->count ?></td>                  
            <td><?php echo $campaign->lastactive != '0000-00-00 00:00:00' ? $campaign->lastactive : __('Never', 'wpomatic') ?></td>
            <td><a href="<?php echo $this->adminurl ?>&amp;s=edit&amp;id=<?php echo $campaign->id ?>" class='edit'>Edit</a></td> 
            <td><?php echo "<a href='" . wp_nonce_url($this->adminurl . '&amp;s=forcefetch&amp;id=' . $campaign->id, 'forcefetch-campaign_' . $campaign->id) . "' class='edit' onclick=\"return confirm('". __('Are you sure you want to process all feeds from this campaign?', 'wpomatic') ."')\">" . __('Fetch', 'wpomatic') . "</a>"; ?></td>
            <td><?php echo "<a href='" . wp_nonce_url($this->adminurl . '&amp;s=reset&amp;id=' . $campaign->id, 'reset-campaign_' . $campaign->id) . "' class='delete' onclick=\"return confirm('". __('Are you sure you want to reset this campaign? Resetting does not affect already created wp posts.', 'wpomatic') ."')\">" . __('Reset', 'wpomatic') . "</a>"; ?></td>
            <td><?php echo "<a href='" . wp_nonce_url($this->adminurl . '&amp;s=delete&amp;id=' . $campaign->id, 'delete-campaign_' . $campaign->id) . "' class='delete' onclick=\"return deleteSomething( 'campaign', " . $campaign->id . ", '" . js_escape(sprintf(__("You are about to delete the campaign '%s'. This action doesn't remove campaign generated wp posts.\n'OK' to delete, 'Cancel' to stop."), attribute_escape($campaign->title))) . "' );\">" . __('Delete', 'wpomatic') . "</a>"; ?></td>            
          </tr>              
          <?php endforeach; ?>                    
        <?php endif; ?>
      </tbody>
    </table>
    
    <div id="ajax-response"></div>
    
  </div>
  
<?php $this->adminFooter() ?>