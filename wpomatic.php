<?php

/*
 * Plugin Name: WP-o-Matic
 * Description: Enables administrators to create posts automatically from RSS/Atom feeds.
 * Author: Guillermo Rauch
 * Plugin URI: http://devthought.com/wp-o-matic-the-wordpress-rss-agreggator/
 * Version: 1.0                     
 * =======================================================================
 
 Todo:
 - 'View campaign' view
 - Bulk actions in campaign list
 - Pagination/sorting in campaign list
 - Advanced search in campaign list
 - 'Time ago' for 'Last active' (on hover) in campaign list
 - Image thumbnailing option
 - More advanced post templates
 - Advanced filters
 - Finish help
 - Import drag and drop to current campaigns.
 - Export extended OPML to save WP-o-Matic options
 - Proper commenting
 
 Changelog:                         
 - 0.1beta
   WP-o-Matic released.
   
 - 0.2beta:           
   Fixed use of MagpieRSS legacy functions. 
   Updated cron code to check every twenty minutes. 
   Wordpress pseudocron disabled.
   
 - 1.0RC1:                         
   Renamed everything to WPOMatic, instead of the previous WPRSS.
   Renamed "lib" to "inc"       
   SimplePie updated to 1.0.1 (Razzleberry), relocated and server compatibility tests included.            
   Static reusable functions moved to WPOTools class.
   Improved Unix detection for cron.
   Removed MooTools dependency for optimization reasons. 
   Redesigned admin panel, now divided into sections. 
   Logging now database-based.                
   Posts are now saved in a WP-o-Matic table. They're later parsed and created as posts.
   Added a dashboard with quick stats and log display. 
   Added campaign support to centralize options for multiple feeds.
   Added import/export support through OPML files   
   Added image caching capabilities.
   Added word/phrase rewriting and relinking capabilities.   
   Added nonce support         
   Added i18n support with translation domain 'wpomatic'             
   Added help throughout the system.
                    
 - 1.0
   Added compatibility with Wordpress 2.3
   Added setup screen
   
*/    
                         
# WP-o-Matic paths. With trailing slash.
define('WPODIR', dirname(__FILE__) . '/');                
define('WPOINC', WPODIR . 'inc/');   
define('WPOTPL', WPOINC . 'admin/');
    
# Dependencies                            
require_once( WPOINC . 'tools.class.php' );               
            
class WPOMatic {               
            
  var $version = '1.0';              
                           
  # Editable options
  var $delete_tables = false;  # only if you know what you're doing
                         
  # Internal            
  var $sections = array('home', 'setup', 'list', 'add', 'edit', 'options', 'import', 'export',
                        'reset', 'delete', 'logs', 'testfeed', 'forcefetch');  
                        
  var $campaign_structure = array('main' => array(), 'rewrites' => array(), 
                                  'categories' => array(), 'feeds' => array());
  
  # __construct()
  function WPOMatic()
  {              
    global $wpdb;
                                     
    # Table names init
    $this->db = array(
      'campaign'            => $wpdb->prefix . 'wpo_campaign',
      'campaign_category'   => $wpdb->prefix . 'wpo_campaign_category',
      'campaign_feed'       => $wpdb->prefix . 'wpo_campaign_feed',     
      'campaign_word'       => $wpdb->prefix . 'wpo_campaign_word',   
      'campaign_post'       => $wpdb->prefix . 'wpo_campaign_post',
      'log'                 => $wpdb->prefix . 'wpo_log'
    );                                    
    
    # Is installed ?
    $this->installed = get_option('wpo_version') == $this->version;
    $this->setup = get_option('wpo_setup');
    
    # Actions
    add_action('activate_wp-o-matic/wpomatic.php', array(&$this, 'install'));                 # Plugin installed
    add_action('deactivate_wp-o-matic/wpomatic.php', array(&$this, 'uninstall'));             # Plugin unintalled
    add_action('init', array(&$this, 'init'));                                                # Wordpress init      
    add_action('admin_footer', array(&$this, 'adminWarning'));                                # Admin footer
    add_action('admin_menu', array(&$this, 'adminMenu'));                                     # Admin menu creation            
   
    # Ajax actions
    add_action('wp_ajax_delete-campaign', array(&$this, 'adminDelete'));
    add_action('wp_ajax_test-feed', array(&$this, 'adminTestfeed'));
   
    # WP-o-Matic URIs. Without trailing slash               
    $this->optionsurl = get_option('siteurl') . '/wp-admin/options-general.php';                                           
    $this->adminurl = $this->optionsurl . '?page=wpomatic.php';
    $this->pluginpath = get_option('siteurl') . '/wp-content/plugins/wp-o-matic';           
    $this->helpurl = $this->pluginpath . '/help.php?item=';
    $this->tplpath = $this->pluginpath . '/inc/admin';
    $this->cachepath = WPODIR . get_option('wpo_cachepath');
    
    # Cron command / url
    $this->cron_command = attribute_escape('*/20 * * * * '. $php . ' ' . dirname(__FILE__)  . '/cron.php?code=' . get_option('wpo_croncode'));        
    $this->cron_url = $this->pluginpath . '/cron.php?code=' . get_option('wpo_croncode');
  }
  
  /**
   * Called when plugin is activated 
   *
   *
   */ 
  function install()
  {
    global $wpdb;
    
    if(file_exists(ABSPATH . '/wp-admin/upgrade-functions.php'))
      require_once(ABSPATH . '/wp-admin/upgrade-functions.php');
    else
      require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
                           
    if(! $this->installed) 
    {			
			# wpo_campaign
			dbDelta( "CREATE TABLE `{$this->db['campaign']}` (
							    `id` int(11) unsigned NOT NULL auto_increment,
							    `title` varchar(250) NOT NULL, 
							    `active` tinyint(1) default '1', 
							    `slug` varchar(250),         
							    `template` MEDIUMTEXT,         
  							  `frequency` int(5) default '180',
							    `feeddate` tinyint(1) default '0', 
							    `cacheimages` tinyint(1) default '1',
							    `posttype` varchar(50),
							    `authorid` int(11),                  
							    `allowcomments` tinyint(1) default '1',
							    `allowpings` tinyint(1) default '1',
							    `dopingbacks` tinyint(1) default '1',
							    `count` int(11),
							    `lastactive` timestamp,
							    `created_on` timestamp,  							  
							    PRIMARY KEY (`id`)
						   );" ); 
		 
		 # wpo_campaign_category 			               
     dbDelta(  "CREATE TABLE `{$this->db['campaign_category']}` (
  						    `id` int(11) unsigned NOT NULL auto_increment,
  							  `category_id` int(11) NOT NULL,
  							  `campaign_id` int(11) NOT NULL,
  							  PRIMARY KEY  (`id`)
  						 );" );              
  	 
  	 # wpo_campaign_feed 				 
     dbDelta(  "CREATE TABLE `{$this->db['campaign_feed']}` (
  						    `id` int(11) unsigned NOT NULL auto_increment,
  							  `campaign_id` int(11) NOT NULL,   
  							  `url` varchar(255) NOT NULL,  
  							  `type` varchar(255) NOT NULL,    
  							  `title` varchar(255) NOT NULL,   
  							  `description` varchar(255) NOT NULL,
  							  `logo` varchar(255),                         
  							  `count` int(11),
  							  `hash` varchar(255),
  							  `lastactive` timestamp,							    
  							  PRIMARY KEY  (`id`)
  						 );" );  
  						 
    # wpo_campaign_post				 
    dbDelta(  "CREATE TABLE `{$this->db['campaign_post']}` (
    				    `id` int(11) unsigned NOT NULL auto_increment,
    					  `campaign_id` int(11) NOT NULL,
    					  `feed_id` int(11) NOT NULL,
    					  `post_id` int(11) NOT NULL,						    
    					  PRIMARY KEY  (`id`)
    				 );" ); 
  						 
  	 # wpo_campaign_word 				 
     dbDelta(  "CREATE TABLE `{$this->db['campaign_word']}` (
  						    `id` int(11) unsigned NOT NULL auto_increment,
  							  `campaign_id` int(11) NOT NULL,
  							  `word` varchar(255) NOT NULL,
							    `regex` tinyint(1) default '0',
  							  `rewrite` tinyint(1) default '1',
  							  `rewrite_to` varchar(255),
  							  `relink` varchar(255),
  							  PRIMARY KEY  (`id`)
  						 );" );  						 
		                      
		 # wpo_log 			
     dbDelta(  "CREATE TABLE `{$this->db['log']}` (
  						    `id` int(11) unsigned NOT NULL auto_increment,
  							  `message` varchar(255) NOT NULL,
  							  `created_on` timestamp,
  							  PRIMARY KEY  (`id`)
  						 );" ); 			      
  						 
  	  add_option('wpo_version', $this->version);
  	  $this->installed = true;
    }                       
    
    # Options   
    WPOTools::addOptions(array(
      'wpo_log'         => array(1, 'Log WP-o-Matic actions'),
      'wpo_unixcron'    => array(WPOTools::isUnix(), 'Use unix-style cron'),
      'wpo_croncode'    => array(substr(md5(time()), 0, 8), 'Cron job password.'),
      'wpo_cacheimages' => array(0, 'Cache all images. Overrides campaign options'),
      'wpo_cachepath'   => array('cache', 'Cache path relative to wpomatic directory')
    ));
  }                                                                                      
  
  /**
   * Called when plugin is deactivated 
   *
   *
   */
  function uninstall()
  {   
    global $wpdb;
      
    // Delete tables
    if($this->delete_tables)
    {
      foreach($this->db as $table) 
        $wpdb->query("DROP TABLE `{$table}` ");
    }                                
    
    // Delete options
    WPOTools::deleteOptions(array('wpo_version', 'wpo_setup', 'wpo_log', 'wpo_unixcron', 'wpo_croncode', 'wpo_cacheimages', 'wpo_cachepath'));
  }                                                                                              
   
  /**
   * Called when blog is initialized 
   *
   *
   */
  function init() 
  {
    global $wpdb;
    
    if($this->installed)
    {
      if(! get_option('wpo_unixcron'))
        $this->processAll();   

      if(isset($_REQUEST['page']))
      {
        if(isset($_REQUEST['campaign_add']) || isset($_REQUEST['campaign_edit']))
          $this->adminCampaignRequest();

        $this->adminExportProcess();
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
    global $wpdb;
    
    if(get_option('wpo_log'))
    {
      $message = $wpdb->escape($message);
      $wpdb->query("INSERT INTO `{$this->db['log']}` (message, created_on) VALUES ('$message', NOW()) "); 
    }
  }
    
  /**
   * Called by cron.php to update the site
   *
   *
   */     
  function runCron($log = true)
  {
    $this->log('Running cron job');   
    $this->processAll();
  }   
  
  /**
   * Processes all campaigns
   *
   */
  function processAll()
  {
    @set_time_limit(0);
    
    $campaigns = $this->getCampaigns('unparsed=1');

    foreach($campaigns as $campaign) 
    {
      if($campaign->active)
        $this->processCampaign($campaign);
    }
  }
  
  /**
   * Processes a campaign
   *
   * @param   object    $campaign   Campaign database object
   * @return  integer   Number of processed items
   */  
  function processCampaign(&$campaign)
  {
    global $wpdb;
    
    // Log 
    $this->log('Processing campaign ' . $campaign->title . ' (ID: ' . $campaign->id . ')');
    
    // Get campaign
    $campaign = is_integer($campaign) ? $this->getCampaignById($campaign) : $campaign;
    
    // Get feeds
    $count = 0;
    $feeds = $this->getCampaignFeeds($campaign->id);    
    
    foreach($feeds as $feed)
      $count += $this->processFeed($campaign, $feed);

    $wpdb->query(WPOTools::updateQuery($this->db['campaign'], array(
      'count' => $campaign->count + $count,
      'lastactive' => current_time('mysql')
    ), "id = {$campaign->id}"));
    
    return $count;
  } 
  
  /**
   * Processes a feed
   *
   * @param   $campaign   object    Campaign database object   
   * @param   $feed       object    Feed database object
   * @return  The number of items added to database
   */
  function processFeed(&$campaign, &$feed)
  {
    global $wpdb;
    
    // Log
    $this->log('Processing feed ' . $feed->title . ' (ID: ' . $feed->id . ')');
    
    // Access the feed
    $simplepie = $this->fetchFeed($feed->url);
    
    // Get posts (last is first)
    $items = array();
    $count = 0;
    
    foreach($simplepie->get_items() as $item)
    {
      if($feed->hash == $item->get_id())
      {
        if($count == 0) $this->log('No new posts');
        break;
      }        
      
      $count++;
      array_unshift($items, $item);
    }
    
    // Processes post stack
    foreach($items as $item)
    {
      $this->processItem($campaign, $feed, $item);
      $lasthash = $item->get_id();
    }
    
    // If we have added items, let's update the hash
    if($count)
    {
      $wpdb->query(WPOTools::updateQuery($this->db['campaign_feed'], array(
        'count' => $count,
        'lastactive' => current_time('mysql'),
        'hash' => $lasthash
      ), "id = {$feed->id}"));    
    
      $this->log( $count . ' posts added' );
    }
    
    return $count;
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
    global $wpdb;
    
    $this->log('Processing item');
    
    // Item content
    $content = $this->parseItemContent($campaign, $feed, $item);
    
    // Item date
    if($campaign->feeddate && ($item->get_date('U') > (time() - $campaign->frequency) && $item->get_date('U') < time()))
      $date = $item->get_date('U') + (get_option('gmt_offset') * 3600);
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
    
    // Create post
    $postid = $this->insertPost($wpdb->escape($item->get_title()), $wpdb->escape($content), $date, $categories, $campaign->posttype, $campaign->authorid, $campaign->allowpings, $campaign->alowcomments, $meta);
    
    // If pingback/trackbacks
    if($campaign->dopingbacks)
    {
      require_once(ABSPATH . WPINC . '/comment.php');
    	pingback($content, $postid);      
    }      
    
    // Save post to log database
    $wpdb->query(WPOTools::insertQuery($this->db['campaign_post'], array(
      'campaign_id' => $campaign->id,
      'feed_id' => $feed->id,
      'post_id' => $postid
    )));
  }
  
  /**
   * Writes a post to blog
   *
   *  
   * @param   string    $title          Post title
   * @param   string    $content        Post content
   * @param   integer   $timestamp      Post timestamp
   * @param   array     $category       Array of categories
   * @param   string    $status         'draft', 'published' or 'private'
   * @param   integer   $authorid       ID of author.
   * @param   boolean   $allowpings     Allow pings
   * @param   boolean   $allowcomments  Allow comments
   * @param   array     $meta           Meta key / values
   * @return  integer   Created post id
   */
  function insertPost($title, $content, $timestamp = null, $category = null, $status = 'draft', $authorid = null, $allowpings = true, $allowcomments = true, $meta = array())
  {
    $date = ($timestamp) ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    $postid = wp_insert_post(array(
		  'post_title' 	            => $title,
  		'post_content'  	        => $content,
  		'post_content_filtered'  	=> $content,
  		'post_category'           => $category,
  		'post_status' 	          => $status,
  		'post_author'             => $authorid,
  		'post_date'               => $date,
  		'comment_status'          => $allowcomments,
  		'ping_status'             => $allowpings
    ));
    	
		foreach($meta as $key => $value) 
			$this->insertPostMeta($postid, $key, $value);			
		
		return $postid;
  }
  
  /**
   * insertPostMeta
   *
   *
   */
	function insertPostMeta($postid, $key, $value) {
		global $wpdb;
		
		$result = $wpdb->query( "INSERT INTO `$wpdb->postmeta` (post_id,meta_key,meta_value ) " 
					                . " VALUES ('$postid','$key','$value') ");
					
		return $wpdb->insert_id;		
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
      '{content}',
      '{title}',
      '{permalink}',
      '{feedurl}',
      '{feedtitle}',
      '{feedlogo}',
      '{campaigntitle}',
      '{campaignid}',
      '{campaignslug}'
    );
    
    $replace = array(
      $content,
      $item->get_title(),
      $item->get_link(),
      $feed->url,
      $feed->title,
      $feed->logo,
      $campaign->title,
      $campaign->id,
      $campaign->slug
    );
    
    $content = str_replace($vars, $replace, ($campaign->template) ? $campaign->template : '{content}');
    
    // Rewrite
    $rewrites = $this->getCampaignData($campaign->id, 'rewrites');
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
          $content = str_replace($origin, $reword, $content);
      } else if(isset($rewrite['relink'])) 
        $content = str_replace($origin, '<a href="'. $rewrite['relink'] .'">' . $origin . '</a>', $content);
    }
    
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
    
    $cachepath = $this->cachepath;
    
    if(is_writeable($cachepath) && $contents)
    { 
      file_put_contents($cachepath . '/' . $filename, $contents);
      return $this->pluginpath . '/' . get_option('wpo_cachepath') . '/' . $filename;
    }
    
    return false;
  }
   
  /**
   * Parses a feed with SimplePie
   *
   * @param   boolean   $stupidly_fast    Set fast mode. Best for checks
   * @return  SimplePie_Item    Feed object 
   * @return  string            Error string if $check is enabled
   */
  function fetchFeed($url, $stupidly_fast = false)
  {
    # SimplePie
    require_once( WPOINC . 'simplepie/simplepie.class.php' );
    
    $feed = new SimplePie();
    $feed->set_feed_url($url);
    $feed->set_stupidly_fast($stupidly_fast);
    $feed->enable_cache(false);    
    $feed->init();
    $feed->handle_content_type();
                                 
    return $feed;
  }
  
  /**
   * Returns all blog categories
   *
   *
   */
  function getBlogCategories()
  {
    global $wpdb;
    $categories = get_categories('hide_empty=0');
    
    return $categories;
  }
  
  /**
   * Returns all data for a campaign
   *
   *
   */
  function getCampaignData($id, $section = null)
  {
    global $wpdb;
    $campaign = (array) $this->getCampaignById($id);
    
    if($campaign)
    {
      $campaign_data = $this->campaign_structure;
      
      // Main
      if(!$section || $section == 'main')
      {
        $campaign_data['main'] = array_merge($campaign_data['main'], (array) $campaign);
        $userdata = get_userdata($campaign_data['main']['authorid']);
        $campaign_data['main']['author'] = $userdata->user_login; 
      }
      
      // Categories
      if(!$section || $section == 'categories')
      {
        $categories = $wpdb->get_results("SELECT * FROM `{$this->db['campaign_category']}` WHERE campaign_id = $id");
        foreach($categories as $category)
          $campaign_data['categories'][] = $category->category_id;
      }
      
      // Feeds
      if(!$section || $section == 'feeds')
      {
        $campaign_data['feeds']['edit'] = array();
        
        $feeds = $this->getCampaignFeeds($id);
        foreach($feeds as $feed)
          $campaign_data['feeds']['edit'][$feed->id] = $feed->url;
      }
      
      // Rewrites      
      if(!$section || $section == 'rewrites')
      {
        $rewrites = $wpdb->get_results("SELECT * FROM `{$this->db['campaign_word']}` WHERE campaign_id = $id");
        foreach($rewrites as $rewrite)
        {
          $word = array('origin' => array('search' => $rewrite->word, 'regex' => $rewrite->regex), 'rewrite' => $rewrite->rewrite_to, 'relink' => $rewrite->relink);
        
          if(! $rewrite->rewrite) unset($word['rewrite']);
          if(empty($rewrite->relink)) unset($word['relink']);
        
          $campaign_data['rewrites'][] = $word;
        }
      }

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
    global $wpdb;
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
  	
  	return $wpdb->get_results("SELECT * FROM `{$this->db['log']}` ORDER BY $orderby $ordertype $limit");
  }
           
  /**
   * Retrieves a campaign by its id
   *
   *
   */  
  function getCampaignById($id)
  {
    global $wpdb;
    
    $id = intval($id);
    return $wpdb->get_row("SELECT * FROM `{$this->db['campaign']}` WHERE id = $id");
  }
  
  /**
   * Retrieves a feed by its id
   *
   *
   */  
  function getFeedById($id)
  {
    global $wpdb;
    
    $id = intval($id);
    return $wpdb->get_row("SELECT * FROM `{$this->db['campaign_feed']}` WHERE id = $id");
  }
         
  /**
   * Retrieves campaigns from database
   *
   *
   */       
  function getCampaigns($args = '') 
  {   
    global $wpdb;
    extract(WPOTools::getQueryArgs($args, array('fields' => '*',      
                                                'search' => '',
                                                'orderby' => 'created_on', 
                                                'ordertype' => 'DESC', 
                                                'where' => '',
                                                'unparsed' => false,
                                                'limit' => null)));
  
  	if(! empty($search))
  	  $where .= "AND title LIKE '%{$search}%' ";
  	  
  	if($unparsed)
  	  $where .= "AND (frequency + lastactive) < NOW()";
  	                              
  	$sql = "SELECT $fields FROM `{$this->db['campaign']}` WHERE 1 = 1 $where "
         . "ORDER BY $orderby $ordertype $limit";
         
  	return $wpdb->get_results($sql);
  }            
  
  /**
   * Retrieves feeds for a certain campaign
   *
   * @param   integer   $id     Campaign id
   */
  function getCampaignFeeds($id)
  {    
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM `{$this->db['campaign_feed']}` WHERE campaign_id = $id");
  }
  
  /**
   * Retrieves all WP posts for a certain campaign
   *
   * @param   integer   $id     Campaign id
   */
  function getCampaignPosts($id)
  {
    global $wpdb;
    return $wpdb->get_results("SELECT post_id FROM `{$this->db['campaign_post']}` WHERE campaign_id = $id ");          
  }
  
  /**
   * Adds a feed by url and campaign id
   *
   *
   */
  function addCampaignFeed($id, $feed)
  {
    global $wpdb;
    
    $simplepie = $this->fetchFeed($feed, true);
    $url = $wpdb->escape($simplepie->subscribe_url());
    
    // If it already exists, ignore it
    if(! $wpdb->get_var("SELECT id FROM `{$this->db['campaign_feed']}` WHERE campaign_id = $id AND url = '$url' "))
    {
      $wpdb->query(WPOTools::insertQuery($this->db['campaign_feed'], 
        array('url' => $url, 
              'title' => $wpdb->escape($simplepie->get_title()),
              'description' => $wpdb->escape($simplepie->get_description()),
              'logo' => $wpdb->escape($simplepie->get_image_url()),
              'campaign_id' => $id)
      ));  
      
      return $wpdb->insert_id;
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
    global $wpdb;
    extract(WPOTools::getQueryArgs($args, array('fields' => '*',  
                                                'campid' => '',    
                                                'join' => false,
                                                'orderby' => 'created_on', 
                                                'ordertype' => 'DESC', 
                                                'limit' => null)));
  	
  	$sql = "SELECT $fields FROM `{$this->db['campaign_feed']}` cf ";
  	
  	if(!empty($join))
  	  $sql .= "INNER JOIN `{$this->db['campaign']}` camp ON camp.id = cf.campaign_id ";
  	     
  	if(!empty($campid))
  	  $sql .= "WHERE cf.campaign_id = $campid";
  	
  	return $wpdb->get_results($sql);  
  }        
  
  /**
   * Inserts a post into Wordpress
   * 
   * @param object  $campaign   Campaign object  
   * @param object  $feed       Feed object
   * @param array   $post       Post array (title, content, date, etc)
   */
  function createPost($campaign, $feed, $data)
  {
    $this->log('Post created');
  }
  
  /**
   * Called when WP-o-Matic admin pages initialize.
   * 
   * 
   */
  function adminInit() 
  {                 
    auth_redirect();
    
    $this->section = ($this->setup) ? ($_REQUEST['s'] ? $_REQUEST['s'] : $this->sections[0]) : 'setup';
    
    wp_enqueue_script('prototype');
    wp_enqueue_script('wpoadmin', $this->tplpath . '/admin.js', array('prototype'), $this->version);
    
    if($this->section == 'list')
      wp_enqueue_script('listman');
          
    if(WPOTools::isAjax())
    {              
      $this->admin();
      exit;
    }      
  }
  
  /**
   * Shows a warning fading box
   *
   * -idea from Akismet plugin
   */
  function adminWarning()
  {
    if(! $this->setup && $this->section != 'setup')
    {
      echo "<div id='wpo-warning' class='updated fade-ff0000'><p>".sprintf(__('Please <a href="%s">click here</a> to setup and configure WP-o-Matic.', 'wpomatic'), $this->adminurl . '&amp;s=setup')."</p></div>

  		  <style type='text/css'>
  		    #adminmenu { margin-bottom: 5em; }
  		    #wpo-warning { position: absolute; top: 6.8em; }
  		  </style>
  	  "; 
    }
  }
  
  /**
   * Executes the current section method.
   * 
   *
   */
  function admin()
  {                       
    if(in_array($this->section, $this->sections))
    {
      $method = 'admin' . ucfirst($this->section);
      $this->$method();        
    }  
  }
    
  /**
   * Adds the WP-o-Matic item to menu 
   *           
   * 
   */
  function adminMenu()
  {
    add_submenu_page('options-general.php', 'WP-o-Matic', 'WP-o-Matic', 10, basename(__FILE__), array(&$this, 'admin'));
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
  }                                                                  
                                
  /**
   * Outputs the admin footer in a template
   *            
   * 
   */
  function adminFooter()
  {
    include(WPOTPL . 'footer.php');
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
    $nextcampaigns = $this->getCampaigns('fields=id,title,lastactive,frequency&limit=5&orderby=UNIX_TIMESTAMP(lastactive)%2Bfrequency&ordertype=ASC');
    $lastcampaigns = $this->getCampaigns('fields=id,title,lastactive,frequency&limit=5&where=AND UNIX_TIMESTAMP(lastactive)>0&orderby=lastactive');
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
      update_option('wpo_unixcron', isset($_REQUEST['option_unixcron']));
      update_option('wpo_setup', 1);
      
      header('Location: ' . $this->adminurl);
      exit;
    }
    
    $php = WPOTools::getBinaryPath('php', '/usr/bin/php');
    $nophp = !file_exists($php);        
    $safe_mode = ini_get('safe_mode');
        
    $command = $this->cron_command;
    $url = $this->cron_url;
        
    include(WPOTPL . 'setup.php');
  }     

  /**
   * List campaigns section
   *
   *
   */
  function adminList()
  {                                                                                
    global $wpdb;
    
    if(isset($_REQUEST['q']))
    {
      $q = $wpdb->escape($_REQUEST['q']);
      $campaigns = $this->getCampaigns('search=' . $q);
    } else
      $campaigns = $this->getCampaigns('orderby=CREATED_ON');
  
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
      check_admin_referer('wpomatic-edit-campaign');
      
      if(! $this->errno)
        $addedid = $this->adminProcessAdd();
      else      
        $data = $this->campaign_data;
    }
    
    $categories = $this->getBlogCategories();	      
    $campaign_add = true;
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
    if(!$id) die("Can't be called directly");
    
    if(isset($_REQUEST['campaign_edit']))
    {
      check_admin_referer('wpomatic-edit-campaign');
            
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
    
    $categories = $this->getBlogCategories();	
    $campaign_edit = true;
    include(WPOTPL . 'edit.php');
  }
  
  /**
   * Resets a campaign (sets post count to 0, forgets last parsed post)
   *
   *
   * @todo Make it ajax-compatible here and add javascript code
   */
  function adminReset()
  {
    global $wpdb;
    
    $id = intval($_REQUEST['id']);

    if(! defined('DOING_AJAX'))
      check_admin_referer('reset-campaign_'.$id);    
      
    // Reset count and lasactive
    $wpdb->query(WPOTools::updateQuery($this->db['campaign'], array(
      'count' => 0,
      'lastactive' => 0
    ), "id = $id"));
      
    // Reset feeds hashes, count, and lasactive
    foreach($this->getCampaignFeeds($id) as $feed)
    {
      $wpdb->query(WPOTools::updateQuery($this->db['campaign_feed'], array(
        'count' => 0,
        'lastactive' => 0,
        'hash' => ''
      ), "id = {$feed->id}"));
    }
      
    if(defined('DOING_AJAX'))
      die('1');
    else
      header('Location: ' . $this->adminurl . '&s=list');
  }
  
  /**
   * Deletes a campaign
   *
   *
   */
  function adminDelete()
  {
    global $wpdb;
    
    $id = intval($_REQUEST['id']);

    // If not called through admin-ajax.php
    if(! defined('DOING_AJAX'))
      check_admin_referer('delete-campaign_'.$id);    
      
    $wpdb->query("DELETE FROM `{$this->db['campaign']}` WHERE id = $id");
    $wpdb->query("DELETE FROM `{$this->db['campaign_feed']}` WHERE campaign_id = $id");
    $wpdb->query("DELETE FROM `{$this->db['campaign_word']}` WHERE campaign_id = $id");
    $wpdb->query("DELETE FROM `{$this->db['campaign_category']}` WHERE campaign_id = $id");
    
    if(defined('DOING_AJAX'))
      die('1');
    else
      header('Location: ' . $this->adminurl . '&s=list');
  }
  
  /**
   * Options section 
   *
   *
   */
  function adminOptions()
  {  
              
    if(isset($_REQUEST['update']))
    {              
      update_option('wpo_unixcron',     isset($_REQUEST['option_unixcron']));
      update_option('wpo_log',          isset($_REQUEST['option_logging']));
      update_option('wpo_cacheimages',  isset($_REQUEST['option_caching']));
      update_option('wpo_cachepath',    rtrim($_REQUEST['option_cachepath'], '/'));
      
      $updated = 1;
    }
    
    if(!is_writable(WPODIR . get_option('wpo_cachepath')))
      $not_writable = true;
    
    include(WPOTPL . 'options.php');
  }
   
  /**
   * Import section
   *
   *
   */
  function adminImport()
  {  
    global $wpdb;
    
    @session_start();
    
    if(!$_POST) unset($_SESSION['opmlimport']);
    
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
      
    include(WPOTPL . 'import.php');
  }      
  
  /**
   * Import process
   *
   *
   */
  function adminImportProcess()
  {
    global $wpdb;
    
    if(isset($_REQUEST['add']))
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
                $title = $wpdb->escape($_REQUEST['campaign'][$campaignid]);
                if(!$title) continue;
                
                $slug = $wpdb->escape(WPOTools::stripText($title));
                $wpdb->query("INSERT INTO `{$this->db['campaign']}` (title, active, slug, lastactive, count) VALUES ('$title', 0, '$slug', 0, 0) ");
                $created_campaigns[] = $wpdb->insert_id;  
              
                // Add feeds
                foreach($feeds as $feedurl => $yes){
                  $this->addCampaignFeed($campaignid, urldecode($feedurl));
                }              
                  
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
            $title = $wpdb->escape($_REQUEST['import_new_campaign']);
            $slug = $wpdb->escape(WPOTools::stripText($title));
            $wpdb->query("INSERT INTO `{$this->db['campaign']}` (title, active, slug, lastactive, count) VALUES ('$title', 0, '$slug', 0, 0) ");
            $campaignid = $wpdb->insert_id;
            
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
    
    include(WPOTPL . 'export.php');    
  }
  
  /** 
   * Export process
   *
   *
   */
  function adminExportProcess()
  {
    if($_POST)
    {
      if(!isset($_REQUEST['export_campaign']))
      {
        $this->export_error = __('Please select at least one campaign', 'wpomatic');
      } else 
      {
        $campaigns = array();
        foreach($_REQUEST['export_campaign'] as $cid)
        {
          $campaign = $this->getCampaignById($cid);
          $campaign->feeds = (array) $this->getCampaignFeeds($cid);
          $campaigns[] = $campaign;
        }
        
        header("Content-type: text/x-opml");
        header('Content-Disposition: attachment; filename="wpomatic.opml"');
        
        include(WPOTPL . 'export.opml.php');
        exit;
      }
    }
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
    
    if(defined('DOING_AJAX')){
      echo intval($works);
      die();
    } else
      include(WPOTPL . 'testfeed.php');
  }
  
  /**
   * Forcedfully processes a campaign
   *
   *
   */
  function adminForcefetch()
  {
    $cid = intval($_REQUEST['id']);
    
    if(! defined('DOING_AJAX'))
      check_admin_referer('forcefetch-campaign_'.$cid);    
    
    $this->forcefetched = $this->processCampaign($cid);    
    
    if(defined('DOING_AJAX'))
      die('1');
    else
      $this->adminList();
  }
  
  /**
   * Checks submitted campaign edit form for errors
   * 
   *
   * @return array  errors 
   */
  function adminCampaignRequest()
  {  
    global $wpdb;
    
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
        'cacheimages'   => isset($_REQUEST['campaign_cacheimages']),
        'feeddate'      => isset($_REQUEST['campaign_feeddate']),
        'posttype'      => $_REQUEST['campaign_posttype'],
        'author'        => sanitize_user($_REQUEST['campaign_author']),
        'allowcomments' => isset($_REQUEST['campaign_allowcomments']),
        'allowpings'    => isset($_REQUEST['campaign_allowpings']),
        'dopingbacks'   => isset($_REQUEST['campaign_dopingbacks'])
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
      foreach($this->getCampaignFeeds(intval($_REQUEST['id'])) as $feed)
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
      $errors['basic'][] = __('You need to enter a campaign title', 'wpomatic');
      $this->errno++;
    }
    
    # Feeds
    $feedscount = 0;
    
    if(isset($this->campaign_data['feeds']['new'])) $feedscount += count($this->campaign_data['feeds']['new']);
    if(isset($this->campaign_data['feeds']['edit'])) $feedscount += count($this->campaign_data['feeds']['edit']);
    if(isset($this->campaign_data['feeds']['delete'])) $feedscount -= count($this->campaign_data['feeds']['new']);
    
    if(!$feedscount)
    {
      $errors['feeds'][] = __('You have to enter at least one feed', 'wpomatic');
    } else {  
      if(isset($this->campaign_data['feeds']['new']))    
      {
        foreach($this->campaign_data['feeds']['new'] as $feed)
        {
          $simplepie = $this->fetchFeed($feed, true);
          if($simplepie->error())
          {
            $errors['feeds'][] = sprintf(__('Feed <strong>%s</strong> could not be parsed (SimplePie said: %s)', 'wpomatic'), $feed, $xmlerror);
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
    
    if($this->campaign_data['main']['cacheimages'] && !is_writable($this->cachepath))
    {
      $errors['options'][] = sprintf(__('Cache path (in <a href="%s">Options</a>) must be writable before enabling image caching.', 'wpomatic'), $this->adminurl . '&s=options' );
      $this->errno++;
    }
    
    $this->errors = $errors;
  }
  
  /**
   * Creates a campaign, and runs processEdit. If processEdit fails, campaign is removed
   *
   * @return campaign id if created successfully, errors if not
   */
  function adminProcessAdd()
  {
    global $wpdb;
    
    // Insert a campaign with dumb data
    $wpdb->query(WPOTools::insertQuery($this->db['campaign'], array('count' => 0)));
    $cid = $wpdb->insert_id;
    
    // Process the edit
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
    global $wpdb;
    
    // If we need to execute a tool action we stop here
    if($this->adminProcessTools()) return;    
    
    // Delete all do recreate
    //$wpdb->query("DELETE FROM `{$this->db['campaign_feed']}` WHERE campaign_id = $id");
    $wpdb->query("DELETE FROM `{$this->db['campaign_word']}` WHERE campaign_id = $id");
    $wpdb->query("DELETE FROM `{$this->db['campaign_category']}` WHERE campaign_id = $id");    
    
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
      $wpdb->query(WPOTools::insertQuery($this->db['campaign_category'], 
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
        $wpdb->query("DELETE FROM `{$this->db['campaign_feed']}` WHERE id = $feed ");
    }
    
    // Process words
    foreach($this->campaign_data['rewrites'] as $rewrite)
    {
      $wpdb->query(WPOTools::insertQuery($this->db['campaign_word'], 
        array('word' => $wpdb->escape($rewrite['origin']['search']), 
              'regex' => $rewrite['origin']['regex'],
              'rewrite' => isset($rewrite['rewrite']),
              'rewrite_to' => $wpdb->escape($rewrite['rewrite']),
              'relink' => isset($rewrite['relink']) ? $wpdb->escape($rewrite['relink']) : null,
              'campaign_id' => $id)
      ));
    }
    
    // Main 
    $main = array();
    foreach($this->campaign_data['main'] as $k => $v)
      $main[$k] = $wpdb->escape($v);
      
    // Fetch author id
    $author = get_userdatabylogin($this->campaign_data['main']['author']);
    $main['authorid'] = $author->ID;
    
    unset($main['author']);
    
    $query = WPOTools::updateQuery($this->db['campaign'], $main, 'id = ' . intval($id));    
    $wpdb->query($query);
  }
  
  /**
   * Processes edit campaign tools actions
   *
   *
   */
  function adminProcessTools()
  {
    global $wpdb;
        
    $id = intval($_REQUEST['id']);
    
    if(isset($_REQUEST['tool_removeall']))
    {      
      $posts = $this->getCampaignPosts($id);
      
      foreach($posts as $post)
      {
        $wpdb->query("DELETE FROM `{$wpdb->posts}` WHERE ID = {$post->post_id} ");
      }
            
      // Delete log
      $wpdb->query("DELETE FROM `{$this->db['campaign_post']}` WHERE campaign_id = {$id} ");
      
      // Update feed and campaign posts count
      $wpdb->query(WPOTools::updateQuery($this->db['campaign'], array('count' => 0), "id = {$id}"));
      $wpdb->query(WPOTools::updateQuery($this->db['campaign_feed'], array('hash' => 0, 'count' => 0), "campaign_id = {$id}"));
      
      $this->tool_success = __('All posts removed', 'wpomatic');
      return true;
    }
    
    if(isset($_REQUEST['tool_changetype']))
    {
      $this->adminUpdateCampaignPosts($id, array(
        'post_status' => $wpdb->escape($_REQUEST['campaign_tool_changetype'])
      ));
      
      $this->tool_success = __('Posts status updated', 'wpomatic');
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
      return true;
    }
    
    return false;
  }
  
  function adminUpdateCampaignPosts($id, $properties)
  {
    $posts = $this->getCampaignPosts($id);
    
    foreach($posts as $post)
      $wpdb->query(WPOTools::updateQuery($wpdb->posts, $properties, "ID = {$post->id}"));
  }
  
  
  /** 
   * Show logs
   *
   *
   */  
  function adminLogs()
  {
    global $wpdb;
    
    // Clean logs?
    if(isset($_REQUEST['clean_logs']))
    {
      check_admin_referer('clean-logs');
      $wpdb->query("DELETE FROM `{$this->db['log']}` WHERE 1=1 ");
    }
    
    // Logs to show per page
    $logs_per_page = 20;
        
    $page = isset($_REQUEST['p']) ? intval($_REQUEST['p']) : 0;
    $total = $wpdb->get_var("SELECT COUNT(*) as cnt FROM `{$this->db['log']}` ");
    $logs = $this->getLogs("page={$page}&perpage={$logs_per_page}");
    
    $paging = paginate_links(array(
      'base' => $this->adminurl . '&s=logs&%_%',
      'format' => 'p=%#%',
      'total' => ceil($total / $logs_per_page),
      'current' => $page,
      'end_size' => 3
    ));
    
    include(WPOTPL . 'logs.php');
  }
}        

$wpomatic = & new WPOMatic();