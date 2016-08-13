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
		add_action( 'wp_ajax_nopriv_get_hotspot_json', [ $this, 'output_hotspot_json' ]);
		add_action( 'wp_ajax_get_crop_json', [ $this, 'output_crop_json' ]);
		add_action( 'wp_ajax_nopriv_get_crop_json', [ $this, 'output_crop_json' ]);
		add_action( 'wp_ajax_360_slider', [ $this, 'zoomcomp_360' ]);
		add_action( 'wp_ajax_nopriv_360_slider', [ $this, 'zoomcomp_360' ]);
		add_action( 'save_post', [ $this, 'update_gallery_images' ]);
		add_action( 'save_post', [ $this, 'save_hotspot_data' ]);
		add_action( 'save_post', [ $this, 'save_crop_data' ]);
		add_action( 'delete_post', [ $this, 'delete_gallery_images' ]);

		$this->create_shortcodes();
	}

	/**
	 * Generate unique id for shortcode elements that require id.
	 */
	public function get_next_el_id() {
		global $zoomcomp_unique_id;
		if( !isset( $zoomcomp_unique_id ) ) $zoomcomp_unique_id = 1;

		return "zoomcomposer-element-".($zoomcomp_unique_id++);
	}

	/**
	 * Register the shortcode for ZoomComposer.
	 */
	public function create_shortcodes() {
		add_shortcode( 'zoomcomp_thumb_hover_zoom_gallery', [ $this, 'shortcode_thumb_hover_zoom_gallery' ] );
		add_shortcode( 'zoomcomp_thumb_hover_zoom_item', [ $this, 'shortcode_thumb_hover_zoom_item' ] );
		add_shortcode( 'zoomcomp_gallery_button', [ $this, 'shortcode_gallery_button' ] );
		add_shortcode( 'zoomcomp_360', [ $this, 'shortcode_zoomcomp_360' ] );
		add_shortcode( 'zoomcomp_gallery', [ $this, 'shortcode_zoomcomp_gallery' ] );
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
	 * Generate shortcode content in gallery for single image.
	 */
	public function shortcode_gallery_thumb_hover_zoom_item( $atts ) {
		global $galleryData, $galleryHotspots, $galleryDescriptions;

		extract( shortcode_atts( [
			'attachment_id' => 0,
			'description'   => ''
		], $atts ));

		$image = wp_get_attachment_image_src( $attachment_id, 'full' );
		$url   = wp_make_link_relative( $image[0] );

		$galleryData[] = ['imageZoom', $url];
		if('' != $description) $galleryDescriptions[basename($url)] = $description;
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
	 * Shortcode processor for gallery button
	 */
	public function shortcode_gallery_button( $atts, $content ) {
		extract( shortcode_atts( [
			'images'     => '',
			'slider_id'  => '',
			'class'      => '',
			'type'       => 'regular',
			'element'    => 'link',
			'target'     => 'window',
			'fullscreen' => false
		], $atts ) );

		if( 'regular' == $type ) {
			$images = explode( ',', $images );
			$images = array_map( function($img){
				$img_id = (int) $img;

				$image = wp_get_attachment_image_src( $img, 'full' );
				return wp_make_link_relative( $image[0] );
			}, $images );

			$images = implode( '|', $images );
			$qstring = http_build_query([ 'zoomData' => $images ]);
		}
		elseif( '3d' == $type ) {
			$qstring = http_build_query([ '3dDir' => self::pic_dir().'/360/'.trim($slider_id) ]);
		}

		$axzm_dir = plugins_url( 'axZm/', __FILE__ );
		$axzm_dir = wp_make_link_relative( $axzm_dir );

		$function_call_string = "jQuery.fn.axZm.openFullScreen('$axzm_dir', '$qstring', {}, '$target', ".($fullscreen?'true':'false')." )";

		ob_start();

		if( 'button' == $element ){
			echo '<button class="'.$class.'" onclick="'.$function_call_string.'">'.$content.'</button>';
		}
		else {
			echo '<a class="'.$class.'" href="javascript:void(0)" onclick="'.$function_call_string.'">'.$content.'</a>';
		}

		return ob_get_clean();
	}

	/**
	 * Generate shortcode content for gallery
	 */
	public function shortcode_zoomcomp_gallery( $atts, $content ){
		extract( shortcode_atts([
			'thumbslider_orientation' => 'vertical',
			'height'                  => '400px'
		], $atts ) );

		$thumbs_at_fullscreen = 'vertical' == $thumbslider_orientation ? 'right' : 'bottom';

		// Redefine shortcodes for this gallery.
		remove_shortcode('zoomcomp_thumb_hover_zoom_item');
		add_shortcode( 'zoomcomp_thumb_hover_zoom_item', [ $this, 'shortcode_gallery_thumb_hover_zoom_item' ] );
		remove_shortcode( 'zoomcomp_360' );
		add_shortcode( 'zoomcomp_360', [ $this, 'shortcode_gallery_360' ] );
		add_shortcode( 'zoomcomp_video', [ $this, 'shorcode_gallery_video' ] );

		global $galleryData, $galleryHotspots, $galleryDescriptions;
		do_shortcode($content);

		remove_shortcode('zoomcomp_thumb_hover_zoom_item');
		add_shortcode( 'zoomcomp_thumb_hover_zoom_item', [ $this, 'shortcode_thumb_hover_zoom_item' ] );
		remove_shortcode( 'zoomcomp_360' );
		add_shortcode( 'zoomcomp_360', [ $this, 'shortcode_zoomcomp_360' ] );
		remove_shortcode( 'zoomcomp_video' );

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'gallery_360', plugins_url( 'axZm/extensions/jquery.axZm.360Gallery.js', __FILE__ ) );
		wp_enqueue_style( 'gallery_360', plugins_url( 'axZm/extensions/jquery.axZm.360Gallery.css', __FILE__ ) );
		wp_enqueue_script( 'ajaxzoom', plugins_url( 'axZm/jquery.axZm.js', __FILE__ ) );
		wp_enqueue_style( 'ajaxzoom', plugins_url( 'axZm/axZm.css', __FILE__ ) );
		wp_enqueue_script( 'thumbslider', plugins_url( 'axZm/extensions/axZmThumbSlider/lib/jquery.axZm.thumbSlider.js', __FILE__ ) );
		wp_enqueue_style( 'thumbslider', plugins_url( 'axZm/extensions/axZmThumbSlider/skins/default/jquery.axZm.thumbSlider.css', __FILE__ ) );
		wp_enqueue_script( 'zoomcomposer', plugins_url( 'js/zoomcomp.js', __FILE__ ) );
		wp_enqueue_style( 'zoomcomposer', plugins_url( 'css/zoomcomp.css', __FILE__ ) );

		?>
		<div id="playerWrap">
			<div id="axZmPlayerContainer">
				<!-- This div will be removed after anything is loaded into "content" div -->
				<div style="padding:20px; color: #000000; font-size: 16pt">
					<?php _e( 'Loading, please wait...', 'zoomcomp' ); ?>
				</div>
			</div>

			<div id="spinGalleryContainer">
				<!-- Thumb slider -->
				<div id="spinGallery">
				</div>
			</div>
		</div>

		<style media="screen">
			<?php if( 'vertical' == $thumbslider_orientation ): ?>
			#playerWrap{
				padding-right: 120px;
				height: <?php echo $height; ?>;
				position: relative;
			}

			#spinGalleryContainer{
				position: absolute;
				z-index: 11;
				width: 120px;
				height: 100%;
				right: 0px;
				top: 0px;
			}

			#spinGallery{
				position: absolute;
				right: 0;
				width: 110px;
				height: 100%;
				background-color: #FFFFFF;
				overflow: hidden;
			}

			#highlightsText{
				position: absolute; top: -23px; width: 90px; height: 17px; right: 0; text-align: left;
				padding: 2px 5px; font-family: monospace;
				background-color: #F2D3A2;
			}
			<?php else: ?>
			.axZmFsSpaceBottom { height: 100px !important; }
			<?php endif; ?>
		</style>

		<script type="text/javascript">
			jQuery(document).ready(function() {
				// Load 360 gallery and first spin
				jQuery.axZm360Gallery ({
					axZmPath: "auto", // Path to /axZm/ directory, e.g. "/test/axZm/"
					galleryData: <?php echo json_encode($galleryData); ?>,
					galleryHotspots: <?php echo json_encode($galleryHotspots); ?>,


					divID: "axZmPlayerContainer", // The ID of the element (placeholder) where AJAX-ZOOM has to be inserted into
					embedResponsive: true, // if divID is responsive, set this to true
					spinGalleryContainerID: "spinGalleryContainer", // Parent container of gallery div
					spinGalleryID: "spinGallery",
					spinGallery_tempID: "spinGallery_temp",
					backgroundColor: "#FFFFFF",

					// use axZmThumbSlider extension for the thumbs, set false to disable
					axZmThumbSlider: true,

					// Options passed to $.axZmThumbSlider
					// For more information see /axZm/extensions/axZmThumbSlider/
					axZmThumbSliderParam: {
						btn: false,
						orientation: "<?php echo $thumbslider_orientation; ?>",
						scrollbar: true,
						scrollbarMargin: 10,
						wrapStyle: {borderWidth: 0}
						//scrollbarClass: "axZmThumbSlider_scrollbar_thin"
					},

					// try to open AJAX-ZOOM at browsers fullscreen mode
					fullScreenApi: true,

					// Show 360 thumb gallery at fullscreen mode,
					// possible values: "bottom", "top", "left", "right" or false
					thumbsAtFullscreen: <?php echo $thumbs_at_fullscreen ? "'$thumbs_at_fullscreen'" : 'false'; ?>,

					thumbsCache: true, // cache thumbnails
					thumbWidth: 87, // width of the thumbnail image
					thumbHeight: 87, // height of the thumbnail image
					thumbQual: 90, // jpg quality of the thumbnail image
					thumbMode: false, // possible values: "contain", "cover" or false
					thumbBackColor: "#FFFFFF", // background color of the thumb if thumbMode is set to "contain"
					thumbRetina: true, // true will double the resolution of the thumbnails
					thumbDescr: true, // Show thumb description

					// Custom description of the thumbs
					thumbDescrObj: <?php echo json_encode($galleryDescriptions); ?>,

					thumbIcon: true // Show 360 or 3D icon for the thumbs
				});
			});
		</script>
		<?php
	}

	/**
	 * Generate shortcode content for 360º slider
	 */
	public function shortcode_zoomcomp_360( $atts ) {
		$id  = $this->get_next_el_id();
		$url = add_query_arg( array_merge( $atts, ['action' => '360_slider', 'container_id' => $id] ), admin_url('admin-ajax.php') );
		return '<iframe src="'.$url.'" id="'.$id.'" frameborder="none" width="100%" allowFullScreen></iframe>';
	}

	/**
	 * Generate shorcode content in gallery for 360º slider
	 */
	public function shortcode_gallery_360( $atts ) {
		global $galleryData, $galleryHotspots, $galleryDescriptions;

		extract( shortcode_atts( [
			'slider_id' => 0,
			'description' => ''
		], $atts ));

		if( !$slider_id ) return;

		$galleryData[] = ['image360', self::pic_dir().'/360/'.$slider_id];
		$galleryHotspots[$slider_id] = add_query_arg(['action' => 'get_hotspot_json', 'post_id' => $slider_id], admin_url( 'admin-ajax.php' ) );
		if('' != $description) $galleryDescriptions[$slider_id] = $description;

	}

	/**
	 * Generate shortcode content for video in gallery.
	 */
	public function shorcode_gallery_video( $atts ){

		global $galleryData, $galleryDescriptions;

		extract( shortcode_atts([
			'url' => '',
			'description' => ''
		], $atts));

		if( preg_match("/https?:\/\/(www\.)?youtube.com\/watch\?v=([a-zA-Z0-9_]*)/", $url, $output_array) ){
			$galleryData[] = ['youtube', $output_array[2]];
			if('' != $description) $galleryDescriptions[$output_array[2]] = $description;
		}
		elseif( preg_match("/https?:\/\/(www\.)?vimeo.com\/([0-9_]*)/", $url, $output_array) ){
			$galleryData[] = ['vimeo', $output_array[2]];
			if('' != $description) $galleryDescriptions[$output_array[2]] = $description;
		}
		elseif( preg_match("/https?:\/\/(www\.)?dailymotion.com\/video\/([a-z0-9]*)_?.*/", $url, $output_array) ){
			$galleryData[] = ['dailymotion', $output_array[2]];
			if('' != $description) $galleryDescriptions[$output_array[2]] = $description;
		}
	}

	/**
	 * Display a 360º element.
	 */
	public function zoomcomp_360( ) {
		extract( shortcode_atts( [
			'height'                  => '400px',
			'hotspot'                 => 'yes',
			'crop'                    => 'no',
			'slider_id'               => 0,
			'thumbslider_orientation' => 'vertical',
			'container_id'            => '',
			'mnavi_gravity'           => 'bottom',
			'slider_navi'             => 'yes',
			'map'                     => 'topLeft',
			'm_pan_spin'              => 'yes',
			'm_zoom'                  => 'yes',
			'm_spin_play'             => 'yes'
		], $_GET) );

		$navi_order = [
			'mSpin'        => 5,
			'mPan'         => 20,
			'mZoomIn'      => 5,
			'mZoomOut'     => 20,
			'mMap'         => 5,
			'mSpinPlay'    => 20
		];

		if( $m_pan_spin == 'no' ) unset($navi_order['mSpin'], $navi_order['mPan'] );
		if( $m_zoom == 'no' ) unset($navi_order['mZoomIn'], $navi_order['mZoomOut'] );
		if( $map == 'no' ) unset($navi_order['mMap'] );
		if( $m_spin_play == 'no' ) unset($navi_order['mSpinPlay'] );


		global $post;
		$player_id = $this->get_next_el_id();
		$wrapper_id = $this->get_next_el_id();
		$cropslider_wrap_id = $this->get_next_el_id();
		$cropslider_id = $this->get_next_el_id();
		$navigation_id = $this->get_next_el_id();


		$hotspot = 'no' == $hotspot ? false : true;
		$crop = 'no' == $crop ? false : true;
		$slider_navi = 'no' == $slider_navi ? false : true;
		$map = 'no' == $map ? false : $map;

		if( !$slider_id ) return;


		wp_enqueue_script( 'jquery' );

		wp_enqueue_script( 'ajaxzoom', plugins_url( 'axZm/jquery.axZm.js', __FILE__ ) );
		wp_enqueue_style( 'ajaxzoom', plugins_url( 'axZm/axZm.css', __FILE__ ) );

		wp_enqueue_script( 'imageCropLoad', plugins_url( 'axZm/extensions/jquery.axZm.imageCropLoad.js', __FILE__ ) );

		wp_enqueue_script( 'thumbslider', plugins_url( 'axZm/extensions/axZmThumbSlider/lib/jquery.axZm.thumbSlider.js', __FILE__ ) );
		wp_enqueue_style( 'thumbslider', plugins_url( 'axZm/extensions/axZmThumbSlider/skins/default/jquery.axZm.thumbSlider.css', __FILE__ ) );

		wp_enqueue_script( 'hover-thumb', plugins_url( 'axZm/extensions/jquery.axZm.hoverThumb.js', __FILE__ ) );
		wp_enqueue_style( 'hover-thumb', plugins_url( 'axZm/extensions/jquery.axZm.hoverThumb.css', __FILE__ ) );

		wp_enqueue_script( 'zoomcomposer', plugins_url( 'js/zoomcomp.js', __FILE__ ) );
		wp_enqueue_style( 'zoomcomposer', plugins_url( 'css/zoomcomp.css', __FILE__ ) );

		?>
			<!DOCTYPE html>
			<html>
				<head>
					<meta charset="utf-8">
					<title><?php _e('360º Slider', 'zoomcomp' ); ?></title>
					<?php do_action('wp_head'); ?>
					<style media="screen">
						body {
							padding: 0;
							background-color: #fff;
						}
						body:before, body:after {
							height: 0 !important;
							width: 0 !important;
						}
					</style>
				</head>
				<body>
					<?php if ($crop): ?>
					<div id="<?php echo $wrapper_id; ?>" style="<?php if( 'vertical' == $thumbslider_orientation ) echo 'padding-right: 100px;'; ?> position: relative; min-height: <?php echo $height; ?>;">
						<div id="<?php echo $player_id; ?>" style="height: 100%; position: relative;">
							<!-- Content inside target will be removed -->
							<div style="padding: 20px">Loading, please wait...</div>

						</div>

						<!-- Thumb slider with croped images -->
						<div id="<?php echo $cropslider_wrap_id; ?>" class="cropslider_wrap_<?php echo $thumbslider_orientation; ?>">
							<div id="<?php echo $cropslider_id; ?>">
								<ul></ul>
							</div>
						</div>
					</div>
					<?php else : ?>
						<div id="<?php echo $player_id; ?>" class="axZmBorderBox" style="width: 100%; min-height: <?php echo $height; ?>;"><?php _e( "Loading...", "zoomcomp" ); ?></div>
					<?php endif; ?>

					<script type="text/javascript">
					jQuery(document).ready(function(){
						<?php if ( $crop ) : ?>
						jQuery("#<?php echo $cropslider_id; ?>").axZmThumbSlider({
							orientation: "<?php echo $thumbslider_orientation; ?>",
							btnOver: true,
							btnHidden: true,
							btnFwdStyle: {borderRadius: 0, height: 20, bottom: -1, lineHeight: "20px"},
							btnBwdStyle: {borderRadius: 0, height: 20, top: -1, lineHeight: "20px"},

							thumbLiStyle: {
								height: 90,
								width: 90,
								lineHeight: 90,
								borderRadius: 0,
								margin: 3
							}
						});
						<?php endif; ?>

						// AJAX-ZOOM
						// Create empty jQuery object (no not rename here)
						var ajaxZoom = {};

						// Define the path to the axZm folder, adjust the path if needed!
						// ajaxZoom.path = "<?php echo wp_make_link_relative( plugins_url( 'axZm/', __FILE__ ) ); ?>";

						ajaxZoom.parameter = "<?php echo http_build_query(['3dDir' => self::pic_dir().'/360/'.$slider_id]); ?>";

						// Id of element where AJAX-ZOOM will be loaded into
						ajaxZoom.divID = "<?php echo $player_id; ?>";

						// Define callbacks, for complete list check the docs
						ajaxZoom.opt = {
							onLoad: function(){

								<?php if( $crop ): ?>
								jQuery.axZmImageCropLoad({
									cropJsonURL: "<?php echo add_query_arg(['action' => 'get_crop_json', 'post_id' => $slider_id], admin_url( 'admin-ajax.php' ) ) ?>",
									sliderID: "<?php echo $cropslider_id; ?>",
									spinToSpeed: "2500", // as string to override spinDemoTime when clicked on the thumbs
									spinToMotion: "easeOutQuint", // optionally pass spinToMotion to override spinToMotion set in config file, def. easeOutQuad
									handleTexts: "default" // would do about the same as commented out below...
								});
								<?php endif; ?>

								<?php if( $hotspot ): ?>
								// This would be the code for additionally loading hotspots made e.g. with example33.php
								jQuery.fn.axZm.loadHotspotsFromJsFile("<?php echo add_query_arg(['action' => 'get_hotspot_json', 'post_id' => $slider_id], admin_url( 'admin-ajax.php' ) ) ?>");
								<?php endif; ?>
							},
							onBeforeStart: function(){
								if (jQuery.axZm.spinMod){
									jQuery.axZm.restoreSpeed = 300;
								}else{
									jQuery.axZm.restoreSpeed = 0;
								}

								//jQuery.axZm.fullScreenCornerButton = false;
								jQuery.axZm.fullScreenExitText = false;

								jQuery.axZm.gallerySlideNavi = <?php echo $slider_navi ? 'true' : 'false' ?>;

								<?php if( $map ): ?>
								jQuery.axZm.mapPos = '<?php echo $map ?>';
								<?php else : ?>
								jQuery.axZm.useMap = false;
								<?php endif; ?>


								if (typeof jQuery.axZm.mNavi == 'object'){
									jQuery.axZm.mNavi.enabled = true; // enable AJAX-ZOOM mNavi
									jQuery.axZm.mNavi.alt.enabled = true; // enable button descriptions
									jQuery.axZm.mNavi.fullScreenShow = true; // show at fullscreen too
									jQuery.axZm.mNavi.mouseOver = true; // should be alsways visible
									jQuery.axZm.mNavi.gravity = '<?php echo $mnavi_gravity; ?>'; // position of AJAX-ZOOM mNavi
									jQuery.axZm.mNavi.offsetVert = 5; // vertical offset
									jQuery.axZm.mNavi.offsetVertFS = 30; // vertical offset at fullscreen

									// Define order and space between the buttons
									if (jQuery.axZm.spinMod){ // if it is 360 or 3D
										jQuery.axZm.mNavi.order = <?php echo json_encode($navi_order); ?>
									}else{
										<?php
										unset($navi_order['mSpin']);
										unset($navi_order['mPan']);
										unset($navi_order['mSpinPlay']);
										$navi_order['mGallery'] = 5;
										?>
										jQuery.axZm.mNavi.order = <?php echo json_encode($navi_order); ?>;
									}
								}

								// Set extra space to the right at fullscreen mode for the crop gallery
								jQuery.axZm.fullScreenSpace = {
									right: <?php echo 'vertical' == $thumbslider_orientation ? 'jQuery("#'.$cropslider_id.'").outerWidth()' : 0 ?>,
									top: 0,
									bottom: <?php echo 'horizontal' == $thumbslider_orientation ? 'jQuery("#'.$cropslider_id.'").outerHeight()' : 0 ?>,
									left: 0,
									layout: 1
								};
							},
							onFullScreenSpaceAdded: function(){
								<?php if( 'vertical' == $thumbslider_orientation ): ?>
									jQuery("#<?php echo $cropslider_id; ?>")
										.css({bottom: 0,right: 0, height: "100%", zIndex: 555})
										.appendTo("#axZmFsSpaceRight");
								<?php elseif( 'horizontal' == $thumbslider_orientation ) : ?>
									jQuery("#<?php echo $cropslider_id; ?>")
										.css({top: 4, left: 0, zIndex: 555})
										.appendTo("#axZmFsSpaceBottom");
								<?php endif; ?>


							},
							onFullScreenClose: function(){
								jQuery.fn.axZm.tapShow();

								jQuery("#<?php echo $cropslider_id; ?>")
								.css({bottom: "", right: "", zIndex: ""})
								.appendTo("#<?php echo $cropslider_wrap_id; ?>");
							},
							onFullScreenCloseEndFromRel: function(){
								// Restore position of the slider
								jQuery("#<?php echo $cropslider_id; ?>")
								.css({bottom: "", right: "", zIndex: ""})
								.appendTo("#<?php echo $cropslider_wrap_id; ?>");
							},
							onFullScreenStart: function(){
								jQuery('#<?php echo $container_id; ?>', window.parent.document).height(jQuery(document).height());
							}
						};

						// Load responsive
						window.fullScreenStartSplash = {enable: false, className: false, opacity: 0.75};
						jQuery.fn.axZm.openFullScreen(ajaxZoom.path, ajaxZoom.parameter, ajaxZoom.opt, ajaxZoom.divID, true, false);
					});
					</script>

				</body>
			</html>
		<?php

		exit;
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
				],
				[
					'type' => 'textfield',
					'heading' => __( 'Description', 'zoomcomp' ),
					'param_name' => 'description'
				]
			]
		] );

		$slider_posts = get_posts( [
			'posts_per_page'   => -1,
			'post_type'        => '360_gallery',
			'post_status'      => 'publish'
		] );

		$sliders = [];
		foreach( $slider_posts as $slide ){
			$sliders[$slide->post_title] = $slide->ID;
		}

		vc_map( [
			'name' => __( 'Gallery Button', 'zoomcomp' ),
			'base' => 'zoomcomp_gallery_button',
			'category' => __( 'Content', 'zoomcomp' ),
			'params' => [
				[
					'type' => 'dropdown',
					'heading' => __( 'Content Type', 'zoomcomp' ),
					'param_name' => 'type',
					'value' => ['regular', '3d']
				],
				[
					'type' => 'dropdown',
					'heading' => __( '360º Slider', 'zoomcomp' ),
					'param_name' => 'slider_id',
					'value' => $sliders,
					'dependency' => [
						'element' => 'type',
						'value' => ['3d']
					]
				],
				[
					'type' => 'attach_images',
					'heading' => __( 'Images', 'zoomcomp' ),
					'param_name' => 'images',
					'dependency' => [
						'element' => 'type',
						'value' => ['regular']
					]
				],
				[
					'type' => 'textfield',
					'heading' => __( 'CSS Class', 'zoomcomp' ),
					'param_name' => 'class'
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Element', 'zoomcomp' ),
					'param_name' => 'element',
					'value' => ['link', 'button']
				],
				[
					'type' => 'textfield',
					'heading' => __( 'Target', 'zoomcomp' ),
					'param_name' => 'target',
					'value' => 'window'
				]
			]
		]);

		vc_map( [
			'name' => __( 'ZoomComp Gallery', 'zoomcomp' ),
			'base' => 'zoomcomp_gallery',
			'category' => __( 'Contant', 'zoomcomp' ),
			'as_parent' => ['only' => 'zoomcomp_360,zoomcomp_video,zoomcomp_thumb_hover_zoom_item'],
			'params' => [
				[
					'type' => 'dropdown',
					'heading' => __( 'Thumbslider Orientation', 'zoomcomp' ),
					'param_name' => 'thumbslider_orientation',
					'value' => ['vertical', 'horizontal']
				],
				[
					'type' => 'textfield',
					'heading' => __( 'Height', 'zoomcomp' ),
					'param_name' => 'height',
					'value' => '400px'
				]
			],
			'js_view' => 'VcColumnView'
		]);

		vc_map( [
			'name' => __( '360º Slider', 'zoomcomp' ),
			'base' => 'zoomcomp_360',
			'category' => __( 'Content', 'zoomcomp' ),
			'params' => [
				[
					'type' => 'dropdown',
					'heading' => __( 'Slider', 'zoomcomp' ),
					'param_name' => 'slider_id',
					'value' => $sliders
				],
				[
					'type' => 'textfield',
					'heading' => __( 'Height', 'zoomcomp' ),
					'param_name' => 'height',
					'value' => '400px'
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Hotspot', 'zoomcomp' ),
					'param_name' => 'hotspot',
					'value' => ['yes', 'no']
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Crop', 'zoomcomp' ),
					'param_name' => 'crop',
					'value' => ['no', 'yes']
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Thumbslider Orientation', 'zoomcomp' ),
					'param_name' => 'thumbslider_orientation',
					'value' => ['vertical', 'horizontal']
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Navbar Gravity', 'zoomcomp' ),
					'param_name' => 'mnavi_gravity',
					'value' => ['left', 'bottom'],
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Slider Navigation', 'zoomcomp' ),
					'param_name' => 'slider_navi',
					'value' => ['yes', 'no'],
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Image Map', 'zoomcomp' ),
					'param_name' => 'map',
					'value' => ['topLeft', 'topRight', 'bottomLeft', 'bottomRight', 'none' => 'no'],
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Pan & Spin Controls', 'zoomcomp' ),
					'param_name' => 'm_pan_spin',
					'value' => ['yes', 'no'],
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Zoom Control', 'zoomcomp' ),
					'param_name' => 'm_zoom',
					'value' => ['yes', 'no'],
				],
				[
					'type' => 'dropdown',
					'heading' => __( 'Spin Play/Pause', 'zoomcomp' ),
					'param_name' => 'm_spin_play',
					'value' => ['yes', 'no'],
				],
				[
					'type' => 'textfield',
					'heading' => __( 'Description', 'zoomcomp' ),
					'param_name' => 'description'
				]
			]
		]);

		vc_map( [
			'name' => __( 'Gallery Video', 'zoomcomp' ),
			'base' => 'zoomcomp_video',
			'as_child' => ['only' => 'zoomcomp_gallery'],
			'category' => __( 'Contant', 'zoomcomp' ),
			'params' => [
				[
					'type' => 'textfield',
					'heading' => __( 'Video URL', 'zoomcomp' ),
					'param_name' => 'url'
				],
				[
					'type' => 'textfield',
					'heading' => __( 'Description', 'zoomcomp' ),
					'param_name' => 'description'
				]
			]
		]);
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
			add_meta_box( 'gallery_thumb_crop', __( 'Crop Thumbnails', 'zoomcomp' ), [ $this, 'gallery_thumb_crop_metabox_content' ], '360_gallery', 'normal', 'low' );
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
	 * Output the json data of crop thumbnails related to a 360º gallery.
	 */
	public function output_crop_json() {
		$post_id = $_REQUEST['post_id'];

		echo get_post_meta( $post_id, 'crop_json', true );
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
		if( !isset( $_POST['hotspot_json'] ) ) return;

		update_post_meta( $post_id, 'hotspot_json', $_POST['hotspot_json'] );
	}

	/**
	 * Save crop thumbnail configuration
	 */
	public function save_crop_data( $post_id ) {
		if( '360_gallery' != get_post_type( $post_id ) ) return;
		if( !isset( $_POST['crop_json'] ) ) return;

		update_post_meta( $post_id, 'crop_json', $_POST['crop_json'] );
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
		<div>
			<div id="AZplayerParentContainer"></div>
			<br style="clear:both" />

			<div id='testCustomNavi' class="ui-widget-header" style="width: 720px;"></div>
		</div>

		<!-- Thumb slider with croped images -->
		<div id="cropSliderWrap">
			<div id="cropSlider">
				<ul></ul>
			</div>
		</div>
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
	 * Output metabox content for crop gallery.
	 */
	public function gallery_thumb_crop_metabox_content() {

		$axzm_cms_mode = true;

		/* Default size of the thumbnails */
		$default_thumb_size = $axzm_cms_mode ? 140 : 180;

		/* In CMS mode the player should be best started responsive */
		$player_responsive = $axzm_cms_mode ? true : false;

		$langugaes_array = json_encode(array('en', 'de', 'fr', 'es', 'it'));
		?>

			<script type="text/javascript">
			<?php
			echo 'jQuery.aZcropEd.langugaesArray = '.$langugaes_array.'; ';
			echo 'jQuery.aZcropEd.playerResponsive = '.($player_responsive ? 'true' : 'false').'; ';

			if ($axzm_cms_mode)
				echo 'jQuery.aZcropEd.errors = false;';
			?>
			</script>

			<div id="aZcR_tabs">
				<!-- Tab titles -->
				<ul>
					<li><a href="#aZcR_tabs-sel">Crop settings</a></li>
					<li><a href="#aZcR_tabs-crops">Cropped images</a></li>
					<li><a href="#aZcR_tabs-descr">Description / Settings</a></li>
					<li><a href="#aZcR_tabs-import">JSON Data</a></li>
				</ul>

				<!-- Crop settings -->
				<div id="aZcR_tabs-sel">
					<!-- Crop options for Jcrop selector and AJAX-ZOOM thumbnail generator-->
					<div id="cropOptionsParent">
						<div id="cropOptions">
							<div class="legend">Jcrop (selector) settings</div>
							<div style="clear: both; margin: 5px 0px 5px 0px;">
								<label>Selection:</label>
								<select id="cropOpt_selection" onchange="jQuery.aZcropEd.jCropHandleSelection()">
									<option value="">normal</option>
									<option value="aspectRatio" selected="selected">Aspect ratio</option>
									<option value="fixedSize">Fixed size</option>
								</select>
							</div>
							<div id="cropOpt_ratioBox" style="clear: both; margin: 5px 0px 5px 0px;">
								<label>Aspect ratio:</label>
								W: <input id="cropOpt_ratio1" type="text" value="1" style="width: 50px" onchange="jQuery.aZcropEd.jCropAspectRatio()">
								<input type="button" style="width: 30px;" value="&#8660;" onclick="jQuery.aZcropEd.jCropAspectFlipValues()">
								H: <input id="cropOpt_ratio2" type="text" value="1" style="width: 50px" onchange="jQuery.aZcropEd.jCropAspectRatio()">
								<div>
									<label></label>
									<input type="button" value="as thumb" style="margin-top: 3px; width: 80px;" onclick="jQuery.aZcropEd.jCropAspectAsThumb()">
									<input type="button" value="as image" style="margin-top: 3px; width: 80px;" onclick="jQuery.aZcropEd.jCropAspectAsImage()">
								</div>
							</div>
							<div id="cropOpt_sizeBox" style="clear: both; margin: 5px 0px 5px 0px; display: none;">
								<label>Fixed size:</label>
								W: <input id="cropOpt_sizeW" type="text" value="" style="width: 50px" onchange="jQuery.aZcropEd.jCropFixedSize()">
								H: <input id="cropOpt_sizeH" type="text" value="" style="width: 50px" onchange="jQuery.aZcropEd.jCropFixedSize()"> px
							</div>

							<div class="legend">Thumbnail settings</div>

							<div style="clear: both; margin: 5px 0px 5px 0px;">
								<label>Thumbnail size:</label>
								W: <input id="cropOpt_thumbSizeW" type="text" value="<?php echo $default_thumb_size; ?>"
									style="width: 50px" onchange="jQuery.aZcropEd.jCropInitSettings()">
								H: <input id="cropOpt_thumbSizeH" type="text" value="<?php echo $default_thumb_size; ?>"
									style="width: 50px" onchange="jQuery.aZcropEd.jCropInitSettings()"> px
							</div>

							<div style="clear: both; margin: 5px 0px 5px 0px;">
								<label>Thumbnail mode:</label>
								<select id="cropOpt_thumbMode" onchange="jQuery.aZcropEd.jCropInitSettings()">
									<option value="">-</option>
									<option value="contain">contain</option>
									<option value="cover">cover</option>
								</select>
							</div>

							<div id="cropOpt_colorBox" style="clear: both; margin: 5px 0px 5px 0px; display: none;">
								<label>Background color (hex):</label>
								#<input id="cropOpt_backColor" type="text" value="FFFFFF" style="width: 100px" onchange="jQuery.aZcropEd.jCropInitSettings()">
							</div>
							<div style="clear: both; margin: 5px 0px 5px 0px;">
								<label>Jpeg quality:</label>
								<input id="cropOpt_jpgQual" type="text" value="90" style="width: 40px" onchange="jQuery.aZcropEd.jCropInitSettings()">
								(10 - 100)
							</div>
							<div style="clear: both; margin: 5px 0px 5px 0px;">
								<label>Cache (can be set later):</label>
								<input id="cropOpt_cache" type="checkbox" value="1" onchange="jQuery.aZcropEd.jCropInitSettings()">
							</div>
						</div>
					</div>

				</div>

				<!-- Cropped images -->
				<div id="aZcR_tabs-crops">
					<?php
					if ($axzm_cms_mode)
					{
					?>
						<div class="legend">Crops, Drag & drop to reorder</div>
					<?php
					}
					else
					{
					?>
						<div class="legend">Crop results (real size)</div>
					<?php
					}
					?>

					<?php
					if (!$axzm_cms_mode)
					{
					?>
						<div class="azMsg">Drag & drop to reorder the thumbs, click to get the paths and other information (see below),
						double click to zoom.
						</div>
					<?php
					}
					?>

					<!-- Crop results real size -->
					<div id="aZcR_cropResults"></div>
					<input type="button" value="Reamove all crops" style="margin-top: 5px" onclick="jQuery.aZcropEd.clearAll()" />
					<?php
					if (!$axzm_cms_mode)
					{
					?>
						- crops will be not deleted physically here!

						<div class="legend">Paths</div>

						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Query string:</label>
							<input id="aZcR_qString" type="text" onClick="this.select();" style="margin-bottom: 5px; width: 100%" value="">
						</div>

						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Url:</label>
							(please note that full Url might differ if this editor is implemented in a backend of some CMS)
							<input id="aZcR_url" type="text" onClick="this.select();" style="margin-bottom: 5px; width: 100%" value="">
						</div>

						<div style="clear: both; margin: 5px 0px 5px 0px;">
							<label>Cached image url:</label>
							(only available if "cache" option is chacked under "crop settings" tab)
							<input id="aZcR_contentLocation" type="text" onClick="this.select();" style="margin-bottom: 5px; width: 100%" value="">
						</div>
					<?php
					}
					?>
				</div>

				<!-- Description -->
				<div id="aZcR_tabs-descr">
					<div class="legend">Crop description</div>

					<div class="azMsg">
						<img border="0" style="position: relative; cursor: pointer; float: right; margin-right: -5px; margin-top: -5px;"
							alt="close this box" title="close this message" onclick="jQuery(this).parent().remove()" src="<?php echo plugins_url( 'axZm/icons/default/zoombutton_close.png', __FILE__ );?>">

						<?php
						if (!$axzm_cms_mode)
						{
						?>
							Optionally add a title || description to use them later in various ways.
							In this editor and also in the derived "clean" examples like
							<a href="example35_clean.php">example35_clean.php</a>
							we use "axZmEb" - expandable button (AJAX-ZOOM additional plugin) to display these titles || descriptions
							over the image respectively inside the player. You could however easily change the usage of title || description in your implementation,
							e.g. display them under the player or whatever. Just change the "handleTexts" property of the options object
							when passing it to jQuery.axZmImageCropLoad - see source code of e.g. <a href="example35_clean.php">example35_clean.php</a>;<br><hr />

							Besides HTML or your text you could also load external content in iframe! The prefix for the source is "iframe:"<br><br>
							e.g. to load an extennal page simply put something like this in the descripion:<br>
							iframe://www.canon.co.uk/For_Home/Product_Finder/Cameras/Digital_SLR/EOS_1100D
							<br><br>
							To load a YouTube video you could put this (replace eLvvPr6WPdg with your video code): <br>
							iframe://www.youtube.com/embed/eLvvPr6WPdg?feature=player_detailpage
							<br><br>
							To load some dynamic content over AJAX use "ajax:" as prefix, e.g.<br>
							ajax:/test/some_content_data.php?req=123
							<br><br>
							If you do not define the title, then the content will be loaded instantly as soon as the spin animation finishes.

						<?php
						}
						else
						{
						?>
							Optionally add a title and/or description.
							Besides HTML or your text you could also load external content in iframe!
							The prefix for the source is "iframe:"<br><br>
							e.g. to load an extennal page simply put something like this in the descripion:<br>
							iframe://www.canon.co.uk/For_Home/Product_Finder/Cameras/Digital_SLR/EOS_1100D
							<br><br>
							To load a YouTube video you could put this (replace eLvvPr6WPdg with your video code): <br>
							iframe://www.youtube.com/embed/eLvvPr6WPdg?feature=player_detailpage
							<br><br>
							To load some dynamic content over AJAX use "ajax:" as prefix, e.g.<br>
							ajax:/test/some_content_data.php?req=123
							<br><br>
							If you do not define the title, then the content will be loaded instantly as soon as the spin animation finishes.
						<?php
						}
						?>
					</div>

					<div id="aZcR_descrWrap">
						<!-- Tables with title and description field will be added here -->
					</div>
				</div>

				<!-- Import / Save -->
				<div id="aZcR_tabs-import">
					<div class="legend">JSON Data</div>

					<!-- Import form, do not change order of the fields-->
					<div id="aZcR_getAllThumbsForm">
						<input type="button" value="Refresh" onclick="jQuery.aZcropEd.getAllThumbs()">

						<select style="display: none;" onchange="jQuery.aZcropEd.getAllThumbs()" autocomplete=off>
							<option value="qString">Query string</option>
							<option value="url">Url</option>
							<option value="contentLocation">Cached image url</option>
						</select>

						<select style="display: none;" onchange="handleDisplayLongLine(this)" autocomplete=off>
							<option value="JSON_data">JSON with data</option>
							<option value="JSON">JSON</option>
							<option value="CSV">CSV</option>
						</select>

						<span style="display: none;"> <input type="text" value="|" style="width: 20px; display: none;"
							onchange="jQuery.aZcropEd.getAllThumbs()" autocomplete=off></span>
						<input style="display: none;" type="checkbox" value="1" onclick="jQuery.aZcropEd.getAllThumbs()" checked="true" autocomplete=off>
						and replace thumb size
						<input type="checkbox" value="1" onclick="jQuery(this).next().toggle(); jQuery.aZcropEd.getAllThumbs();" autocomplete=off>
						<span style="display: none">
							W: <input type="text" style="width: 50px" onchange="jQuery.aZcropEd.getAllThumbs();" autocomplete=off>
							H: <input type="text" style="width: 50px" onchange="jQuery.aZcropEd.getAllThumbs();" autocomplete=off> px
						</span>
						<input style="display: none;" type="checkbox" value="1" onclick="jQuery.aZcropEd.getAllThumbs();" autocomplete=off>
					</div>

					<textarea id="aZcR_getAllThumbs" name="crop_json" style="width: 100%; height: 350px; font-size: 10px; margin-top: 5px;" spellcheck="false"></textarea>
				</div>
			<!-- end Tabs wrapper -->
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

		if( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && $post->post_type == '360_gallery' ){
			wp_localize_script( 'zoomcomposer', 'zoomcomp', [
				'azParam' => http_build_query([
					'3dDir' => self::pic_dir().'/360/'.$post->ID,
					'cache' => 0
				]),
				'hotspotJsonUrl' => add_query_arg( [
					'action' => 'get_hotspot_json',
					'post_id' => $post->ID
				], admin_url( 'admin-ajax.php' ) ),
				'cropJsonUrl' => add_query_arg( [
					'action' => 'get_crop_json',
					'post_id' => $post->ID
				], admin_url( 'admin-ajax.php' ) )
			]);

			wp_enqueue_script( 'hotspot-editor', plugins_url( 'axZm/extensions/jquery.axZm.hotspotEditor.js', __FILE__ ) );
			wp_enqueue_style( 'hotspot-editor', plugins_url( 'axZm/extensions/jquery.axZm.hotspotEditor.css', __FILE__ ) );
			wp_enqueue_style( 'jquery-ui', plugins_url( 'axZm/plugins/jquery.ui/themes/ajax-zoom/jquery-ui.css', __FILE__ ) );

			wp_enqueue_script( 'jquery-json', plugins_url( 'axZm/plugins/JSON/jquery.json-2.3.min.js', __FILE__ ) );
			wp_enqueue_script( 'jquery-scrollTo', plugins_url( 'axZm/plugins/jquery.scrollTo.min.js', __FILE__ ) );
			wp_enqueue_script( 'beautify-all', plugins_url( 'axZm/plugins/js-beautify/beautify-all.min.js', __FILE__ ) );
			wp_enqueue_script( 'jquery-ui-tabs' );


			wp_enqueue_style( 'jcrop', plugins_url( 'axZm/plugins/jCrop/css/jquery.Jcrop.css', __FILE__ ) );
			wp_enqueue_script( 'jcrop', plugins_url( 'axZm/plugins/jCrop/js/jquery.Jcrop.js', __FILE__ ), ['jquery'] );
			wp_enqueue_script( 'mousewheel', plugins_url( 'axZm/extensions/axZmThumbSlider/lib/jquery.mousewheel.min.js', __FILE__ ) );

			wp_enqueue_script( 'thumbslider', plugins_url( 'axZm/extensions/axZmThumbSlider/lib/jquery.axZm.thumbSlider.js', __FILE__ ) );
			wp_enqueue_style( 'thumbslider', plugins_url( 'axZm/extensions/axZmThumbSlider/skins/default/jquery.axZm.thumbSlider.css', __FILE__ ) );

			wp_enqueue_script( 'image-crop-editor', plugins_url( 'js/jquery.axZm.imageCropEditor.js', __FILE__ ), ['zoomcomposer'] );
			wp_enqueue_style( 'image-crop-editor', plugins_url( 'axZm/extensions/jquery.axZm.imageCropEditor.css', __FILE__ ) );


			wp_enqueue_style( 'cleditor', plugins_url( 'axZm/plugins/CLEditor/jquery.cleditor.css', __FILE__ ) );
			wp_enqueue_script( 'cleditor', plugins_url( 'axZm/plugins/CLEditor/jquery.cleditor.min.js', __FILE__ ), ['zoomcomposer'] );
			wp_enqueue_script( 'cleditor-table', plugins_url( 'axZm/plugins/CLEditor/jquery.cleditor.table.min.js', __FILE__ ), ['zoomcomposer'] );

		}

		wp_enqueue_script( 'zoomcomposer' );
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

if ( class_exists( 'WPBakeryShortCodesContainer' ) ) {
    class WPBakeryShortCode_Zoomcomp_Gallery extends WPBakeryShortCodesContainer {
    }
}
if ( class_exists( 'WPBakeryShortCode' ) ) {
	class WPBakeryShortCode_Zoomcomp_Thumb_Hover_Zoom_Gallery extends WPBakeryShortCode {}
	class WPBakeryShortCode_Zoomcomp_Thumb_Hover_Zoom_Item extends WPBakeryShortCode {}
	class WPBakeryShortCode_Zoomcomp_Gallery_Button extends WPBakeryShortCode {}
	class WPBakeryShortCode_Zoomcomp_360 extends WPBakeryShortCode {}
	class WPBakeryShortCode_Zoomcomp_Video extends WPBakeryShortCode {}
}


global $zoomComposer;
$zoomComposer = new ZoomComposer;
