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
				// autoProcessQueue: false
			});

			function toggle_remove() {
				$(this).toggleClass('removed');

				if( $(this).hasClass('removed') ){
					$(this).find(".remove-flag").val('yes');
				}
				else {
					$(this).find(".remove-flag").val('no');
				}
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
					new_item.append('<img src="'+response.url+'">');
					new_item.append('<input type="hidden" name="gallery_filename[]" value="'+response.filename+'" />');
					new_item.append('<input type="hidden" class="remove-flag" name="gallery_removed[]" value="no" />');
					new_item.append('<br style="clear:both" />');

					new_item.click(toggle_remove)


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
				ajaxZoom.opt = {};

				// Get path to images folder
				ajaxZoom.parameter = zoomcomp.azParam; 

				// The ID of the element where ajax-zoom has to be inserted into
				ajaxZoom.divID = "AZplayerParentContainer";
				ajaxZoom.galleryWidth = "1000";


				jQuery.fn.axZm.openFullScreen(ajaxZoom.path, ajaxZoom.parameter, ajaxZoom.opt, ajaxZoom.divID, false, false);
			}

			$('#gallery_images .existing-images')
				.sortable()
				.children('li').click(toggle_remove);
		}
	})
})(jQuery);