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
		add_action( 'wp_ajax_get_hotspot_json', [ $this, 'output_hotspot_json' ]);
		add_action( 'save_post', [ $this, 'update_gallery_images' ]);
		add_action( 'save_post', [ $this, 'save_hotspot_data' ]);
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

		add_meta_box( 'gallery_images', __( 'Images', 'zoomcomp' ), [ $this, 'gallery_image_upload_metabox_content' ], '360_gallery', 'normal', 'high' );
		$zoomcomp_upload_dir = $upload_dir['basedir'].'/zoomcomp/360/'.$post->ID;

		if( !empty( glob( $zoomcomp_upload_dir . '/*.*' ) ) ) {
			add_meta_box( 'gallery_360', __( 'Gallery', 'zoomcomp' ), [ $this, 'gallery_metabox_content' ], '360_gallery', 'normal', 'high' );
			add_meta_box( 'gallery_hotspot', __( 'Hotspots', 'zoomcomp' ), [ $this, 'gallery_hotspot_metabox_content' ], '360_gallery', 'normal', 'low' );
		}
	}

	/**
	 * Set the metabox content for 360 gallery image uploader.
	 */
	public function gallery_image_upload_metabox_content() {
		global $post;
		?>
		<div class="gallery-image-upload">
			<p class="dz-message"><?php _e( 'Drag & drop your image here or click to upload.', 'zoomcomp' ); ?></p>
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
						<span class="remove-btn">x</span>
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
	 * Output the json data of hotspot related to a 360º gallery.
	 */
	public function output_hotspot_json() {
		$post_id = $_REQUEST['post_id'];

		echo get_post_meta( $post_id, 'hotspot_json', true );
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

		// Remove generated images
		$file_prefix = strrev($post_id);
		array_map('unlink', glob(self::pic_dir().'/cache/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*.*'));
		array_map('unlink', glob(self::pic_dir().'/zoommap/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*.*'));
		array_map('unlink', glob(self::pic_dir().'/zoomthumb/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*.*'));
		array_map('unlink', glob(self::pic_dir().'/zoomtiles_80/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*/*.*'));
		array_map('rmdir', glob(self::pic_dir().'/zoomtiles_80/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*'));
	}

	/**
	 * Save hotspot configuration
	 */
	public function save_hotspot_data( $post_id ) {
		if( '360_gallery' != get_post_type( $post_id ) ) return;
		
		update_post_meta( $post_id, 'hotspot_json', $_POST['hotspot_json'] );
	}

	/**
	 * Delete gallery images and all the files related to it.
	 */
	public function delete_gallery_images( $post_id ){
		$dir = self::pic_dir().'/360/'.$post_id;
		$file_prefix = strrev($post_id);

		array_map('unlink', glob($dir.'/*.*'));
		if( file_exists( $dir ) ) rmdir( $dir );
		array_map('unlink', glob(self::pic_dir().'/cache/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*.*'));
		array_map('unlink', glob(self::pic_dir().'/zoommap/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*.*'));
		array_map('unlink', glob(self::pic_dir().'/zoomthumb/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*.*'));
		array_map('unlink', glob(self::pic_dir().'/zoomtiles_80/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*/*.*'));
		array_map('rmdir', glob(self::pic_dir().'/zoomtiles_80/'.$file_prefix[0].'/'.$file_prefix[1].'/'.$file_prefix.'_*'));
	}

	/**
	 * Output content of gallery metabox.
	 */
	public function gallery_metabox_content() {
		?>
		<div id="AZplayerParentContainer"></div>
		<br style="clear:both" />
		<?php
	}

	/**
	 * Output metabox content for gallery hotspots.
	 */
	public function gallery_hotspot_metabox_content() {
		?>
		
		
		<div id="aZhS_tabs" style="margin-top: 20px; margin-bottom: 20px;">
			
			<ul>
				<li><a href="#aZhS_tabs-1">Hotspots</a></li>
				<li><a href="#aZhS_tabs-2">Tooltips / Text</a></li>
				<li><a href="#aZhS_tabs-5">Edit JSON</a></li>
			</ul>
			
			<div id="aZhS_tabs-1">
				<div style="clear: both; margin: 5px 0px 10px 0px; padding: 5px;" class="ui-widget-header ui-corner-all">
				<label>Select hotspot to edit:</label> <select id="hotspotSelector" style="font-size: 120%" onchange="jQuery('#hotspotSelector2').val(jQuery('#hotspotSelector').val()).attr('selected', true); jQuery.aZhSpotEd.colorSelectedHotspot();"></select>
				<input type="button" value="Update List" onClick="jQuery.aZhSpotEd.updateHotspotSelector()">
				</div>
				
				
				<div id="aZhS_hotspots" style="margin-top: 5px; margin-bottom: 5px;">

					<ul>
						<li><a href="#aZhS_hotspots-1">New Hotspot</a></li>
						<li><a href="#aZhS_hotspots-2">Actions / Delete</a></li>
						<li><a href="#aZhS_hotspots-3">Appearance</a></li>
					</ul>
					
					<div id="aZhS_hotspots-1">
						<div class="legend">Create new hotspot</div>
						<p>Below are only couple settings you can set right away. 
						
						</p>
							
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>New hotspot name:</label>
						<input type="text" style="width: 300px" value="" id="fieldNewHotSpotName"> 
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>Hotspot type (<a href="javascript: void(0)" class="optDescr">shape</a>):</label>
						<input name="hotspotShape" type="radio" value="point" onclick="jQuery('#rectDimFields, #rectSettings, #rectAddMessage').css('display', 'none')" checked> - point &nbsp;&nbsp;
						<input name="hotspotShape" type="radio" value="rect" onclick="jQuery('#rectDimFields, #rectSettings, #rectAddMessage').css('display', '')"> - rectangle
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>Place on all frames:</label>
						<input type="checkbox" id="newHotspotAllFrames" value="1" checked> - makes most sense for 360 / 3D
						</div>

						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>Auto alt title:</label>
						<input type="checkbox" id="newHotspotAltTitle" value="1" checked> - set <a href="javascript: void(0)" class="optDescr">altTitle</a>
						same as hotspot name
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>Size:</label>
						Left: <input type="text" style="width: 50px" value="" id="fieldRectLeft"> 
						Top: <input type="text" style="width: 50px" value="" id="fieldRectTop">
							<span id="rectDimFields" style="display: none;">
								&nbsp;&nbsp;Width: <input type="text" style="width: 50px" value="" id="fieldRectWidth">
								Height: <input type="text" style="width: 50px" value="" id="fieldRectHeight"> 
							</span>
						</div>
						
						<div class='labelOffset' style="clear: both; margin: 5px 0px 5px 0px;">
							<div class="azMsg">The 'left', 'top', 'width' and 'height' values can be pixel values related to original size of the image 
							(e.g. left: 1600, top: 900 or left: '1600px', top: '900px') 
							or they can be percentage values (e.g. left: '45.75%', top: '37.3%'). 
							<span id="rectAddMessage" style="display: none">
							For rectangles, if you want to put a full covering overlay, set left: 0, top: 0, width: '100%' and height: '100%'
							</span>
							</div>
						</div>
						
						<div id="rectSettings" style="display: none">
							<div style="clear: both; margin: 5px 0px 5px 0px;">
								<label>Text width, height 100% (<a href="javascript: void(0)" class="optDescr">hotspotTextFill</a>):</label>
									<input type="checkbox" value="1" id="fieldHotspotTextFill"> - for more settings see 
									<a href="javascript: void(0)" class="linkShowTab" onclick="jQuery('#aZhS_tabs').tabs('select','#aZhS_tabs-2'); jQuery('#aZhS_tooltip').tabs('select','#aZhS_tooltip-2');">Tooltips / Text -> For rectangles</a>
							</div>
		
							<div style="clear: both; margin: 5px 0px 5px 0px;">
								<label>CSS Class (<a href="javascript: void(0)" class="optDescr">hotspotTextClass</a>):</label>
								<input type="text" value="" style="width: 200px" id="fieldHotspotTextClass">
								e.g. axZmHotspotTextCustom (try it)
							</div>
							<div style="clear: both; margin: 5px 0px 5px 0px;">
								<label>Inline CSS (<a href="javascript: void(0)" class="optDescr">hotspotTextCss</a>):</label>
								e.g. {"color":"black","height":"100%","width":"100%"}
								<input type="text" value="" style="width: 100%" id="fieldHotspotTextCss"> 
							</div>
						</div>
						

						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>&nbsp;</label>
						<input type="button" value="CREATE" onClick="jQuery.aZhSpotEd.addNewHotspot()"> 
						</div>
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>&nbsp;</label>
						<!-- Todo: form for all possible options !-->
						</div>					
					</div>
				
					<div id="aZhS_hotspots-2">
						<div class="legend">Delete / disable hotspots</div>
						<div class="azMsg">
							Instead of clicking on the "disable in this frame" button below you can also simply right click on any hotspot to disable it in current frame. 
							This right click action is only activated when hotspots are draggable / editable in this editor.
						</div>	
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>Enable / disable selected hotspot for current frame:</label> 
						<input type="button" value="Disable in this frame" onClick="jQuery.fn.axZm.showHotspotLayer(); jQuery.fn.axZm.toggleHotspotFrame(jQuery.aZhSpotEd.getHotspotSelector(), 'disable')"> 
						<input type="button" value="Enable in this frame" onClick="jQuery.fn.axZm.showHotspotLayer(); jQuery.fn.axZm.toggleHotspotFrame(jQuery.aZhSpotEd.getHotspotSelector(), 'enable')"> 
						(for 360 and 3D)
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>Delete hotspot:</label>
						<input type="button" value="Delete hotspot" id="hotspotDeleteButton" onClick="jQuery.aZhSpotEd.deleteHotspot()">
						</div>
						

						
						<div class="legend">Some API functions</div>
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>Hotspots draggable:</label> 
						<input type="button" value="Draggable" onClick="jQuery.fn.axZm.showHotspotLayer(); jQuery.fn.axZm.hotspotsDraggable()"> 
						<input type="button" value="Not Draggable" onClick="jQuery.fn.axZm.showHotspotLayer(); jQuery.fn.axZm.hotspotsDraggable(true)"> 
						- only for changing positions in the editor
						
						</div>	
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
						<label>Hide / Show all Hotspots:</label> 
						<input type="button" value="Show" onClick="jQuery.fn.axZm.showHotspotLayer()"> 
						<input type="button" value="Hide" onClick="jQuery.fn.axZm.hideHotspotLayer()"> 
						</div>
						
					</div>
					
					<div id="aZhS_hotspots-3">
			
						<div class="legend" style="position: relative;">Hotspot appearance
							<div id="hotspotImgPreview" style="position: absolute; right: 0px; top: 0px; background-color: #EDEDED; width: auto; height: auto; padding: 5px;"></div>
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Hotspot enabled (<a href="javascript: void(0)" class="optDescr">enabled</a>):</label>
							<input type="checkbox" name="hotspot_enabled" id="hotspot_enabled" value="1" checked>
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Icon dimensions:</label>
							<a href="javascript: void(0)" class="optDescr">width</a>: 
							<input type="text" value="32" style="width: 50px;" id="hotspot_width">px  &nbsp;&nbsp;
							<a href="javascript: void(0)" class="optDescr">height</a>: 
							<input type="text" value="32" style="width: 50px;" id="hotspot_height">px				
						</div>
				
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Icon image (<a href="javascript: void(0)" class="optDescr">hotspotImage</a>):</label>
							<input type="text" value="hotspot64_green.png" style="width: 450px;" id="hotspot_hotspotImage">
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Icon over (<a href="javascript: void(0)" class="optDescr">hotspotImageOnHover</a>):</label>
							<input type="text" value="" style="width: 450px;" id="hotspot_hotspotImageOnHover">
						</div>

						<div class="azMsg">
							Please note, that when a specific hotspot is selected in the editor, 
							the default "hotspotImage" (the red round with plus sign on it) is applied to highlight the selection.  
							This happens only in this hotspot configurator and does not affect your final code. 
							Also the hotspots are only draggable in this configurator.
						</div>	

						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Visibility range:</label>
							<a href="javascript: void(0)" class="optDescr">zoomRangeMin</a>: 
							<input type="text" value="0" style="width: 50px;" id="hotspot_zoomRangeMin">% &nbsp;&nbsp;
							<a href="javascript: void(0)" class="optDescr">zoomRangeMax</a>: 
							<input type="text" value="100" style="width: 50px;" id="hotspot_zoomRangeMax">%
						</div>

						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Gravity (<a href="javascript: void(0)" class="optDescr">gravity</a>):</label> 
							<select id="hotspot_gravity">
								<option value="center" selected>center</option>
								<option value="topLeft">topLeft</option>
								<option value="top">top</option>
								<option value="topRight">topRight</option>
								<option value="right">right</option>
								<option value="bottomRight">bottomRight</option>
								<option value="bottom">bottom</option>
								<option value="bottomLeft">bottomLeft</option>
								<option value="left">left</option>
							</select> 
							&nbsp;&nbsp;
							<a href="javascript: void(0)" class="optDescr">offsetX</a>:
							<input type="text" value="0" style="width: 50px;" id="hotspot_offsetX">px &nbsp;&nbsp;
							<a href="javascript: void(0)" class="optDescr">offsetY</a>:
							<input type="text" value="0" style="width: 50px;" id="hotspot_offsetY">px
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Padding (<a href="javascript: void(0)" class="optDescr">padding</a>):</label>
							<input type="text" value="0" style="width: 50px;" id="hotspot_padding">px
						</div>

						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Opacity:</label>
							<a href="javascript: void(0)" class="optDescr">opacity</a>: 
							<input type="text" value="1" style="width: 50px;" id="hotspot_opacity"> &nbsp;&nbsp;
							<a href="javascript: void(0)" class="optDescr">opacityOnHover</a>: 
							<input type="text" value="1" style="width: 50px;" id="hotspot_opacityOnHover">
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Layer level (<a href="javascript: void(0)" class="optDescr">zIndex</a>):</label>
							<input type="text" value="1" style="width: 50px;" id="hotspot_zIndex"> &nbsp;&nbsp;
						</div>
						
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Background color (<a href="javascript: void(0)" class="optDescr">backColor</a>:)</label>
							<input type="text" value="none" style="width: 150px;" id="hotspot_backColor"> &nbsp;&nbsp;
						</div>

						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Border:</label>
							<a href="javascript: void(0)" class="optDescr">borderWidth</a>: 
							<input type="text" value="0" style="width: 30px;" id="hotspot_borderWidth">px &nbsp;&nbsp;
							<a href="javascript: void(0)" class="optDescr">borderColor</a>: 
							<input type="text" value="red" style="width: 100px;" id="hotspot_borderColor"> &nbsp;&nbsp;
							<a href="javascript: void(0)" class="optDescr">borderStyle</a>: 
							<input type="text" value="solid" style="width: 70px;" id="hotspot_borderStyle">
						</div>

						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Cursor (<a href="javascript: void(0)" class="optDescr">cursor</a>):</label>
							<input type="text" value="pointer" style="width: 450px;" id="hotspot_cursor">
						</div>

						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<input type="button" value="Apply" onClick="jQuery.aZhSpotEd.saveHotspotTooltip()"> &nbsp;&nbsp;
							<input type="checkbox" value="1" id="hotspotApplyAll"> - apply for all hotspots
						</div>	
								
						
					</div>
				
				</div>

				
			</div>
		
			<div id="aZhS_tabs-2">
				<div style="clear: both; margin: 5px 0px 10px 0px; padding: 5px;" class="ui-widget-header ui-corner-all">
					<label>Select hotspot to edit:</label> 
					<select id="hotspotSelector2" style="font-size: 120%" onchange="jQuery('#hotspotSelector').val(jQuery('#hotspotSelector2').val()).attr('selected', true); jQuery.aZhSpotEd.colorSelectedHotspot();"></select>
					<input type="button" value="Update List" onClick="jQuery.aZhSpotEd.updateHotspotSelector()">
				</div>
							
				<div id="aZhS_tooltip" style="margin-top: 5px; margin-bottom: 5px;">

					<ul>
						<li><a href="#aZhS_tooltip-1">Tooltips</a></li>
						<li><a href="#aZhS_tooltip-2">For rectangles</a></li>
						<li><a href="#aZhS_tooltip-3">Link / Events</a></li>
					</ul>
				
					<div id="aZhS_tooltip-1">

						<div id="aZhS_tooltips" style="margin-top: 5px; margin-bottom: 5px;">
			
							<ul>
								<li><a href="#aZhS_tooltips-3">Default "Popup"</a></li>
								<li><a href="#aZhS_tooltips-2">Sticky Label</a></li>
								<li><a href="#aZhS_tooltips-1">Alt Title</a></li>
							</ul>
					
							<div id="aZhS_tooltips-1">
							
								<div class="legend">Alt title</div>
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Alt title (<a href="javascript: void(0)" class="optDescr">altTitle</a>):</label> 
									<input type="text" value="" id="hotspot_altTitle" style="width: 100%;">
								</div>
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>CSS Class (<a href="javascript: void(0)" class="optDescr">altTitleClass</a>):</label> 
									<input type="text" value="" id="hotspot_altTitleClass" style="width: 350px;">
								</div>					
								
								
								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<label>Alt title hotspot offset:</label>
									Left (<a href="javascript: void(0)" class="optDescr">altTitleAdjustX</a>): 
									<input type="text" value="20" style="width: 50px;" id="hotspot_altTitleAdjustX">px &nbsp;&nbsp;
									Top (<a href="javascript: void(0)" class="optDescr">altTitleAdjustY</a>): 
									<input type="text" value="20" style="width: 50px;" id="hotspot_altTitleAdjustY">px		
								</div>
								
								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<input type="button" value="Apply" onClick="jQuery.aZhSpotEd.saveHotspotTooltip()">
								</div>
								
							</div>
							
							<div id="aZhS_tooltips-2">
								<div class="legend">Sticky label</div>
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Label title (<a href="javascript: void(0)" class="optDescr">labelTitle</a>):</label> 
									<textarea id="hotspot_labelTitle" style="height: 100px; width: 100%;"></textarea>
								</div>
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Label gravity (<a href="javascript: void(0)" class="optDescr">labelGravity</a>):</label> 
									<select id="hotspot_labelGravity" onchange="jQuery.aZhSpotEd.saveHotspotTooltip();">
										<option value="topLeft">topLeft</option>
										<option value="topLeftFlag1">topLeftFlag 1</option>
										<option value="topLeftFlag2">topLeftFlag 2</option>
										<option value="top">top</option>
										<option value="topRight">topRight</option>
										<option value="topRightFlag1">topRightFlag 1</option>
										<option value="topRightFlag2">topRightFlag 2</option>
										<option value="right">right</option>
										<option value="rightTopFlag1">rightTopFlag 1</option>
										<option value="rightTopFlag2">rightTopFlag 2</option>
										<option value="rightBottomFlag1">rightBottomFlag 1</option>
										<option value="rightBottomFlag2">rightBottomFlag 2</option>
										<option value="bottomRight">bottomRight</option>
										<option value="bottomRightFlag1">bottomRightFlag 1</option>
										<option value="bottomRightFlag2">bottomRightFlag 2</option>
										<option value="bottom">bottom</option>
										<option value="bottomLeft">bottomLeft</option>
										<option value="bottomLeftFlag1">bottomLeftFlag 1</option>
										<option value="bottomLeftFlag2">bottomLeftFlag 2</option>
										<option value="left">left</option>
										<option value="leftTopFlag1">leftTopFlag 1</option>
										<option value="leftTopFlag2">leftTopFlag 2</option>
										<option value="leftBottomFlag1">leftBottomFlag 1</option>
										<option value="leftBottomFlag2">leftBottomFlag 2</option>
										<option value="center">center</option>
									</select> 						 
								</div>
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Instant offset (<a href="javascript: void(0)" class="optDescr">labelBaseOffset</a>):</label> 
									<input type="text" value="5" id="hotspot_labelBaseOffset" style="width: 50px;">px
								</div>
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Offsets: </label> 
									Left (<a href="javascript: void(0)" class="optDescr">labelOffsetX</a>): 
									<input type="text" value="0" id="hotspot_labelOffsetX" style="width: 50px;">px &nbsp;&nbsp;
									Top (<a href="javascript: void(0)" class="optDescr">labelOffsetY</a>): 
									<input type="text" value="0" id="hotspot_labelOffsetY" style="width: 50px;">px
								</div>

								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>CSS class (<a href="javascript: void(0)" class="optDescr">labelClass</a>):</label> 
									<input type="text" value="" id="hotspot_labelClass" style="width: 450px;">
								</div>
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Opacity (<a href="javascript: void(0)" class="optDescr">labelOpacity</a>):</label> 
									<input type="text" value="1.0" id="hotspot_labelOpacity" style="width: 100px;">
								</div>		
								
								
								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<input type="button" value="Apply" onClick="jQuery.aZhSpotEd.saveHotspotTooltip()">
								</div>
								
								
							</div>
							<div id="aZhS_tooltips-3">
								<div class="legend">Default popup contents</div>
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Title (<a href="javascript: void(0)" class="optDescr">toolTipTitle</a>):</label> 
									<input type="text" value="" id="hotspot_toolTipTitle" style="width: 100%;">
								</div>
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Description (<a href="javascript: void(0)" class="optDescr">toolTipHtml</a>):</label>
									<a href="javascript: void(0)" onclick="$.aZhSpotEd.toggleWYSIWYG()" style="float: right;">WYSIWYG</a>
									<div id="hotspot_toolTipHtml_parent">
										<textarea id="hotspot_toolTipHtml" style="height: 250px; width: 100%;"></textarea>
									</div>
								</div>
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Dynamic content (<a href="javascript: void(0)" class="optDescr">toolTipAjaxUrl</a>):</label> 
									<input type="text" value="" id="hotspot_toolTipAjaxUrl" style="width: 100%;">
								</div>
						
								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<input type="button" value="Apply" onClick="jQuery.aZhSpotEd.saveHotspotTooltip()">
								</div>
								
								<div class="legend">Size and look</div>
								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<label>Dimensions:</label>
									Width (<a href="javascript: void(0)" class="optDescr">toolTipWidth</a>): 
									<input type="text" value="250" style="width: 50px;" id="hotspot_toolTipWidth">px &nbsp;&nbsp;
									Height (<a href="javascript: void(0)" class="optDescr">toolTipHeight</a>): 
									<input type="text" value="120" style="width: 50px;" id="hotspot_toolTipHeight">px
								</div>
								
								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<label>Gravity (<a href="javascript: void(0)" class="optDescr">toolTipGravity</a>):</label> 
									<select id="hotspot_toolTipGravity">
										<option value="hover" selected>hover</option>
										<option value="fullsize">fullsize</option>
										<option value="fullscreen">fullscreen</option>
										<option value="topLeft">topLeft</option>
										<option value="top">top</option>
										<option value="topRight">topRight</option>
										<option value="right">right</option>
										<option value="bottomRight">bottomRight</option>
										<option value="bottom">bottom</option>
										<option value="bottomLeft">bottomLeft</option>
										<option value="left">left</option>
									</select> 
									&nbsp;<input type="checkbox" value="1" id="hotspot_toolTipGravFixed"> fixed position 
									(<a href="javascript: void(0)" class="optDescr">toolTipGravFixed</a>)
									&nbsp;<input type="checkbox" value="1" id="hotspot_toolTipAutoFlip"> autoflip 
									(<a href="javascript: void(0)" class="optDescr">toolTipAutoFlip</a>)
								</div>
								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<label>Tooltip hotspot offset:</label>
									Left (<a href="javascript: void(0)" class="optDescr">toolTipAdjustX</a>): 
									<input type="text" value="10" style="width: 50px;" id="hotspot_toolTipAdjustX">px &nbsp;&nbsp;
									Top (<a href="javascript: void(0)" class="optDescr">toolTipAdjustY</a>): 
									<input type="text" value="10" style="width: 50px;" id="hotspot_toolTipAdjustY">px		
								</div>

								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<label>Tooltip edge offset (<a href="javascript: void(0)" class="optDescr">toolTipFullSizeOffset</a>):</label>
									<input type="text" value="40" style="width: 50px;" id="hotspot_toolTipFullSizeOffset">px - from all edges
								</div>
			
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Event (<a href="javascript: void(0)" class="optDescr">toolTipEvent</a>):</label>
									<input name="hotspot_toolTipEvent" id="hotspot_toolTipEvent" type="radio" value="click" checked> - click &nbsp;&nbsp;
									<input name="hotspot_toolTipEvent" id="hotspot_toolTipEvent" type="radio" value="mouseover"> - mouseover &nbsp;&nbsp;
									<input type="text" value="1000" style="width: 50px;" id="hotspot_toolTipHideTimout"> - hide time if mouseover 
									(<a href="javascript: void(0)" class="optDescr">toolTipHideTimout</a>)
								</div>
					
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Title Class (<a href="javascript: void(0)" class="optDescr">toolTipTitleCustomClass</a>):</label>
									<input type="text" value="" style="width: 200px" id="hotspot_toolTipTitleCustomClass"> 
									e.g. axZmToolTipTitleCustom (try it)
								</div>
			
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Inner Class (<a href="javascript: void(0)" class="optDescr">toolTipCustomClass</a>):</label>
									<input type="text" value="" style="width: 200px" id="hotspot_toolTipCustomClass"> 
									e.g. axZmToolTipInnerCustom (try it)
								</div>
					
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Opacity (<a href="javascript: void(0)" class="optDescr">toolTipOpacity</a>):</label>
									<input type="text" value="1.0" style="width:50px" id="hotspot_toolTipOpacity"> 
									(use transparent PNG in toolTipCustomClass for only backgound opacity)
								</div>
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Draggable (<a href="javascript: void(0)" class="optDescr">toolTipDraggable</a>):</label>
									<input type="checkbox" value="1" id="hotspot_toolTipDraggable" name="hotspot_toolTipDraggable"> - title needs to be defined too (title div is handle)
								</div>
								
								<div class="legend">Close icon and overlay</div>
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Close icon image (<a href="javascript: void(0)" class="optDescr">toolTipCloseIcon</a>):</label>
									<input type="text" value="fancy_closebox.png" style="width: 450px" id="hotspot_toolTipCloseIcon" name="hotspot_toolTipCloseIcon">
								</div>
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Close icon position (<a href="javascript: void(0)" class="optDescr">toolTipCloseIconPosition</a>):</label>  
									<select id="hotspot_toolTipCloseIconPosition">
										<option value="topRight" selected>topRight</option>
										<option value="topLeft">topLeft</option>
										<option value="bottomRight">bottomRight</option>
										<option value="bottomLeft">bottomLeft</option>
									</select>&nbsp;&nbsp;
									offset (<a href="javascript: void(0)" class="optDescr">toolTipCloseIconOffset</a>): 
									<input type="text" value="" style="width: 160px" id="hotspot_toolTipCloseIconOffset"> 
								</div>			

								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Overlay</label>
									Show: (<a href="javascript: void(0)" class="optDescr">toolTipOverlayShow</a>):
									<input type="checkbox" value="1" id="hotspot_toolTipOverlayShow" name="hotspot_toolTipOverlayShow"> &nbsp;&nbsp;
									Close on click (<a href="javascript: void(0)" class="optDescr">toolTipOverlayClickClose</a>): 
									<input type="checkbox" value="1" id="hotspot_toolTipOverlayClickClose" name="hotspot_toolTipOverlayClickClose">
								</div>			
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Overlay settings:</label>
									Opacity (<a href="javascript: void(0)" class="optDescr">toolTipOverlayOpacity</a>): 
									<input type="text" value="" style="width: 50px" id="hotspot_toolTipOverlayOpacity"> &nbsp;&nbsp;
									Color (<a href="javascript: void(0)" class="optDescr">toolTipOverlayColor</a>): 
									<input type="text" value="" style="width: 80px" id="hotspot_toolTipOverlayColor">
								</div>	

								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<input type="button" value="Apply" onClick="jQuery.aZhSpotEd.saveHotspotTooltip()">
								</div>
							
							</div>
							
						</div>
						
					</div>
					
					<div id="aZhS_tooltip-2">
						
						<div class="legend">Mainly for rectangles</div>
						<div style="clear: both; margin: 5px 0px 10px 0px;">
							<label>Text inside hotspot area (<a href="javascript: void(0)" class="optDescr">hotspotText</a>):</label> 
							Do not use " (double quotation marks) in html tags. Use ' instead! <a href="javascript: void(0)" onclick="jQuery.aZhSpotEd.setLorem('hotspot_hotspotText')">set Lorem</a>
							<textarea id="hotspot_hotspotText" style="height: 250px; width: 100%;"></textarea>
						</div>
						<div style="clear: both; margin: 5px 0px 10px 0px;">
						<label>Text width, height 100% (<a href="javascript: void(0)" class="optDescr">hotspotTextFill</a>):</label>
							<input type="checkbox" value="1" id="hotspot_hotspotTextFill">
						</div>
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>CSS Class (<a href="javascript: void(0)" class="optDescr">hotspotTextClass</a>):</label>
							<input type="text" value="" style="width: 200px" id="hotspot_hotspotTextClass">
							e.g. axZmHotspotTextCustom (try it)
						</div>
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Inline CSS (<a href="javascript: void(0)" class="optDescr">hotspotTextCss</a>):</label>
							e.g. {"color":"black","height":"100%","width":"100%"}
							<input type="text" value="" style="width: 100%" id="hotspot_hotspotTextCss"> 
						</div>
						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<input type="button" value="Apply" onClick="jQuery.aZhSpotEd.saveHotspotTooltip()">
						</div>
					
					</div>
					
					<div id="aZhS_tooltip-3">
						
						<div class="legend">Link and other events (JavaScript)</div>

						<div id="aZhS_events" style="margin-top: 5px; margin-bottom: 5px;">
							
							<ul>
								<li><a href="#aZhS_events-1" style="font-size: 14px !important;">Link</a></li>
								<li><a href="#aZhS_events-2" style="font-size: 14px !important;">Click</a></li>
								<li><a href="#aZhS_events-3" style="font-size: 14px !important;">Mouseover</a></li>
								<li><a href="#aZhS_events-4" style="font-size: 14px !important;">Mouseout</a></li>
								<li><a href="#aZhS_events-5" style="font-size: 14px !important;">Mouseenter</a></li>
								<li><a href="#aZhS_events-6" style="font-size: 14px !important;">Mouseleave</a></li>
								<li><a href="#aZhS_events-7" style="font-size: 14px !important;">Mousedown</a></li>
								<li><a href="#aZhS_events-8" style="font-size: 14px !important;">Mouseup</a></li>
							</ul>
							
							<div id="aZhS_events-1" style="min-height: 200px;">
								
								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<label>Link (<a href="javascript: void(0)" class="optDescr">href</a>):</label>
									<input type="text" value="" style="width: 100%" id="hotspot_href">
								</div>

								<div style="clear: both; margin: 5px 0px 5px 0px;">
									<label>Link in new window (<a href="javascript: void(0)" class="optDescr">hrefTarget</a>):</label>
									<input type="checkbox" value="_blank" id="hotspot_hrefTarget">
								</div>
								
							</div>
							
							<div id="aZhS_events-2" style="min-height: 200px;">
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Click event (<a href="javascript: void(0)" class="optDescr">click</a>):</label>
									<textarea id="hotspot_click" style="height: 300px; width: 100%;"></textarea>
								</div>
								
							</div>
							
							<div id="aZhS_events-3" style="min-height: 200px;">
			
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Mouseover event (<a href="javascript: void(0)" class="optDescr">mouseover</a>):</label>
									<textarea id="hotspot_mouseover" style="height: 300px; width: 100%;"></textarea>
								</div>
								
							</div>
							
							<div id="aZhS_events-4" style="min-height: 200px;">
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Mouseout event (<a href="javascript: void(0)" class="optDescr">mouseout</a>):</label>
									<textarea id="hotspot_mouseout" style="height: 300px; width: 100%;"></textarea>
								</div>
								
							</div>
							
							<div id="aZhS_events-5" style="min-height: 200px;">
			
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Mouseenter event (<a href="javascript: void(0)" class="optDescr">mouseenter</a>):</label>
									<textarea id="hotspot_mouseenter" style="height: 300px; width: 100%;"></textarea>
								</div>

							</div>
							
							<div id="aZhS_events-6" style="min-height: 200px;">
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Mouseleave event (<a href="javascript: void(0)" class="optDescr">mouseleave</a>):</label>
									<textarea id="hotspot_mouseleave" style="height: 300px; width: 100%;"></textarea>
								</div>					

							</div>
							
							<div id="aZhS_events-7" style="min-height: 200px;">
			
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Mousedown event (<a href="javascript: void(0)" class="optDescr">mousedown</a>):</label>
									<textarea id="hotspot_mousedown" style="height: 300px; width: 100%;"></textarea>
								</div>
								
							</div>
							
							<div id="aZhS_events-8" style="min-height: 200px;">
								
								<div style="clear: both; margin: 5px 0px 10px 0px;">
									<label>Mouseup event (<a href="javascript: void(0)" class="optDescr">mouseup</a>):</label>
									<textarea id="hotspot_mouseup" style="height: 300px; width: 100%;"></textarea>
								</div>

							</div>
								
							<div style="clear: both; margin: 5px 0px 5px 0px;">
								<input type="button" value="Apply" onClick="jQuery.aZhSpotEd.saveHotspotTooltip()">
							</div>
						
						</div>
						
					</div>
				
				</div>
				
			</div>

		
			<div id="aZhS_tabs-5">
					
				<div class="legend">Edit, apply entire JSON for all hotspots manually</div>
				<div style="clear: both; margin: 5px 0px 5px 0px;">
					<label>Import current loaded object:</label> <input type="button" value="Import" onClick="jQuery.aZhSpotEd.importJSON();"> 
					<input type="checkbox" id="allHotspotsCodeDefaults" value="1" checked> - with defaults 
					<input type="checkbox" id="allHotspotsCodeImgNames" value="1" checked> - positions as image names 
					<input type="checkbox" id="allHotspotsCodeFormat" value="1"> - do not format <br /> 
				</div>
				
				<div style="clear: both; margin: 5px 0px 5px 0px;">
					<label>Search for a word:</label> 
					<input type="text" id="jsonSearchField" value="" style="width: 300px"> &nbsp;
					<input type="button" id="jsonSearchFieldSubmit" value="Search" onClick="jQuery.aZhSpotEd.findTextInTextArea('allHotspotsCode', jQuery('#jsonSearchField').val());"> 
				</div>
				
				<div style="clear: both; margin: 5px 0px 5px 0px;">
					<label>Scroll to hotspot JSON:</label> 
					<div id="scrollToHotspotJSON"></div>
				</div>	
				
				<div style="clear: both; margin: 5px 0px 5px 0px;">
					<div style="height: auto;">
						<textarea id="allHotspotsCode" name="hotspot_json" style="width: 100%; font-size:12px; line-height: 14px; height: 400px;"></textarea>
					</div>
					<div>
						<label>Apply above changes:</label><br>
						<div class="buttonWrap" id="applyJSON">
							<input style="width: 100px;" type="button" value="Apply" onClick="jQuery.aZhSpotEd.applyJSON();"> 
						</div>
						<div class="buttonWrapNext">
							<input type="checkbox" value="1" id="keepDraggable" checked> - keep draggable (will not affect final JSON)
						</div>
					</div>
					<div style="height: 30px;"></div>
				</div>
				
			</div>
		
		</div>
		<?php
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

		global $pagenow, $post;

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script( 'ajaxzoom', plugins_url( 'axZm/jquery.axZm.js', __FILE__ ) );
		wp_enqueue_style( 'ajaxzoom', plugins_url( 'axZm/axZm.css', __FILE__ ) );

		wp_enqueue_script( 'dropzone', plugins_url( 'js/dropzone.min.js', __FILE__ ), [ 'jquery' ] );
		wp_enqueue_script( 'dropzone-amd-module', plugins_url( 'js/dropzone-amd-module.min.js', __FILE__ ), [ 'jquery' ] );
		wp_enqueue_style( 'dropzone', plugins_url( 'css/dropzone.min.css', __FILE__ ) );

		wp_register_script( 'zoomcomposer', plugins_url( 'js/zoomcomp.js', __FILE__ ), [ 'jquery', 'jquery-ui-sortable' ] );
		wp_enqueue_style( 'zoomcomposer', plugins_url( 'css/zoomcomp.css', __FILE__ ) );

		if( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && $post->post_type == '360_gallery' ){
			wp_localize_script( 'zoomcomposer', 'zoomcomp', [
				'azParam' => http_build_query([
					'3dDir' => self::pic_dir().'/360/'.$post->ID,
					'cache' => 0
				]),
				'hotspotJsonUrl' => add_query_arg( [
					'action' => 'get_hotspot_json',
					'post_id' => $post->ID
				], admin_url( 'admin-ajax.php' ) )
			]);

			wp_enqueue_style( 'hotspot-editor', plugins_url( 'axZm/extensions/jquery.axZm.hotspotEditor.css', __FILE__ ) );
			wp_enqueue_style( 'jquery-ui', plugins_url( 'axZm/plugins/jquery.ui/themes/ajax-zoom/jquery-ui.css', __FILE__ ) );

			wp_enqueue_script( 'jquery-json', plugins_url( 'axZm/plugins/JSON/jquery.json-2.3.min.js', __FILE__ ) );
			wp_enqueue_script( 'jquery-scrollTo', plugins_url( 'axZm/plugins/jquery.scrollTo.min.js', __FILE__ ) );
			wp_enqueue_script( 'beautify-all', plugins_url( 'axZm/plugins/js-beautify/beautify-all.min.js', __FILE__ ) );
			wp_enqueue_script( 'hotspot-editor', plugins_url( 'axZm/extensions/jquery.axZm.hotspotEditor.js', __FILE__ ) );
			wp_enqueue_script( 'jquery-ui-tabs' );
		}

		wp_enqueue_script( 'zoomcomposer' );

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
