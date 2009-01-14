<?php $this->adminHeader() ?>

  <form action="<?php echo $this->adminurl ?>&amp;s=setup" method="post">    
    <?php wp_nonce_field('setup') ?>    
          
    <div id="wpo-section-setup" class="wrap">
      <h2><?php _e('Setup', 'wpomatic') ?></h2>     
    
      <p><?php _e('Please follow the next few steps to make sure WP-o-Matic works perfectly for you.', 'wpomatic') ?></p>
    
      <ol id="setup_steps">
        <li id="step_1" class="step step_current">
          <p><?php _e('First of all, make sure <a href="http://simplepie.org" target="_blank">SimplePie</a>, the feed parsing engine that empowers WP-o-Matic is compatible with your server setup.', 'wpomatic') ?></p>          
          <p><?php printf(__('To do so, please run <a href="%s" target="_blank">this test</a> and evaluate the results. Typically, <em>You have everything you need to run SimplePie properly! Congratulations!</em> is a result you\'d be happy with.', 'wpomatic'), $this->pluginpath . '/inc/simplepie/simplepie.tests.php') ?></p>
          <p><?php _e('Even though WP-o-Matic is bundled with the latest SimplePie version at the time it was released, we encourage you to install the <a href="http://wordpress.org/extend/plugins/simplepie-core/">SimplePie Core Wordpress</a> plugin. It is automatically used in place of the bundled version, and it allows you to update SimplePie easily for all the plugins that use it.', 'wpomatic') ?></p>
        </li>
      
        <li class="step" id="step_2">
          <p><?php _e('Timing is a key aspect of this type of feed aggregating software.', 'wpomatic') ?></p>
          <p><?php printf(__('For WP-o-Matic to work properly, you have to make sure server time is accurate, and that the correct timezone is configured <a href="%s">here</a> (hint: <strong>Date and Time</strong> subsection)', 'wpomatic'), $this->optionsurl) ?></p>
          <p><?php _e('Make sure the following settings are correct:', 'wpomatic') ?></p>
          <div class="command">
             <strong><?php _e('UTC time:', 'wpomatic') ?></strong> <?php echo gmdate('d F, Y H:i:s', current_time('timestamp', true)) ?> <br />
             <strong><?php _e('Your time:', 'wpomatic') ?></strong> <?php echo gmdate('d F, Y H:i:s', current_time('timestamp')) ?>
          </div>
          <p><?php _e('Do not proceed unless time is configured properly.', 'wpomatic') ?></p>
        </li>
      
        <li class="step" id="step_3">
          <p><?php _e('Do you want to process campaigns in automated or manual mode?', 'wpomatic') ?></p>
          <ul class="radio_options">
            <li><input type="radio" name="mode" value="yes" id="option_mode_automated" checked="checked" /> <label for="option_mode_automated"><?php _e('Automated', 'wpomatic') ?></label></li> 
            <li><input type="radio" name="mode" value="no" id="option_mode_manual" /> <label for="option_mode_manual"><?php _e('Manual', 'wpomatic') ?></label></p></li>
          </ul>
          
          <div class="answer_mode" id="mode_automated">
            <p><?php _e('What type of automation are you going to use?', 'wpomatic') ?></p>
            <ul class="radio_options">
              <li><input type="radio" name="automated_mode" value="visitor" checked="checked" id="option_automated_mode_visit" /> <label for="option_automated_mode_visitor"><?php _e('Visitor', 'wpomatic') ?></label></p></li>
              <li><input type="radio" name="automated_mode" value="webcron" id="option_automated_mode_webcron" /> <label for="option_automated_mode_webcron"><?php _e('WebCron', 'wpomatic') ?></label></li>
              <li><input type="radio" name="automated_mode" value="cron" id="option_automated_mode_cron" /> <label for="option_automated_mode_cron"><?php _e('Cron', 'wpomatic') ?></label></li>
            </ul>
            
            <div class="answer_automated_mode answer_automated_mode_current" id="automated_mode_visitor">
              <p><?php _e('This method is available since WP-o-Matic 1.5. It works like a PseudoCron, but splits the process of fetching a Campaign to multiple visitors. With this method, if a campaign is ready to be processed, a visitor processes one feed, the next processes another one, till the campaign is complete. Unlike typical PseudoCron approach, the effect on user experience is negligible.', 'wpomatic') ?></p>
              <p><?php _e('Advantages:', 'wpomatic') ?></p>
              <ol>
                <li><?php _e('No custom setup or configuration required.', 'wpomatic') ?></li>
                <li><?php _e('Great if you don\'t have hundreds of feeds.', 'wpomatic') ?></li>
                <li><?php _e('Makes sure the same visitor doesnt\'t process more than one feed per session.', 'wpomatic') ?></li>
              </ol>
              
              <p><?php _e('Disadvantages:', 'wpomatic') ?></p>
              <ol>
                <li><?php _e('Naturally, it adds some extra loading time for specific visits.', 'wpomatic') ?></li>
              </ol>
            </div>
            
            <div class="answer_automated_mode" id="automated_mode_cron">
              <p><?php _e('This method uses the unix <a href="http://en.wikipedia.org/wiki/Cron">Cron</a> system.', 'wpomatic') ?></p>
              <p><?php _e('Advantages:', 'wpomatic') ?></p>
              <ol>
                <li><?php _e('Doesn\'t rely on blog visitors', 'wpomatic') ?></li>
                <li><?php _e('Processes your campaign all at once', 'wpomatic') ?></li>
                <li><?php _e('It\'s as reliable as your server is', 'wpomatic') ?></li>
              </ol>
              
              <p><?php _e('Disadvantages:', 'wpomatic') ?></p>
              <ol>
                <li><?php _e('Not supported by all hosting plans', 'wpomatic') ?></li>
                <li><?php _e('Some people struggle to set it up', 'wpomatic') ?></li>
              </ol>
            </div>
            
            <div class="answer_automated_mode" id="automated_mode_webcron">
              <p><?php _e('This method uses a Web Service that lets you schedule online tasks, such as accessing the WP-o-Matic link that processes campaigns. We recommend <a href="http://cronme.org/">CronMe</a> (free) and <a href="http://webcron.org">WebCron</a> (paid)', 'wpomatic') ?></p>
              
              <p><?php _e('Advantages:', 'wpomatic') ?></p>
              <ol>
                <li><?php _e('Doesn\'t rely on blog visitors', 'wpomatic') ?></li>
                <li><?php _e('Processes your campaign all at once', 'wpomatic') ?></li>
              </ol>
              
              <p><?php _e('Disadvantages:', 'wpomatic') ?></p>
              <ol>
                <li><?php _e('It\'s a 3rd party company that provides the service. They have shown to be working perfectly, but they can go out of business any day.', 'wpomatic') ?></li>
              </ol>
              
              <p><?php _e('This is the URL to supply to the service', 'wpomatic') ?></p>
              <div class="command"><?php echo $command ?></div>
            </div>
                                                
          </div>
          
          <div class="answer_mode" id="mode_manual">
            <p><?php _e('Fine! You\'ll have to click <strong>Fetch</strong> on those campaigns you want to process, or click <strong>Fetch all</strong> to process all of them at the same time. The latter, however, may take a lot of time!', 'wpomatic') ?></p>
          </div>
        
          <p><input type="checkbox" name="option_unixcron" checked="checked" id="option_unixcron" /> <label for="option_unixcron"><?php _e('I\'ll be using a cron job (for Unix-like cron or WebCron, uncheck if you want pseudo-cron functionality)', 'wpomatic') ?></label></p>
        </li>
      
        <?php if($safe_mode): ?>
        <li id="step_4">
          <p><?php _e('It appears that you\'re running Wordpress in a <strong>Safe Mode</strong> environment. If you\'re <strong>not going</strong> to use cron, or when you process feeds manually from your browser, you may experience problems with the execution time.', 'wpomatic') ?></p>
        
          <p><?php _e('PHP sets a limit (that you hosting provider can tweak) for execution time of scripts, except when running from command line. WP-o-Matic tries to override it, but is unable to do so when safe_mode directive is enabled, like in this case.', 'wpomatic') ?></p>
        
          <p><?php _e('The solution typically involves contacting your hosting support, or switching to a new host (d\'oh)', 'wpomatic') ?></p>
        </li>
        <?php endif ?>
      
        <li class="step" id="step_<?php echo ($safe_mode) ? 5 : 4 ?>">
          <p><?php _e('And you\'re done!', 'wpomatic') ?></p>
          <p><?php _e('Remember that these settings can be edited from the Options tab in the future.') ?></p>
          <p><strong><?php _e('Hit Submit to complete the installation.') ?></strong></p>
        </li>
      </ol>
    
      <div id="setup_buttons" class="submit">      
        <input id="setup_button_submit" class="disabled" type="submit" value="<?php _e('Submit', 'wpomatic') ?>" disabled="disabled" />      
        <input id="setup_button_previous" class="disabled" type="button" name="next" value="Previous" disabled="disabled" />
        <input id="setup_button_next" type="button" name="next" value="Next" /> <span id="current_indicator">1</span> / <?php echo ($safe_mode) ? 5 : 4 ?>
      </div>
    
    </div>
  </form>

<?php $this->adminFooter() ?>