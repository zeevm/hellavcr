<?php
error_reporting(E_ALL);
set_time_limit(0);
require_once('hellavcr.config.php');
require_once('hellavcr.vars.php');
require_once('classes/HellaController.php');
if($config['twitter']) {
  require_once('classes/twitter.class.php');
  $twitter = new Twitter($config['twitter_username'], $config['twitter_password']);
}
date_default_timezone_set(isset($config['timezone']) ? $config['timezone'] : 'US/Pacific');

$PID_FILES = array();
$pid_file_name = "/var/lock/hellavcr.pid";
    
function check_pid_file($file) {
    $f = fopen($file, 'r');
    if($f) {
        flock($f, LOCK_SH);
        $pid = trim(fgets($f));
        if(posix_getsid($pid))
            die("Running job using PID $pid from $file found, exiting\n");
        fclose($f);
    }   
    $f = fopen($file, 'w');
    flock($f, LOCK_EX); 
    fwrite($f, posix_getpid() . "\n");
    fclose($f);
    global $PID_FILES;
    $PID_FILES[] = $file;
    return $file;
}       

function clean_pid_files() {
    global $PID_FILES;
    foreach($PID_FILES as $file) {
        $f = fopen($file, 'r');
        if(!$f)
            continue;
        flock($f, LOCK_SH);
        $pid = trim(fgets($f));
        fclose($f);
        if($pid == posix_getpid())
            unlink($file);
    }
}

//
function process_tv() {
  global $config, $twitter;
  $shows_added = 0;
  $mail_string = '';
  print date($config['logging']['date_format']) . "processing tv...\n";
  
  //check to make sure the file exists
  if(file_exists($config['xml_tv'])) {
  
    //create a SimpleXML object
    $xml = simplexml_load_file($config['xml_tv']);
    
    //loop over each show
    $shows = $xml->xpath('/tv/show');
    foreach($shows as $show) {
      //extra show name info
      $nameExtra = array();
      if(array_key_exists(strval($show->format), $GLOBALS['formats'])) {
        $nameExtra[] = $GLOBALS['formats'][strval($show->format)];
      }
      if(array_key_exists(strval($show->language), $GLOBALS['languages'])) {
        $nameExtra[] = $GLOBALS['languages'][strval($show->language)];
      }
      if(array_key_exists(strval($show->source), $GLOBALS['sources'])) {
        $nameExtra[] = $GLOBALS['sources'][strval($show->source)];
      }
      $nameExtra = implode(', ', $nameExtra);
      if(strlen($nameExtra) > 0) $nameExtra = ' (' . $nameExtra . ')';
      
      //full show name
      $name = htmlspecialchars_decode($show->name) . $nameExtra;
      print date($config['logging']['date_format']) . $name . "\n";
      
      //get info from tvrage
      $show_info = get_show_info($show->name);
      
      //no show info (skip)
      if(empty($show_info)) {
        print date($config['logging']['date_format']) . '  get show info FAILED! (' . $config['info_scraper'] . " likely down)\n";
        continue;
      }
      
      //make sure it has an ID
      if(empty($show->attributes()->id)) {
        $show->addAttribute('id', generate_id());
      }
      
      //make sure it has a downloads node
      if(!$show->downloads) $show->addChild('downloads', '');
      //convert old style download to new style
      else {
        $remove = 0;
        foreach($show->downloads->download as $download) {
          if(!empty($download->episode)) {
            $ep_parts = explode('x', $download->episode);
            $double_parts = explode('-', $ep_parts[1]);
            
            //add new nodes
            foreach($double_parts as $ep) {
              $d = $show->downloads->addChild('download');
              $d->addAttribute('season', intval($ep_parts[0]));
              $d->addAttribute('episode', intval($ep));
              $d->addAttribute('timestamp', $download->timestamp);
            }
            
            $remove++;
          }
        }
        
        //remove old nodes
        while($remove-- > 0) {
          unset($show->downloads->download[0]);
        } 
      }
      
      //auto update show name to match info scraper
      if($config['update_show_name'] && strlen(trim($show_info['name'])) > 0 && trim($show->name) != $show_info['name']) {
        $show->name = $show_info['name'];
        print date($config['logging']['date_format']) . '  name updated to match ' . $config['info_scraper'] . ': ' . $show->name . "\n";
      }
      
      //update tvrage series id
      if(!$show->tvrageid) $show->addChild('tvrageid', $show_info['tvrageid']);
      else $show->tvrageid = trim($show_info['tvrageid']);
      
      //update thetvdb series id
      if(!$show->thetvdbid) $show->addChild('thetvdbid', $show_info['thetvdbid']);
      else $show->thetvdbid = trim($show_info['thetvdbid']);

      //episode list (seasons, eps)
      if(!$show->episodelist) $show->addChild('episodelist', '');
      
      if(!empty($show_info['episodelist'])) {
        foreach($show_info['episodelist'] as $season => $episodes) {
          $s_existing = $show->episodelist->xpath('season[@num=' . $season . ']');
          
          //exists, so just update
          if(!empty($s_existing)) {
            $s_existing[0]['episodes'] = sizeof($episodes);
          }
          //add new
          else {
            $s = $show->episodelist->addChild('season');
            $s->addAttribute('num', $season);
            $s->addAttribute('episodes', sizeof($episodes));
          }
          
          /*
          //full episode details
          foreach($episodes as $episode) {
            $e = $s->addChild('episode');
            $e->addAttribute('num', $episode['num']);
            $e->addAttribute('aired', $episode['aired']);
            $e->addAttribute('title', htmlentities($episode['title']));
          }
          */
        }
      }

      //update episode URL
      if(!$show->url) $show->addChild('url', $show_info['Show URL']);
      else $show->url = trim($show_info['Show URL']);
      
      //update status
      if(!$show->status) $show->addChild('status', $show_info['Status']);
      else $show->status = htmlspecialchars(trim($show_info['Status']));
      
      //update airtime
      if(!$show->airtime) $show->addChild('airtime', $show_info['Airtime']);
      else $show->airtime = htmlspecialchars(trim($show_info['Airtime']));
      
      //update network
      if(!$show->network) $show->addChild('network', $show_info['Network']);
      else $show->network = htmlspecialchars(trim($show_info['Network']));
      
      //update year the show started
      if(!$show->year) $show->addChild('year', $show_info['Premiered']);
      else $show->year = htmlspecialchars(trim($show_info['Premiered']));
      
      //update next ep
      $air_date = $show_info['Next Episode']['airdate'];
      if(substr_count($air_date, '/') == 2) {
      	$date_parts = explode('/', $air_date);
      	$air_date = date('(l) F j, Y', strtotime($date_parts[1] . ' ' . $date_parts[0] . ' ' . $date_parts[2]));
      }
      $next_info = (strlen(trim($show_info['Next Episode']['episode'])) > 0 ? $show_info['Next Episode']['episode'] . ' - "' . $show_info['Next Episode']['title'] . '" airs ' . $air_date : '');
      if(!$show->next) $show->addChild('next', htmlspecialchars(trim($next_info)));
      else $show->next = htmlspecialchars(trim($next_info));
      
      //print date($config['logging']['date_format']) . '  next episode: ' . $show_info['Next Episode']['episode'] . "\n";
      
      //update next timestamp (includes date and time)
      if(strpos($show_info['RFC3339'], 'T:00-') !== false) {
        $time_prefix = '00:00';
        if(strlen($show_info['Airtime']) > 0) {
          $time_parts = explode('at', $show_info['Airtime']);
          $time_prefix = date('H:i', strtotime('today ' . $time_parts[1]));
        }
        $show_info['next_timestamp'] = strtotime(str_replace('T:00-', 'T' . $time_prefix . ':00-', $show_info['RFC3339']));
      }
      
      if(!$show->next_timestamp) $show->addChild('next_timestamp', $show_info['next_timestamp']);
      else $show->next_timestamp = trim($show_info['next_timestamp']);
      
      //queue up any episodes prior to the last episode (there must be at least 1 aired episode)
      if($show_info['Latest Episode']['episode']) {
        $latest = explode('x', $show_info['Latest Episode']['episode']);
        $latest_season = intval($latest[0]);
        $latest_episode = intval($latest[1]);

        //if season or episode are blank, default to the last episode so shows are downloded moving forward
        if($show->season == '' && $show->episode == '') {
          $show->season = $latest_season;
          $show->episode = $latest_episode;
          
          //special case for a brand new series
          if($latest_season == '1' && strpos($latest_episode, '01') !== false) {
          	$show->episode = 0;
          }
        }
        
        //check on the day of air since tvrage doesn't update 'Latest Episode' until midnight
        if($show_info['next_timestamp'] != '' && date('m/d/Y') == date('m/d/Y', $show_info['next_timestamp']) && $show->episode == $latest_episode) {
          $pre_midnight_check = true;
          $latest_episode = intval($latest_episode) + 1;
        }
        
        print date($config['logging']['date_format']) . '  last episode: ' . $show->season . 'x' . sprintf('%02d', $show->episode) . "\n";
        $one_ep_behind = ($show->season == $latest_season && $show->episode == $latest_episode - 1);
        $skipped_episodes = 0;
        
        //loop over all mising episodes
        $current_season = intval($show->season);
        while($current_season <= $latest_season && $current_season <= sizeof($show_info['episodelist'])) {
          $current_episode = ($current_season > intval($show->season) ? 1 : intval($show->episode + 1));
            
          while($current_episode <= $latest_episode || $current_season < $latest_season) {
            $episode_string = $current_season . 'x' . sprintf('%02d', $current_episode);
            $episode_info = get_show_info($show->name, $episode_string);
            
            //episode found, attempt to queue
            if($episode_info['Episode Info']) {
              //search newzbin
              $newzbin_info = search_newzbin($show->name, $show->year, $current_season, $current_episode, $show->language, $show->format, $show->source);
              
              $nzb_downloaded = false;
              $double_ep = false;
              
              //id found
              if($newzbin_info) {
                //double episode found
								if(strpos($newzbin_info['title'], $current_season . 'x' . sprintf('%02d', $current_episode + 1)) !== false) {
								  $current_episode++;
								  $episode_string .= '-' . sprintf('%02d', $current_episode);
								  $double_ep = true;
								  print 'double episode found' . $config['debug_separator'];
								}
								
								switch($config['newzbin_handler']) {
									//move to directory
									case 'nzb':
										$nzb_downloaded = download_nzb($newzbin_info['id']);
										break;
									
									//send to hellanzb
									case 'hellanzb':
										$nzb_downloaded = send_to_hellanzb($newzbin_info['id']);
										if($nzb_downloaded) $shows_added++;
										break;
										
								  //send to sabnzbd
                  case 'sabnzbd':
                    $nzb_downloaded = send_to_sabnzbd($newzbin_info['id']);
                    if($nzb_downloaded) $shows_added++;
                    break;
								}

								//newzbin has a limit of 5 nzb's per minute
								//rate limit not needed in nzb mode since download_nzb handles the exact time to wait
								if($config['newzbin_handler'] != 'nzb' && $shows_added % 5 == 0) {
								  sleep(60);
								}
								
								//send XBMC update
                if($nzb_downloaded && $config['xbmc'] && $config['xbmc_host']) {
                  $xbmc_ch = curl_init('http://' . $config['xbmc_host'] . '/xbmcCmds/xbmcHttp?command=ExecBuiltIn&parameter=Notification(Download+Started,' . urlencode($show->name) . '+' .$episode_string . ')');
                  curl_setopt($xbmc_ch, CURLOPT_RETURNTRANSFER, 1);
                  curl_setopt($xbmc_ch, CURLOPT_TIMEOUT, 5);
                  $result = curl_exec($xbmc_ch);
                  curl_close($xbmc_ch);
                  print $config['debug_separator'] . 'XBMC notification ' . ($result ? 'ok' : 'FAILED');
                }
								
								//send twitter update
								if($nzb_downloaded && $config['twitter'] && $twitter) {
                  $prefix = $config['hollers'][@array_rand($config['hollers'])];
                  $status = $twitter->send($prefix . $show->name . ' ' . $episode_string . $nameExtra);
                  print $config['debug_separator'] . ($status ? 'tweeted ok' : 'twitter FAILED');
                }
                
                //build mail string
								if($nzb_downloaded && $config['mail']) {
								  $mail_string .= $show->name . ' ' . $episode_string . ' (' . $GLOBALS['formats'][strval($show->format)] . ', ' . $GLOBALS['languages'][strval($show->language)] . ")\n";
								}
								
								//save to download history
								if($nzb_downloaded) {
                  $download_node = $show->downloads->addChild('download', '');
                  $download_node->addAttribute('season', $current_season);
                  $download_node->addAttribute('episode', $current_episode);
                  $download_node->addAttribute('timestamp', time());
                  
                  if($double_ep) {
                    $download_node = $show->downloads->addChild('download', '');
                    $download_node->addAttribute('season', $current_season);
                    $download_node->addAttribute('episode', $current_episode + 1);
                    $download_node->addAttribute('timestamp', time());
                  }
                }
								
								print "\n";
							}
							else {
							  //$skipped_episodes++;
								print "skipping this episode\n";
								
								//increment season if we're out of eps
								if(isset($show_info['episodelist'][$current_season]) && (($current_episode + 1) > sizeof($show_info['episodelist'][$current_season]))) {
								  break;
								}
							}
							
							//increment last episode for the show
							//if(!$one_ep_behind || ($one_ep_behind && $nzb_downloaded)) {
							if($nzb_downloaded) {
								$show->season = $current_season;
								$show->episode = $current_episode;
							}
							
              $current_episode++;
              //if($skipped_episodes >= 5) {
              //  break;
              //}
            }
            //invalid episode, proceed to next season
            else {
              break;
            }
            
          }
          
          $current_season++;
        }
      }
    }
    
    //send email
    if($config['mail'] && $mail_string != '') {
      $mail_sent = mail($config['mail_to'], '[hellaVCR] ' . substr_count($mail_string, "\n") . ' episodes found', "The following episodes have been queued in hellanzb:\n\n" . $mail_string, 'From: hellaVCR <hellaVCR@faketown.com>');
      print date($config['logging']['date_format']) . 'emailing queue' . $config['debug_separator'] . ($mail_sent ? 'done' : 'FAIL') . "\n"; 
    }

		//write new xml file    
		$xml_updated = saveXML($xml);
		print date($config['logging']['date_format']) . 'saving xml file' . $config['debug_separator'] . ($xml_updated ? 'done' : 'FAIL') . "\n";  

  }
  //xml file not found
  else {
    print date($config['logging']['date_format']) . 'tv XML (' . $config['xml_tv'] . ') file not found...make sure to use an absolute path' . "\n";
  }
}

//
function get_show_info($show, $ep = '', $exact = '', $thetvdbid = 0) {
  global $config;
  $show_info = array(
    'name' => '',
    'Show Name' => '',
    'Show URL' => '',
    'Episode URL' => '',
    'Status' => '',
    'Airtime' => '',
    'Network' => '',
    'Premiered' => '',
    'Next Episode' => array('airdate' => '', 'episode' => '', 'title' => ''),
    'Latest Episode' => array('airdate' => '', 'episode' => '', 'title' => ''),
    'Episode Info' => array('airdate' => '', 'episode' => '', 'title' => ''),
    'RFC3339' => '',
    'next_timestamp' => '',
    'tvrageid' => 0,
    'thetvdbid' => 0, //$thetvdbid
    'episodelist' => array()
  );
  
  
  //no show provided
  if(!$show) return false;
  
  if(empty($config['info_scraper'])) $config['info_scraper'] = '';
  switch($config['info_scraper']) {
    //use thetvdb api
    case 'thetvdb':
      //get mirrors
      $sxe_mirrors = simplexml_load_string(file_get_contents('http://www.thetvdb.com/api/' . $config['thetvdb']['api_key'] . '/mirrors.xml'));
      if(!$sxe_mirrors) return false;
      
      /*
      <Mirrors>
       <Mirror>
         <id>1</id>
         <mirrorpath>http://thetvdb.com</mirrorpath>
         <typemask>7</typemask>
       </Mirror>
      </Mirrors>
      
      typemask (sum):
      1 xml files
      2 banner files
      4 zip files 
      */
      
      $xmlmirrors = $sxe_mirrors->xpath("/Mirrors/Mirror/typemask[.>0]/parent::*");
      $bannermirrors = $sxe_mirrors->xpath("/Mirrors/Mirror/typemask[.>1]/parent::*");
      $zipmirrors = $sxe_mirrors->xpath("/Mirrors/Mirror/typemask[.>3]/parent::*");
      
      $mirrors = array(
        'xml' => $xmlmirrors[array_rand($xmlmirrors)]->mirrorpath,
        'banner' => $bannermirrors[array_rand($bannermirrors)]->mirrorpath,
        'zip' => $zipmirrors[array_rand($zipmirrors)]->mirrorpath,
      );
      
      //get current server time
      //
      
      //get series id (normally added on insert/update on index.php)
      if($thetvdbid == 0) {
        $sxe_series = simplexml_load_string(file_get_contents('http://www.thetvdb.com/api/GetSeries.php?seriesname=' . urlencode($show)));
        if(!$sxe_series) return false;
        
        /*
        <Data>
          <Series>
            <seriesid>73739</seriesid>
            <language>en</language>
            <SeriesName>Lost</SeriesName>
            <banner>graphical/73739-g4.jpg</banner>
            <Overview>After their plane, Oceanic Air flight 815, tore apart whilst thousands of miles off course, the survivors find themselves on a mysterious deserted island where they soon find out they are not alone.</Overview>
            <FirstAired>2004-09-22</FirstAired>
            <IMDB_ID>tt0411008</IMDB_ID>
            <zap2it_id>SH672362</zap2it_id>
            <id>73739</id>
          </Series>
        </Data>
        */
       
        //assume first result
        if($sxe_series->Series) {
          $sxe_show = $sxe_series->Series[0];
          $show_info['thetvdbid'] = intval($sxe_show->seriesid);
          $show_info['Premiered'] = date('Y', strtotime($sxe_show->FirstAired));
          $show_info['language'] = strval($sxe_show->language);
        }
        
        //get episode info
        //--
      }
      
      var_dump($show_info);
      die();
    
      break;
  
    //get quickinfo from tvrage    
    case 'tvrage':
    default:
      if($fp = @fopen($config['tvrage']['quickinfo'] . '?show=' . urlencode($show) . '&ep=' . urlencode($ep) . '&exact=' . urlencode($exact), 'r')) {
  
        //get all info for the show
        while(!feof($fp)) {
          $line = fgets($fp);
          if(strlen($line) == 0) continue; //line is empty
          list($prop, $val) = explode('@', $line, 2);
          
          /*
          "Show Name" (Name Of The TV Show)
          "Show URL" (URL from the TV Show On TVrage)
          "Premiered" (Year when the show first premiered)
          "Episode Info" (Information About the chosen episode "&ep=2x04")
          "Episode URL" (URL About the chosen episode "&ep=2x04")
          "Latest Episode" (Information About the Last Episode That aired)
          "Next Episode" (Information About the Next Episode)
          "Status" (Current Status of show)
          "Country" (Country of origin)
          "Classification" (Classification of show)
          "Genres" (Genres of show)
          "Network" (Network On Which it airs)
          "Airtime" (Which day of the week and time it is broadcasted)
          "RFC3339" (raw date/time the next episode will air, blank if nothing scheduled)
          */
          
          switch($prop) {
            case 'Latest Episode':
            case 'Episode Info':
            case 'Next Episode':
              list ($ep, $title, $airdate) = explode('^', $val);
              $val = array(
                'episode' => $ep,
                'title' => $title,
                'airdate' => $airdate
              );
              break;
            case 'RFC3339':
              $show_info['next_timestamp'] = strtotime($val);
              break;
            case 'Show Name':
              $show_info['name'] = trim($val);
              break;
            case 'Show ID':
            case '<pre>Show ID':
              $show_info['tvrageid'] = intval($val);
          }
          
          $show_info[$prop] = $val;
        }
        
        //close file pointer
        fclose($fp);
      }
      else {
        return false;
      }
      //--
      
      //get the episode list (how many seasons, how many eps)
      if(!empty($show_info['tvrageid'])) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
          CURLOPT_URL => $config['tvrage']['episode_list'] . '?sid=' . $show_info['tvrageid'],
          CURLOPT_USERAGENT => 'hellaVCR/' . $config['version'],
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_SSL_VERIFYPEER => 0
        ));
        
        //create simplexml object
        $raw_xml = curl_exec($ch);
        $tvrage_sxe = simplexml_load_string($raw_xml);
    
        //loop over each season
        $seasons = $tvrage_sxe->xpath('/Show/Episodelist/Season');
        foreach($seasons as $season) {
          $show_info['episodelist'][intval($season['no'])] = array();
          foreach($season->episode as $episode) {
            $show_info['episodelist'][intval($season['no'])][] = array(
              'num' => strval($episode->seasonnum),
              'aired' => strtotime($episode->airdate),
              'title' => strval($episode->title)
            );
          }
        }
      }
      //--

      break;
  }
  
  return $show_info;
}

//
function search_newzbin($show, $year, $season, $episode, $language, $format, $source) {
  global $config;
	
	//main query
	$q = '"' . str_replace('"', '\"', $show) . ' - ' . $season . 'x' . sprintf('%02d', $episode) . '" OR "' . str_replace('"', '\"', $show) . ' (' . $year . ') - ' . $season . 'x' . sprintf('%02d', $episode) . '"';
	$q_debug = $show . ' - ' . $season . 'x' . sprintf('%02d', $episode);
	print date($config['logging']['date_format']) . '  searching newzbin for ' . $q_debug . $config['debug_separator'];
	
	//formatted query
	$query = build_newzbin_search_string($q, $language, $format, $source, true, true);
	
	//send to newzbin
	if($fp = @fopen($query, 'r')) {
		$line = @fgetcsv($fp);
		
		/*
		0: posted date
		1: nzb id
		2: nzb title
		3: newzbin url
		4: tv url
		5: newsgroup names (separated by +)
		*/
		
		//newzbin id found
		if($line[1] > 0) {
	    print 'found nzb ID ' . $line[1] . $config['debug_separator'];
  	  return array('id' => $line[1], 'title' => $line[2]);
  	}
  	//newzbin id not found
  	else {
  		print 'nzb ID not found' . $config['debug_separator'];
  		return false;
  	}
	}
	
}

//common function to build up the full newzbin search string
//used by search_newzbin() and the newzbin icon on index.php
function build_newzbin_search_string($name, $language, $format, $source, $csv = false, $useauth = false) {
  global $config;
  
  //build up params
  $query = array(
    'q=^' . urlencode($name),
    'u_v3_retention=' . ($config['ng_retention'] * 24 * 60 * 60),
    'searchaction=Search',
    'fpn=p',
    'category=8',
    'area=-1',
    'u_nfo_posts_only=0',
    'u_url_posts_only=0',
    'u_comment_posts_only=0',
    'sort=ps_edit_date',
    'order=desc',
    'areadone=-1'
  );
  if(strlen($language) > 0) {
    $query[] = 'ps_rb_language=' . $language;
  }
  if(strlen($format) > 0) {
    $query[] = 'ps_rb_video_format=' . $format;
  }
  if(strlen($source) > 0) {
    $query[] = 'ps_rb_source=' . $source;
  }
  if(!empty($config['newzbin_groups'])) {
   $query[] = 'group=' . urlencode($config['newzbin_groups']);
  }
  if($csv) {
    $query[] = 'feed=csv';
  }
  
  return $config['newzbin']['protocol'] . ($useauth ? $config['newzbin_username'] . ':' . $config['newzbin_password'] . '@' : '') . $config['newzbin']['base_url'] . 'search/?' . implode( '&', $query);
}

//
function download_nzb($newzbin_id) {
  global $config, $nzb_headers;
  print 'downloading nzb' . $config['debug_separator'];
  
  //newzbin info blank
  if(empty($config['newzbin_username']) || empty($config['newzbin_password'])) {
    print 'FAIL (newzbin username/password not set)';
    return false;
  }
  
  $nzb_headers = array();
  $ch = curl_init();
  curl_setopt_array($ch, array(
  	CURLOPT_URL => $config['newzbin']['root_url'] . 'api/dnzb/',
  	CURLOPT_USERAGENT => 'hellaVCR/' . $config['version'],
  	CURLOPT_POST => 1,
  	CURLOPT_HEADER => 0,
  	CURLOPT_RETURNTRANSFER => 1,
  	CURLOPT_SSL_VERIFYPEER => 0,
  	CURLOPT_HEADERFUNCTION => 'read_header',
  	CURLOPT_POSTFIELDS => 'username=' . $config['newzbin_username'] . '&password=' . $config['newzbin_password'] . '&reportid=' . $newzbin_id
  ));
  
  $raw_nzb = curl_exec($ch);
  
  /*
  [X-DNZB-Name] =>  Eureka - 3x05 - Show Me the Mummy
  [X-DNZB-Category] =>  TV
  [X-DNZB-MoreInfo] =>  http://www.tvrage.com/Eureka/episodes/664264/3x05/
  [X-DNZB-NFO] =>  196018317
  [X-DNZB-RCode] =>  200
  [X-DNZB-RText] =>  OK, NZB content follows
	*/
  
  switch($nzb_headers['X-DNZB-RCode']) {
  	case 200:
  	  $filename = trim($nzb_headers['X-DNZB-Name']);
  	  $filename = str_replace('/', ' ', $filename);
  		$fp_nzb = fopen($config['nzb_queue'] . $filename . '.nzb', 'w');
  		$nzb_written = fwrite($fp_nzb, $raw_nzb);
  		print ($nzb_written ? 'written' : 'FAIL (writing the nzb, check directory permissions)');
  		return $nzb_written;
    case 400:
      print 'FAIL (400: bad request, please supply all parameters)';
      return false;
    case 401:
      print 'FAIL (401: unauthorized, check username/password)';
      return false;
    case 402:
      print 'FAIL (402: premium account required)';
      return false;
    case 404:
      print 'FAIL (404: data not found)';
      return false;
    case 450:
      //api rate limited exceeded, so wait the required time and try again
      print 'FAIL (450: too many requests)';
      $wait_parts = explode(' ', $nzb_headers['X-DNZB-RText']);
      print $config['debug_separator'] . 'waiting ' . $wait_parts[4] . ' second' . ($wait_parts[4] > 1 ? 's' : '') . $config['debug_separator'];
      sleep($wait_parts[4] + 1);
      download_nzb($newzbin_id);
      return false;
    case 500:
      print 'FAIL (500: internal server error)';
      return false;
    case 503:
      print 'FAIL (503: service unavailable, newzbin is down)';
      return false;
  	default:
  		print 'FAIL (' . trim($nzb_headers['X-DNZB-RCode']) . ': ' . trim($nzb_headers['X-DNZB-RText']) . ')';
  		return false;
  }
  
  return true;
}

function read_header($ch, $string) {
	global $nzb_headers;
	$colon_index = strpos($string, ':');
	$nzb_headers[substr($string, 0, $colon_index)] = substr($string, $colon_index + 1);
	return strlen($string);
}

//
function send_to_hellanzb($newzbin_id) {
  global $config;
  print 'sending to hellanzb' . $config['debug_separator'];
  
  try {
    $hc = new HellaController($config['hellanzb_server'], $config['hellanzb_port'], 'hellanzb', $config['hellanzb_password']);
  }
  //error thrown, probably since hellanzb isn't running
  catch(Exception $e) {
    print 'hellanzb not running!';
    return false;
  }
  
  //use the hellanzb class to send the id
  $hc->enqueueNewzbin($newzbin_id);
  
  //check hellanzb log to see if the id was processed successfully
  //--
  
  print 'sent';
  return true;
}

//
function send_to_sabnzbd($newzbin_id, $isURL = false) {
  global $config;
  print 'sending to sabnzbd' . $config['debug_separator'];

  //set params
  $authString = ((strlen($config['sabnzbd_username']) > 0 && strlen($config['sabnzbd_password']) > 0) ? '&ma_username=' . $config['sabnzbd_username'] . '&ma_password=' . $config['sabnzbd_password'] : '');
  $apiString = '&apikey=' . $config['sabnzbd_apikey'];
  $modeString = ($isURL ? 'addurl' : 'addid');
  $priority = ((isset($config['sabnzbd_0.5']) && $config['sabnzbd_0.5']) ? '&priority=1' : '');

  //make curl call
  $ch = curl_init();
  curl_setopt_array($ch, array(
    CURLOPT_URL => 'http://' . $config['sabnzbd_server'] . ':' . $config['sabnzbd_port'] . '/sabnzbd/api?mode=' . $modeString . $authString . $apiString . '&cat=' . $config['sabnzbd_category'] . '&pp=3&name=' . urlencode($newzbin_id) . $priority,
    CURLOPT_HEADER => 0,
    CURLOPT_RETURNTRANSFER => 1
  ));
  $result = curl_exec($ch);
  curl_close($ch);

  print ($result ? 'sent' : 'FAIL (check sabnzbd is running and config values are correct)');
  return $result;
}


//
function generate_id() {
  return substr(md5(microtime()), 0, 10);
}

//
function saveXML($simplexml) {
  global $config;
  $domDoc = new DomDocument('1.0', 'utf-8');
  $domDoc->formatOutput = true;
  $domDoc->preserveWhiteSpace = false;
  $domDoc->loadXml($simplexml->asXml());
  return file_put_contents($config['xml_tv'], $domDoc->saveXml());
  //print $domDoc->saveXml();
  //return true;
}

##### main call

if(empty($hellavcr_include)) {
  register_shutdown_function('clean_pid_files');
  check_pid_file($pid_file_name);
  process_tv();
}

?>
