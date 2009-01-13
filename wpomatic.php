<?php
/*
 * Plugin Name: WP-o-Matic
 * Description: Enables administrators to create posts automatically from RSS/Atom feeds.
 * Author: Guillermo Rauch
 * Plugin URI: http://devthought.com/wp-o-matic-the-wordpress-rss-agreggator/
 * Version: 1.0RC5
 * =======================================================================
 
 Homepage:    http://wpomatic.org
 Wiki:        http://wpomatic.org/wiki
 Changelogs:  http://wpomatic.org/wiki/changelogs
 Forums:      http://wpomatic.org/forums
 FAQ:         http://wpomatic.org/faq 
 Author:      http://devthought.com
 
 Changelog 1.0RC5:
  - Added IntelliCron (processing, processed fields to campaigns and feeds respectively)
  - processCampaign, processFeed now take as argument the type of process
  - No more $wpdb globals used
  - Added manual or automatic processing parameter for campaign processing function
  - isDuplicate now considers all posts in database
  - Added uninstall link
  - Added timezone fix
  - Optimized for Wordpress 2.7
  - Added multiple plugins hooks and filters
  
 TODO: 
  - move simple caching to a plugin. phpthumb plugin
  - add options.php/edit.php/list.php/logs.php/import.php/export.php template hooks
  - reset resets feeds processed status and campaign processing status
  - delete all deletes category assignements and related tables (look for wp delete function)
  - sql select only what we need
  - adminProcessCampaign when called from ajax outputs json with:
      - status: [total processed, total]
      - nexturl: url of next ajax request
      - message: status message
  - display in campaign list if the campaign is being processed, when it started, and if it has feeds being processed and when they started
  - fix feed url escaping
  
  For 1.0:
  - Plugins section, with url/upload installation
  - Change newadmin to wpversion, which should contain '25', '26', '27'

  Todo:
  - 'View campaign' view, with stats, thus getting rid of Tools tab
  - Bulk actions in campaign list
  - 'Time ago' for 'Last active' (on hover) in campaign list
  - More advanced post templates
  - Import drag and drop to current campaigns.
  - Export extended OPML to save WP-o-Matic options
  - Proper commenting
  - queue of pingbacks
  
  Future plugins:
   - Image thumbnailing
   - Favicon fetch
   - Limit number of characters
*/    
                         
# WP-o-Matic paths. With trailing slash.
define('WPODIR', dirname(__FILE__) . '/');                
define('WPOINC', WPODIR . 'inc/');   
define('WPOTPL', WPOINC . 'admin/');
    
# Dependencies                            
require_once(WPOINC . 'tools.class.php');
            
class WPOMatic {               
             
  # Internal
  var $version = '1.0RC5';   
                        
  var $sections = array('home', 'setup', 'list', 'add', 'edit', 'options', 'uninstall', 'import', 'export', 'reset', 'delete', 'logs', 'testfeed', 'fetch', 'processcampaign');  
                        
  var $campaign_structure = array('main' => array(), 'rewrites' => array(), 'categories' => array(), 'feeds' => array());
  
  # Singleton 
  function getInstance()
  {
    static $instance = null;
    if (null === $instance) {
      $instance = new WPOMatic();
    }
    return $instance;
  }  
  
  # __construct()
  function WPOMatic()
  {              
    global $wpdb, $wp_version;
    
    date_default_timezone_set(get_option('gmt_offset'));
                                   
    # Table names init
    $this->db = array(
      'campaign'            => $wpdb->prefix . 'wpo_campaign',
      'campaign_category'   => $wpdb->prefix . 'wpo_campaign_category',
      'campaign_feed'       => $wpdb->prefix . 'wpo_campaign_feed',     
      'campaign_word'       => $wpdb->prefix . 'wpo_campaign_word',   
      'campaign_post'       => $wpdb->prefix . 'wpo_campaign_post',
      'log'                 => $wpdb->prefix . 'wpo_log'
    );
    
    # We reference some useful WP variables here
    $this->wpdb = & $wpdb;
    $this->wpversion = $wp_version;
        
    # The branch contains the first two digits for easy version comparison (ie, 25 = 2.5.x, 26 = 2.6.x, 27 = 2.7.x)
    $this->wpbranch = (int) str_replace('.', '', substr($wp_version, 0, 3));
    
    # Is installed ?
    $this->installed = get_option('wpo_version') == $this->version;
    $this->setup = get_option('wpo_setup');
    
    # Actions
    add_action('init', array(&$this, 'init'));                                                # Wordpress init      
    add_action('admin_head', array(&$this, 'adminWPHeader'));                                 # Admin head
    add_action('admin_footer', array(&$this, 'adminWPFooter'));                               # Admin footer
    add_action('admin_menu', array(&$this, 'adminMenu'));                                     # Admin menu creation 
    add_action('admin_notices', array(&$this, 'adminWarning'));                               # Admin warnings
    register_activation_hook(__FILE__, array(&$this, 'activate'));                            # Plugin activated
    register_activation_hook(__FILE__, array(&$this, 'deactivate'));                          # Plugin deactivated
    if(function_exists('register_uninstall_hook'))
      register_uninstall_hook(__FILE__, array(&$this, 'uninstall'));        
   
    # Ajax actions
    add_action('wp_ajax_delete-campaign', array(&$this, 'adminDelete'));
    add_action('wp_ajax_test-feed', array(&$this, 'adminTestfeed'));
    add_action('wp_ajax_fetch', array(&$this, 'adminFetch'));
    
    # Filters
    add_filter('the_permalink', array(&$this, 'filterPermalink'));
    add_filter('plugin_action_links', array(&$this, 'filterPluginLinks'), 10, 2);
   
    # WP-o-Matic URIs. Without trailing slash               
    $this->optionsurl = get_option('siteurl') . '/wp-admin/options-general.php';                                           
    $this->adminurl = $this->optionsurl . '?page=wp-o-matic/wpomatic.php';
    $this->pluginpath = get_option('siteurl') . '/wp-content/plugins/wp-o-matic';           
    $this->helpurl = $this->pluginpath . '/help.php?item=';
    $this->tplpath = $this->pluginpath . '/inc/admin';
    $this->cachepath = WPODIR . get_option('wpo_cachepath');
    
    # Cron command / url
    $this->cron_url = $this->pluginpath . '/cron.php?code=' . get_option('wpo_croncode');
    $this->cron_command = attribute_escape('*/20 * * * * '. $this->getCommand() . ' ' . $this->cron_url);
    
    do_action('wpo_init');
  }
  
  /**
   * Called when plugin is activated 
   *
   *
   */ 
  function activate($force_install = false)
  {    
    if(file_exists(ABSPATH . '/wp-admin/upgrade-functions.php'))
      require_once(ABSPATH . '/wp-admin/upgrade-functions.php');
    else
      require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
                                                  
    # Options   
    WPOTools::addMissingOptions(array(
     'wpo_log'          => array(1, 'Log WP-o-Matic actions'),
     'wpo_log_stdout'   => array(0, 'Output logs to browser while a campaign is being processed'),
     'wpo_unixcron'     => array(WPOTools::isUnix(), 'Use unix-style cron'),
     'wpo_unixcron_max' => array(0, 'Maximum number of campaigns'),
     'wpo_croncode'     => array(substr(md5(time()), 0, 8), 'Cron job password.'),
     'wpo_cacheimages'  => array(0, 'Cache all images. Overrides campaign options'),
     'wpo_cachepath'    => array('cache', 'Cache path relative to wpomatic directory')
    ));
    
    // only re-install if new version or uninstalled
    if($force_install || ! $this->installed) 
    {
			# wpo_campaign
			dbDelta(  "CREATE TABLE {$this->db['campaign']} (
							    id int(11) unsigned NOT NULL auto_increment,
							    title varchar(255) NOT NULL default '', 
							    active tinyint(1) default '1', 
							    slug varchar(250) default '',         
							    template MEDIUMTEXT default '',         
  							  frequency int(5) default '180',
							    feeddate tinyint(1) default '0', 
							    cacheimages tinyint(1) default '1',
							    status enum('publish','draft','private') NOT NULL default 'publish',
							    authorid int(11) default NULL,                  
							    comment_status enum('open','closed','registered_only') NOT NULL default 'open',
							    allowpings tinyint(1) default '1',
							    dopingbacks tinyint(1) default '1',
							    max smallint(3) default '10',
							    max_feeds_process smallint(3) default '1',
							    linktosource tinyint(1) default '0',
							    count int(11) default '0',							    
							    processing tinyint(1) default '0',
							    lastactive datetime NOT NULL default '0000-00-00 00:00:00',	
							    created_on datetime NOT NULL default '0000-00-00 00:00:00',  							  
							    PRIMARY KEY (id)
						   );" ); 
		 
		 # wpo_campaign_category 			               
     dbDelta(  "CREATE TABLE {$this->db['campaign_category']} (
  						    id int(11) unsigned NOT NULL auto_increment,
  							  category_id int(11) NOT NULL,
  							  campaign_id int(11) NOT NULL,
  							  PRIMARY KEY  (id)
  						 );" );              
  	 
  	 # wpo_campaign_feed 				 
     dbDelta(  "CREATE TABLE {$this->db['campaign_feed']} (
  						    id int(11) unsigned NOT NULL auto_increment,
  							  campaign_id int(11) NOT NULL default '0',   
  							  url text NOT NULL default '',  
  							  type varchar(255) NOT NULL default '',    
  							  title varchar(255) NOT NULL default '',   
  							  description varchar(255) NOT NULL default '',
  							  logo varchar(255) default '',                         
  							  count int(11) default '0',
							    processed tinyint(1) default '0',
							    processing tinyint(1) default '0',
  							  hash varchar(255) default '',
  							  lastactive datetime NOT NULL default '0000-00-00 00:00:00',							    
  							  PRIMARY KEY  (id)
  						 );" );  
  						 
    # wpo_campaign_post				 
    dbDelta(  "CREATE TABLE {$this->db['campaign_post']} (
    				    id int(11) unsigned NOT NULL auto_increment,
    					  campaign_id int(11) NOT NULL,
    					  feed_id int(11) NOT NULL,
    					  post_id int(11) NOT NULL,					
						    hash varchar(255) default '',	    
    					  PRIMARY KEY  (id)
    				 );" ); 
  						 
  	 # wpo_campaign_word 				 
     dbDelta(  "CREATE TABLE {$this->db['campaign_word']} (
  						    id int(11) unsigned NOT NULL auto_increment,
  							  campaign_id int(11) NOT NULL,
  							  word varchar(255) NOT NULL default '',
							    regex tinyint(1) default '0',
  							  rewrite tinyint(1) default '1',
  							  rewrite_to varchar(255) default '',
  							  relink varchar(255) default '',
  							  PRIMARY KEY  (id)
  						 );" );  						 
		                      
		 # wpo_log 			
     dbDelta(  "CREATE TABLE {$this->db['log']} (
  						    id int(11) unsigned NOT NULL auto_increment,
  							  message mediumtext NOT NULL default '',
  							  created_on datetime NOT NULL default '0000-00-00 00:00:00',
  							  PRIMARY KEY  (id)
  						 );" ); 			      
      
      
      add_option('wpo_version', $this->version, 'Installed version log');
      
   	  $this->installed = true;
    }
  }                                                                                      
  
  /**
   * Called when plugin is deactivated 
   *
   *
   */
  function deactivate() {}       
  
  /**
   * Uninstalls
   *
   *
   */
  function uninstall()
  {   
    foreach($this->db as $table) 
      $this->wpdb->query("DROP TABLE {$table} ");
    
    // Delete options
    WPOTools::deleteOptions(array('wpo_log', 'wpo_log_stdout', 'wpo_unixcron', 'wpo_unixcron_max', 'wpo_croncode', 'wpo_cacheimages', 'wpo_cachepath'));
  }                                
  
  /** 
   * Adds uninstall link to plugin list
   *
   *
   */
  function filterPluginLinks($action_links, $plugin_file)
  {
    if(function_exists('register_uninstall_hook') || $plugin_file != plugin_basename(__FILE__))
      return $action_links;
      
    $action_links[] = '<a href="'. wp_nonce_url('options-general.php?page=wpomatic.php&s=uninstall', 'uninstall') .'" onclick="return confirm(\'This will not only deactivate, but also remove all your campaigns and settings permanently. Click ok to proceed.\')">Uninstall</a>';
    array_unshift($action_links, '<a href="'. $this->adminurl . '">Settings</a>');
    
    $action_links = apply_filters('wpo_plugin_action_links', $action_links);
    
    return $action_links;
  }
   
   
  /**
   * Called when blog is initialized 
   *
   *
   */
  function init() 
  {  
    do_action('wpo_init');
  
    if($this->installed)
    {
      if(! get_option('wpo_unixcron'))
      {       
        $this->processOne('manual'); 
        do_action('wpo_pseudo_cron');
      }  

      if(isset($_REQUEST['page']) && (strstr($_REQUEST['page'], 'wpomatic.php') || strstr($_REQUEST['page'], 'wpo_')))
      {
        if(isset($_REQUEST['campaign_add']) || isset($_REQUEST['campaign_edit']))
          $this->adminCampaignRequest();

        if(isset($_REQUEST['export_campaign']))
          $this->adminExportRequest();
          
        if(strstr($_REQUEST['page'], 'wpo_'))
          $_REQUEST['s'] = str_replace('wpo_', '', $_REQUEST['page']);
          
        $this->adminInit();  
      }        
    }
  } 
    
  /** 
   * Saves a log message to database
   *
   *
   * @param string  $message  Message to save  
   */
  function log($message)
  {
    $message = apply_filters('wpo_pre_log');
    
    if(get_option('wpo_log_stdout'))
      echo $message;    
    
    if(get_option('wpo_log'))
    {
      $message = $this->wpdb->escape($message);
      $time = current_time('mysql', true);
      $this->wpdb->query("INSERT INTO {$this->db['log']} (message, created_on) VALUES ('{$message}', '{$time}') "); 
    }
    
    do_action('wpo_log', $message);
  }
    
  /**
   * Called by cron.php to update the site
   *
   *
   */     
  function runCron($log = true)
  {
    $this->log('Running cron job');   
    $this->processAll('automated');
    
    do_action('wpo_cron');
  }   
  
  /**
   * Finds a suitable command to run cron
   *
   * @return string command
   **/
  function getCommand()
  {
    $commands = array(
      @WPOTools::getBinaryPath('curl'),
      @WPOTools::getBinaryPath('wget'),
      @WPOTools::getBinaryPath('lynx', ' -dump'),
      @WPOTools::getBinaryPath('ftp')
    );
    
    $commands = apply_filters('wpo_cron_commands', $commands);
    
    foreach($commands as $command)
      if($command) return $command;
    
    return '<em>{wget or similar command here}</em>';
  }
  
  /**
   * Determines what the title has to link to
   *
   * @return string new text
   **/
  function filterPermalink($url)
  {
    // if from admin panel
    if($this->admin)
      return $url;
      
    if(get_the_ID())
    {
    	$campaignid = (int) get_post_meta(get_the_ID(), 'wpo_campaignid', true);

    	if($campaignid)
    	{
    	  $campaign = $this->getCampaignById($campaignid);
    	  if($campaign->linktosource)
    	    return get_post_meta(get_the_ID(), 'wpo_sourcepermalink', true);
    	}  	  

    	return $url;      
    }
  }
  
  /**
   * Processes all campaigns
   *   
   * @param   boolean   $type       automated | manual | user
   */
  function processAll($type = 'manual')
  {
    @set_time_limit(0);
    
    $campaigns = $this->getCampaigns('unparsed=1');
    $count = 0;
    
    foreach($campaigns as $campaign)
    {
      if(get_option('wpo_unixcron_max') && $count === get_option('wpo_unixcron_max'))
        break;
        
      $this->processCampaign($campaign, $type);
      $count++;
    }
      
    do_action('wpo_processall');
    
    return $count;
  }
  
  // to be called by pseudocron
  function processOne($type = 'manual')
  {
    @set_time_limit(0);
      
    $campaigns = $this->getCampaigns('unparsed=1');
    
    foreach($campaigns as $campaign) 
    {
      $this->processCampaign($campaign, $type);
      break;
    }      
    
    do_action('wpo_processone');
    
    return 1;
  }
  
  /**
   * Processes a campaign
   *
   * type param explanation:
   *  - automated: this is the type of process of cron, processes all feeds
   *  - manual: this is the type of process of pseudocron, processes as many feeds as configured, normally 1 or 2
   *  - user: this is the type of process of ajax from the admin panel, processes only one rss
   *
   * @param   object    $campaign   Campaign database object
   * @param   boolean   $type       automated | manual
   * @return  integer   Number of processed items
   */  
  function processCampaign(&$campaign, $type = 'manual')
  {
    @set_time_limit(0);
    ob_implicit_flush();
  
    // Get campaign
    $campaign = is_numeric($campaign) ? $this->getCampaignById($campaign) : $campaign;
    
    do_action('wpo_pre_process_campaign', &$campaign, $type);

    // Log 
    $this->log('Processing campaign ' . $campaign->title . ' (ID: ' . $campaign->id . ')');
        
    // Mark campaign as being processed
    if($type === 'manual' && ! $campaign->processing)
      $this->wpdb->query("UPDATE {$this->db['campaign']} SET processing = 1 WHERE id = {$campaign->id} ");
        
    // Get feeds
    $feeds = $this->getCampaignFeeds('campaign=' . $campaign->id);    
    $process_feeds = $this->getCampaignFeeds('unprocessed=1&campaign=' . $campaign->id);
    $processed_feeds = 0;
    $posts = 0;
    
    foreach($feeds as $feed)
    {
      if($type === 'manual' && $campaign->max_feeds_process && $processed_feeds === $campaign->max_feeds_process)
        break;
      
      $posts += $this->processFeed(&$campaign, &$feed, $type);
      $processed_feeds++;      
    }
      
    # If processed, mark it as finalized.
    if($processed_feeds === count($feeds))
    {
      if($type === 'manual')
        $wpdb->query("UPDATE {$this->db['campaign']} SET processing = 0 WHERE id = {$campaign->id} ");

      # Set all feeds to unprocessed
      $wpdb->query("UPDATE {$this->db['campaign_feed']} SET processed = 0 WHERE campaign_id = {$campaign->id} ");
    }

    $this->wpdb->query(WPOTools::updateQuery($this->db['campaign'], array(
      'count' => $campaign->count + $posts,
      'lastactive' => current_time('mysql', true)
    ), "id = {$campaign->id}"));
    
    do_action('wpo_process_campaign', &$campaign);
    
    return $posts;
  } 
  
  /**
   * Processes a feed
   *
   * @param   $campaign   object    Campaign database object   
   * @param   $feed       object    Feed database object
   * @param   $type       boolean   automated | manual
   * @return  The number of items added to database
   */
  function processFeed(&$campaign, &$feed, $type)
  {
    @set_time_limit(0);
    
    do_action('wpo_pre_process_feed', &$campaign, &$feed, $type);
    
    if($type == 'manual' && $feed->processing)
      return $this->log("Feed {$feed->title} ignored because it's being processed.");
    
    // Log
    $this->log('Processing feed ' . $feed->title . ' (ID: ' . $feed->id . ')');
    
    // Mark as processing
    $this->wpdb->query("UPDATE $this->db['campaign_feed'] SET processing = 1 WHERE id = {$feed->id}");    
    
    // Access the feed
    $simplepie = $this->fetchFeed($feed->url, false, $campaign->max);
    
    // Get posts (last is first)
    $items = array();
    $count = 0;
    
    foreach($simplepie->get_items() as $item)
    {
      if($feed->hash == $this->getItemHash($item))
      {
        if($count == 0) $this->log('No new posts');
        break;
      }        
      
      if($this->isDuplicate(&$campaign, &$feed, &$item))
      {
        $this->log('Filtering duplicate post');
        continue;
      }
      
      $count++;
      array_unshift($items, $item);
      
      if($count == $campaign->max)
      {
        $this->log('Campaign fetch limit reached at ' . $campaign->max);
        break;
      }
    }
    
    // Processes post stack
    foreach($items as $item)
    {
      $this->processItem(&$campaign, &$feed, &$item);
      $lasthash = $this->getItemHash($item);
      
      do_action('wpo_process_feed_item', &$campaign, &$feed, &$item);
    }
    
    // If we have added items, let's update the hash
    // If the type is automatic, we leave it at unprocessed state, because we are gonna process them all.
    $update_fields = array(
      'processed' => ($type === 'manual') ? 1 : 0,
      'processing' => 0,
      'lastactive' => current_time('mysql', true)
    );
    
    if($count)
      $update_fields = array_merge($update_fields, array(
        'count' => $feed->count + $count,
        'hash' => $lasthash
      ));
    
    $this->wpdb->query(WPOTools::updateQuery($this->db['campaign_feed'], $update_fields, "id = {$feed->id}"));    
    
    $this->log( $count . ' posts added' );
    
    do_action('wpo_process_feed', &$campaign, &$feed, $type);
  
    return $count;
  }              
  
  /**
   * Processes an item
   *
   * @param   $item       object    SimplePie_Item object
   */
  function getItemHash($item)
  {
    $hash = sha1($item->get_title() . $item->get_permalink());
    return apply_filters('wpo_item_hash', $hash);
  }  
   
  /**
   * Processes an item
   *
   * @param   $campaign   object    Campaign database object   
   * @param   $feed       object    Feed database object
   * @param   $item       object    SimplePie_Item object
   */
  function processItem(&$campaign, &$feed, &$item)
  {
    $this->log('Processing item');
    
    do_action('wpo_pre_process_item', &$campaign, &$feed, &$item);
    
    // Item content
    $content = $this->parseItemContent(&$campaign, &$feed, &$item);
    
    // Item date
    if($campaign->feeddate && ($item->get_date('U') > (current_time('timestamp', 1) - $campaign->frequency) && $item->get_date('U') < current_time('timestamp', 1)))
      $date = $item->get_date('U');
    else
      $date = null;
      
    // Categories
    $categories = $this->getCampaignData($campaign->id, 'categories');
      
    // Meta
    $meta = array(
      'wpo_campaignid' => $campaign->id,
      'wpo_feedid' => $feed->id,
      'wpo_sourcepermalink' => $item->get_permalink()
    );  
    
    $item_data = array(
      'title' => $this->wpdb->escape($item->get_title()),
      'content' => $this->wpdb->escape($content),
      'date' => $date,
      'categories' => $categories,
      'status' => $campaign->status,
      'authorid' => $campaign->authorid,
      'allowpings' => $campaign->allowpings,
      'comments_status' => $campaign->comment_status,
      'meta' => $meta
    );
    
    $item_data = apply_filters('wpo_item_data', $item_data);
    
    // Create post
    $postid = $this->insertPost($item_data['title'], $item_data['content'], $item_data['date'], $item_data['categories'], $item_data['status'], $item_data['authorid'], $item_data['allowpings'], $item_data['comment_status'], $item_data['meta']);
    
    // If pingback/trackbacks
    if($campaign->dopingbacks)
    {
      $this->log('Processing item pingbacks');
      
      require_once(ABSPATH . WPINC . '/comment.php');
    	pingback($content, $postid);      
    	
      do_action('wpo_pingback', &$campaign, &$feed, &$item);
    }      
    
    // Save post to log database
    $this->wpdb->query(WPOTools::insertQuery($this->db['campaign_post'], array(
      'campaign_id' => $campaign->id,
      'feed_id' => $feed->id,
      'post_id' => $postid,
      'hash' => $this->getItemHash($item)
    )));
    
    do_action('wpo_process_item', &$campaign, &$feed, &$item);
  }
  
  /**
   * Processes an item
   *
   * @param   $campaign   object    Campaign database object   
   * @param   $feed       object    Feed database object
   * @param   $item       object    SimplePie_Item object
   */
  function isDuplicate(&$campaign, &$feed, &$item)
  {
    $hash = $this->getItemHash($item);
    $row = $this->wpdb->get_var("SELECT id FROM {$this->db['campaign_post']} WHERE hash = '$hash' ");        
    $ret = !! $row;
    
    do_action('wpo_duplicate_check', &$ret, &$campaign, &$feed, &$item);
    return $ret;
  }
  
  /**
   * Writes a post to blog
   *
   *  
   * @param   string    $title            Post title
   * @param   string    $content          Post content
   * @param   integer   $timestamp        Post timestamp
   * @param   array     $category         Array of categories
   * @param   string    $status           'draft', 'published' or 'private'
   * @param   integer   $authorid         ID of author.
   * @param   boolean   $allowpings       Allow pings
   * @param   boolean   $comment_status   'open', 'closed', 'registered_only'
   * @param   array     $meta             Meta key / values
   * @return  integer   Created post id
   */
  function insertPost($title, $content, $timestamp = null, $category = null, $status = 'draft', $authorid = null, $allowpings = true, $comment_status = 'open', $meta = array())
  {
    $date = ($timestamp) ? gmdate('Y-m-d H:i:s', $timestamp + (get_option('gmt_offset') * 3600)) : null;
    $postid = wp_insert_post(array(
    	'post_title' 	            => $title,
  		'post_content'  	        => $content,
  		'post_content_filtered'  	=> $content,
  		'post_category'           => $category,
  		'post_status' 	          => $status,
  		'post_author'             => $authorid,
  		'post_date'               => $date,
  		'comment_status'          => $comment_status,
  		'ping_status'             => $allowpings
    ));
    	
		foreach($meta as $key => $value) 
			$this->insertPostMeta($postid, $key, $value);			
		
		do_action('wpo_inserted_post', $postid);
		
		return $postid;
  }
  
  /**
   * insertPostMeta
   *
   *
   */
	function insertPostMeta($postid, $key, $value) {
		$result = $this->wpdb->query( "INSERT INTO $this->wpdb->postmeta (post_id,meta_key,meta_value ) " 
					                . " VALUES ('$postid','$key','$value') ");
					
    do_action('wpo_inserted_post_meta', $postid, $this->wpdb->insert_id);
                    		
		return $this->wpdb->insert_id;		
	}
  
  /**
   * Parses an item content
   *
   * @param   $campaign       object    Campaign database object   
   * @param   $feed           object    Feed database object
   * @param   $item           object    SimplePie_Item object
   */
  function parseItemContent(&$campaign, &$feed, &$item)
  {  
    $content = $item->get_content();
    
    $content = apply_filters('wpo_pre_post_content', $content, &$campaign, &$feed, &$item);
    
    // Caching
    if(get_option('wpo_cacheimages') || $campaign->cacheimages)
    {
      $images = WPOTools::parseImages($content);
      $urls = $images[2];
      
      if(sizeof($urls))
      {
        $this->log('Caching images');
        
        foreach($urls as $url)
        {
          $newurl = $this->cacheRemoteImage($url);
          if($newurl)
            $content = str_replace($url, $newurl, $content);
        } 
      }
    }
    
    // Template parse
    $vars = array(
      '{content}' => $content,
      '{title}' => $item->get_title(),
      '{permalink}' => $item->get_link(),
      '{feedurl}' => $feed->url,
      '{feedtitle}' => $feed->title,
      '{feedlogo}' => $feed->logo,
      '{campaigntitle}' => $campaign->title,
      '{campaignid}' => $campaign->id,
      '{campaignslug}' => $campaign->slug
    );
    
    $vars = apply_filters('wpo_template_vars', $vars, &$campaign, &$feed, &$item);
    
    $content = str_ireplace(array_keys($vars), array_values($vars), ($campaign->template) ? $campaign->template : '{content}');
    
    // Rewrite
    $rewrites = $this->getCampaignData($campaign->id, 'rewrites');
    $rewrites = apply_filters('wpo_post_rewrites', $rewrites, &$campaign, &$feed, &$item);
    
    foreach($rewrites as $rewrite)
    {
      $origin = $rewrite['origin']['search'];
      
      if(isset($rewrite['rewrite']))
      {
        $reword = isset($rewrite['relink']) 
                    ? '<a href="'. $rewrite['relink'] .'">' . $rewrite['rewrite'] . '</a>' 
                    : $rewrite['rewrite'];
        
        if($rewrite['origin']['regex'])
        {
          $content = preg_replace($origin, $reword, $content);
        } else
          $content = str_ireplace($origin, $reword, $content);
      } else if(isset($rewrite['relink'])) 
        $content = str_ireplace($origin, '<a href="'. $rewrite['relink'] .'">' . $origin . '</a>', $content);
    }
    
    $content = apply_filters('wpo_post_content', $content, &$campaign, &$feed, &$item);
    
    return $content;
  }
  
  /**
   * Cache remote image
   *
   * @return string New url
   */
  function cacheRemoteImage($url)
  {
    $contents = @file_get_contents($url);
    $filename = substr(md5(time()), 0, 5) . '_' . basename($url);
    $ret = false;
    
    $cachepath = $this->cachepath;
    
    if(is_writable($cachepath) && $contents)
    { 
      file_put_contents($cachepath . '/' . $filename, $contents);
      $ret = $this->pluginpath . '/' . get_option('wpo_cachepath') . '/' . $filename;
    }
        
    do_action('wpo_cache_image', &$ret, $url);
    
    return $ret;
  }
   
  /**
   * Parses a feed with SimplePie
   *
   * @param   boolean     $stupidly_fast    Set fast mode. Best for checks
   * @param   integer     $max              Limit of items to fetch
   * @return  SimplePie_Item    Feed object
   **/
  function fetchFeed($url, $stupidly_fast = false, $max = 0)
  {
    # SimplePie
    if(! class_exists('SimplePie'))
      require_once( WPOINC . 'simplepie/simplepie.class.php' );
    
    $feed = new SimplePie();
    $feed->enable_order_by_date(false); // thanks Julian Popov
    $feed->set_feed_url($url);
    $feed->set_item_limit($max);
    $feed->set_stupidly_fast($stupidly_fast);
    $feed->enable_cache(false);    
    $feed->init();
    $feed->handle_content_type(); 
    
    do_action('wpo_simplepie_setup', &$feed);
    
    return $feed;
  }
  
  /**
   * Returns all blog usernames (in form [user_login => display_name (user_login)] )
   *
   * @return array $usernames
   **/
  function getBlogUsernames()
  {
    $return = array();
    $users = get_users_of_blog();
    
    foreach($users as $user)
    {
      if($user->display_name == $user->user_login)
        $return[$user->user_login] = "{$user->display_name}";      
      else
        $return[$user->user_login] = "{$user->display_name} ({$user->user_login})";
    }
    
    return $return;
  }
  
  /**
   * Returns all data for a campaign
   *
   *
   */
  function getCampaignData($id, $section = null)
  {
    $campaign = (array) $this->getCampaignById($id);
    
    if($campaign)
    {
      $campaign_data = $this->campaign_structure;
      
      // Main
      if(!$section || $section == 'main')
      {
        $campaign_data['main'] = array_merge($campaign_data['main'], $campaign);
        $userdata = get_userdata($campaign_data['main']['authorid']);
        $campaign_data['main']['author'] = $userdata->user_login; 
      }
      
      // Categories
      if(!$section || $section == 'categories')
      {
        $categories = $this->wpdb->get_results("SELECT * FROM {$this->db['campaign_category']} WHERE campaign_id = $id");
        foreach($categories as $category)
          $campaign_data['categories'][] = $category->category_id;
      }
      
      // Feeds
      if(!$section || $section == 'feeds')
      {
        $campaign_data['feeds']['edit'] = array();
        
        $feeds = $this->getCampaignFeeds('campaign=' . $id);
        foreach($feeds as $feed)
          $campaign_data['feeds']['edit'][$feed->id] = $feed->url;
      }
      
      // Rewrites      
      if(!$section || $section == 'rewrites')
      {
        $rewrites = $this->wpdb->get_results("SELECT * FROM {$this->db['campaign_word']} WHERE campaign_id = $id");
        foreach($rewrites as $rewrite)
        {
          $word = array('origin' => array('search' => $rewrite->word, 'regex' => $rewrite->regex), 'rewrite' => $rewrite->rewrite_to, 'relink' => $rewrite->relink);
        
          if(! $rewrite->rewrite) unset($word['rewrite']);
          if(empty($rewrite->relink)) unset($word['relink']);
        
          $campaign_data['rewrites'][] = $word;
        }
      }
      
      apply_filters('wpo_get_campaign_data', $campaign_data);

      if($section)
        return $campaign_data[$section];
        
      return $campaign_data; 
    }
    
    return false;
  }
  
  /**
   * Retrieves logs from database
   *
   *
   */       
  function getLogs($args = '') 
  {   
    extract(WPOTools::getQueryArgs($args, array('orderby' => 'created_on', 
                                                'ordertype' => 'DESC', 
                                                'limit' => null,
                                                'page' => null,
                                                'perpage' => null))); 
    if(!is_null($page))
    {
      if($page == 0) $page = 1;
      $page--;
      
      $start = $page * $perpage;
      $end = $start + $perpage;
      $limit = "LIMIT {$start}, {$end}";
    }
  	
  	return $this->wpdb->get_results("SELECT * FROM {$this->db['log']} ORDER BY $orderby $ordertype $limit");
  }
           
  /**
   * Retrieves a campaign by its id
   *
   *
   */  
  function getCampaignById($id)
  {
    $id = intval($id);
    return $this->wpdb->get_row("SELECT * FROM {$this->db['campaign']} WHERE id = $id");
  }
  
  /**
   * Retrieves a feed by its id
   *
   *
   */  
  function getFeedById($id)
  {
    $id = intval($id);
    return $this->wpdb->get_row("SELECT * FROM {$this->db['campaign_feed']} WHERE id = $id");
  }
         
  /**
   * Retrieves campaigns from database
   *
   *
   */       
  function getCampaigns($args = '') 
  {   
    extract(WPOTools::getQueryArgs($args, array('fields' => '*',      
                                                'search' => '',
                                                'orderby' => 'created_on', 
                                                'ordertype' => 'DESC', 
                                                'where' => '',
                                                'unparsed' => false,
                                                'limit' => null)));
  
  	if(! empty($search))
  	  $where .= " AND title LIKE '%{$search}%' ";
  	  
  	if($unparsed)
  	  $where .= " AND active = 1 AND (processing = 1 OR ((frequency + UNIX_TIMESTAMP(lastactive)) < ". (current_time('timestamp', true) - get_option('gmt_offset') * 3600) . "))";
  	                              
  	$where = apply_filters('wpo_campaign_sql_where', $where, $args);
  	                              
  	$sql = "SELECT $fields FROM {$this->db['campaign']} WHERE 1 = 1 $where "
         . "ORDER BY $orderby $ordertype $limit";
         
  	return $this->wpdb->get_results($sql);
  }            
  
  /**
   * Retrieves feeds for a certain campaign
   *
   * @param   integer   $id     Campaign id
   */
  function getCampaignFeeds($args = '')
  {    
    extract(WPOTools::getQueryArgs($args, array('campaign' => false,
                                                'fields' => '*',
                                                'orderby' => 'created_on', 
                                                'ordertype' => 'DESC',
                                                'unprocessed' => false,
                                                'limit' => null)));
    
    if($campaign)
      $where = " AND campaign_id = $campaign ";
    
    if($unprocessed)
      $where = " AND processing != 1 AND processed != 1 ";
      
    $where = apply_filters('wpo_feed_sql_where', $where, $args);
    
    $sql = "SELECT $fields FROM {$this->db['campaign_feed']} WHERE 1 = 1 $where"
         . "ORDER BY $orderby $ordertype $limit";
         
    return $this->wpdb->get_results($sql);
  }
  
  /**
   * Retrieves all WP posts for a certain campaign
   *
   * @param   integer   $id     Campaign id
   */
  function getCampaignPosts($id)
  {
    return $this->wpdb->get_results("SELECT post_id FROM {$this->db['campaign_post']} WHERE campaign_id = $id ");          
  }
  
  /**
   * Adds a feed by url and campaign id
   *
   *
   */
  function addCampaignFeed($id, $feed)
  {    
    $simplepie = $this->fetchFeed($feed, true);
    # fixes &amp / & issue
    $url = $this->wpdb->escape(html_entity_decode($simplepie->subscribe_url()));
    
    
    // If it already exists, ignore it
    if(! $this->wpdb->get_var("SELECT id FROM {$this->db['campaign_feed']} WHERE campaign_id = $id AND url = '$url' "))
    {
      $data = array('url' => $url, 
                    'title' => $this->wpdb->escape($simplepie->get_title()),
                    'description' => $this->wpdb->escape($simplepie->get_description()),
                    'logo' => $this->wpdb->escape($simplepie->get_image_url()),
                    'campaign_id' => $id
      );
      
      $data = apply_filters('wpo_feed_sql_data', $data);
      
      $this->wpdb->query(WPOTools::insertQuery($this->db['campaign_feed'], $data));  
      
      do_action('wpo_add_feed', $this->wpdb->insert_id, $feed);
      
      return $this->wpdb->insert_id;
    }
    
    return false;
  }
  
  
  /**
   * Retrieves feeds from database
   *         
   * @param   mixed   $args
   */  
  function getFeeds($args = '') 
  {
    extract(WPOTools::getQueryArgs($args, array('fields' => '*',  
                                                'campid' => '',    
                                                'join' => false,
                                                'orderby' => 'created_on', 
                                                'ordertype' => 'DESC', 
                                                'limit' => null)));
  	
  	$sql = "SELECT $fields FROM {$this->db['campaign_feed']} cf ";
  	
  	if(!empty($join))
  	  $sql .= "INNER JOIN {$this->db['campaign']} camp ON camp.id = cf.campaign_id ";
  	     
  	if(!empty($campid))
  	  $sql .= "WHERE cf.campaign_id = $campid";
  	
  	return $this->wpdb->get_results($sql);  
  }        
  
  /**
   * Returns how many seconds left till reprocessing
   *
   * @return seconds
   **/
  function getCampaignRemaining(&$campaign, $gmt = 0)
  {    
    return mysql2date('U', $campaign->lastactive) + $campaign->frequency - current_time('timestamp', true) + ($gmt ? 0 : (get_option('gmt_offset') * 3600));
  }
  
  /**
   * Called when WP-o-Matic admin pages initialize.
   * 
   * 
   */
  function adminInit() 
  {             
    if(! current_user_can('manage_options')) die('Unauthorized');
    
    // force display of a certain section    
    $this->section = ($this->setup) ? ((isset($_REQUEST['s']) && $_REQUEST['s']) ? $_REQUEST['s'] : $this->sections[0]) : 'setup';
    
    wp_enqueue_script('wpoadmin', $this->tplpath . '/admin.js', array('prototype', 'jquery'), $this->version);
    
    if($this->section == 'list')
      wp_enqueue_script('listman');
          
    // TODO: enqueue css here ?
          
    do_action('wpo_admin_init');
          
    // TODO: check
    if(defined('DOING_AJAX'))
    {              
      $this->admin();
      exit;
    }      
  }
  
  /**
   * Called by admin-header.php
   *
   * @return void
   **/
  function adminWPHeader()
  {
    $this->admin = true;
  }
  
  /**
   * Shows a warning box for setup
   *
   *
   */
  function adminWarning()
  {
    if(! $this->setup && $this->section != 'setup')
    {      
      echo "<div id='wpo-warning' class='updated fade-ff0000'><p>" . sprintf(__('WP-o-Matic has been installed but it hasn\'t been configured yet. Please <a href="%s">click here</a> to setup and configure WP-o-Matic.', 'wpomatic'), $this->adminurl . '&amp;s=setup') . "</p></div>"; 
    }  
  }
  
  /**
   * Called by admin-footer.php
   * 
   * 
   */
  function adminWPFooter()
  {
    
  }
  
  /**
   * Executes the current section method.
   * 
   *
   */
  function admin($section = null)
  {                       
    if(in_array($this->section, $this->sections))
    {    
      do_action('wpo_pre_admin-' . $this->section);
      
      $method = 'admin' . ucfirst($this->section);
      $this->$method();        
      
      if(isset($this->json))
        apply_filters('wpo_admin_json-' . $this->section, $this->json);
    }  
  }
    
  /**
   * Adds the WP-o-Matic item to menu 
   *           
   * 
   */
  function adminMenu()
  {
    if($this->wpbranch >= 27) 
    {
      // add top level menu
      add_menu_page('WP-o-Matic', 'WP-o-Matic', 8, __FILE__, array(&$this, 'admin'));
      
      if($this->setup)
      {
        add_submenu_page(__FILE__, 'WP-o-Matic', 'Dashboard', 8, 'wpo_home', array(&$this, 'admin'));
        add_submenu_page(__FILE__, 'WP-o-Matic', 'Campaigns', 8, 'wpo_list', array(&$this, 'admin'));      
        add_submenu_page(__FILE__, 'WP-o-Matic', 'Add campaign', 8, 'wpo_add', array(&$this, 'admin'));      
        add_submenu_page(__FILE__, 'WP-o-Matic', 'Options', 8, 'wpo_options', array(&$this, 'admin'));      
        add_submenu_page(__FILE__, 'WP-o-Matic', 'Import', 8, 'wpo_import', array(&$this, 'admin'));
        add_submenu_page(__FILE__, 'WP-o-Matic', 'Export', 8, 'wpo_export', array(&$this, 'admin'));
      }
      else
        add_submenu_page(__FILE__, 'WP-o-Matic', 'Setup', 8, __FILE__, array(&$this, 'admin'));      
    }       
    else
      add_submenu_page('options-general.php', 'WP-o-Matic', 'WP-o-Matic', 10, basename(__FILE__), array(&$this, 'admin'));
      
    do_action('wpo_menu');
  }              
  
  /** 
   * Returns the section link
   *
   *
   */
  function adminLink($section, $args = '')
  {
    return $this->adminurl . '&s=' . $section . ($args ? '&' . trim($args, '&') : '');
  }      
    
  /**
   * Outputs the admin header in a template 
   *            
   * 
   */
  function adminHeader()
  {              
    $current = array();
                    
    foreach($this->sections as $s)
      $current[$s] = ($s == $this->section) ? 'class="current"' : '';
    
    include(WPOTPL . 'header.php');
    
    do_action('wpo_admin_header');
  }                                                                  
                                
  /**
   * Outputs the admin footer in a template
   *            
   * 
   */
  function adminFooter()
  {
    include(WPOTPL . 'footer.php');
    do_action('wpo_admin_footer');
  }
  
  /**
   * Uninstalls and deactivates
   *
   *
   */
  function adminUninstall()
  {
    check_admin_referer('uninstall');
    
    $this->uninstall();
    deactivate_plugins('wpomatic.php');
  }
    
  /**
   * Home section
   *
   *
   */
  function adminHome()
  {                                   
    $logging = get_option('wpo_log');
    $logs = $this->getLogs('limit=7');    
    $nextcampaigns = $this->getCampaigns('fields=id,title,lastactive,frequency&limit=5&where=active=1' .
                                          '&orderby=UNIX_TIMESTAMP(lastactive)%2Bfrequency&ordertype=ASC');
    $lastcampaigns = $this->getCampaigns('fields=id,title,lastactive,frequency&limit=5' .
                                          '&where=UNIX_TIMESTAMP(lastactive)>0&orderby=lastactive');
    $campaigns = $this->getCampaigns('fields=id,title,count&limit=5&orderby=count');
    
    include(WPOTPL . 'home.php');
  }      
    
  
  /** 
   * Setup admin
   *
   *
   */
  function adminSetup()
  {
    if($_POST)
    {
      check_admin_referer('setup');
      
      update_option('wpo_unixcron', isset($_REQUEST['option_unixcron']));
      update_option('wpo_setup', 1);
      
      $this->adminHome();
      exit;
    }
    
    # Commands
    $prefix = $this->getCommand();
    $nocommand = ! file_exists($prefix);
                
    $safe_mode = ini_get('safe_mode');
        
    $command = $this->cron_command;
    $url = $this->cron_url;
        
    include(WPOTPL . 'setup.php');
  }     

  
  /**
   * Process a campaign and fetches feeds (manual)
   *
   *
   */
  function adminProcesscampaign()
  {
    $cid = intval($_REQUEST['id']);
    check_admin_referer('wpo_processcampaign-' . $cid);
    
    $campaign = $this->getCampaignById($cid);
    $feedid = intval($_REQUEST['feedid']);
    $json = '';
    
    if(! $feedid)
    {
      $feeds = $this->getCampaignFeeds('unprocessed=1&campaign=' . $campaign->id);
      if($feeds)
      {
        $feed = array_pop($feeds);        
        $this->processFeed($feed->id, 'manual');
      }
    } else    
      $this->processFeed($feedid, 'manual');
        
    // check for next unprocessed feed
    $feeds = $this->getCampaignFeeds('unprocessed=1&campaign=' . $campaign->id);
    if($feeds)
    {
      $nextfeed = array_pop($feeds);
      if(defined('DOING_AJAX'))
        $this->json = array(
          'status' => '',
          'nexturl' => '',
          'message'
        );
    } else 
    {
      if(defined('DOING_AJAX'))
        $this->json = array('status' => '', 'message' => '');
    }    
    
    do_action('wpo_admin-processcampaign');
  }
  
  
  /**
   * Fetch all campaigns
   *
   *
   */
  function adminFetch()
  {
    $cid = intval($_REQUEST['id']);
    
    if(! defined('DOING_AJAX'))
    {
      if($cid) // assume single campaign
        check_admin_referer('fetch-campaign_'.$cid);    
      else // assume multiple campaign
        check_admin_referer('fetch-campaigns');
    }
    
    do_action('wpo_admin-fetch');    
  }

  /**
   * List campaigns section
   *
   *
   */
  function adminList()
  {             
    $this->section = 'list';
    
    if(isset($_REQUEST['q']))
    {
      $q = $_REQUEST['q'];
      $campaigns = $this->getCampaigns('search=' . $q);
    } else
      $campaigns = $this->getCampaigns('orderby=CREATED_ON');
  
    do_action('wpo_admin-list');
  
    include(WPOTPL . 'list.php');
  }
            
  /**
   * Add campaign section
   *
   *
   */
  function adminAdd()
  {                
    $data = $this->campaign_structure;
  
    if(isset($_REQUEST['campaign_add']))
    {
      check_admin_referer('edit-campaign');
      
      if($this->errno)
        $data = $this->campaign_data;
      else
        $addedid = $this->adminProcessAdd();   
    }
    
    $author_usernames = $this->getBlogUsernames();   
    $campaign_add = true;
    
    do_action('wpo_admin-add');
    
    include(WPOTPL . 'edit.php');
  }     
  
  /**
   * Edit campaign section
   *
   *
   */
  function adminEdit()
  {    
    $id = intval($_REQUEST['id']);
    if($id)
    {
      if(isset($_REQUEST['campaign_edit']))
      {
        check_admin_referer('edit-campaign');

        $data = $this->campaign_data;
        $submitted = true;

        if(! $this->errno) 
        {
          $this->adminProcessEdit($id);
          $edited = true;
          $data = $this->getCampaignData($id);
        }
      } else      
        $data = $this->getCampaignData($id);    

      $author_usernames = $this->getBlogUsernames();
      $campaign_edit = true;

      do_action('wpo_admin-edit');

      include(WPOTPL . 'edit.php');  
    }
  }
  
  function adminEditCategories(&$data, $parent = 0, $level = 0, $categories = 0)
  {    
  	if ( !$categories )
  		$categories = get_categories(array('hide_empty' => 0));

    if(function_exists('_get_category_hierarchy'))
      $children = _get_category_hierarchy();
    elseif(function_exists('_get_term_hierarchy'))
      $children = _get_term_hierarchy('category');
    else
      $children = array();

  	if ( $categories ) {
  		ob_start();
  		foreach ( $categories as $category ) {
  			if ( $category->parent == $parent) {
  				echo "\t" . _wpo_edit_cat_row($category, $level, $data);
  				if ( isset($children[$category->term_id]) )
  					$this->adminEditCategories($data, $category->term_id, $level + 1, $categories );
  			}
  		}
  		$output = ob_get_contents();
  		ob_end_clean();

  		echo $output;
  	} else {
  		return false;
  	}
    	
  }
  
  /**
   * Resets a campaign (sets post count to 0, forgets last parsed post)
   *
   *
   * @todo Make it ajax-compatible here and add javascript code
   */
  function adminReset()
  {
    $id = intval($_REQUEST['id']);

    if(! defined('DOING_AJAX'))
      check_admin_referer('reset-campaign_'.$id);    
      
    // Reset count and lasactive
    $this->wpdb->query(WPOTools::updateQuery($this->db['campaign'], array(
      'count' => 0,
      'lastactive' => 0,
      'processing' => 0
    ), "id = $id"));
      
    // Reset feeds hashes, count, and lasactive
    foreach($this->getCampaignFeeds('campaign=' . $id) as $feed)
    {
      $this->wpdb->query(WPOTools::updateQuery($this->db['campaign_feed'], array(
        'count' => 0,
        'lastactive' => 0,
        'processed' => 0,
        'processing' => 0,
        'hash' => ''
      ), "id = {$feed->id}"));
    }
    
    do_action('wpo_admin-reset');
      
    if(defined('DOING_AJAX'))
      $this->json = array('success' => 1);
    else
      $this->adminList();
  }
  
  /**
   * Deletes a campaign
   *
   *
   */
  function adminDelete()
  {
    $id = intval($_REQUEST['id']);

    // If not called through admin-ajax.php
    if(! defined('DOING_AJAX'))
      check_admin_referer('delete-campaign_'.$id);    
      
    $this->wpdb->query("DELETE FROM {$this->db['campaign']} WHERE id = $id");
    $this->wpdb->query("DELETE FROM {$this->db['campaign_feed']} WHERE campaign_id = $id");
    $this->wpdb->query("DELETE FROM {$this->db['campaign_word']} WHERE campaign_id = $id");
    $this->wpdb->query("DELETE FROM {$this->db['campaign_category']} WHERE campaign_id = $id");
    
    do_action('wpo_admin-delete');    
    
    if(defined('DOING_AJAX'))
      $this->json = array('success' => 1);
    else
      $this->adminList();
  }
  
  /**
   * Options section 
   *
   *
   */
  function adminOptions()
  {                
    $this->section = 'options';
    
    if($_POST)
    {              
      update_option('wpo_unixcron',     isset($_REQUEST['option_unixcron']));
      update_option('wpo_unixcron_max', isset($_REQUEST['option_unixcron_max']));
      update_option('wpo_log',          isset($_REQUEST['option_logging']));
      update_option('wpo_log_stdout',   isset($_REQUEST['option_logging_stdout']));      
      update_option('wpo_cacheimages',  isset($_REQUEST['option_caching']));
      update_option('wpo_cachepath',    rtrim($_REQUEST['option_cachepath'], '/'));
      
      $updated = 1;
    }
    
    if(! is_writable($this->cachepath))
      $not_writable = true;
      
    do_action('wpo_admin-options');
      
    include(WPOTPL . 'options.php');
  }
   
  /**
   * Import section
   *
   *
   */
  function adminImport()
  {  
    @session_start();
    
    if(! $_POST) unset($_SESSION['opmlimport']);
    
    if(isset($_FILES['importfile']) || $_POST)
      check_admin_referer('import-campaign');   
           
    if(!isset($_SESSION['opmlimport']))
    {
      if(isset($_FILES['importfile']))      
      {
        if(is_uploaded_file($_FILES['importfile']['tmp_name']))
          $file = $_FILES['importfile']['tmp_name'];
        else
          $file = false;
      } else if(isset($_REQUEST['importurl']))
      {
        $fromurl = true;
        $file = $_REQUEST['importurl'];
      }  
    }
    
    if(isset($file) || ($_POST && isset($_SESSION['opmlimport'])) )
    {               
      require_once( WPOINC . 'xmlparser.class.php' );
    
      $contents = (isset($file) ? @file_get_contents($file) : $_SESSION['opmlimport']);
      $_SESSION['opmlimport'] = $contents;    
    
      # Get OPML data
      $opml = new XMLParser($contents);
      $opml = $opml->getTree();                                             
              
      # Check that it is indeed opml      
      if(is_array($opml) && isset($opml['OPML'])) 
      {                            
        $opml = $opml['OPML'][0];
        
        $title = isset($opml['HEAD'][0]['TITLE'][0]['VALUE']) 
                  ? $opml['HEAD'][0]['TITLE'][0]['VALUE'] 
                  : null;          
                  
        $opml = $opml['BODY'][0];
                   
        $success = 1;
        
        # Campaigns dropdown
        $campaigns = array();
        foreach($this->getCampaigns() as $campaign)
          $campaigns[$campaign->id] = $campaign->title;
      }          
      else 
        $import_error = 1;
    }    
    
    $this->adminImportProcess();
      
    do_action('wpo_admin-import');      
      
    include(WPOTPL . 'import.php');
  }      
  
  /**
   * Import process
   *
   *
   */
  function adminImportProcess()
  {
    if($_POST)
    {
      if(!isset($_REQUEST['feed']))
        $add_error = __('You must select at least one feed', 'wpomatic');
      else 
      {
        switch($_REQUEST['import_mode'])
        {
          // Several campaigns
          case '1':
            $created_campaigns = array();

            foreach($_REQUEST['feed'] as $campaignid => $feeds)
            {
              if(!in_array($campaignid, $created_campaigns))
              {
                // Create campaign
                $title = $_REQUEST['campaign'][$campaignid];
                if(!$title) continue;
                
                $slug = WPOTools::stripText($title);
                $this->wpdb->query("INSERT INTO {$this->db['campaign']} (title, active, slug, lastactive, count) VALUES ('$title', 0, '$slug', 0, 0) ");
                $created_campaigns[] = $this->wpdb->insert_id;  
              
                // Add feeds
                foreach($feeds as $feedurl => $yes)
                  $this->addCampaignFeed($campaignid, urldecode($feedurl));
                  
              }            
            }
            
            $this->add_success = __('Campaigns added successfully. Feel free to edit them', 'wpomatic');
            
            break;
          
          // All feeds into an existing campaign
          case '2':
            $campaignid = $_REQUEST['import_custom_campaign'];
            
            foreach($_REQUEST['feed'] as $cid => $feeds)
            {
              // Add feeds              
              foreach($feeds as $feedurl => $yes)
                $this->addCampaignFeed($campaignid, urldecode($feedurl));
            }  
            
            $this->add_success = sprintf(__('Feeds added successfully. <a href="%s">Edit campaign</a>', 'wpomatic'), $this->adminurl . '&s=edit&id=' . $campaignid);
            
            break;
            
          // All feeds into new campaign
          case '3':
            $title = $_REQUEST['import_new_campaign'];
            $slug = WPOTools::stripText($title);
            $this->wpdb->query("INSERT INTO {$this->db['campaign']} (title, active, slug, lastactive, count) VALUES ('$title', 0, '$slug', 0, 0) ");
            $campaignid = $this->wpdb->insert_id;
            
            // Add feeds
            foreach($_REQUEST['feed'] as $cid => $feeds)
            {
              // Add feeds              
              foreach($feeds as $feedurl => $yes)
                $this->addCampaignFeed($campaignid, urldecode($feedurl));
            }
            
            $this->add_success = sprintf(__('Feeds added successfully. <a href="%s">Edit campaign</a>', 'wpomatic'), $this->adminurl . '&s=edit&id=' . $campaignid);
            
            break;
        }
      }
    }
  }
  
  /**
   * Export
   *
   *
   */
  function adminExport()
  {    
    if(isset($this->export_error))
      $error = $this->export_error;
    
    $campaigns = $this->getCampaigns();
    
    do_action('wpo_admin-export');
    
    include(WPOTPL . 'export.php');    
  }
  
  /** 
   * Export process
   *
   *
   */
  function adminExportRequest()
  {
    $campaigns = array();
    foreach($_REQUEST['export_campaign'] as $cid)
    {
      $campaign = $this->getCampaignById($cid);
      $campaign->feeds = (array) $this->getCampaignFeeds('campaign=' . $cid);
      $campaigns[] = $campaign;
    }
  
    header("Content-type: text/x-opml");
    header('Content-Disposition: attachment; filename="wpomatic.opml"');
  
    do_action('wpo_admin-export_request');  
  
    include(WPOTPL . 'export.opml.php');
    exit;
  }
  
  /**
   * Tests a feed
   *
   *
   */
  function adminTestfeed()
  {
    if(!isset($_REQUEST['url'])) return false;
    
    $url = $_REQUEST['url'];
    $feed = $this->fetchFeed($url, true);
    $works = ! $feed->error(); // if no error returned
    
    do_action('wpo_admin-testfeed');    
    
    if(defined('DOING_AJAX'))
      $this->json = array('status' => (int) $works);
    else
      include(WPOTPL . 'testfeed.php');
  }
  
  /**
   * Checks submitted campaign edit form for errors
   * 
   *
   * @return array  errors 
   */
  function adminCampaignRequest()
  {  
    # Main data
    $this->campaign_data = $this->campaign_structure;
    $this->campaign_data['main'] = array(      
        'title'         => $_REQUEST['campaign_title'],
        'active'        => isset($_REQUEST['campaign_active']),
        'slug'          => $_REQUEST['campaign_slug'],
        'template'      => (isset($_REQUEST['campaign_templatechk'])) 
                            ? $_REQUEST['campaign_template'] : null,
        'frequency'     => intval($_REQUEST['campaign_frequency_d']) * 86400 
                          + intval($_REQUEST['campaign_frequency_h']) * 3600 
                          + intval($_REQUEST['campaign_frequency_m']) * 60,
        'cacheimages'   => (int) isset($_REQUEST['campaign_cacheimages']),
        'feeddate'      => (int) isset($_REQUEST['campaign_feeddate']),
        'status'      => $_REQUEST['campaign_posttype'],
        'author'        => sanitize_user($_REQUEST['campaign_author']),
        'comment_status' => $_REQUEST['campaign_commentstatus'],
        'allowpings'    => (int) isset($_REQUEST['campaign_allowpings']),
        'dopingbacks'   => (int) isset($_REQUEST['campaign_dopingbacks']),
        'max'           => intval($_REQUEST['campaign_max']),
        'linktosource'  => (int) isset($_REQUEST['campaign_linktosource'])
    );
    
    // New feeds     
    foreach($_REQUEST['campaign_feed']['new'] as $i => $feed) 
    {
      $feed = trim($feed);
      
      if(!empty($feed))
      {        
        if(!isset($this->campaign_data['feeds']['new']))
          $this->campaign_data['feeds']['new'] = array();
          
        $this->campaign_data['feeds']['new'][$i] = $feed;
      }
    } 
    
    // Existing feeds to delete
    if(isset($_REQUEST['campaign_feed']['delete']))
    {
      $this->campaign_data['feeds']['delete'] = array();
      
      foreach($_REQUEST['campaign_feed']['delete'] as $feedid => $yes)
        $this->campaign_data['feeds']['delete'][] = intval($feedid);
    }
    
    // Existing feeds.
    if(isset($_REQUEST['id']))
    {
      $this->campaign_data['feeds']['edit'] = array();
      foreach($this->getCampaignFeeds('campaign=' . intval($_REQUEST['id'])) as $feed)
        $this->campaign_data['feeds']['edit'][$feed->id] = $feed->url;
    }
    
    // Categories
    if(isset($_REQUEST['campaign_categories']))
    {
      foreach($_REQUEST['campaign_categories'] as $category)
      {
        $id = intval($category);
        $this->campaign_data['categories'][] = $category;
      }
    }
    
    # New categories
    if(isset($_REQUEST['campaign_newcat']))
    {
      foreach($_REQUEST['campaign_newcat'] as $k => $on)
      {
        $catname = $_REQUEST['campaign_newcatname'][$k];
        if(!empty($catname))
        {
          if(!isset($this->campaign_data['categories']['new']))
            $this->campaign_data['categories']['new'] = array();
          
          $this->campaign_data['categories']['new'][] = $catname;
        }
      }
    }
    
    // Rewrites
    if(isset($_REQUEST['campaign_word_origin']))
    {
      foreach($_REQUEST['campaign_word_origin'] as $id => $origin_data)
      {
        $rewrite = isset($_REQUEST['campaign_word_option_rewrite']) 
                && isset($_REQUEST['campaign_word_option_rewrite'][$id]); 
        $relink = isset($_REQUEST['campaign_word_option_relink']) 
                && isset($_REQUEST['campaign_word_option_relink'][$id]);  

        if($rewrite || $relink)
        {
          $rewrite_data = trim($_REQUEST['campaign_word_rewrite'][$id]);
          $relink_data = trim($_REQUEST['campaign_word_relink'][$id]);
        
          // Relink data field can't be empty
          if(($relink && !empty($relink_data)) || !$relink) 
          {
            $regex = isset($_REQUEST['campaign_word_option_regex']) 
                  && isset($_REQUEST['campaign_word_option_regex'][$id]);

            $data = array();        
            $data['origin'] = array('search' => $origin_data, 'regex' => $regex);
            
            if($rewrite)
              $data['rewrite'] = $rewrite_data;
              
            if($relink)
              $data['relink'] = $relink_data;
              
            $this->campaign_data['rewrites'][] = $data; 
          }  
        }
      }
    }
    
    $errors = array('basic' => array(), 'feeds' => array(), 'categories' => array(), 
                    'rewrite' => array(), 'options' => array());
    
    # Main    
    if(empty($this->campaign_data['main']['title']))
    {
      $errors['basic'][] = __('You have to enter a campaign title', 'wpomatic');
      $this->errno++;
    }
    
    # Feeds
    $feedscount = 0;
    
    if(isset($this->campaign_data['feeds']['new'])) $feedscount += count($this->campaign_data['feeds']['new']);
    if(isset($this->campaign_data['feeds']['edit'])) $feedscount += count($this->campaign_data['feeds']['edit']);
    if(isset($this->campaign_data['feeds']['delete'])) $feedscount -= count($this->campaign_data['feeds']['delete']);
    
    if(!$feedscount)
    {
      $errors['feeds'][] = __('You have to enter at least one feed', 'wpomatic');
      $this->errno++;
    } else {  
      if(isset($this->campaign_data['feeds']['new']))    
      {
        foreach($this->campaign_data['feeds']['new'] as $feed)
        {
          $simplepie = $this->fetchFeed($feed, true);
          if($simplepie->error())
          {
            $errors['feeds'][] = sprintf(__('Feed <strong>%s</strong> could not be parsed (SimplePie said: %s)', 'wpomatic'), $feed, $simplepie->error());
            $this->errno++;
          }          
        }  
      }
    }
    
    # Categories
    if(! sizeof($this->campaign_data['categories']))
    {
      $errors['categories'][] = __('Select at least one category', 'wpomatic');
      $this->errno++;
    }
    
    # Rewrite
    if(sizeof($this->campaign_data['rewrites']))
    {
      foreach($this->campaign_data['rewrites'] as $rewrite)
      {
        if($rewrite['origin']['regex'])
        {
          if(false === @preg_match($rewrite['origin']['search'], ''))
          {
            $errors['rewrites'][] = __('There\'s an error with the supplied RegEx expression', 'wpomatic');         
            $this->errno++;
          }
        }
      }
    }
    
    # Options    
    if(! get_userdatabylogin($this->campaign_data['main']['author']))
    {
      $errors['options'][] = __('Author username not found', 'wpomatic');
      $this->errno++;
    }
    
    if(! $this->campaign_data['main']['frequency'])
    {
      $errors['options'][] = __('Selected frequency is not valid', 'wpomatic');
      $this->errno++;
    }
    
    if(! ($this->campaign_data['main']['max'] === 0 || $this->campaign_data['main']['max'] > 0))
    {
      $errors['options'][] = __('Max items should be a valid number (greater than zero)', 'wpomatic');
      $this->errno++;
    }
    
    if($this->campaign_data['main']['cacheimages'] && !is_writable($this->cachepath))
    {
      $errors['options'][] = sprintf(__('Cache path (in <a href="%s">Options</a>) must be writable before enabling image caching.', 'wpomatic'), $this->adminurl . '&s=options' );
      $this->errno++;
    }
    
    $this->errors = $errors;
    
    apply_filters('wpo_add_campaign_data', $this->campaign_data);
    apply_filters('wpo_add_campaign_errors', $this->errors);
  }
  
  /**
   * Creates a campaign, and runs processEdit. If processEdit fails, campaign is removed
   *
   * @return campaign id if created successfully, errors if not
   */
  function adminProcessAdd()
  {
    // Insert a campaign with dumb data
    $this->wpdb->query(WPOTools::insertQuery($this->db['campaign'], array('lastactive' => 0, 'count' => 0)));
    $cid = $this->wpdb->insert_id;
    
    // Process the edit
    $this->campaign_data['main']['lastactive'] = 0;
    $this->adminProcessEdit($cid);    
    return $cid;
  }
 
  /**
   * Cleans everything for the given id, then redoes everything
   *
   * @param integer $id           The id to edit
   */
  function adminProcessEdit($id)
  {
    // If we need to execute a tool action we stop here
    if($this->adminProcessTools()) return;    
    
    // Delete all to recreate
    $this->wpdb->query("DELETE FROM {$this->db['campaign_word']} WHERE campaign_id = $id");
    $this->wpdb->query("DELETE FROM {$this->db['campaign_category']} WHERE campaign_id = $id");    
    
    // Process categories    
    # New
    if(isset($this->campaign_data['categories']['new']))
    {
      foreach($this->campaign_data['categories']['new'] as $category)
        $this->campaign_data['categories'][] = wp_insert_category(array('cat_name' => $category));
      
      unset($this->campaign_data['categories']['new']);
    }
    
    # All
    foreach($this->campaign_data['categories'] as $category)
    {
      // Insert
      $this->wpdb->query(WPOTools::insertQuery($this->db['campaign_category'], 
        array('category_id' => $category, 
              'campaign_id' => $id)
      ));
    }
    
    // Process feeds
    # New
    if(isset($this->campaign_data['feeds']['new']))
    {
      foreach($this->campaign_data['feeds']['new'] as $feed)
        $this->addCampaignFeed($id, $feed);        
    }
    
    # Delete
    if(isset($this->campaign_data['feeds']['delete']))
    {
      foreach($this->campaign_data['feeds']['delete'] as $feed)
        $this->wpdb->query("DELETE FROM {$this->db['campaign_feed']} WHERE id = $feed ");
    }
    
    // Process words
    foreach($this->campaign_data['rewrites'] as $rewrite)
    {
      $this->wpdb->query(WPOTools::insertQuery($this->db['campaign_word'], 
        array('word' => $rewrite['origin']['search'], 
              'regex' => $rewrite['origin']['regex'],
              'rewrite' => isset($rewrite['rewrite']),
              'rewrite_to' => isset($rewrite['rewrite']) ? $rewrite['rewrite'] : '',
              'relink' => isset($rewrite['relink']) ? $rewrite['relink'] : null,
              'campaign_id' => $id)
      ));
    }
    
    // Main 
    $main = $this->campaign_data['main'];

    // Fetch author id
    $author = get_userdatabylogin($this->campaign_data['main']['author']);
    $main['authorid'] = $author->ID;
    unset($main['author']);
    
    // Query
    $query = WPOTools::updateQuery($this->db['campaign'], $main, 'id = ' . intval($id));    
    $this->wpdb->query($query);
    
    do_action('wpo_admin-process_edit');
  }
  
  /**
   * Processes edit campaign tools actions
   *
   *
   */
  function adminProcessTools()
  {
    $id = intval($_REQUEST['id']);
    
    if(isset($_REQUEST['tool_removeall']))
    {      
      $posts = $this->getCampaignPosts($id);
      
      foreach($posts as $post)
      {
        $this->wpdb->query("DELETE FROM {$this->wpdb->posts} WHERE ID = {$post->post_id} ");
      }
            
      // Delete log
      $this->wpdb->query("DELETE FROM {$this->db['campaign_post']} WHERE campaign_id = {$id} ");
      
      // Update feed and campaign posts count
      $this->wpdb->query(WPOTools::updateQuery($this->db['campaign'], array('count' => 0), "id = {$id}"));
      $this->wpdb->query(WPOTools::updateQuery($this->db['campaign_feed'], array('hash' => 0, 'count' => 0), "campaign_id = {$id}"));
      
      $this->tool_success = __('All posts removed', 'wpomatic');
      do_action('wpo_admin-process_tool-remove');
      return true;
    }
    
    if(isset($_REQUEST['tool_changetype']))
    {
      $this->adminUpdateCampaignPosts($id, array(
        'post_status' => $this->wpdb->escape($_REQUEST['campaign_tool_changetype'])
      ));
      
      $this->tool_success = __('Posts status updated', 'wpomatic');
      do_action('wpo_admin-process_tool-changetype');      
      return true;
    }
    
    if(isset($_REQUEST['tool_changeauthor']))
    {
      $author = get_userdatabylogin($_REQUEST['campaign_tool_changeauthor']);

      if($author)
      {
        $authorid = $author->ID;      
        $this->adminUpdateCampaignPosts($id, array('post_author' => $authorid)); 
      } else {
        $this->errno = 1;
        $this->errors = array('tools' => array(sprintf(__('Author %s not found', 'wpomatic'), attribute_escape($_REQUEST['campaign_tool_changeauthor']))));
      }
      
      $this->tool_success = __('Posts status updated', 'wpomatic');
      do_action('wpo_admin-process_tool-changeauthor');
      return true;
    }

    do_action('wpo_admin-process_tools');
    
    return false;
  }
  
  function adminUpdateCampaignPosts($id, $properties)
  {
    $posts = $this->getCampaignPosts($id);
    
    foreach($posts as $post)
      $this->wpdb->query(WPOTools::updateQuery($this->wpdb->posts, $properties, "ID = {$post->id}"));
      
    do_action('wpo_alter_post', $id, $properties);
  }
  
  
  /** 
   * Show logs
   *
   *
   */  
  function adminLogs()
  {
    // Clean logs?
    if(isset($_REQUEST['clean_logs']))
    {
      check_admin_referer('clean-logs');
      $this->wpdb->query("DELETE FROM {$this->db['log']} WHERE 1=1 ");
    }
    
    // Logs to show per page
    $logs_per_page = 20;
        
    $page = isset($_REQUEST['p']) ? intval($_REQUEST['p']) : 0;
    $total = $this->wpdb->get_var("SELECT COUNT(*) as cnt FROM {$this->db['log']} ");
    $logs = $this->getLogs("page={$page}&perpage={$logs_per_page}");
    
    $paging = paginate_links(array(
      'base' => $this->adminurl . '&s=logs&%_%',
      'format' => 'p=%#%',
      'total' => ceil($total / $logs_per_page),
      'current' => $page,
      'end_size' => 3
    ));
    
    do_action('wpo_admin-logs');
    
    include(WPOTPL . 'logs.php');
  }
}        

$wpomatic = WPOMatic::getInstance();