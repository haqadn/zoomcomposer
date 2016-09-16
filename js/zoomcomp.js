// Create empty object
var ajaxZoom = {}; 

(function($){
	// Fire azHoverThumb on .azHoverThumb
	$(document).ready(function(){
		if( 'undefined' != typeof $.fn.azHoverThumb ) $(".azHoverThumb").azHoverThumb();
		if( 'undefined' != typeof $.fn.dropzone ){
			var dz = $(".gallery-image-upload").dropzone({
				url: ajaxurl,
				uploadMultiple: false,
				addRemoveLinks: true,
				acceptedFiles: 'image/*',
				parallelUploads: 1
				// autoProcessQueue: false
			});

			function toggle_remove() {
				$(this).parent().toggleClass('removed');

				if( $(this).parent().hasClass('removed') ){
					$(this).parent().find(".remove-flag").val('yes');
				}
				else {
					$(this).parent().find(".remove-flag").val('no');
				}
			}

			function navigate_frame() {
				var img_name = $(this).find('[name="gallery_filename[]"]').val();
				$.fn.axZm.spinTo(img_name, 'auto', 'easeOutCubic');
			}

			if( $(".gallery-image-upload").length ){
				var dzObj = Dropzone.forElement(".gallery-image-upload");

				$('button.upload-dropzone').click(function(e){
					e.preventDefault();
					dzObj.processQueue();
				});

				dzObj.
				on('success', function(file, response){
					dzObj.removeFile(file);

					var new_item = $('<li/>').insertBefore( $('.existing-images > br') );
					var remove_btn = $('<span class="remove-btn">x</span>').appendTo(new_item);
					new_item.append('<img src="'+response.url+'">');
					new_item.append('<input type="hidden" name="gallery_filename[]" value="'+response.filename+'" />');
					new_item.append('<input type="hidden" class="remove-flag" name="gallery_removed[]" value="no" />');
					new_item.append('<br style="clear:both" />');

					remove_btn.click(toggle_remove);
					new_item.click(navigate_frame);


					$('#gallery_images .existing-images').sortable("refresh");
				}).
				on('sending', function(file, request, formData){
					formData.append("action", "upload_gallery_image");
					formData.append("post_id", $("#post_ID").val());
				})

				Dropzone.autoDiscover = false;
			}

			if( $("#AZplayerParentContainer").length ){


				// Define callbacks, for complete list check the docs
				ajaxZoom.opt = {
					onBeforeStart: function(){
						// Set backgrounf color, can also be done in css file
						jQuery('.axZm_zoomContainer').css({backgroundColor: '#FFFFFF'});		
						
						jQuery.axZm.displayNavi = false;
						
						jQuery.axZm.mapButTitle.fullScreenCornerInit = '';
						jQuery.axZm.mapButTitle.fullScreenCornerRestore = '';

						if (jQuery.axZm.spinMod){
							jQuery.axZm.restoreSpeed = 300;
						}else{
							jQuery.axZm.restoreSpeed = 0;
						}

						

						// Set extra space to the right at fullscreen mode for the crop gallery
						jQuery.axZm.fullScreenSpace = {
							top: 0,
							right: 0,
							bottom: $('#cropSlider').outerHeight() + 6,
							left: 0,
							layout: 1
						};

						//jQuery.axZm.fullScreenApi = true;

						//jQuery.axZm.fullScreenCornerButton = false;
						jQuery.axZm.fullScreenExitText = false;

						// Chnage position of the map
						//jQuery.axZm.mapPos = 'bottomLeft';

						// Set mNavi buttons here if you want, can be done in the config file too
						if (typeof jQuery.axZm.mNavi == 'object'){
							jQuery.axZm.mNavi.enabled = true; // enable AJAX-ZOOM mNavi
							jQuery.axZm.mNavi.alt.enabled = true; // enable button descriptions
							jQuery.axZm.mNavi.fullScreenShow = true; // show at fullscreen too
							jQuery.axZm.mNavi.mouseOver = true; // should be alsways visible
							jQuery.axZm.mNavi.gravity = 'bottom'; // position of AJAX-ZOOM mNavi
							jQuery.axZm.mNavi.offsetVert = 5; // vertical offset
							jQuery.axZm.mNavi.offsetVertFS = 30; // vertical offset at fullscreen
							jQuery.axZm.mNavi.parentID = 'testCustomNavi';

							// Define order and space between the buttons
							if (jQuery.axZm.spinMod){ // if it is 360 or 3D
								jQuery.axZm.mNavi.order = {
									mSpin: 5, mPan: 20, mZoomIn: 5, mZoomOut: 20, mReset: 5, mMap: 5, mSpinPlay: 20, 
									mCustomBtn4: 20, mCustomBtn1: 5, mCustomBtn2: 5, mCustomBtn3: 5
								};
							}else{
								jQuery.axZm.mNavi.order = {
									mZoomIn: 5, mZoomOut: 5, mReset: 20, mGallery: 5, mMap: 20, 
									mCustomBtn4: 20, mCustomBtn1: 5, mCustomBtn2: 5, mCustomBtn3: 5
								};
							}

							// Define images for custom button to toggle Jcrop (see below)
							jQuery.axZm.icons.mCustomBtn1 = {file: jQuery.axZm.buttonSet+'/button_iPad_jcrop', ext: 'png', w: 50, h: 50};
							jQuery.axZm.mapButTitle.customBtn1 = 'Toggle jCrop';

							// Define image for settings button
							jQuery.axZm.icons.mCustomBtn2 = {file: jQuery.axZm.buttonSet+'/button_iPad_settings', ext: 'png', w: 50, h: 50};
							jQuery.axZm.mapButTitle.customBtn2 = 'jCrop settings';

							// Define image for 
							jQuery.axZm.icons.mCustomBtn3 = {file: jQuery.axZm.buttonSet+'/button_iPad_fire', ext: 'png', w: 50, h: 50};		
							jQuery.axZm.mapButTitle.customBtn3 = 'Fire crop!';

							// Toggle jQuery.axZm.spinReverse
							jQuery.axZm.icons.mCustomBtn4 = {file: jQuery.axZm.buttonSet+'/button_iPad_reverse', ext: 'png', w: 50, h: 50};		
							jQuery.axZm.mapButTitle.customBtn4 = 'Toggle drag spin direction';

							// function when clicked on this custom button (mCustomBtn1)
							jQuery.axZm.mNavi.mCustomBtn1 = function(){
								jQuery.aZcropEd.jCropMethod('toggle');
								return false;
							};

							// Toggle Jcrop and AJAX-ZOOM thumbnail settings popup
							jQuery.axZm.mNavi.mCustomBtn2 = function(){
								jQuery.aZcropEd.jCropSettingsPopup();
								return false;
							};	

							// Function when clicked on the fire crop button
							jQuery.axZm.mNavi.mCustomBtn3 = function(){
								jQuery.aZcropEd.jCropFire();
								return false;
							};

							// Toggle jQuery.axZm.spinReverse
							jQuery.axZm.mNavi.mCustomBtn4 = function(){
								if (jQuery.axZm.spinReverse){
									jQuery.axZm.spinReverse = false;
								}else{
									jQuery.axZm.spinReverse = true;
								}
								return false;
							};
						}


						
					},
					
					onLoad: function(){ // onSpinPreloadEnd
						jQuery.axZm.spinReverse = true;
						// Load hotspots over this function... or just define jQuery.axZm.hotspots here and trigger jQuery.fn.axZm.initHotspots(); after this.
						jQuery.fn.axZm.loadHotspotsFromJsFile( zoomcomp.hotspotJsonUrl, false, function(){
							// This is just for hotspot editor
							if (typeof jQuery.aZhSpotEd !== 'undefined' ){
								setTimeout(jQuery.aZhSpotEd.updateHotspotSelector, 200);
								var HotspotJsFile = jQuery.fn.axZm.getHotspotJsFile();
								
								if (HotspotJsFile){
									HotspotJsFile = jQuery.aZhSpotEd.getf('.', jQuery.aZhSpotEd.getl('/', HotspotJsFile));
								}
							}				
						});

						jQuery.aZcropEd.getJSONdataFromFile(zoomcomp.cropJsonUrl);

						$('#post').submit(function(e){
							jQuery.aZcropEd.getAllThumbs();

							jQuery.aZhSpotEd.importJSON();
							jQuery.aZhSpotEd.removeWarningNotSaved();

							return true;
						});
					},

					onCropEnd: function(){
						jQuery.aZcropEd.jCropOnChange();
					},

					onFullScreenResizeEnd: function(){
						// Toggle Jcrop
						if (jcrop_api){
							jQuery.aZcropEd.jCropMethod('destroy');
						}
					},

					onFullScreenSpaceAdded: function(){
							jQuery('#cropSlider')
							.css({
								bottom: 0,
								right: 0,
								width: '100%',
								zIndex: 555
							})
							.appendTo('#axZmFsSpaceBottom');
					},

					onFullScreenStart: function(){
						jQuery.aZcropEd.jCropMethod('destroy');
					},

					onFullScreenClose: function(){
						jQuery.aZcropEd.jCropMethod('destroy');
						jQuery.fn.axZm.tapShow();

						jQuery('#cropSlider')
						.css({
							bottom: '',
							right: '',
							zIndex: ''
						})
						.appendTo('#cropSliderWrap');
					},
					onFullScreenCloseEndFromRel: function(){

						// Restore position of the slider
						jQuery('#cropSlider')
						.css({
							bottom: '',
							right: '',
							zIndex: ''
						})
						.appendTo('#cropSliderWrap');
					}
				};

				// Get path to images folder
				ajaxZoom.parameter = zoomcomp.azParam; 

				// The ID of the element where ajax-zoom has to be inserted into
				ajaxZoom.divID = "AZplayerParentContainer";


				jQuery.fn.axZm.openFullScreen(ajaxZoom.path, ajaxZoom.parameter, ajaxZoom.opt, ajaxZoom.divID, false, false);
			}

			$('#gallery_images .existing-images')
				.sortable()
				.children('li').click(navigate_frame)
				.find('.remove-btn').click(toggle_remove);
		}
	});

	function fixThumbContainer() {
		$('.thumbContainer').each(function(){
			$(this).css('max-height', '9999px');
			var height = $(this).find('.azHoverThumb').css('height');

			$(this).css('max-height', height);
		});
	}

	$(window).load(fixThumbContainer);
	$(window).resize(fixThumbContainer);
})(jQuery);