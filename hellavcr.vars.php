<?php

##### newzbin

$GLOBALS['formats'] = array(
  '1' => 'Divx',
  '2' => 'DVD',
  '4' => 'SVCD',
  '8' => 'VCD',
  '16' => 'Xvid',
  '32' => 'HDts',
  '64' => 'WMV',
  '128' => 'Other',
  '256' => 'ratDVD',
  '512' => 'Ipod',
  '1024' => 'PSP',
  '2048' => 'H.264',
  '65536' => 'HD-DVD',
  '131072' => 'x264',
  '262144' => 'Blu-ray',
  '524288' => '720p',
  '1048576' => '1080i',
  '2097152' => '1080p',
  '1073741824' => 'Unknown'
);

$GLOBALS['languages'] = array(
  '2' => 'French',
  '4' => 'German',
  '8' => 'Spanish',
  '16' => 'Danish',
  '32' => 'Dutch',
  '64' => 'Japanese',
  '128' => 'Korean',
  '256' => 'Russian',
  '512' => 'Italian',
  '1024' => 'Cantronese',
  '2048' => 'Polish',
  '4096' => 'English',
  '8192' => 'Vietnamese',
  '16384' => 'Swedish',
  '32768' => 'Norwegian',
  '65536' => 'Finnish',
  '131072' => 'Mandarin',
  '1073741824' => 'Unknown'
);

$GLOBALS['sources'] = array(
  '1' => 'CAM',
  '2' => 'Screener',
  '4' => 'TeleCine',
  '8' => 'TeleSync',
  '16' => 'Workprint',
  '32' => 'VHS',
  '64' => 'DVD',
  '128' => 'HDTV',
  '256' => 'TV Cap',
  '512' => 'HD-DVD',
  '1024' => 'R5 Retail',
  '2048' => 'Blu-ray',
  '1073741824' => 'Unknown',
);					

##### system values

$config['version'] = '0.7';
$config['debug_separator'] = ' | ';
$config['project_url'] = 'http://code.google.com/p/hellavcr/';
$config['logging'] = array(
  'date_format' => '[m/d/y H:i:s] '
);
$config['thetvdb'] = array(
  'small_poster' => 'http://thetvdb.com/banners/_cache/posters/',
  'large_poster' => 'http://thetvdb.com/banners/posters/',
  'show_info' => 'http://www.thetvdb.com/api/GetSeries.php?seriesname=',
  'api_key' => 'A6D11F92201EEBFA'
);

//clever beginnings to tweets, rest of sentence ends in: [show] [season]x[episode]
$config['hollers'] = array(
  'queued '
);

//index page
$config['index'] = array(
  'unknown_timestamp' => '999999999999',
  'headers' => array(
    '1week+' => 'more than a week',
    'unknown' => 'unknown'
  )
);

?>
