<?php

##### user values

$config = array(
  //xml files (use absolute path)
  'xml_tv' => 'tv.xml',
  
  //account info for newzbin
  //only required if you use the nzb handler
  'newzbin_username' => 'username',
  'newzbin_password' => 'password',
  
  //only search the following groups in newzbin
  //leave blank or false to search all (default for pre 0.6)
  'newzbin_groups' => '', //space separated
  
  //days of retention for your newsgroup server
  'ng_retention' => 120,
  
  //newzbin hander
  //values:
  //  nzb: downoad the newzbin nzb file and move it to the nzb_queue directory
  //  hellanzb: pass the newzbin id to hellanzb
  //  sabnzbd: pass the newzbin id or NZB URL to sabnzbd
  'newzbin_handler' => 'nzb',
  
  //nzb handler (use absolute path, end with a /)
  'nzb_queue' => '/path/to/nzb/daemon.queue/',
  
  //hellanzb handler
  'hellanzb_server' => 'localhost',
  'hellanzb_port' => '8760',
  'hellanzb_password' => 'changeme',
  
  //sabnzbd handler
  'sabnzbd_server' => 'localhost',
  'sabnzbd_port' => '8080',
  'sabnzbd_apikey' => 'key',
  'sabnzbd_username' => 'username',
  'sabnzbd_password' => 'password',
  'sabnzbd_category' => 'tv',
  'sabnzbd_0.5' => false, // <-- until 0.5 is released, currently svn only
  
  //mail options
  'mail' => false,
  'mail_to' => 'youremail@something.com',
  
  //twitter account info
  'twitter' => false,
  'twitter_username' => 'username',
  'twitter_password' => 'password',
  
  //timezone
  'timezone' => 'US/Pacific',
  'timezone_24hrmode' => false,
  
  //index sorting
  'sort_by' => 'upcoming',
  'sort_how' => 'asc',
  
  //auto check for a new version in index.php
  'check_for_update' => true,
  
  //auto update show name from tvrage (recommended set to false)
  'update_show_name' => false,
  
  //alert xbmc client when you download a file
  'xbmc' => false,
  'xbmc_host' => '192.168.0.100:80',
  
  //whether you want to print the daily headers in index.php
  'print_day_headers' => true
);

?>