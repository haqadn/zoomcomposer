<?php
/*
Plugin Name: Zoom Composer
Plugin URI: http://zoomlookbook.com/
Description: Visual composer integration for ajax-zoom
Version: 0.1
Author: Mohaimenul Adnan
Author URI: http://eadnan.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $zoomcomp_db_version, $notices;
$notices = [];

// Helper file for development environment.
if( file_exists( __DIR__ . '/development.php' ) ) include_once __DIR__.'/development.php';


/**
 * Main class for the zoom composer plugin.
 */
class ZoomComposer {

	function __construct() {
		register_activation_hook( __FILE__, [ $this, 'install' ] );
		add_action( 'admin_init', [ $this, 'deactivate_plugin' ] );
		add_action( 'admin_notices', [ $this, 'show_notice' ] );
	}


	/**
	 * Prepare everything needed for ZoomComposer to work.
	 */
	public static function install() {
		self::install_dir();
		self::install_axzm();
	}

	/**
	 * Create necessery directories to store ajaxzoom data.
	 */
	public static function install_dir() {
		
		$dir = self::dir();
		if ( ! file_exists( $dir . 'pic' ) ) mkdir( $dir . 'pic', 0766 );

		foreach ( array( '2d', '360', 'cache', 'zoomgallery', 'zoommap', 'zoomthumb', 'zoomtiles_80', 'tmp' ) as $folder ) {
			$path = $dir . 'pic/' . $folder;
			if ( ! file_exists( $path )) {
				mkdir( $path, 0766 );
			} else {
				chmod( $path, 0766 );
			}
		}
	}

	/**
	 * Download ajaxzoom and copy it to plugin.
	 */
	public static function install_axzm() {

		$dir = self::dir();
		if ( ! file_exists( $dir . 'axZm' ) && ini_get( 'allow_url_fopen' ) ) {
			$remoteFileContents = file_get_contents( 'http://www.ajax-zoom.com/download.php?ver=latest&module=woo' );
			$localFilePath = $dir . 'pic/tmp/jquery.ajaxZoom_ver_latest.zip';

			file_put_contents( $localFilePath, $remoteFileContents );

			$zip = new \ZipArchive();
			$res = $zip->open( $localFilePath );
			$zip->extractTo( $dir . 'pic/tmp/' );
			$zip->close();

			rename( $dir . 'pic/tmp/axZm', $dir . 'axZm' );
		}
	}

	/**
	 * Create a notice to show on admin notice.
	 * 
	 * @var string $type Type of the notice. Possible values are error, warning, success, info.
	 * @var string $message Message to show on the notice.
	 */
	public static function notice( $type, $message ) {
		global $notices;

		$message = trim( (string) $message );

		if( !in_array( $type, [ 'error', 'warning', 'success', 'info' ] ) ) return;
		if( '' == $message ) return;

		$notices[] = [ 'type' => $type, 'message' => $message ];
	}

	/**
	 * Uninstall the plugin if requirements do not match.
	 */
	public static function deactivate_plugin() {
		$dir = self::dir();

		if ( ! file_exists( $dir . 'axZm' ) && ! ini_get( 'allow_url_fopen' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			unset($_GET['activate']);

			self::notice( 'error', sprintf( __( 'Unable to download ajaxzoom. Please download manually from %s and extract the contents to %s and try reactivating.', 'zoomcomp' ),
				'http://www.ajax-zoom.com/download.php?ver=latest&module=woo',
				__DIR__ ) );
		}
		elseif( ! file_exists( $dir . 'axZm' ) ) {
			self::install_axzm();
		}
	}

	/**
	 * Output the admin notices.
	 */
	public static function show_notice() {
		global $notices;

		foreach( $notices as $notice ){
			extract( $notice );

			echo "<div class=\"notice notice-$type is-dismissible\">";
			echo "<p>";
			echo $message;
			echo "</p>";
			echo "</div>";
		}
	}



	public static function dir() {
		return plugin_dir_path( __FILE__ );
	}
}

global $zoomComposer;
$zoomComposer = new ZoomComposer;
