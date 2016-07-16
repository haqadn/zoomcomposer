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
		add_action( 'vc_before_init', [ $this, 'map_shortcodes' ] );
		add_action( 'init', [ $this, 'add_post_types' ] );

		$this->create_shortcodes();
	}

	/**
	 * Register the shortcode for ZoomComposer.
	 */
	public function create_shortcodes() {
		add_shortcode( 'zoomcomp_thumb_hover_zoom_gallery', [ $this, 'shortcode_thumb_hover_zoom_gallery' ] );
		add_shortcode( 'zoomcomp_thumb_hover_zoom_item', [ $this, 'shortcode_thumb_hover_zoom_item' ] );
	}

	/**
	 * Shortcode processor for thumb hover zoom gallery.
	 */
	public function shortcode_thumb_hover_zoom_gallery( $atts, $content ) {
		$content = trim( $content );

		global $thumb_dimention, $thumb_group;

		extract( shortcode_atts( [
			'thumb_width'      => 400,
			'thumb_height'     => 400,
			'thumb_group'      => '',
			'images'           => false ], $atts ) );

		if( $images ){
			$images = explode( ',', $images );
			$images = array_map( function($image_id) {
				return "[zoomcomp_thumb_hover_zoom_item attachment_id='$image_id']";
			}, $images);

			$content = implode( "\n", $images );
		}

		$thumb_dimention = [ 'thumb_width' => $thumb_width, 'thumb_height' => $thumb_height ];

		ob_start();
		?>
		<div class="thumbHoverZoomGallery clearfix">
			<?php echo do_shortcode( $content ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode processor thumb hover zoom item.
	 */
	public function shortcode_thumb_hover_zoom_item( $atts ) {

		global $thumb_dimention, $thumb_group;
		if( !isset( $thumb_group ) ) $thumb_group = '';
		extract( shortcode_atts( [
			'thumb_width'   => 400,
			'thumb_height'  => 400 ], $thumb_dimention ) );

		extract( shortcode_atts( [
			'attachment_id' => 0,
			'image_quality' => 90,
			'thumb_width'   => $thumb_width,
			'thumb_height'  => $thumb_height,
			'thumb_group'   => $thumb_group ], $atts ), EXTR_OVERWRITE );


		if( 0 == $attachment_id ) return '';
		$image = wp_get_attachment_image_src( $attachment_id, 'full' );
		if( !$image ) return '';

		$src = $image[0];
		$relative_path = wp_make_link_relative( $src );
		$path_parts = pathinfo( $relative_path );
		$filename = $path_parts['basename'];
		$directory = $path_parts['dirname'];

		$zoomload_url = plugins_url( 'axZm/zoomLoad.php', __FILE__ );
		$zoomload_url = add_query_arg( [
			'previewPic' => $filename,
			'previewDir' => $directory,
			'qual'	   => $image_quality,
			'width'	  => $thumb_width,
			'height'	 => $thumb_height ], $zoomload_url );

		$attachment = get_post( $attachment_id );
		$alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$description = htmlspecialchars( $attachment->post_excerpt );

		ob_start();
		?>
		<div class="thumbContainer" style="<?php echo "width:{$thumb_width}px; height: {$thumb_height}px;" ?>">
			<img class="azHoverThumb" data-group="<?php echo $thumb_group; ?>" data-descr="<?php echo $description; ?>" data-img="<?php echo $relative_path; ?>" src="<?php echo $zoomload_url; ?>" alt="<?php echo $alt; ?>" />
		</div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Map shortcodes in visual composer.
	 */
	public function map_shortcodes() {
		vc_map( [
			"name" => __( "Zoom Hover Thumb Gallery", "zoomcomp" ),
			"base" => "zoomcomp_thumb_hover_zoom_gallery",
			"show_settings_on_create" => true,
			"category" => __( "Structure", "zoomcomp"),
			"params" => [
				[
					"type" => "textfield",
					"heading" => __( "Item Width", "zoomcomp" ),
					"param_name" => "thumb_width",
					'value' => 400,
					"description" => __( "Default width of items inside it(number of pixels).", "zoomcomp" )
				],
				[
					"type" => "textfield",
					"heading" => __( "Item Height", "zoomcomp" ),
					"param_name" => "thumb_height",
					'value' => 400,
					"description" => __( "Default height of items inside it(number of pixels).", "zoomcomp" )
				],
				[
					"type" => "textfield",
					"heading" => __( "Item group", "zoomcomp" ),
					"param_name" => "thumb_group",
					"value" => ''
				],
				[
					"type" => "attach_images",
					"heading" => __( "Images", "zoomcomp" ),
					"param_name" => "images"
				]
			]
		] );

		vc_map( [
			"name" => __( "Zoom Hover Thumb Image", "zoomcomp" ),
			"base" => "zoomcomp_thumb_hover_zoom_item",
			"category" => __( "Content", "zoomcomp"),
			"params" => [
				[
					"type" => "textfield",
					"heading" => __( "Item Width", "zoomcomp" ),
					"param_name" => "thumb_width",
					'value' => 400,
					"description" => __( "Width of items(number of pixels).", "zoomcomp" )
				],
				[
					"type" => "textfield",
					"heading" => __( "Item Height", "zoomcomp" ),
					"param_name" => "thumb_height",
					'value' => 400,
					"description" => __( "Height of items(number of pixels).", "zoomcomp" )
				],
				[
					"type" => "textfield",
					"heading" => __( "Item group", "zoomcomp" ),
					"param_name" => "thumb_group",
					"value" => ''
				],
				[
					"type" => "textfield",
					"heading" => __( "Image quality (1-100)", "zoomcomp" ),
					"param_name" => "image_quality",
					"value" => '90'
				],
				[
					"type" => "attach_image",
					"heading" => __( "Image", "zoomcomp" ),
					"param_name" => "attachment_id"
				]
			]
		] );
	}

	/**
	 * Register custom post type(s).
	 */
	public function add_post_types() {

		$labels = array(
			'name'                  => _x( '360º Galleries', 'Post Type General Name', 'zoomcomp' ),
			'singular_name'         => _x( '360º Gallery', 'Post Type Singular Name', 'zoomcomp' ),
			'menu_name'             => __( '360º Galleries', 'zoomcomp' ),
			'name_admin_bar'        => __( '360º Gallery', 'zoomcomp' ),
			'archives'              => __( '360º Gallery Archives', 'zoomcomp' ),
			'parent_item_colon'     => __( 'Parent Item:', 'zoomcomp' ),
			'all_items'             => __( 'All Galleries', 'zoomcomp' ),
			'add_new_item'          => __( 'Add New 360º Gallery', 'zoomcomp' ),
			'add_new'               => __( 'Add New', 'zoomcomp' ),
			'new_item'              => __( 'New 360º Gallery', 'zoomcomp' ),
			'edit_item'             => __( 'Edit Gallery', 'zoomcomp' ),
			'update_item'           => __( 'Update Gallery', 'zoomcomp' ),
			'view_item'             => __( 'View Gallery', 'zoomcomp' ),
			'search_items'          => __( 'Search Gallery', 'zoomcomp' ),
			'not_found'             => __( 'Not found', 'zoomcomp' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'zoomcomp' ),
			'featured_image'        => __( 'Featured Image', 'zoomcomp' ),
			'set_featured_image'    => __( 'Set featured image', 'zoomcomp' ),
			'remove_featured_image' => __( 'Remove featured image', 'zoomcomp' ),
			'use_featured_image'    => __( 'Use as featured image', 'zoomcomp' ),
			'insert_into_item'      => __( 'Insert into Gallery', 'zoomcomp' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Gallery', 'zoomcomp' ),
			'items_list'            => __( 'Items list', 'zoomcomp' ),
			'items_list_navigation' => __( 'Items list navigation', 'zoomcomp' ),
			'filter_items_list'     => __( 'Filter items list', 'zoomcomp' ),
		);
		$args = array(
			'label'                 => __( '360º Gallery', 'zoomcomp' ),
			'labels'                => $labels,
			'supports'              => array( 'title', ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 20,
			'menu_icon'             => 'dashicons-images-alt',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => false,
			'has_archive'           => false,		
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'rewrite'               => false,
			'capability_type'       => 'page',
		);
		register_post_type( '360_gallery', $args );

	}

	/**
	 * Prepare everything needed for ZoomComposer to work.
	 */
	public static function install() {
		self::install_dir();
		self::install_axzm();
		self::install_config();
	}

	/**
	 * Create necessery directories to store ajaxzoom data.
	 */
	public static function install_dir() {
		
		$dir = self::pic_dir();
		if ( ! file_exists( $dir ) ) mkdir( $dir, 0755 );

		foreach ( array( '2d', '360', 'cache', 'zoomgallery', 'zoommap', 'zoomthumb', 'zoomtiles_80', 'tmp' ) as $folder ) {
			$path = $dir . '/' . $folder;
			if ( ! file_exists( $path )) {
				mkdir( $path, 0755 );
			} else {
				chmod( $path, 0755 );
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
			$localFilePath = self::pic_dir() . '/tmp/jquery.ajaxZoom_ver_latest.zip';

			file_put_contents( $localFilePath, $remoteFileContents );

			$zip = new \ZipArchive();
			$res = $zip->open( $localFilePath );
			$zip->extractTo( self::pic_dir() . '/tmp/' );
			$zip->close();

			rename( self::pic_dir() . '/tmp/axZm', $dir . 'axZm' );
		}
	}

	/**
	 * Copy the config file to place when installed.
	 */
	public static function install_config() {
		if( !copy( __DIR__ . '/zoomConfigCustom.inc.php',  __DIR__ . '/axZm/zoomConfigCustom.inc.php' ) )
			self::notice( 'error', __( 'Unable to copy config file. Please copy zoomConfigCustom.inc.php to axZm directory manually.', 'zoomcomp' ) );
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

	public static function pic_dir() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'zoomcomp';
	}
}


if ( class_exists( 'WPBakeryShortCode' ) ) {
	class WPBakeryShortCode_Zoomcomp_Thumb_Hover_Zoom_Gallery extends WPBakeryShortCode {
	}
	class WPBakeryShortCode_Zoomcomp_Thumb_Hover_Zoom_Item extends WPBakeryShortCode {
	}
}


global $zoomComposer;
$zoomComposer = new ZoomComposer;
