<?php
$hellavcr_include = true;
require_once('hellavcr.config.php');
require_once('hellavcr.vars.php');
require_once('hellavcr.php');
asort($GLOBALS['formats']);
asort($GLOBALS['languages']);
asort($GLOBALS['sources']);

if(empty($_GET['sort'])) $_GET['sort'] = $config['sort_by'];
if(empty($_GET['dir'])) $_GET['dir'] = $config['sort_how'];
if(empty($_REQUEST['op'])) $_REQUEST['op'] = '';

switch($_REQUEST['op']) {
  //add
  case 'add':
    //check to make sure the file exists
    if(file_exists($config['xml_tv'])) {
    
      //create a SimpleXML object
      $xml = simplexml_load_file($config['xml_tv']);
      
      $season = $_POST['season'];
      $episode = $_POST['episode'];
      
      if($_POST['dlnew'] == 1) {
        $season = '';
        $episode = '';
      }
      elseif($_POST['dlfull'] == 1) {
        $season = 1;
        $episode = 0;
      }
      
      $show = $xml->addChild('show');
      $show->addAttribute('id', generate_id());
      $show->addChild('name', htmlspecialchars(trim(get_magic_quotes_gpc() ? stripslashes($_POST['name']) : $_POST['name'])));
      $show->addChild('format', $_POST['format']);
      $show->addChild('language', $_POST['language']);
      $show->addChild('source', $_POST['source']);
      $show->addChild('season', $season);
      $show->addChild('episode', $episode);
      $show->addChild('poster', trim($_POST['poster']));
      $show->addChild('next', '');
      $show->addChild('url', '');
      $show->addChild('status', '');
      
      //write updated xml file
      print saveXML($xml) ? 1 : 0;
    }

    exit();
  
  //edit
  case 'edit':
    //check to make sure the file exists
    if(file_exists($config['xml_tv'])) {
      //create a SimpleXML object
      $xml = simplexml_load_file($config['xml_tv']);
      
      //find index to edit
      $i = 0;
      foreach($xml->show as $show) {
        if($show->attributes()->id == $_POST['id']) {
          $edit = $i;
        }
        $i++;
      }
      
      $show = $xml->show[$edit];
      if($show) {
        $show->name = htmlspecialchars(trim(get_magic_quotes_gpc() ? stripslashes($_POST['name']) : $_POST['name']));
        $show->season = trim($_POST['season']);
        $show->episode = trim($_POST['episode']);
        $show->language = trim($_POST['language']);
        $show->source = trim($_POST['source']);
        $show->format = trim($_POST['format']);
        $show->poster = trim($_POST['poster']);
        
        $saved = saveXML($xml);
        header('Location: ' . $_SERVER['REQUEST_URI'] . '#' . urlencode(trim($_POST['name'])));
      }
      else {
        header('Location: index.php');
      }
    }
    else {
      header('Location: index.php');
    }
    
    exit();
  
  //delete
  case 'delete':    
    //check to make sure the file exists
    if(file_exists($config['xml_tv'])) {
      //create a SimpleXML object
      $xml = simplexml_load_file($config['xml_tv']);
      
      //find index to remove
      $i = 0;
      foreach($xml->show as $show) {
        if($show->attributes()->id == $_POST['id']) {
          $remove = $i;
        }
        $i++;
      }
      
      //remove it
      if(isset($remove)) {
        unset($xml->show[$remove]);
      }
      
      //write updated xml file
      print saveXML($xml) ? 1 : 0;
    }
    
    exit();
    
  //get posters
  case 'get_posters':
    $show_xml = file_get_contents($config['thetvdb']['show_info'] . urlencode($_REQUEST['show_name']));
    $xml = simplexml_load_string($show_xml);
    
    if($xml) {
      $series = $xml->xpath('/Data/Series');
      
      if(sizeof($series) > 0) {
        //1 match found
        if(sizeof($series) == 1) {
          $series_id = $series[0]->seriesid;
        }
        //2+ matches found, use first match with an exact name match
        else {
          foreach($series as $show) {
            if(strtolower($show->SeriesName) == strtolower($_REQUEST['show_name'])) {
              $series_id = $show->seriesid;
              break;
            }
          }
        }
      
        //exact match found
        if($series_id > 0) {
          $posters = array();
          for($i = 1; $i <= 10; $i++) {
            $poster_url = $config['thetvdb']['small_poster'] . $series_id . '-' . $i . '.jpg';
            if(@fopen($poster_url, 'r')) {
              $posters[] = $poster_url;
            }
          }
          print implode(',', $posters);
        }
        //nothing exact, so present a list
        /*
        else {
          print $_REQUEST['show_name'] . ' NAMES ';
          $names = array();
          foreach($series as $show) {
            $names[] = $show->SeriesName;
          }
          print implode(',', $names);
        }
        */
      }
    }
    
    exit();
}

//
function sortShows($show1, $show2) {
  global $config;
  $by = $_GET['sort'];
  $dir = $_GET['dir'];
  
  switch($by) {
    case 'upcoming':
      if(strlen($show1->next_timestamp) <= 0) $show1->next_timestamp = $config['index']['unknown_timestamp'];
      else if(!empty($show1->next) && !empty($show1->season) && !empty($show1->episode) && strpos($show1->next, $show1->season . 'x' . sprintf('%02d', $show1->episode)) !== false) $show1->next_timestamp = strtotime('+1 week');
      if(strlen($show2->next_timestamp) <= 0) $show2->next_timestamp = $config['index']['unknown_timestamp'];
      else if(!empty($show2->next) && !empty($show2->season) && !empty($show2->episode) && strpos($show2->next, $show2->season . 'x' . sprintf('%02d', $show2->episode)) !== false) $show2->next_timestamp = strtotime('+1 week');
      if($show1->next_timestamp == $show2->next_timestamp) return 0;
      if($dir == 'desc') return (intval($show1->next_timestamp) < intval($show2->next_timestamp)) ? 1 : -1;
      else return (intval($show1->next_timestamp) < intval($show2->next_timestamp)) ? -1 : 1;
      break;
    case 'downloaded':
      $show1_last = 0;
      if($show1->downloads) {
        foreach($show1->downloads->download as $d) {
          $show1_last = $d->timestamp;
        }
      }
      $show2_last = 0;
      if($show2->downloads) {
        foreach($show2->downloads->download as $d) {
          $show2_last = $d->timestamp;
        }
      }
      if($show1_last == $show2_last) return 0;
      if($dir == 'desc') return (intval($show1_last) < intval($show2_last)) ? -1 : 1;
      else return (intval($show1_last) < intval($show2_last)) ? 1 : -1;
      break;
    case 'name':
    default:
      return ($dir == 'desc' ? strcmp(strtolower($show2->name), strtolower($show1->name)) : strcmp(strtolower($show1->name), strtolower($show2->name)));
  };
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title>hellaVCR/<?php print $config['version']; ?></title>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />
  <script type="text/javascript" src="js/mootools-1.2-core.js"></script>
  <script type="text/javascript" src="js/mootools-1.2-more.js"></script>
  <script type="text/javascript" src="js/hellavcr.js"></script>
  <link type="text/css" rel="stylesheet" media="all" href="css/main.css" />
  <link type="text/css" rel="stylesheet" media="only screen and (max-device-width: 480px)" href="css/iphone.css" />
</head>
<body>
<div id="outerWrapper">
  <div id="titleBar">
    <div id="addWrapper">
<?php if(file_exists($config['xml_tv'])) { ?>
      <a href="#" title="Add Show" id="addButton"><img alt="" src="images/add.png" /> Add Show</a>
<?php } ?>
    </div>
    <div class="version"><?php print $config['version']; ?></div>
  </div>
  <div id="formWrapper" class="formWrapper">
    <table cellpadding="0" cellspacing="0">
      <tr>
        <th>Show Name:</th>
        <td><input type="text" name="showName" id="showName" /></td>
      </tr>
      <tr>
        <th></th>
        <td>
          <input type="checkbox" id="showFullSeries" /> Download Full Series<br />
          <input type="checkbox" id="showNewEpisodes" /> Download New Episodes
        </td>
      </tr>
      <tr>
        <th>Last Episode:</th>
        <td>
          <input type="text" id="showSeason" size="2" /> x <input type="text" id="showEpisode" size="2" />
        </td>
      </tr>
      <tr>
        <th>Format:</th>
        <td>
          <select id="showFormat">
            <option value=""></option>
            <?php foreach($GLOBALS['formats'] as $id => $format) { ?>
            <option value="<?php print $id; ?>" <?php print $format == 'x264' ? 'selected="selected"' : ''; ?>><?php print $format; ?></option>
            <?php } ?>
          </select>
        </td>
      </tr>
      <tr>
        <th>Language:</th>
        <td>
          <select id="showLanguage">
            <option value=""></option>
            <?php foreach($GLOBALS['languages'] as $id => $language) { ?>
            <option value="<?php print $id; ?>" <?php print $language == 'English' ? 'selected="selected"' : ''; ?>><?php print $language; ?></option>
            <?php } ?>
          </select>
        </td>
      </tr>
      <tr>
        <th>Source:</th>
        <td>
          <select id="showSource">
            <option value=""></option>
            <?php foreach($GLOBALS['sources'] as $id => $source) { ?>
            <option value="<?php print $id; ?>"><?php print $source; ?></option>
            <?php } ?>
          </select>
        </td>
      </tr>
      <tr>
        <th></th>
        <td>
          <br />
          <a href="#" id="addShow" class="addShow"><img alt="" src="images/save.png" /> Save</a>
          <a href="#" id="cancelShow" class="cancelShow"><img alt="" src="images/cancel.png" /> Cancel</a>
        </td>
      </tr>
    </table>
    <div id="postersWrapper" class="postersWrapper">
      <a href="#" id="getPosters" class="getPosters"><img alt="" src="images/download.png" /> Get Posters</a> <img id="postersLoading" class="postersLoading" alt="" src="images/loading_posters.gif" />
      <div class="selectPosters" id="selectPosters"></div>
    </div>
  </div>
  <div id="sortBar">
    sort by&nbsp;
    <?php
    $sort_by = array('name', 'upcoming', 'downloaded');
    foreach($sort_by as $name) {
      print '<a href="index.php?sort=' . $name . '&dir=' . ($_GET['sort'] == $name && $_GET['dir'] == 'asc' ? 'desc' : 'asc') . '" class="' . ($_GET['sort'] == $name ? $_GET['dir'] : '') . '">' . $name . '</a> | ';
    }
    ?>
  </div>
  <div id="listingWrapper">
<?php
//auto check for a newer version
if($config['check_for_update']) {
  $version = file_get_contents($config['project_url']);
  if($version !== false) {
    $version = substr($version, strpos($version, 'Current Version:'));
    $version = substr($version, 0, strpos($version, '</strong'));
    $version = substr($version, strpos($version, '>') + 1);
    if(floatval($config['version']) < floatval($version)) { ?>
    <div class="versionBox">A newer version of hellaVCR is out! <a target="_blank" href="<?php print $config['project_url']; ?>"><img alt="download" src="images/download.png" /> Download version <strong><?php print $version; ?></strong></a></div>
    <?php }
  }
}
?>
<?php if(!file_exists($config['xml_tv'])) { ?>
    <div class="errorBox">TV show XML file not found! (<?php print $config['xml_tv']; ?>)</div>
<?php } ?>
<?php if($config['newzbin_handler'] == 'nzb' && !file_exists($config['nzb_queue'])) { ?>
    <div class="errorBox">NZB directory does not exist! (<?php print $config['nzb_queue']; ?>)</div>
<?php } ?>
<?php if($config['newzbin_handler'] == 'nzb' && ($config['newzbin_username'] == 'username' || strlen(trim($config['newzbin_username'])) == 0) && ($config['newzbin_password'] == 'password' || strlen(trim($config['newzbin_password'])) == 0)) { ?>
    <div class="errorBox">Newzbin account required to download NZB files!</div>
<?php } ?>
<?php if(!is_writeable('posters/')) { ?>
    <div class="errorBox">Posters directory is not writable! (<?php print getcwd(); ?>/posters/)</div>
<?php } ?>
<?php if(!function_exists('curl_init')) { ?>
    <div class="errorBox">cURL is not installed! (please see <a target="_blank" href="http://php.net/curl">http://php.net/curl</a>)</div>
<?php } ?>
<?php
//check to make sure the file exists
if(file_exists($config['xml_tv'])) {

  //create a SimpleXML object
  $xml = simplexml_load_file($config['xml_tv']);
  
  $shows = $xml->xpath('/tv/show');
  usort($shows, 'sortShows');
  
  $lastHeader = '';
  
  //loop over each show
  foreach($shows as $show) {
    $showID = htmlentities($show->attributes()->id);
    $showDay = strtolower(date('l', intval($show->next_timestamp)));
    
    //print day headers
    if($config['print_day_headers'] && $_GET['sort'] == 'upcoming') {
      //less than a week, print day
      if(intval($show->next_timestamp) < intval(strtotime("+1 week"))) {
        if($lastHeader != $showDay) {
          print '<h1 class="day">' . $showDay . '</h1>';
          $lastHeader = $showDay;
        }
        
        //if we're on the current day then paint it accordingly
        //if(date('m/d/Y') == date('m/d/Y', intval($show->next_timestamp)))
        //  $show_div_class = "listing_current";
        //else
        //  $show_div_class = "";
      }

      

      //who knows
      else if($show->next_timestamp == $config['index']['unknown_timestamp'] && $lastHeader != $config['index']['headers']['unknown']) {
        print '<h1 class="day">' . $config['index']['headers']['unknown'] . '</h1>';
        $lastHeader = $config['index']['headers']['unknown'];
        //$show_div_class = "listing_unknown";
      }
      
      //more than a week
      else if($show->next_timestamp < $config['index']['unknown_timestamp'] && $lastHeader != $config['index']['headers']['1week+']) {
        print '<h1 class="day">' . $config['index']['headers']['1week+'] . '</h1>';
        $lastHeader = $config['index']['headers']['1week+'];
        //$show_div_class = "listing_toofar";
      }
    }
?>
    <div class="listing" id="listing_<?php print $showID; ?>">
      <a name="<?php print $show->name; ?>"></a>
      <?php
      //poster
      $poster = $show->poster;
      if(strlen($poster) > 0 && file_exists('posters/')) {
        $poster_image = substr($poster, strrpos($poster, '/') + 1);
        
        //cached image already exists
        if(file_exists('posters/' . $poster_image)) {
          $poster = 'posters/' . $poster_image;
        }
        //attempt to cache
        else {
          if(@copy($poster, 'posters/' . $poster_image) && file_exists('posters/' . $poster_image)) {
            $poster = 'posters/' . $poster_image;
          }
        }
      }
      
      ?>
      <img alt="" src="<?php print $poster; ?>" />
      <div id="info_<?php print $showID; ?>">
        <h1>
          <div class="icons">
            <?php
            //build up params
            $newzbin_query = array(
              'q=^' . urlencode($show->name),
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
            if(strlen($show->language) > 0) {
              $newzbin_query[] = 'ps_rb_language=' . $show->language;
            }
            if(strlen($show->format) > 0) {
              $newzbin_query[] = 'ps_rb_video_format=' . $show->format;
            }
            if(strlen($show->source) > 0) {
              $newzbin_query[] = 'ps_rb_source=' . $show->source;
            }
            if(!empty($config['newzbin_groups'])) {
             $newzbin_query[] = 'group=' . urlencode($config['newzbin_groups']);
            }
            ?>
            <a href="<?php print $config['newzbin']['root_url'] . 'search/?' . implode('&', $newzbin_query); ?>" target="_blank" title="newzbin"><img alt="" src="images/newzbin.png" /></a>
            <a href="<?php print $show->url; ?>" target="_blank" title="tvrage"><img alt="" src="images/info.png" /></a>
            <?php if(!empty($show->attributes()->id)) { ?>
            <a href="#" title="edit" id="edit_<?php print $showID; ?>_link" class="editShow"><img alt="" src="images/edit.png" /></a>
            <a href="#" title="delete?" id="<?php print $showID; ?>|<?php print $show->name; ?>" class="delShow"><img alt="" src="images/delete.png" /></a>
            <?php } ?>
          </div>
          <?php print $show->name; ?>
        </h1>
        <?php
        $next_class = $downloaded_class = $status_class = $format_class = $language_class = '';
        
        //default classes
        $download_class = $status_class = $next_class = $air_class = '';
        
        //format downloaded episode
        $downloaded_episode = $show->season . 'x' . sprintf('%02d', $show->episode);
        if(strlen(trim($show->season)) <= 0 || strlen(trim($show->episode)) <= 0) {
          $downloaded_episode = 'n/a (will download most recent)';
          $downloaded_class = 'unknown';
        }
        else if($show->season == 1 && intval($show->episode) == 0) {
          $downloaded_episode = 'n/a (will download all)';
          $downloaded_class = 'unknown';
        }
        
        //format status
        $status = $show->status;
        if(strlen(trim($status)) <= 0) {
          $status = 'n/a';
          $status_class = 'unknown';
        }
        
        //format next episode
        $next_episode = $show->next;
        if(strpos(strtolower($show->status), 'ended') !== false) {
          $next_episode = 'n/a (show ended)';
          $next_class = 'ended';
          $status_class = 'ended';
        }
        else if(strlen(trim($next_episode)) <= 0) {
          $next_episode = 'n/a';
          $next_class = 'unknown';
        }
        
        //format airs
        $airs = 'n/a';
        if($show->airtime != '') {
          $airs = $show->airtime;
          if($config['timezone_24hrmode']) {
            $after_at = strpos($airs, 'at') + 3;
            $airs = substr($airs, 0, $after_at) . date('H:i', strtotime(substr($airs, $after_at)));
          }
        }
        if($show->network != '') {
          $airs .= ' on ' . $show->network;
        }
        ?>
        <p class="next">
          <span class="title">Next Episode:</span> <span class="info <?php print $next_class; ?>"><?php print $next_episode; ?></span>
        </p>
        <p class="noMargin">
          <span class="title">Downloaded Episode:</span> <span class="info <?php print $downloaded_class; ?>"><?php print $downloaded_episode; ?></span><br />
          <!--
          <span id="downloads_<?php print $showID; ?>" class="downloadedEps">
            <?php
            if(isset($show->season) && isset($show->downloads)) {
              $d_seasons = array();              
              
              foreach($show->downloads->download as $d) {
                list($d_season, $d_episode) = explode('x', $d->episode);
                if(!$d_seasons[$d_season]) $d_seasons[$d_season] = array();
                $d_seasons[$d_season][$d_episode] = array(
                  'episode' => $d_episode,
                  'timestamp' => intval($d->timestamp),
                  'text' => $d->episode . ' - ' . date('m/d/Y ' . ($config['timezone_24hrmode'] ? 'H:i' : 'h:i a'), intval($d->timestamp))
                );
              }
              
              
              krsort($d_seasons);
              
              //print out episodes and their status
              foreach($d_seasons as $d_season => $d_eps) {
                print '<h2>Season ' . $d_season . '</h2>';
                krsort($d_eps);
                foreach($d_eps as $d_ep) {
                  print '<div>' . $d_season . 'x' . $d_ep['episode'] . ' <img alt="" src="images/save.png" /></div>';
                }
                print '<br />';
              }
            }
            ?>
          </span>
          -->
          <span class="title">Status:</span> <span class="info <?php print $status_class; ?>"><?php print $status; ?></span><br />
          <span class="title">Airs:</span> <span class="info <?php print $airs_class; ?>"><?php print $airs; ?></span><br />
          <span class="title">Format:</span> <span class="info <?php print $format_class; ?>"><?php print empty($show->format) ? 'any' : $GLOBALS['formats'][strval($show->format)]; ?></span><br />
          <span class="title">Language:</span> <span class="info <?php print $language_class; ?>"><?php print empty($show->language) ? 'any' : $GLOBALS['languages'][strval($show->language)]; ?></span>
        </p>
      </div>
      <div id="edit_<?php print $showID; ?>" class="formWrapper editFormWrapper">
        <form id="editform_<?php print $showID; ?>" method="post" action="">
          <table cellpadding="0" cellspacing="0">
            <tr>
              <th>Show Name:</th>
              <td><input type="text" name="name" id="edit_<?php print $showID; ?>_name" value="<?php print htmlspecialchars_decode($show->name); ?>" /></td>
            </tr>
            <tr>
              <th>Last Episode:</th>
              <td>
                <input type="text" name="season" size="2" value="<?php print htmlentities($show->season); ?>" /> x <input type="text" name="episode" size="2" value="<?php print htmlentities($show->episode); ?>" />
              </td>
            </tr>
            <tr>
              <th>Format:</th>
              <td>
                <select name="format">
                  <option value=""></option>
                  <?php foreach($GLOBALS['formats'] as $id => $format) { ?>
                  <option value="<?php print $id; ?>" <?php print $id == $show->format ? 'selected="selected"' : ''; ?>><?php print $format; ?></option>
                  <?php } ?>
                </select>
              </td>
            </tr>
            <tr>
              <th>Language:</th>
              <td>
                <select name="language">
                  <option value=""></option>
                  <?php foreach($GLOBALS['languages'] as $id => $language) { ?>
                  <option value="<?php print $id; ?>" <?php print $id == $show->language ? 'selected="selected"' : ''; ?>><?php print $language; ?></option>
                  <?php } ?>
                </select>
              </td>
            </tr>
            <tr>
              <th>Source:</th>
              <td>
                <select name="source">
                  <option value=""></option>
                  <?php foreach($GLOBALS['sources'] as $id => $source) { ?>
                  <option value="<?php print $id; ?>" <?php print $id == $show->source ? 'selected="selected"' : ''; ?>><?php print $source; ?></option>
                  <?php } ?>
                </select>
              </td>
            </tr>
            <tr>
              <th></th>
              <td>
                <br />
                <a href="#" id="addShow_<?php print $showID; ?>" class="addShow" onclick="$('editform_<?php print $showID; ?>').submit(); return false;"><img alt="" src="images/save.png" /> Save</a>
                <a href="#" id="cancelShow_<?php print $showID; ?>" class="cancelShow"><img alt="" src="images/cancel.png" /> Cancel</a>
              </td>
            </tr>
          </table>
          <div id="postersWrapper_<?php print $showID; ?>" class="postersWrapper">
            <a href="#" id="getPosters_<?php print $showID; ?>" class="getPosters" onclick="return getPoster('<?php print $showID; ?>');"><img alt="" src="images/download.png" /> Get Posters</a> <img class="postersLoading" id="postersLoading_<?php print $showID; ?>" alt="" src="images/loading_posters.gif" />
            <div class="selectPosters" id="selectPosters_<?php print $showID; ?>"></div>
          </div>
          <input type="hidden" name="op" value="edit" />
          <input type="hidden" name="id" value="<?php print $showID; ?>" />
          <input type="hidden" name="poster" value="<?php print $show->poster; ?>" />
        </form>
      </div>
    </div>
<?php
  }
}
?>
  </div>
</div>
</body>
</html>