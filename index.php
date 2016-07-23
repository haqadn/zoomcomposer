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
		add_action( 'add_meta_boxes', [ $this, 'add_360_gallery_metaboxes' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_upload_gallery_image', [ $this, 'process_upload_gallery_image' ]);
		add_action( 'save_post', [ $this, 'update_gallery_images' ]);
		add_action( 'delete_post', [ $this, 'delete_gallery_images' ]);

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

		$zoomload_url = $this->make_thumb_link( $image[0], [
			'qual'      => $image_quality,
			'width'     => $thumb_width,
			'height'    => $thumb_height,
			'thumbMode' => 'cover'
		] );

		$attachment = get_post( $attachment_id );
		$alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$description = htmlspecialchars( $attachment->post_excerpt );

		ob_start();
		?>
		<div class="thumbContainer" style="<?php echo "width:{$thumb_width}px; height: {$thumb_height}px;" ?>">
			<img class="azHoverThumb" data-group="<?php echo $thumb_group; ?>" data-descr="<?php echo $description; ?>" data-img="<?php echo wp_make_link_relative( $image[0] ); ?>" src="<?php echo $zoomload_url; ?>" alt="<?php echo $alt; ?>" />
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
	 * Add metabox to 360_gallery
	 */
	public function add_360_gallery_metaboxes() {

		global $post;

		if( $post->post_type != '360_gallery' ) return;

		$upload_dir = wp_upload_dir();

		add_meta_box( 'gallery_images', 'Images', [ $this, 'gallery_image_upload_metabox_content' ], '360_gallery', 'normal', 'high' );
		$zoomcomp_upload_dir = $upload_dir['basedir'].'/zoomcomp/360/'.$post->ID;
		if( !empty( glob( $zoomcomp_upload_dir . '/*.*' ) ) )
			add_meta_box( 'gallery_360', 'Gallery', [ $this, 'gallery_metabox_content' ], '360_gallery', 'normal', 'high' );
	}

	/**
	 * Set the metabox content for 360 gallery image uploader.
	 */
	public function gallery_image_upload_metabox_content() {
		global $post;
		?>
		<div class="gallery-image-upload">
			<p class="dz-message">Drag &amp; drop your image here or click to upload.</p>
		</div>
		<ul class="existing-images">
			<?php 
			$upload_dir = wp_upload_dir();
			if( file_exists( self::pic_dir().'/360/'.$post->ID ) ){
				foreach( glob(self::pic_dir().'/360/'.$post->ID."/*.*") as $filename ){
					$filename = wp_basename($filename);
					$image_url = $this->make_thumb_link( $upload_dir['baseurl'].'/zoomcomp/360/'.$post->ID."/$filename", [
						'qual'       => 80,
						'width'      => 70,
						'height'     => 70,
						'cache'      => 0,
						'thumbMode'  => 'cover',
						'timestamp'  => time()
					]);

					?>
					<li>
						<img src='<?php echo $image_url; ?>'>
						<input type='hidden' name='gallery_filename[]' value='<?php echo $filename; ?>'/>
						<input type='hidden' class="remove-flag" name='gallery_removed[]' value='no'/>
						<br style="clear:both">
					</li>
					<?php
				}
			}
			?>
			<br style="clear:both">
			
		</ul>
		<?php
	}

	/**
	 * Handles the upload of gallery image.
	 */
	public function process_upload_gallery_image() {
		header('Content-Type: application/json');

		$action = 'upload_gallery_image';

		global $zoomcomp_upload_dir;
		$zoomcomp_upload_dir = '/zoomcomp/360/'.$_POST['post_id'];

		add_filter( 'upload_dir', [$this, 'filter_pic_directory'] );
		$upload = wp_handle_upload( $_FILES['file'], ['action' => $action] );
		remove_filter( 'upload_dir', [$this, 'filter_pic_directory'] );


		$url = $this->make_thumb_link( $upload['url'], [
			'qual'       => 80,
			'width'      => 70,
			'height'     => 70,
			'cache'      => 0,
			'thumbMode'  => 'cover',
			'timestamp'  => time()
		] );

		echo json_encode( ['success' => true, 'url' => $url, 'filename' => wp_basename($upload['url'])] );

		exit;
	}

	/**
	 * Update the image file names for gallery.
	 */
	public function update_gallery_images( $post_id ) {

		if( !isset( $_POST['gallery_filename'] ) || !isset( $_POST['gallery_removed'] ) ) return;
		$dir = self::pic_dir().'/360/'.$post_id;
		$temp_dir = $dir.'_temp';

		if( file_exists( $temp_dir ) )
			rmdir( $temp_dir );

		if( file_exists( $dir ) )
			rename( $dir, $temp_dir );
		mkdir( $dir, 0755 );


		$filenames = $_POST['gallery_filename'];
		$remove_file = $_POST['gallery_removed'];


		for( $i = 0; $i < count( $filenames ); $i++ ){
			$pathinfo = pathinfo( $filenames[$i] );
			$new_name = strrev( $post_id ) . '_' . str_pad($i,4,"0",STR_PAD_LEFT);

			if( $remove_file[$i] != 'yes' )
				rename( $temp_dir.'/'.$filenames[$i], $dir.'/'.$new_name.'.'.$pathinfo['extension'] );
			else
				unlink( $temp_dir.'/'.$filenames[$i] );

			array_map('unlink', glob(self::pic_dir().'/cache/'.$pathinfo['filename'][0].'/'.$pathinfo['filename'][1].'/'.$pathinfo['filename'].'*.'.$pathinfo['extension']));
		}

		if( file_exists( $temp_dir ) )
			rmdir( $temp_dir );

	}

	/**
	 * Delete gallery images and all the files related to it.
	 */
	public function delete_gallery_images( $post_id ){
		$dir = self::pic_dir().'/360/'.$post_id;
		$file_prefix = strrev($post_id);

		array_map('unlink', glob($dir.'/*.*'));
		rmdir( $dir );
		array_map('unlink', glob(self::pic_dir().'/cache/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*.*'));
	}

	/**
	 * Generate a link to thumbnail that is provided with axZm.
	 */
	public function make_thumb_link( $image_url, $atts = array() ){
		$src = $image_url;
		$relative_path = wp_make_link_relative( $src );
		$path_parts = pathinfo( $relative_path );
		$filename = $path_parts['basename'];
		$directory = $path_parts['dirname'];

		$zoomload_url = plugins_url( 'axZm/zoomLoad.php', __FILE__ );
		$args = array_merge(
			[
				'previewPic' => $filename,
				'previewDir' => $directory,
			], $atts );

		return add_query_arg( $args, $zoomload_url );
	}

	/**
	 * Enqueue javascript and css files on back-end.
	 */
	public function enqueue_admin_scripts() {

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script( 'ajaxzoom', plugins_url( 'axZm/jquery.axZm.js', __FILE__ ) );
		wp_enqueue_style( 'ajaxzoom', plugins_url( 'axZm/axZm.css', __FILE__ ) );

		wp_enqueue_script( 'dropzone', plugins_url( 'js/dropzone.min.js', __FILE__ ), [ 'jquery' ] );
		wp_enqueue_script( 'dropzone-amd-module', plugins_url( 'js/dropzone-amd-module.min.js', __FILE__ ), [ 'jquery' ] );
		wp_enqueue_style( 'dropzone', plugins_url( 'css/dropzone.min.css', __FILE__ ) );

		wp_enqueue_script( 'zoomcomposer', plugins_url( 'js/zoomcomp.js', __FILE__ ), [ 'jquery', 'jquery-ui-sortable' ] );
		wp_enqueue_style( 'zoomcomposer', plugins_url( 'css/zoomcomp.css', __FILE__ ) );

	}

	/**
	 * Scripts and styles enqueued for front end.
	 */
	public function enqueue_scripts (){
		wp_enqueue_script( 'jquery' );

		wp_enqueue_script( 'ajaxzoom', plugins_url( 'axZm/jquery.axZm.js', __FILE__ ) );
		wp_enqueue_style( 'ajaxzoom', plugins_url( 'axZm/axZm.css', __FILE__ ) );
		
		wp_enqueue_script( 'hover-thumb', plugins_url( 'axZm/extensions/jquery.axZm.hoverThumb.js', __FILE__ ) );
		wp_enqueue_style( 'hover-thumb', plugins_url( 'axZm/extensions/jquery.axZm.hoverThumb.css', __FILE__ ) );

		wp_enqueue_script( 'zoomcomposer', plugins_url( 'js/zoomcomp.js', __FILE__ ) );
		wp_enqueue_style( 'zoomcomposer', plugins_url( 'css/zoomcomp.css', __FILE__ ) );

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

	/**
	 * Provides plugin directory.
	 */
	public static function dir() {
		return plugin_dir_path( __FILE__ );
	}

	/**
	 * The directory where all zoomcomposer files are stored.
	 */
	public static function pic_dir() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'zoomcomp';
	}

	/**
	 * Change the directory of upload to be used with filters. Store files in the zoomcomp folder instead of default wp structure.
	 */
	public function filter_pic_directory( $dirs ) {
		global $zoomcomp_upload_dir;

		$dirs['subdir'] = $zoomcomp_upload_dir;
		$dirs['path'] = $dirs['basedir'] . $zoomcomp_upload_dir;
		$dirs['url'] = $dirs['baseurl'] . $zoomcomp_upload_dir;

		return $dirs;
	}
}


if ( class_exists( 'WPBakeryShortCode' ) ) {
	class WPBakeryShortCode_Zoomcomp_Thumb_Hover_Zoom_Gallery extends WPBakeryShortCode {}
	class WPBakeryShortCode_Zoomcomp_Thumb_Hover_Zoom_Item extends WPBakeryShortCode {}
}


global $zoomComposer;
$zoomComposer = new ZoomComposer;
