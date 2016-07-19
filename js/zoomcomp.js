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

			if( $(".gallery-image-upload").length ){
				var dzObj = Dropzone.forElement(".gallery-image-upload");

				$('button.upload-dropzone').click(function(e){
					e.preventDefault();
					dzObj.processQueue();
				});

				dzObj.
				on('success', function(file, response){
					dzObj.removeFile(file);

					$('.existing-images').append('<li><img src="'+response.url+'"></li>');
				}).
				on('sending', function(file, request, formData){
					formData.append("action", "upload_gallery_image");
					formData.append("post_id", $("#post_ID").val());
				})

				Dropzone.autoDiscover = false;
			}
		}
	})
})(jQuery);