<?php
/**
 * Licence settings
 */
$zoom['config']['licenceKey'] = 'demo';
$zoom['config']['licenceType'] = 'Basic';
$zoom['config']['error300'] = '';

/**
 * Set directories
 */
$zoom['config']['pic']    = '/wp-content/uploads/';
$zoom['config']['thumbs'] = '/wp-content/uploads/zoomcomp/zoomthumb/'; // string
$zoom['config']['temp'] = '/wp-content/uploads/zoomcomp/temp/'; // string
$zoom['config']['gallery'] = '/wp-content/uploads/zoomcomp/zoomgallery/'; // string
$zoom['config']['tempCache'] = '/wp-content/uploads/zoomcomp/cache/';
$zoom['config']['gPyramidPath'] = '/wp-content/uploads/zoomcomp/zoompyramid/';
$zoom['config']['mapPath'] = '/wp-content/uploads/zoomcomp/zoommap/';
$zoom['config']['pyrTilesPath'] = '/wp-content/uploads/zoomcomp/zoomtiles_80/'; //string

$zoom['config']['picDir'] = dirname(dirname(dirname(__FILE__))).'/uploads/zoomcomp/';
$zoom['config']['thumbDir'] = dirname(dirname(dirname(__FILE__))).'/uploads/zoomcomp/zoomthumb/'; // string
$zoom['config']['gPyramidDir'] = dirname(dirname(dirname(__FILE__))).'/uploads/zoomcomp/zoompyramid/';
$zoom['config']['tempDir'] = dirname(dirname(dirname(__FILE__))).'/uploads/zoomcomp/temp/'; // string
$zoom['config']['galleryDir'] = dirname(dirname(dirname(__FILE__))).'/uploads/zoomcomp/zoomgallery/'; // string
$zoom['config']['tempCacheDir'] = dirname(dirname(dirname(__FILE__))).'/uploads/zoomcomp/cache/';
$zoom['config']['mapDir'] = dirname(dirname(dirname(__FILE__))).'/uploads/zoomcomp/zoommap/';
$zoom['config']['pyrTilesDir'] = dirname(dirname(dirname(__FILE__))).'/uploads/zoomcomp/zoomtiles_80/'; //string
/**
 * Disable messages
 */
$zoom['config']['disableAllMsg'] = true;

// echo "<pre>";
// print_r( $zoom );
// exit;