<?php $this->adminHeader() ?>

  <script type="text/javascript" charset="utf-8">
    var WPOMATIC_TEXT_LOADING = '<?php _e('Loading, please wait.', 'wpomatic') ?>';
  </script>
  
  <div class="wrap">
    <h2>Campaigns</h2> 
  
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
          <tr id='campaign-<?php echo $campaign->id ?>' class='<?php echo $class ?> <?php if($_REQUEST['id'] == $campaign->id) echo 'highlight'; ?>'> 
            <th scope="row" style="text-align: center"><?php echo $campaign->id ?></th> 
            <td><?php echo attribute_escape($campaign->title) ?> <?php if($campaign->processing) _e('(processing)', 'wpomatic') ?></td>
            <td style="text-align: center"><?php echo _e($campaign->active ? 'Yes' : 'No', 'wpomatic') ?></td>
            <td style="text-align: center"><?php echo $campaign->count ?></td>        
            <td><?php echo $campaign->lastactive != '0000-00-00 00:00:00' ? WPOTools::timezoneMysql('F j, g:i a', $campaign->lastactive) : __('Never', 'wpomatic') ?></td>
            <td><a href="<?php echo $this->adminurl ?>&amp;s=edit&amp;id=<?php echo $campaign->id ?>" class='edit'>Edit</a></td> 
            <td><?php echo "<a class='fetch' rel='". $campaign->id ."' href='" . wp_nonce_url($this->adminurl . '&amp;s=fetch&amp;id=' . $campaign->id, 'fetch-campaign_' . $campaign->id) . "' class='edit' title='". __('Force fetch this campaign', 'wpomatic') ."' onclick=\"return confirm('". __('Are you sure you want to process all feeds from this campaign?', 'wpomatic') ."')\">" . __('Fetch', 'wpomatic') . "</a>"; ?></td>
            <td><?php echo "<a href='" . wp_nonce_url($this->adminurl . '&amp;s=reset&amp;id=' . $campaign->id, 'reset-campaign_' . $campaign->id) . "' class='delete' onclick=\"return confirm('". __('Are you sure you want to reset this campaign? Resetting does not affect already created wp posts.', 'wpomatic') ."')\">" . __('Reset', 'wpomatic') . "</a>"; ?></td>
            <td><?php echo "<a href='" . wp_nonce_url($this->adminurl . '&amp;s=delete&amp;id=' . $campaign->id, 'delete-campaign_' . $campaign->id) . "' class='delete' onclick=\"return confirm('" . __("You are about to delete the campaign '%s'. This action doesn't remove campaign generated wp posts.\n'OK' to delete, 'Cancel' to stop.") ."')\">" . __('Delete', 'wpomatic') . "</a>"; ?></td>            
          </tr>              
          <?php endforeach; ?>                    
        <?php endif; ?>
      </tbody>
    </table>
    
    <p><a href="<?php echo wp_nonce_url($this->adminurl . '&amp;s=fetch', 'fetch-campaigns') ?>" class="fetch button"><?php echo _e('Fetch all', 'wpomatic') ?></a> <a href="<?php echo wp_nonce_url($this->adminurl . '&amp;s=fetch', 'fetch-campaigns') ?>" class="fetch button"><?php echo _e('Fetch all (forced)', 'wpomatic') ?></a></p>
  </div>
  
<?php $this->adminFooter() ?>