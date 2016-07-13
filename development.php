<?php
add_action( 'wp_enqueue_scripts', function(){
	wp_enqueue_script( 'jquery' );

	wp_enqueue_script( 'ajaxzoom', plugins_url( 'axZm/jquery.axZm.js', __FILE__ ) );
	wp_enqueue_style( 'ajaxzoom', plugins_url( 'axZm/axZm.css', __FILE__ ) );
	
	wp_enqueue_script( 'hover-thumb', plugins_url( 'axZm/extensions/jquery.axZm.hoverThumb.js', __FILE__ ) );
	wp_enqueue_style( 'hover-thumb', plugins_url( 'axZm/extensions/jquery.axZm.hoverThumb.css', __FILE__ ) );

	wp_enqueue_script( 'zoomcomposer', plugins_url( 'js/zoomcomp.js', __FILE__ ) );
	wp_enqueue_style( 'zoomcomposer', plugins_url( 'css/zoomcomp.css', __FILE__ ) );

});