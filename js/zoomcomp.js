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

				// Create empty object
				var ajaxZoom = {}; 

				// Define callbacks, for complete list check the docs
				ajaxZoom.opt = {
					onBeforeStart: function(){
						// Set backgrounf color, can also be done in css file
						jQuery('.axZm_zoomContainer').css({backgroundColor: '#FFFFFF'});		
						
						jQuery.axZm.displayNavi = true;
						
						jQuery.axZm.mapButTitle.fullScreenCornerInit = '';
						jQuery.axZm.mapButTitle.fullScreenCornerRestore = '';
						
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

						$('#post').submit(function(e){
							jQuery.aZhSpotEd.importJSON();
							jQuery.aZhSpotEd.removeWarningNotSaved();

							return true;
						});
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
})(jQuery);