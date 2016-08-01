/*!
* Plugin: jQuery AJAX-ZOOM, jquery.axZm.imageCropEditor.js
* Copyright: Copyright (c) 2010-2016 Vadim Jacobi
* License Agreement: http://www.ajax-zoom.com/index.php?cid=download
* Extension Version: 1.9
* Extension Date: 2016-07-03
* URL: http://www.ajax-zoom.com
* Documentation: http://www.ajax-zoom.com/index.php?cid=docs
* Example: http://www.ajax-zoom.com/examples/example35.php
*/
 
 ;(function($) {

var effectObj = [
		{val: 0, txt: "0. Spin and zoom simultaneously"},
		{val: 1, txt: "1. Zoom out and zoomin while spinning"},
		{val: 2, txt: "2. Zoom out while spinning, then zoom in"},
		{val: 3, txt: "3. Zoom out, then spin and zoom"},
		{val: 4, txt: "4. Zoom out, spin, zoom in after spinning"},
		{val: 5, txt: "5. Zoom out and zoomin while spinning with extra round"},
		{val: 6, txt: "6. Zoom out 1/3 out, zoom in from 2/3 to 1 while spinning with extra round"},
		{val: 7, txt: "7. Zoom out while spinning with extra round, zoom in after spinning"},
		{val: 8, txt: "8. Zoom out, spin with extra round, zoom in after spinning"},
		{val: 'rand', txt: "9: Randomly from the above"}
	],
	
	axZmEbGravity = [
		{val: "top", txt: "Top"},
		{val: "topRight", txt: "Top - Right"},
		{val: "bottomLeft", txt: "Bottom - Left"},
		{val: "bottom", txt: "Bottom"},
		{val: "bottomRight", txt: "Bottom - Right"},
		{val: "topLeft", txt: "Top - Left"},
 		{val: "center", txt: "Center"}
 	],
 	
	realTypeOf = function(v) {
		if (typeof(v) == "object") {
		if (v === null) return "null";
		if (v.constructor == (new Array).constructor) return "array";
		if (v.constructor == (new Date).constructor) return "date";
		if (v.constructor == (new RegExp).constructor) return "regex";
			return "object";
		}
		return typeof(v);
	},
	
	toggleNaviBar = function(a){
		if ($('#testCustomNavi').css('display') == 'block'){
			$('#axZm_zoomCustomNavi, #testCustomNavi').css('display', 'none');
			$(a).html('activate');
		}else{
			$('#axZm_zoomCustomNavi, #testCustomNavi').css('display', '');
			$(a).html('deactivate');
		}
	},
	
	// Function executed after thumbs have been dragged and sorted
	sortUpdate = function(e, ui){
		var i = 0;
		jcrop_az.order = {}; // reset order
		
		// Loop throw #aZcR_cropResults first level children
		$('#aZcR_cropResults').children().each(function(nm, el){
			// Get the jQuery data
			var elData = $('.aZcR_croppedImage', el).data();
			// Set new order starting from 1
			jcrop_az.order[nm+1] = elData.thumbNumber;
		});
		
		// Reorder thumbs in the slider (data-cropNum has been attached when the thumb has been added to the slider)
		$('#cropSlider').axZmThumbSlider('sortMap', jcrop_az.order, 'data-cropNum');
		
		// Reorder thumb descriptions
		var tP = $('#aZcR_descrWrap');
		$.each(jcrop_az.order, function(k, v){
			var t = $('table[data-cropNum='+v+']', tP);
			
			if (t.length == 1){
				var o = $('textarea', t);
				if (o.data('cleditor')){
					toggleWYSIWYG(v);
				}
				t.detach().appendTo(tP);
			}
		});
		
		$('#aZcR_getAllThumbs').val('');
		return jcrop_az.order;
	},
 	
 	// Remove all crops
	clearAll = function(){
		// Remove crops from #aZcR_cropResults
		$('#aZcR_cropResults').empty();

		// Remove crops from the slider
		jQuery('#cropSlider').axZmThumbSlider('removeAllThumbs');

		// Update
		jcrop_az.allCrops = {};

		jcrop_az.countThumbs = 0;

		thumbUrls(0);
		$('#aZcR_getAllThumbs').val('');

		$('#aZcR_descrWrap').empty();
	},
	
	// Get all crops into a string separated by some value 
	getAllCropsCSV = function(q, sep, cache, jsn, dim, prc){
		// a can be e.g. url, qString, contentLocation
		if (!jcrop_az.allCrops || $.isEmptyObject(jcrop_az.allCrops)){return '';}
		if (!sep){sep = '|';}
		var retArr = [];
		
		sortUpdate();
		
		$.each(jcrop_az.order, function(a, b){
			var v = jcrop_az.allCrops[b],
				k = b;
			
			if (v[q] && dim && dim[0] && dim[1]){
				v[q] = updateQueryStringParameter(v[q], 'width', dim[0]);
				v[q] = updateQueryStringParameter(v[q], 'height', dim[1]);
			}
			
			if (prc){
				var val = getCropValuesFromUrl(v[q], 'crop'),
					zoomGA = $.axZm.zoomGA[v.zoomID];
				if (zoomGA && val.x1 && val.x1.indexOf('%') == -1){
					var w = zoomGA.ow,
						h = zoomGA.oh,
						x1 = Math.round(val.x1 / w * 100 * 1000)/1000,
						y1 = Math.round(val.y1 / h * 100 * 1000)/1000,
						x2 = Math.round(val.x2 / w * 100 * 1000)/1000,
						y2 = Math.round(val.y2 / h * 100 * 1000)/1000;
					
					v[q] = updateQueryStringParameter(v[q], 'x1', x1+'%');
					v[q] = updateQueryStringParameter(v[q], 'y1', y1+'%');
					v[q] = updateQueryStringParameter(v[q], 'x2', x2+'%');
					v[q] = updateQueryStringParameter(v[q], 'y2', y2+'%');
				}
			}
			
			if (v[q]){
				retArr.push((cache && v[q]) ? v[q].replace('cache=undefined', 'cache=1') : v[q]);
			}
		});
		
		if (jsn){
			return $.toJSON(retArr);
		}else{
			return retArr.join(sep);
		}
		
	},
	
	replaceQuotes = function(a){
		if (typeof a == 'string'){
			return a.replace(/\"/g,'&#34;');
		}else{
			return '';
		}
	},
	
	// Create JSON for all crops
	getAllCropsJSON = function(q, cache, dim, prc){
		if (!jcrop_az.allCrops || $.isEmptyObject(jcrop_az.allCrops)){return '';}
		var ret = [],
			dW = $('#aZcR_descrWrap');
		sortUpdate();
		
		$.each(jcrop_az.order, function(a, b){
			var v = jcrop_az.allCrops[b],
				k = b;
		
			var obj = {};
			obj[q] = (cache && v[q]) ? v[q].replace('cache=undefined', 'cache=1') : v[q];
			obj.zoomID = v.zoomID;
			obj.imgName = v.imgName;
			obj.crop = v.crop; // array

			// Title and desciption
			var tTbl = $('table[data-cropNum='+k+']', dW),
				curLang = tTbl.data('lang'),
				tEffect = $('select[name=effect]', tTbl).val() || getEffectDefault(),
				tebGrav = $('select[name=ebGrav]', tTbl).val() || 'top'
				;
			
			// Update current language...
			if (curLang == 'default'){
				v['title'] = $('input[name=button_title]', tTbl).val();
				v['thumbTitle'] = $('input[name=thumb_title]', tTbl).val();
				v['descr'] = $('textarea', tTbl).val();
			}else{
				v['title_'+curLang] = $('input[name=button_title]', tTbl).val();
				v['thumbTitle_'+curLang] = $('input[name=thumb_title]', tTbl).val();
				v['descr_'+curLang] = $('textarea', tTbl).val();
			}
			
			if (v['title']){
				obj.title = replaceQuotes(v['title']);
			}
			if (v['thumbTitle']){
				obj.thumbTitle = replaceQuotes(v['thumbTitle']);
			}
			if (v['descr']){
				obj.descr = replaceQuotes(v['descr']);
			}
			
			$.each($.aZcropEd.langugaesArray, function(nnn, lll){
				if (v['title_'+lll]){
					obj['title_'+lll] = replaceQuotes(v['title_'+lll]);
				}
				if (v['thumbTitle_'+lll]){
					obj['thumbTitle_'+lll] = replaceQuotes(v['thumbTitle_'+lll]);
				}
				if (v['descr_'+lll]){
					obj['descr_'+lll] = replaceQuotes(v['descr_'+lll]);
				}
			});
			
			obj.effect = tEffect;
			obj.ebGrav = tebGrav;
			
			if (dim && dim[0] && dim[1]){
				obj[q] = updateQueryStringParameter(obj[q], 'width', dim[0]);
				obj[q] = updateQueryStringParameter(obj[q], 'height', dim[1]);
			}
		 
			var val = getCropValuesFromUrl(obj[q], 'crop'),
				zoomGA = $.axZm.zoomGA[v.zoomID];
			
			if (zoomGA){
				var w = zoomGA.ow,
					h = zoomGA.oh;
			}
				
			if (prc && zoomGA && val.x1 && val.x1.indexOf('%') == -1){

				var	x1 = Math.round(val.x1 / w * 100 * 1000)/1000,
					y1 = Math.round(val.y1 / h * 100 * 1000)/1000,
					x2 = Math.round(val.x2 / w * 100 * 1000)/1000,
					y2 = Math.round(val.y2 / h * 100 * 1000)/1000;
				
				obj[q] = updateQueryStringParameter(obj[q], 'x1', x1+'%');
				obj[q] = updateQueryStringParameter(obj[q], 'y1', y1+'%');
				obj[q] = updateQueryStringParameter(obj[q], 'x2', x2+'%');
				obj[q] = updateQueryStringParameter(obj[q], 'y2', y2+'%');
				obj.crop = [x1+'%', y1+'%', x2+'%', y2+'%'];
				
			}else if (!prc && zoomGA && val.x1 && val.x1.indexOf('%') != -1){
				var x1 = Math.round(parseFloat(val.x1)/100 * w),
					y1 = Math.round(parseFloat(val.y1)/100 * h),
					x2 = Math.round(parseFloat(val.x2)/100 * w),
					y2 = Math.round(parseFloat(val.y2)/100 * h);
					
				obj[q] = updateQueryStringParameter(obj[q], 'x1', x1);
				obj[q] = updateQueryStringParameter(obj[q], 'y1', y1);
				obj[q] = updateQueryStringParameter(obj[q], 'x2', x2);
				obj[q] = updateQueryStringParameter(obj[q], 'y2', y2);
				obj.crop = [x1, y1, x2, y2];
			}
		 
			
			ret.push(obj);
		});
		return $.toJSON(ret);
	},
	
	// Split query string / url and get query string parameters
	getCropValuesFromUrl = function(str, what){
		if (!str){return '';}

        var vars = str.split('?').pop().split('&'), 
        	r = {};
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split('=');
            r[pair[0]] = pair[1];
        }

        if (what == 'crop'){
        	return {x1: r.x1, y1: r.y1, x2: r.x2, y2: r.y2};
		}else if (what == 'file'){
			return r.previewPic;
		}else{
			return {};
		}
	},
	
	// Get coordinates from query string already created
	getObjFromCSV = function(str, sep){
		if (!sep){sep='|';}
		var url = str.split(sep),
			r = {};
		$.each(url, function(k, v){
			var file = getCropValuesFromUrl(v, 'file'),
				crop = getCropValuesFromUrl(v, 'crop');
			if (file && crop){
				r[k+1] = {};
				r[k+1]['file'] = file;
				r[k+1]['crop'] = crop;
			}
		});
		return r;
	},

	// Fill forms with url, query string and cached image if available
	thumbUrls = function(num){
		var azJcrop = jcrop_az.allCrops[num];

		if (!$.isEmptyObject(azJcrop)){
			$('#aZcR_url').val(azJcrop.url);
			$('#aZcR_qString').val(azJcrop.qString);
			$('#aZcR_contentLocation').val(azJcrop.contentLocation || '');
		}else{
			$('#aZcR_url').val('');
			$('#aZcR_qString').val('');
			$('#aZcR_contentLocation').val('');
		}
		$('#aZcR_getAllThumbs').val('');
		
	},

	// Replace certain parameter values in a query string
	updateQueryStringParameter = function(uri, key, value) {
		var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
		var separator = uri.indexOf('?') !== -1 ? "&" : "?";
		if (uri.match(re)) {
			return uri.replace(re, '$1' + key + "=" + value + '$2');
		}
		else {
			return uri + separator + key + "=" + value;
		}
	},
	
	// Create CSV or JSON of all crops
	getAllThumbs = function(){
		
		var div = $('#aZcR_getAllThumbsForm'),
			url = $('select:eq(0)', div).val(),
			type = $('select:eq(1)', div).val(),
			sep = $('input[type=text]:eq(0)', div).val(),
			cache = $('input[type=checkbox]:eq(0):checked', div).val(),
			size = $('input[type=checkbox]:eq(1):checked', div).val(),
			prc = $('input[type=checkbox]:eq(2):checked', div).val(),
			w, h, ret;
		
		if (size){
			w = parseInt($('input[type=text]:eq(1)', div).val());
			h = parseInt($('input[type=text]:eq(2)', div).val());
		
			if (!(w > 20 && h > 20)){
				w = null; h = null;
			}
		}
		
		
		if (type == 'CSV'){
			ret = getAllCropsCSV(url, sep, cache, false, [w, h], prc);
		}else if (type == 'JSON'){
			ret = getAllCropsCSV(url, sep, cache, true, [w, h], prc);
		}else{
			ret = getAllCropsJSON(url, cache, [w, h], prc);
		}
		
		
		if (type == 'CSV'){
			ret = ret.split(sep).join(sep+'\r\n')
		}else if ($.isFunction(js_beautify)){
			ret = js_beautify(ret, {
				'indent_size': 1,
				'indent_char': '\t'
			});
		}
		
		$('#aZcR_getAllThumbs').val(ret);
	},

	// Read initial values for thumbs
	jCropInitSettings = function(){
		jcrop_az.azCropOp = {};
		jcrop_az.azCropOp.thumbSizeW = $('#cropOpt_thumbSizeW').val();
		jcrop_az.azCropOp.thumbSizeH = $('#cropOpt_thumbSizeH').val();
		jcrop_az.azCropOp.thumbMode = $('#cropOpt_thumbMode').val();
		jcrop_az.azCropOp.backColor = $('#cropOpt_backColor').val();
		jcrop_az.azCropOp.jpgQual = $('#cropOpt_jpgQual').val();
		jcrop_az.azCropOp.cache = $('#cropOpt_cache:checked').val();
		
		if (!jcrop_az.azCropOp.thumbSizeW || jcrop_az.azCropOp.thumbSize < 10){
			jcrop_az.azCropOp.thumbSizeW = 100;
			$('#cropOpt_thumbSizeW').val(100);
		}
		
		if (!jcrop_az.azCropOp.thumbSizeH || jcrop_az.azCropOp.thumbSizeW < 10){
			jcrop_az.azCropOp.thumbSizeH = 100;
			$('#cropOpt_thumbSizeH').val(100);
		}
		
		if (!jcrop_az.azCropOp.jpgQual || jcrop_az.azCropOp.jpgQual > 100 || jcrop_az.azCropOp.jpgQual < 10){
			jcrop_az.azCropOp.jpgQual = 90;
			$('#cropOpt_jpgQual').val(90);
		}
		
		if (jcrop_az.azCropOp.thumbMode == 'contain'){
			$('#cropOpt_colorBox').css('display', '');
		}else{
			$('#cropOpt_colorBox').css('display', 'none');
		}
		
	},

	// Switch between Jcrop modi (free, fixed size or aspect ratio)
	jCropHandleSelection = function(){
		var selValue = $('#cropOpt_selection').val();
		if (selValue == 'aspectRatio'){
			$('#cropOpt_ratioBox').css('display', '');
			$('#cropOpt_sizeBox').css('display', 'none');
			jCropAspectRatio();
		}
		else if (selValue == 'fixedSize'){
			$('#cropOpt_ratioBox').css('display', 'none');
			$('#cropOpt_sizeBox').css('display', '');
			jCropFixedSize();
		}else{
			$('#cropOpt_ratioBox').css('display', 'none');
			$('#cropOpt_sizeBox').css('display', 'none');
			if (jcrop_api){
				jcrop_api.setOptions({aspectRatio: 0});
				jcrop_api.setOptions({minSize: 0});
				jcrop_api.setOptions({maxSize: 0});
			}
			jcrop_az.aspectRatio = 0;
			jcrop_az.fixedSize = 0;
		}
		
		
		jcrop_az.selMode = selValue;
		
	},
	
	// Set Jcrop selector to a specified aspect ratio
	jCropAspectRatio = function(){
 		var x = parseInt($('#cropOpt_ratio1').val());
		var y = parseInt($('#cropOpt_ratio2').val());
		if (x > 0 && y > 0){
			if (jcrop_api){
				jcrop_api.setOptions({minSize: 0});
				jcrop_api.setOptions({maxSize: 0});
				jcrop_az.fixedSize = 0;
				
				jcrop_api.setOptions({aspectRatio: (x/y)});
			}
			jcrop_az.aspectRatio = x/y;
		}else{
			if (jcrop_api){
				jcrop_api.setOptions({aspectRatio: 0});
			}
			jcrop_az.aspectRatio = 0;
		}
	},
	
	// Flip values of aspect ratio
	jCropAspectFlipValues = function(){
		var x = parseInt($('#cropOpt_ratio1').val());
		var y = parseInt($('#cropOpt_ratio2').val());
		if (y){
			$('#cropOpt_ratio1').val(y);
		}
		if (x){
			$('#cropOpt_ratio2').val(x);
		}
		if (x > 0 && y > 0 && jcrop_api){
			jcrop_api.setOptions({aspectRatio: (y/x)});
		}
	},
	
	// Set Jcrop selector to same aspect ratios as thumb size
	jCropAspectAsThumb = function(){
		var x = $('#cropOpt_thumbSizeW').val();
		var y = $('#cropOpt_thumbSizeH').val();	
		$('#cropOpt_ratio1').val(x);
		$('#cropOpt_ratio2').val(y);
		jCropAspectRatio();
	},
	
	// Set Jcrop selector to same aspect as actual image
	jCropAspectAsImage = function(){
		$('#cropOpt_ratio1').val($.axZm.ow);
		$('#cropOpt_ratio2').val($.axZm.oh);
		jCropAspectRatio();
	},

	// Change Jcrop selector to fixed size
	jCropFixedSize = function(){
		var x = parseInt($('#cropOpt_sizeW').val());
		var y = parseInt($('#cropOpt_sizeH').val());
		if (x > 0 && y > 0){
			if (jcrop_api){
				jcrop_api.setOptions({aspectRatio: 0});
				jcrop_az.aspectRatio = 0;
				
				jcrop_api.setOptions({minSize: [x, y]});
				jcrop_api.setOptions({maxSize: [x, y]});
			}
			jcrop_az.fixedSize = [x, y];
		}else{
			if (jcrop_api){
				jcrop_api.setOptions({minSize: 0});
				jcrop_api.setOptions({maxSize: 0});
			}
			jcrop_az.fixedSize = 0;
		}
	},

	// Function executed when Jcrop selector is changed
	jCropOnChange = function(a){
		if (!jcrop_api){return;}

		if (!a){
			a = jcrop_api.tellScaled();
		}
		
		// Get "real" values from AJAX-ZOOM by passing values from Jcrop
		var gCV = $.fn.axZm.getCropValues([a.x, a.y, a.x2, a.y2]);
		
		// Values returned from jCrop
		var cropFromJcrop = 'x1: '+a.x+', y1: '+a.y+', x2: '+a.x2+', y2: '+a.y2;
		
		// Values considering offset of the image within the player 
		var cropFromAZ = 'x1: '+gCV[0][0]+', y1: '+gCV[0][1]+', x2: '+gCV[0][2]+', y2: '+gCV[0][3];
		
		// Real values to crop original image regardless of zoom
		var cropFromAZzoomed = 'x1: '+gCV[1][0]+', y1: '+gCV[1][1]+'<br>x2: '+gCV[1][2]+', y2: '+gCV[1][3];
		
		// Check if box fits inside selection
		var vis = (a.x2 - a.x > 140 && a.y2 - a.y > 100) ? 'visible' : 'hidden',
			vis1 = (a.x2 - a.x > 80 && a.y2 - a.y > 80) ? 'visible' : 'hidden',
			dspl = (a.x2 - a.x > 1 && a.y2 - a.y > 1) ? 'block' : 'none',
			posTop = 0;
			
		
		// Check of the jCrop selection is not too small in regard to thumbnail size and display warning if so
		var warningSize = '';
		if (gCV[1][2] - gCV[1][0] < jcrop_az.azCropOp.thumbSizeW && 
			gCV[1][3] - gCV[1][1] < jcrop_az.azCropOp.thumbSizeH
		){
			warningSize = '<div style="color: red">Warning: selection size smaller than thumbnail size! Crop will be enlarged.</div>';
		}

		// Populate box with some metrics
		jcrop_az.jcropStatus.html(cropFromAZzoomed + warningSize)

		if (vis == 'hidden'){
			if (a.y > jcrop_api.getBounds()[1] - a.y2){
				posTop = - jcrop_az.jcropStatus.outerHeight() - 10;
			}else{
				posTop = a.y2 - a.y + 10;
			}
		}

		// Save status ( > 1 because 
		if (a.w > 1 && a.h > 1){
			jcrop_az.last = a;
		}
		
		jcrop_az.jcropStatus.css({
			// Change visibility depending on Jcrop size
			//visibility: vis, 
			top: posTop,
			display: dspl,
			backgroundColor: gCV[2] ? 'red' : 'black'
		})
		.data('crop', gCV[1]) // Save information in data as array
		.data('jCrop', a);
		
		jcrop_az.jcropFire.css({
			visibility: vis1
		})
	},
	
 	// Abstracting Jcrop API
 	jCropMethod = function(method, opt){
 		// jCrop native methods:
 		// setSelect(array) - Set selection, format: [ x,y,x2,y2 ] 
 		// animateTo(array) - Animate selection to new selection, format: [ x,y,x2,y2 ] 
 		// release - Release current selection
 		// tellSelect - Query current selection values (true size)
 		// tellScaled - Query current selection values (interface)
 		// setOptions - change options, e.g.  jcrop_api.setOptions({aspectRatio: 4/3 })
 		// disable - Disables Jcrop interactivity
 		// enable - Enables Jcrop interactivity
 		// destroy - Remove Jcrop entirely
 		// focus
 		// getBounds
 		
 		// jCrop native Event Handlers
 		// onSelect
 		// onChange
 		// onRelease
 		
 		// jCrop options: 
 		// aspectRatio - decimal - Aspect ratio of w/h (e.g. 1 for square) 
 		// minSize - array [ w, h ] - Minimum width/height, use 0 for unbounded dimension 
 		// maxSize -  array [ w, h ] - Maximum width/height, use 0 for unbounded dimension 
 		// setSelect - array [ x, y, x2, y2 ] - Set an initial selection area 
 		// bgColor - color value - Set color of background container 
 		// bgOpacity - decimal 0 - 1 - Opacity of outer image when cropping
 		
 		if (method == 'init'){
			// Destroy Jcrop if inited
			if (jcrop_api){
				jCropMethod('destroy');
				jcrop_api = null;
				// Remove layer to which jCrop will be attached within AJAX-ZOOM
				$('#azCropLayer').remove();
			}

			// Create and add layer to which jCrop will be attached within AJAX-ZOOM
			$('<div />')
			.attr('id', 'azCropLayer')
			.addClass('azCropLayer')
			.css({
				position: 'absolute',
				width: '100%',
				height: '100%',
				zIndex: 1,
				pointerEvents: 'none'
			})
			.appendTo('#axZm_zoomLayer');
			
			// Define Jcrop options
			var jCropOtions = {
				// Fired on select end
				onSelect: function(a){
					//console.log(a);
				},
				onChange: function(a){
					jCropOnChange(a);
				},
				// onRelease seems to be fired instantly when w & h == 0 (good)
				onRelease: function(){
					// Destroy Jcrop if the user clicks at the not selected area to enable zoom and pan again
					jCropMethod('destroy');
				},
				// Expand to max, useful?
				onDblClick: function(){
		      		var bounds = jcrop_api.getBounds(),
		      			ww = $.axZm.iw, 
		      			hh = $.axZm.ih,
		      			prc = 1;
		      			
			        jcrop_api.animateTo([
		        		(bounds[0] - ww)/2 + ww, //x1
		        		(bounds[1] - hh)/2 + hh, //y1
		        		(bounds[0] - ww)/2, //x2
		        		(bounds[1] - hh)/2 //y2
			        ]);
				}
			};
			
			// Extend Jcrop options depending on previously set regarding to aspectRatio and fixedSize
			if (jcrop_az.selMode == 'aspectRatio'){
				var x = parseInt($('#cropOpt_ratio1').val());
				var y = parseInt($('#cropOpt_ratio2').val());
				if ( x > 0 && y > 0){
					jCropOtions.aspectRatio = (x/y);
				}
			} 
			else if (jcrop_az.selMode == 'fixedSize'){
				var x = parseInt($('#cropOpt_sizeW').val());
				var y = parseInt($('#cropOpt_sizeH').val());
				if (x > 0 && y > 0){
					jCropOtions.minSize = [x, y];
					jCropOtions.maxSize = [x, y];
				}
			}
			
			// Init Jcrop with some enhancements
			$('#azCropLayer').Jcrop(
				// Options object defined above
				jCropOtions, 
				// this is jCrop callback after jCrop is initialized
				function(){
			        // Define instance to access later over API
			        jcrop_api = this; 

		      		// Set dimensions of Jcrop to the same if Jcrop has been previously inited
		      		if (jcrop_az.last){
		      			// jcrop_az.last is saved
		      			var last = jcrop_az.last;
		      			if (last.x2 > $.axZm.boxW || last.y2 > $.axZm.boxW){
		      				last = null;
		      				jcrop_az.last = null;
						}
						if (last){
					        jcrop_api.animateTo([
		        				jcrop_az.last.x, //x1
		        				jcrop_az.last.y, //y1
		        				jcrop_az.last.x2, //x2
		        				jcrop_az.last.y2 //y2
					        ]);
						}
					}
				
					// Jcrop has not been inited
					if (!jcrop_az.last){
						/*
						jcrop_az.azCropOp.thumbSizeW 
						jcrop_az.azCropOp.thumbSizeH
						jcrop_az.selMode -> aspectRatio, fixedSize
		      			*/
		      			
		      			// Some calculations for initial Jcrop area
		      			var bounds = jcrop_api.getBounds(),
		      				ww = $.axZm.iw, 
		      				hh = $.axZm.ih,
		      				prc = 0.2;
		      				
					    
					    // todo
					    /*
					    if (jcrop_az.selMode == 'aspectRatio' && jcrop_az.aspectRatio){
						    var aspectRatio = jcrop_az.aspectRatio,
					    		ratioX = aspectRatio,
					    		ratioY = 1/aspectRatio;
						} 
						else if (jcrop_az.selMode == 'fixedSize' && jcrop_az.fixedSize){
							var width = jcrop_az.fixedSize[0], height = jcrop_az.fixedSize[1];
						}
						else{
							// thumbnail size or aspect ration of the thumbnail
						}
						*/
					   
					    jcrop_api.animateTo([
		        			(bounds[0] - ww)/2 + (ww - ww*prc)/2, //x1
		        			(bounds[1] - hh)/2 + (hh - hh*prc)/2, //y1
		        			(bounds[0] - ww)/2 + (ww - ww*prc)/2 + ww*prc, //x2
		        			(bounds[1] - hh)/2 + (hh - hh*prc)/2 + hh*prc //y2
					    ]);
					 
					}

					// Create and append an empty div ellement to Jcrop to show cropping coordinates
					jcrop_az.jcropStatus = $('<div />').css({
						position: 'absolute',
						backgroundColor: 'black',
						color: 'white',
						padding: 4,
						width: 120,
						fontSize: '10px',
						fontFamily: 'monospace',
						opacity: 0.4,
						visibility: 'visible',
						left: 0, top: 0
					})
					// Append to Jcrop dom
					.appendTo(
						$('#axZm_zoomLayer>.jcrop-holder>div:first-child')
					);
				
					// Shortcut button to trigger crop
					jcrop_az.jcropFire = $('<div />').css({
						// we just take the same button as in "mNavi" toolbar of AJAX-ZOOM
						backgroundImage: 'url('+$('#customBtn_mCustomBtn3').attr('src')+')',
						backgroundSize: '100% 100%',
						position: 'absolute',
						bottom: 10,
						right: 10,
						cursor: 'pointer',
						color: 'white',
						padding: 0,
						width: 25,
						height: 25,
						fontSize: '10px',
						fontFamily: 'monospace',
						opacity: 1,
						visibility: 'visible',
						zIndex: 666
					})
					.click(function(){
						$('#customBtn_mCustomBtn3').trigger('click').trigger('mouseleave');
					})
					.appendTo(
						$('#axZm_zoomLayer>.jcrop-holder>div:first-child')
					);
	      		}
	      	);
		} 
		else if (method == 'destroy' && jcrop_api){
			jcrop_api.destroy();
			// upon jcrop_api we know if Jcrop is inited or not
			jcrop_api = null;
		}
		else if (method == 'toggle'){
			
			if (jcrop_api){
				jCropMethod('destroy');
			}else{
				// init jcrop
				jCropMethod('init');
			}
		}
		//
		else if (jcrop_api){
			jcrop_api[method](opt);
		}
			
	},
	
	// Toggle Jcrop and AJAX-ZOOM thumbnail settings popup
	jCropSettingsPopup = function(){
		if (jcrop_az.cropSettings){
			$.fn.axZm.zoomAlertClose();
			jcrop_az.cropSettings = false;
		}else{
			$.fn.axZm.zoomAlert(function(){return $('#cropOptions');}, null, null, true, true, function(){
				// Close callback
				jcrop_az.cropSettings = false;
				$('#cropOptions').appendTo('#cropOptionsParent');
			});
			jcrop_az.cropSettings = true;
		}
	},
	
	// Function triggered when the user presses on the crop (make thumbnail) button
	jCropFire = function(){
		// Prevent too fast cropping
		if (jcrop_az.busyCropping){
			$.fn.axZm.zoomAlert('Please wait until the last job is finished', 'Please wait', null, true);
			return false;
		}
		
		// Set cropping status
		jcrop_az.busyCropping = true;
				
		// No coordinates defined (Jcrop is closed)
		if (!jcrop_api){
			
			// Fired without Jcrop selecit, ask if thumb schould be made to what is visible
			var answer = confirm("Do you want to crop by what is visible on the canvas?");
			
			// If yes proceed
			if (answer){
				// Get what is visible
				var gCV =  $.fn.axZm.getCropValues();
			}else{
				// Reset busy status and return false
				jcrop_az.busyCropping = false;
				return false;
			}
			
			// Alternatively just alert and exit
			//$.fn.axZm.zoomAlert('Please select a region to crop and then press this button', 'Fire crop not possible', null, true);
			//jcrop_az.busyCropping = false;
			//return false;
		}else{
			// Get "Screen" values returned from Jcrop
			var a = jcrop_api.tellScaled();
			
			// Get "Real" values from AZ to crop (in regard to original image)
			var gCV =  $.fn.axZm.getCropValues([a.x, a.y, a.x2, a.y2]);
		}
		
		// This is path to /axZm folder
		var axZmPath = $.fn.axZm.installPath();
		
		// Compose string with parameters passed to the cropper
		var imgCropperPath = axZmPath+'zoomLoad.php?'; // File which returns croped image
			imgCropperPath += 'previewDir='+$.fn.axZm.getImgPath().zoomPath; // string - path to the image
			imgCropperPath += '&previewPic='+$.fn.axZm.getImgPath().zoomImage; // string - image filename
			imgCropperPath += '&qual='+jcrop_az.azCropOp.jpgQual; // quality - integer value max. 100
			imgCropperPath += '&width='+jcrop_az.azCropOp.thumbSizeW; // integer - thumbnail width
			imgCropperPath += '&height='+jcrop_az.azCropOp.thumbSizeH; // integer - thumbnail height
			if (jcrop_az.azCropOp.thumbMode){
				imgCropperPath += '&thumbMode='+jcrop_az.azCropOp.thumbMode; // string - cover or contain
				imgCropperPath += '&backColor='+jcrop_az.azCropOp.backColor; // string - any hex (without#) or named color
			}
			imgCropperPath += '&cache='+jcrop_az.azCropOp.cache;
			imgCropperPath += '&x1='+gCV[1][0];
			imgCropperPath += '&y1='+gCV[1][1];
			imgCropperPath += '&x2='+gCV[1][2];
			imgCropperPath += '&y2='+gCV[1][3];
		
        imgCropperPath = imgCropperPath.replace(/ /g, '%20');
        imgCropperPath = imgCropperPath.replace(/\(/g, '%28');
        imgCropperPath = imgCropperPath.replace(/\)/g, '%29');
			
		var imgCropperPathPass = imgCropperPath + '&textError=1'
		
		// Send request first to evalueate the return
		var tempImg = new Image();
		
 		$(tempImg).load(imgCropperPathPass, null, function(responseText, textStatus, jqXHR){
			
			// Reset cropping status
			jcrop_az.busyCropping = false;
			
			// Get all headers
			//console.log(jqXHR.getAllResponseHeaders())

			// Read header content type
			var contentType = jqXHR.getResponseHeader('Content-Type');
			
			// Content-Location is returned when cache is enabled
			var contentLocation = jqXHR.getResponseHeader('Content-Location');
			
			// If the return is an image
			if (textStatus == 'success' && contentType.toLowerCase().indexOf('image') != -1){
				// Preload image
				$(tempImg).load(function(){
					
					// Activate tab which shows the crops
					jQuery('#aZcR_tabs').tabs('option', 'active', 2);
					
					// Counter
					jcrop_az.countThumbs++;
					
					// Define information to store in jQuery data
					var dataObj = {
						thumbNumber: jcrop_az.countThumbs,
						crop: gCV[1],
						url: imgCropperPath,
						qString: imgCropperPath.replace(axZmPath+'zoomLoad.php?', ''),
						zoomID: $.axZm.zoomID,
						imgName: $.axZm.zoomGA[$.axZm.zoomID]['img'],
						contentLocation: contentLocation,
						azPar: $.fn.axZm.getParameter()
					};
					
					// Save everything in separate object
					jcrop_az.allCrops[jcrop_az.countThumbs] = dataObj;
					
					// Wrapper div for cropped image
					var wrapDiv = $('<div />')
					.addClass('aZcR_croppedImageWrap')
					// Create delete icon to remove thumbs
					.append(deleteButton(jcrop_az.countThumbs))
					// Append cropped image to #aZcR_cropResults div
					.append(drowRealCropImg(imgCropperPath, jcrop_az.countThumbs, dataObj))
					// Append wrap div with cropped image and delete button to #aZcR_cropResults
					.appendTo('#aZcR_cropResults');
					
					// Scroll #aZcR_cropResults div to very bottom 
					$('#aZcR_cropResults').scrollTo('#realCropThumb_'+jcrop_az.countThumbs, 300);
					
					// Trigger click to show path in #bottomCropInfo div
					$(tempImg).trigger('click');
					
					// Append same thumb to the slider
					drowSliderThumb(imgCropperPath, jcrop_az.countThumbs, true);
					
					// Add thumb description to #aZcR_descrWrap
					addCropDescrTable(imgCropperPath, jcrop_az.countThumbs);
					
				}).attr('src', imgCropperPath);
			}
			
			// Not an image returned
			else if (textStatus == 'success'){
				// Alert
				//imgCropperPath
				$.fn.axZm.zoomAlert('The return from:<br>'+imgCropperPath + '<br>was not an image!<br><br>Return text: '+responseText, 'Error creating a crop', null, true, true, null);
			} 
			
			// Something else
			else if (textStatus != 'success'){
				$.fn.axZm.zoomAlert(responseText, 'Error occurred', null, true, true, null);
			}
			
		});
	},
	
	deleteCropAction = function(num){
		// Remove thumb from the slider
		$('#cropSlider').axZmThumbSlider('removeThumb', '[data-cropNum='+num+']');
		
		// Delete description
		$('#aZcR_descrWrap>table[data-cropNum='+num+']').remove();
		
		// Delete data stored in global object
		delete jcrop_az.allCrops[num];
		
		// remove this thumb (not physically)
		$('#realCropThumb_'+num).parent().axZmRemove();
		
		// Update order
		sortUpdate();
		
		thumbUrls(0);
	},
	
	deleteCropActionConfirm = function(num){
		var c = confirm("Do you really want to delete?");
		if (c){
			deleteCropAction(num);
		}
	},
	
	// Create delete icon to remove thumbs
	deleteButton = function(num){
		return $('<div />')
		.addClass('aZcR_deleteIcon')
		.data('tNumber', num)
		// Delete action
		.bind('click', function(e){
			e.stopPropagation();
			e.preventDefault(); 
			
			// Get number of thumb
			// var toDelNum = $(this).data('tNumber');
			deleteCropAction(num);

		});
	},

	// Append cropped image to #aZcR_cropResults div
	drowRealCropImg = function(img, num, dataObj){
		return $('<img>')
		.attr('src', img)
		// Dummy class
		.addClass('aZcR_croppedImage')
		.attr('id', 'realCropThumb_'+num)
		.data(dataObj)
		// Zoom to selected area on double click
		.bind('dblclick', function(){
			
			// Read data previously attached to the image
			var azJcrop = $(this).data();
			
			// handleDescr will be executed after spinTo or zoomTo
			var handleDescr = function(){

				// title and desciprion
				// get them from form fields as they can be changed
				var tTbl = $('#aZcR_descrWrap > table[data-cropNum='+num+']'),
					tTitle = $('input[name=button_title]', tTbl).val(),
					tDescr = $('textarea', tTbl).val(),
					tebGrav = $('select[name=ebGrav]', tTbl).val() || 'top'
					
				// One of the possible things to do with title and description
				// Here jquery.axZm.expButton.js required
				if ($.isFunction($.axZmEb)){
					$.axZmEb({
						title: tTitle, // title of the button, can be omited - opens instantly in this case. 
						descr: tDescr, // description when opened, can be omited - does not open then. Shortcuts - iframe:scr and ajax:url
						gravity: tebGrav || 'top', // possible values: topLeft, top, topRight, bottomLeft, bottom, bottomRight, center
						marginY: 5, // vertical margin depending on gravity
						zoomSpinPanRemove: 'cropSlider', // removes button / layer when there is some action inside AJAX-ZOOM
						autoOpen: false,
						removeOnClose: false
					});
				}
			};
			
			$.fn.axZm.tapShow();
			
			// Parameters from JSON as object
			var zoomToParameters = {
				x1: azJcrop.crop[0],
				y1: azJcrop.crop[1],
				x2: azJcrop.crop[2],
				y2: azJcrop.crop[3],
				
				// settings for effects
				settings: {
					speedMin: false,
					speedFixed: false,
					effect: $('select[name=effect]', $('#aZcR_descrWrap > table[data-cropNum='+num+']')).val() || 0
				}
				
			};

 			// Callback for 2D
			var zoomToCallback = function(){
				$.fn.axZm.zoomTo($.extend({},zoomToParameters,{callback: handleDescr}));
			};
			
			// 2D image and same image from gallery selected
			if ($.axZm.zoomGA[$.axZm.zoomID]['img'] == azJcrop.imgName && !$.axZm.spinMod){

				$.extend(true, zoomToParameters, {
					speed: Math.max($.axZm.zoomSpeed, 750),
					speedZoomed: Math.max($.axZm.zoomSpeed, 750)
				});

				zoomToCallback();
			}
			else{
				// 360 / 3D mode
				if ($.axZm.spinMod){
					$.fn.axZm.removeDragToSpinMsg();
					$.fn.axZm.spinTo(
						azJcrop.imgName, 
						'2500', 
						null, // easing
						handleDescr, // callback
						zoomToParameters // zoomTo parameters
					);
				}else{
					$.extend(true, zoomToParameters, {
						speed: Math.max($.axZm.zoomSpeed, 750),
						speedZoomed: Math.max($.axZm.zoomSpeed, 750)
					});
					
					$.fn.axZm.zoomSwitch(azJcrop.imgName, null, true, null, zoomToCallback);
				}
			}
		})
		// Return paths for croped images
		.bind('click', function(){
		
			// Read data previously attached to the image
			var azJcrop = $(this).data();
			
			thumbUrls(azJcrop.thumbNumber);
		});
	},

	toggleWYSIWYG = function(num){
		var o = $('textarea', $('table[data-cropNum='+num+']'));
		if (!o.data('cleditor')){
			o.cleditor({
				height: $.axZm.boxH - 40,
				docCSSFile: ajaxZoom.path+'/extensions/jquery.axZm.expButton.css'.replace('//','/'),
				updateFrame: function(a){
					setTimeout(
						function(){
							// Apply same classes to WYSIWYG
							$('iframe', $('table[data-cropNum='+num+']'))
							.contents().find('body')
							.addClass('axZmEb_Descr axZmEb_Inner')
							.css({overflowY: 'scroll', position: 'static'})
					}, 0); 
					return a;
				}
			})
		}else{
			var v = o.val(), p = o.parent().parent();
			if (v == '<br>'){v = '';}
			o.parent().remove();
			$('<textarea style="width: 100%; height: 250px">'+v+'</textarea>').appendTo(p);
		}
	},
	
	// makeSelectField(effectObj)
	makeSelectField = function(arr, sel, name, css, change){
		var selct = $('<select />');
		if (name){selct.attr('name', name);}
		if (css){selct.css(css);}
		if (change){selct.attr('onchange', change);}
		
		$(arr).each(function(){
			var opt = $('<option />')
			.attr('value', this.val)
			.text(this.txt);
			if (this.val == sel){
				opt.attr('selected', 'selected');
			}
			selct.append(opt);
		});
		return selct;
	},
	
	getEffectDefault = function(){
		if ($.aZcropEd.defaultEffect){
			return $.aZcropEd.defaultEffect;
		} else {
			return 0;
		}
	},
	
	setAllEffect = function(num){
		var t = $('table[data-cropNum='+num+']');
		var chkt = $('input[name=effectAll]').attr('checked');
		if (chkt){
			var effectVal = $('select[name=effect]', t).val();
			$('select[name=effect]').val(effectVal);
		}
	},
	
	setAllGrav = function(num){
		var t = $('table[data-cropNum='+num+']');
		var chkt = $('input[name=ebGravAll]').attr('checked');
		if (chkt){
			var ebGravVal = $('select[name=ebGrav]', t).val();
			$('select[name=ebGrav]').val(ebGravVal);
		}
	},
	
	changeLang = function(num){
		var t = $('table[data-cropNum='+num+']');
		var o = $('textarea', t);
		var editorEnabled = o.data('cleditor');
		var curLang = t.data('lang');
		var switchLang = $('select[name=descrLang]', t).val();
				
		if (curLang != switchLang){
			
			setTimeout(function(){
 				// Save data
 				if (curLang == 'default'){
 					jcrop_az.allCrops[num].title = $('input[name=button_title]', t).val();
 					jcrop_az.allCrops[num].thumbTitle = $('input[name=thumb_title]', t).val();
 					jcrop_az.allCrops[num].descr =  $('textarea', t).val();
				}else{
 					jcrop_az.allCrops[num]['title_'+curLang] = $('input[name=button_title]', t).val();
 					jcrop_az.allCrops[num]['thumbTitle_'+curLang] = $('input[name=thumb_title]', t).val();
 					jcrop_az.allCrops[num]['descr_'+curLang] =  $('textarea', t).val();
				}
				
				// Set Data
				if (switchLang == 'default'){
					$('input[name=button_title]', t).val(jcrop_az.allCrops[num]['title'] || '');
					$('input[name=thumb_title]', t).val(jcrop_az.allCrops[num]['thumbTitle'] || '');
					$('textarea', t).val(jcrop_az.allCrops[num]['descr'] || '');
				}else{
					$('input[name=button_title]', t).val(jcrop_az.allCrops[num]['title_'+switchLang] || '');
					$('input[name=thumb_title]', t).val(jcrop_az.allCrops[num]['thumbTitle_'+switchLang] || '');
					$('textarea', t).val(jcrop_az.allCrops[num]['descr_'+switchLang] || '');
				}
				
				if (editorEnabled){
					$('iframe', t).contents().find('body').html($('textarea', t).val())
				}
				
				t.data('lang', switchLang);
				
			}, 0);
		}
		
	},
	
	addCropDescrTable = function(img, num){
		// Add thumb description to #aZcR_descrWrap
		var button_title = jcrop_az.allCrops[num].title || '',
			thumb_title = jcrop_az.allCrops[num].thumbTitle || '',
			descr = jcrop_az.allCrops[num].descr || '',
			selEffect = jcrop_az.allCrops[num].effect || getEffectDefault(),
			selebGravity = jcrop_az.allCrops[num].ebGrav || 'top'
			;
		
		//descr = descr.replace(/&#34;/g,'\"');
		//title = descr.replace(/&#34;/g,'\"');

		var table = '<table cellspacing="5" cellpadding="0" width="100%" style="border: 1px solid #AAA; margin-bottom: 10px" data-cropNum="'+num+'">\
		<tbody><tr>\
			<td style="vertical-align: top; width: 110px; padding-top: 10px;">\
				<img src="'+img+'" style="max-width: 100px; min-width: 100px; height: auto; border: 2px groove #FFF; cursor: pointer;" onclick="jQuery(\'li[data-cropnum='+num+']\', jQuery(\'#cropSlider\')).trigger(\'click\'); jQuery(window).scrollTo(0)">\
				<br><input type="button" value="Delete" style="width: 88px; margin-left: 7px; margin-top: 7px;" onclick="jQuery.aZcropEd.deleteCropActionConfirm('+num+')">\
				<br><input type="button" value="WYSIWYG" style="width: 88px; margin-left: 7px; margin-top: 5px;" onclick="jQuery.aZcropEd.toggleWYSIWYG('+num+')">\
				<br><label style="white-space: nowrap; margin-left: 7px; margin-top: 15px; width: 80px;">Language:</label><br>'+makeSelectField(jQuery.aZcropEd.langArr, null, 'descrLang', {marginLeft: 7, width: 88}, 'jQuery.aZcropEd.changeLang('+num+')').prop('outerHTML')+'\
			</td>\
			<td style="vertical-align: top; padding-top: 10px;">\
				<label style="white-space: nowrap; width: 120px;">Effect:</label>'+makeSelectField(effectObj, selEffect, 'effect', {'marginBottom': 5, 'width': 330}, 'jQuery.aZcropEd.setAllEffect('+num+')').prop('outerHTML')+' \
				<input type="checkbox" name="effectAll" onclick="jQuery.aZcropEd.setAllEffect('+num+')"> - for all<br>\
				<label style="white-space: nowrap; width: 120px;">Button position:</label>'+makeSelectField(axZmEbGravity, selebGravity, 'ebGrav',{'marginBottom': 5, 'width': 330}, 'jQuery.aZcropEd.setAllGrav('+num+')').prop('outerHTML')+' \
				<input type="checkbox" name="ebGravAll" onclick="jQuery.aZcropEd.setAllGrav('+num+')"> - for all<br>\
				<label style="white-space: nowrap;width: 120px;">Thumb title:</label><input type="text" name="thumb_title" style="margin-bottom: 5px; width: 330px" value="'+thumb_title+'"><br>\
				<label style="white-space: nowrap;width: 120px;">Button title:</label><input type="text" name="button_title" style="margin-bottom: 5px; width: 330px" value="'+button_title+'"><br>\
				<label style="white-space: nowrap">Description (text/html/css and javascript):</label><br>\
				<textarea style="width: 100%; height: 250px" spellcheck="false">'+descr+'</textarea>\
			</td>\
		</tr></tbody></table>';
		
		table = $(table);
		table.data('lang', 'default');
		table.appendTo('#aZcR_descrWrap'); 
	},

	// Append same thumb to the slider
	drowSliderThumb = function(img, num, scrlTo){
		// Create li ellement
		var newLi = $('<li />')
		// Set data attr to be able to fine it later over selector
		.attr('data-cropNum', num)
		// Append thumb image to li
		.append(
			$('<img>')
			.attr('src', img)
		);
		
		// Use axZmThumbSlider "appendThumb" API to add the thumb to the slider
		$('#cropSlider').axZmThumbSlider('appendThumb', newLi, 
			// Click event
			function(){
				//
				var thumbNum = $(this).attr('data-cropNum');
				
				// Find thumb in #aZcR_cropResults div
				var refThumb = $('#realCropThumb_'+thumbNum);
				
				if (refThumb.length > 0){
					$('#aZcR_cropResults').scrollTo(refThumb, 300);
					refThumb.trigger('dblclick');
					refThumb.trigger('click');
				}
			}, 
			// Callback
			function(el, no){
				// After thumb added slide to it
				if (scrlTo){
					$('#cropSlider').axZmThumbSlider('scrollTo', {thumb: no, highlight: true});
				}
			}
		); 
	},
	
	addErrorLog = function(){
		if (!$('#aZcR_cropError').length){
			$('<div />').css({height: 100, overflowY: 'scroll', overflowX: 'hidden', position: 'relative', fontSize: '10px'})
			.attr('id', 'aZcR_cropError')
			.prependTo('#aZcR_tabs-crops');
			
			$('<div />').addClass('legend').css({color: 'red', position: 'relative'})
			.html('Errors')
			.append($('<div />').addClass('aZcR_deleteIcon').click(function(){
				$(this).parent().next().remove();
				$(this).parent().remove();
			}))
			.prependTo('#aZcR_tabs-crops');
		}
	},
	
	proceedJSONdataLoad = function(num){
		var dataObj = jcrop_az.allCrops[num];
		
		if (dataObj){
			var imgCropperPath = dataObj.imgCropperPath;
			// Wrapper div for cropped image
			var wrapDiv = $('<div />')
			.addClass('aZcR_croppedImageWrap')
			// Create delete icon to remove thumbs
			.append(deleteButton(dataObj.thumbNumber))
			// Append cropped image to #aZcR_cropResults div
			.append(drowRealCropImg(imgCropperPath, dataObj.thumbNumber, dataObj))
			// Append wrap div with cropped image and delete button to #aZcR_cropResults
			.appendTo('#aZcR_cropResults');
			
			// Scroll #aZcR_cropResults div to very bottom 
			// $('#aZcR_cropResults').scrollTo('#realCropThumb_'+dataObj.thumbNumber, 300);
			
			// Append same thumb to the slider
			drowSliderThumb(imgCropperPath, dataObj.thumbNumber, false);
			
			// Add thumb description to #aZcR_descrWrap
			addCropDescrTable(imgCropperPath, dataObj.thumbNumber);
		}
		
		return true;
	},
	
	proceedJSONdata = function(d){
		// Clear present thumbs
		clearAll();
		
		// Activate tab which shows the crops
		// jQuery('#aZcR_tabs').tabs('select','#aZcR_tabs-crops');
		
		if (!d || !$.isArray(d) || d.length == 0){
			if (!jQuery.aZcropEd.errors === false){
				addErrorLog();
				$('<div />').css({marginBottom: 5}).html('<span style="color: red">Error loading thumbs: </span> \
				JSON passed is not an array or array is empty')
				.appendTo('#aZcR_cropError');
			}
			return;
		}
		
		jcrop_az.busyCropping = true;

		var arrCount = d.length;
		
		// temp loader
		jcrop_az.tC = {};
		jcrop_az.tC.l = arrCount;
		jcrop_az.tC.c = 1;
		jcrop_az.tC.n = {};

		$(d).each( function(m, o) {
			// Define information from JSON file to store in jQuery data
			var dataObj = {
				thumbNumber: m + 1,
				crop: o.crop,
				url: o.url ? o.url : null,
				qString: o.qString ? o.qString : null,
				zoomID: o.zoomID ? o.zoomID : null,
				imgName: o.imgName,
				contentLocation: o.contentLocation ? o.contentLocation : null,
				title: o.title ? o.title : null,
				thumbTitle: o.thumbTitle ? o.thumbTitle : null,
				descr: o.descr ? o.descr : null,
				effect: o.effect ? o.effect : getEffectDefault(),
				ebGrav: o.ebGrav ? o.ebGrav : 'top'
			};
			
			$.each($.aZcropEd.langugaesArray, function(k, v){
				dataObj['title_'+v] = o['title_'+v] || null;
				dataObj['thumbTitle_'+v] = o['thumbTitle_'+v] || null;
				dataObj['descr_'+v] = o['descr_'+v] || null;
			});
			
			var imgCropperPath;
			
			if (dataObj.url){
				imgCropperPath = dataObj.url;
				dataObj.qString = imgCropperPath.replace($.fn.axZm.installPath()+'zoomLoad.php?', '')
			}else if (dataObj.qString){
				imgCropperPath = $.fn.axZm.installPath()+'zoomLoad.php?'+dataObj.qString;
				dataObj.url = imgCropperPath;
			} else if (o.contentLocation){
				imgCropperPath = o.contentLocation;
			}
			dataObj.imgCropperPath = imgCropperPath;
			
			// Counter
			jcrop_az.countThumbs++;
			
			// Save everything in separate object
			jcrop_az.allCrops[jcrop_az.countThumbs] = dataObj;
			
			// Get also content location if cache is activated
			//$.load(imgCropperPath, null, function(responseText, textStatus, jqXHR){
			$.ajax({
				url: imgCropperPath,
				complete: function(jqXHR, textStatus ){
					// Read header content type
					var contentType = jqXHR.getResponseHeader('Content-Type');
					
					if (!jcrop_az.allCrops[dataObj.thumbNumber]){
						return;
					}
					
					// Content-Location is returned from AZ when cache is enabled
					var contentLocation = jqXHR.getResponseHeader('Content-Location');
					
					if (textStatus == 'success' && contentType.toLowerCase().indexOf('image') != -1){
						var tempImg = new Image();
						$(tempImg).load(function(){
							if (contentLocation){
								jcrop_az.allCrops[dataObj.thumbNumber].contentLocation = contentLocation;
							}
							jcrop_az.tC.n[dataObj.thumbNumber] = dataObj.thumbNumber;
						}).attr('src', imgCropperPath);
					}else{
						// handle errors
						addErrorLog();
						jcrop_az.tC.n[dataObj.thumbNumber] = dataObj.thumbNumber;
						$('<div />').css({marginBottom: 5}).html('<span style="color: red">Error loading thumb:</span> '+imgCropperPath)
						.appendTo('#aZcR_cropError');
					}
				}
			});
			/*
			$(tempImg)
			.error(function(a) {
				// Handle errors
				addErrorLog();
				$('<div />').css({marginBottom: 5}).html('<span style="color: red">Error loading thumb:</span> '+imgCropperPath)
				.appendTo('#aZcR_cropError');
				jcrop_az.tC.n[dataObj.thumbNumber] = dataObj.thumbNumber;
			})
			.load(function(){
				jcrop_az.tC.n[dataObj.thumbNumber] = dataObj.thumbNumber;
			}).attr('src', imgCropperPath);
			*/
		});
		
		// hmm (need to insert images in particular order with preloader)
		jcrop_az.tC.t = setInterval(function(){
			if (jcrop_az.tC.c == jcrop_az.tC.n[jcrop_az.tC.c]){
				if (jcrop_az.tC.c == jcrop_az.tC.l){clearInterval(jcrop_az.tC.t);jcrop_az.busyCropping=false;}
				// Trigger inserting
				proceedJSONdataLoad(jcrop_az.tC.c);
				jcrop_az.tC.c++;
			}
		}, 1000/60);
	},
	
	// Load only JSON file
	getJSONdataFromFile = function(url, firstTry){
		if (!url){return;}
		url = url.replace(/[^a-zA-Z0-9-_:\/.?&=]/g, '');
		//aZcR_jsFileName
		$.ajax({
			url: url,
			dataType: 'json',
			cache: false,
			success: function(d){
				jcrop_az.loadedCropFile = url;
				proceedJSONdata(d);
				$('#aZcR_onlyJSONcropFile').val(url);
				$('#aZcR_jsFileName').val(url.split('/').reverse()[0].split('.')[0]);
				$('aZcR_cropFileToLoad').val('');
			},
			error: function(a){
 				var status = a.status;
 				var txt = 'Error loading JSON file!';
 				
 				if (url.indexOf('/') == -1 && status == '404'){
 					getJSONdataFromFile('../pic/cropJSON/'+url, url);
 					return;
				}
 				
				if (status == '200'){
					$.fn.axZm.zoomAlert('Loaded JSON file ('+url+') contains errors', txt, false, false);
				}else{
					$.fn.axZm.zoomAlert('An error '+a.status+' occurred while loading JSON from '+url+(firstTry ? '; also tried to load from '+firstTry : ''), txt, false, false);
				}
				$('#aZcR_onlyJSONcropFile').val('');
				$('#aZcR_jsFileName').val(url.split('/').reverse()[0].split('.')[0]);
				$('aZcR_cropFileToLoad').val('');
			}
		});
	},
	
	// Load some content into editor
	changeAxZmContentPHP = function(opt){
		if (typeof ajaxZoom !== 'undefined'){
		
			if ($.axZm.spinPreloading){
				alert('Please wait...');
				return;
			}
			
			$('#pathToParameter').empty().removeAttr('style').removeAttr('class');
			
			var pathToLoad = '',
				pathToLoad2D = $('#aZcR_pathToLoad2D').val().replace(/(\"|\')/gm,''),
				pathToLoad360 = $('#aZcR_pathToLoad360').val().replace(/(\"|\')/gm,''),
				hotspotFileToLoad = $('#aZcR_hotspotFileToLoad').val(),
				cropFileToLoad = $('#aZcR_cropFileToLoad').val();
				
			// Allow pass these values to the function for demo (switching between 360 and 2d)
			if (!$.isEmptyObject(opt)){
				pathToLoad2D = ''; pathToLoad360 = ''; hotspotFileToLoad = ''; cropFileToLoad = '';
				if (opt.pathToLoad2D){pathToLoad2D = opt.pathToLoad2D;}
				if (opt.pathToLoad360){pathToLoad360 = opt.pathToLoad360;}
				if (opt.hotspotFileToLoad){hotspotFileToLoad = opt.hotspotFileToLoad;}
				if (opt.cropFileToLoad){cropFileToLoad = opt.cropFileToLoad;}
			}
			
			if (pathToLoad2D && pathToLoad360){
				alert('You have to decide whether to load 2D or 360/3D');
				return;
			} else if (!pathToLoad2D && !pathToLoad360){
				alert('Please enter the Path for 2D or Path for 360/3D');
				return;
			}
			
			if (pathToLoad2D || pathToLoad360){
				clearAll();
				$.fn.axZm.spinStop();
				jcrop_az.loadedCropFile = null;
				if ($.axZm){
					$('#'+$.axZm.mNavi.parentID).empty();
				}
				
				var myCallBacks = ajaxZoom.opt;
				myCallBacks.onLoad = function(){ // onSpinPreloadEnd
					$.axZm.spinReverse = false;
					
					// Load hotspots over this function...
					$.fn.axZm.loadHotspotsFromJsFile(hotspotFileToLoad, false, function(){
						var getHotspotJsFile = $.fn.axZm.getHotspotJsFile(true);		
					});
					
					if (cropFileToLoad){
						getJSONdataFromFile(cropFileToLoad);
					}
				};
				
				// Load / Reload AJAX-ZOOM
				var ajaxZoomReload = function(){
					pathToLoad = pathToLoad2D ? pathToLoad2D : pathToLoad360;
					
					var url = ajaxZoom.path + 'zoomLoad.php';
					var qStringPar = '3dDir';
					
					// check path to load and change 3dDir= to zoomData=
					if (pathToLoad2D && (/\.(gif|png|jp(e|g|eg)|tif|tiff|psd|bmp)((#|\?).*)?$/i.test(pathToLoad2D))){
						qStringPar = 'zoomData';
					} else if (pathToLoad2D) {
						qStringPar = 'zoomDir';
					}

					pathToLoad = pathToLoad.replace(/(zoomDir=|zoomData=|3dDir=|\"|\')/gm,'');
					
					var parameter = 'zoomLoadAjax=1&example=imageCrop&'+qStringPar+'='+pathToLoad;
					
					showRealQueryPath('example=imageCrop&'+qStringPar+'='+pathToLoad);
					
					if ($.axZm){
						delete $.axZm.hotspots;
					}
					
					if ($.aZcropEd.playerResponsive){
						$.fn.axZm.openFullScreen(ajaxZoom.path, parameter, myCallBacks, ajaxZoom.divID, false, false);
					} else {
						$.fn.axZm.load({
				            opt: myCallBacks,
				            path: ajaxZoom.path,
				            parameter: parameter,
				            divID: ajaxZoom.divID
						});
					}
				};
				
				ajaxZoomReload();
			}
		}
	},

 	getLoadedParameters = function(){
		var parToPass = $.axZm.parToPass,
			retString = '',
			parToPassArr = parToPass.split('&');
			
		$.each(parToPassArr, function(k, v){
			if (/(example=|zoomDir=|zoomData=|3dDir=)/gm.test(v)){
				retString += retString ? '&'+v : v;
				if (v.indexOf('3dDir=') != -1){
					$('#aZcR_pathToLoad360').val(v.replace(/(3dDir=)/gm,''));
					$('#aZcR_pathToLoad2D').val('');
				} else if (/(zoomDir=|zoomData=)/gm.test(v)){
					$('#aZcR_pathToLoad2D').val(v.replace(/(zoomDir=|zoomData=)/gm,''));
					$('#aZcR_pathToLoad360').val('');
				}
			}
		});
		var getHotspotJsFile = $.fn.axZm.getHotspotJsFile();
		$('#aZcR_hotspotFileToLoad').val(getHotspotJsFile ? getHotspotJsFile : '');
		$('#aZcR_cropFileToLoad').val(jcrop_az.loadedCropFile ? jcrop_az.loadedCropFile : '');

		showRealQueryPath(retString);
	},

	showRealQueryPath = function(retString){
		$('#aZcR_pathToParameter')
		.addClass('azMsg')
		.html("Below is the parameter which is passed to AJAX-ZOOM. You should change the value of <code>example=</code> \
		depending on the final configuration / example you will be using. <br>\
		<div style=\"margin: 5px 0px 5px 0px;\">\
		<code style=\"padding: 5px; background-color: #FFFFFF\">"+retString+"</code></div>");
	},

	saveJSONtoFile = function(){
		$("#aZcR_saveJSON").unbind('submit').bind('submit', function(event){
			// stop form from submitting normally
			event.preventDefault(); 
			
			$('#aZcR_saveToJSONresults').css('padding', 7);
			
			// get some values from elements on the page
			var Form = $(this),
				jsonCode = $('#aZcR_getAllThumbs').val(),
				keepFormat = $('#aZcR_jsKeepFormated').prop('checked') ? 1 : 0,
				fileName = $('#aZcR_jsFileName').val().replace(/[^a-zA-Z0-9-_]/g, ''),
				password = $('#aZcR_jsFilePass').val(),
				backup = $('#aZcR_jsBackUp').prop('checked') ? 1 : 0,
				url = Form.attr('action');

				fileName = fileName.replace('.json','');
				
			if (!fileName || !jsonCode){
				$('#aZcR_saveToJSONresults').empty().html('Please import your current crops into the \
				formfield above (press "Get all" button and define filename where this JSON should be saved!');
				return;
			}
			
			if (fileName != $('#aZcR_jsFileName').val()){
				$('#aZcR_jsFileName').val(fileName);
				$('#aZcR_saveToJSONresults').empty().html('Please use only a-zA-Z0-9-_ signs in file name');
				return;
			}
			
			$('#aZcR_saveToJSONresults').empty().html('Saving...');
			
			// Send the data using post and put the results in a div
			$.post(url, {jsonCode: jsonCode, keepFormat: keepFormat, fileName: fileName, password: password, backup: backup},
				function(data){
					$('#aZcR_saveToJSONresults').empty().append(data);
				}
			).fail(function(a) {
				if (a.status == 500){
					$('#aZcR_saveToJSONresults').empty().append('An error occured while sending data to '+url+' (status '+a.status+' '+a.statusText+'). Please make sure there are no PHP typo errors in this file!');
				} else if (a.status == 404){
					$('#aZcR_saveToJSONresults').empty().append(url + ' was not found on this server (status '+a.status+' '+a.statusText+'). Please adjust the path to saveHotspotJS.php in the action attribute of the form with id "saveHotspotJS".');
				} else {
					$('#aZcR_saveToJSONresults').empty().append('Error (status '+a.status+' '+a.statusText+'). Please check.');
				}
			});
		});	
	
		$('#aZcR_saveJSON').submit();
	};
	
	//aZcR aZcropEd
	$.aZcropEd = {
		toggleNaviBar: function(a){toggleNaviBar(a)},
		sortUpdate: function(a, b){return sortUpdate(a, b)},
		clearAll: clearAll,
		getAllCropsCSV: function(a, b){return getAllCropsCSV(a, b)},
		getObjFromCSV: function(a, b){return getObjFromCSV(a, b)},
		getAllThumbs: getAllThumbs,
		setAllEffect: function(a){setAllEffect(a)},
		setAllGrav: function(a){setAllGrav(a)},
		jCropInitSettings: jCropInitSettings,
		thumbUrls: function(a){thumbUrls(a)},
		toggleWYSIWYG: function(a){toggleWYSIWYG(a)},
		deleteCropAction: function(a){deleteCropAction(a)},
		deleteCropActionConfirm: function(a){deleteCropActionConfirm(a)},
		jCropHandleSelection: jCropHandleSelection,
		jCropAspectRatio: jCropAspectRatio,
		jCropAspectFlipValues: jCropAspectFlipValues,
		jCropAspectAsThumb: jCropAspectAsThumb,
		jCropAspectAsImage: jCropAspectAsImage,
		jCropFixedSize: jCropFixedSize,
		jCropOnChange: function(a){jCropOnChange(a)},
		jCropMethod: function(a, b){jCropMethod(a, b)},
		jCropSettingsPopup: jCropSettingsPopup,
		jCropFire: jCropFire,
		changeLang: function(a){changeLang(a)},
		
		proceedJSONdata: function(a){proceedJSONdata(a)},
		changeAxZmContentPHP: function(a){changeAxZmContentPHP(a)},
		getLoadedParameters: getLoadedParameters,
		showRealQueryPath: showRealQueryPath,
		saveJSONtoFile: saveJSONtoFile,
		getJSONdataFromFile: function(a){getJSONdataFromFile(a)}
	};


	jQuery(document).ready(function() {
		$.aZcropEd.langArr = [];
		if ($.aZcropEd.langugaesArray){
			$.aZcropEd.langArr[0] = {val: 'default', txt: 'default'};
			var m = 0;
			if ($.aZcropEd.langugaesArray[0]){
				$.each($.aZcropEd.langugaesArray, function(k, v){
					m++;
					$.aZcropEd.langArr[m] = {val: v, txt: v};
				});
			}
		}
		
 		$.each($('.azMsg,.naviInfo'), function(a, b){ // .naviInfo,
			var _this = $(b);
			if (_this.html().indexOf('zoombutton_close.png') != -1){
				return;
			}
			var aaa = '\
			<a class="ui-dialog-titlebar-close ui-corner-all" href="#" role="button" style="margin-left: 10px; margin-bottom: 5px;">\
				<span class="ui-icon ui-icon-closethick">close</span>\
			</a>';
			var closeIcon = $(aaa)
 			.attr({title: 'close this message', alt: 'close this message'})
			.addClass('infoCloseButton')
			.bind('click', function(){
				_this.remove();
			})
			closeIcon.prependTo(_this);
		});
		
		// The variable will hold a reference to Jcrop API once Jcrop is instantiated
		window.jcrop_api = null;

		// Variable to save values made with settings button
		window.jcrop_az = {};
		jcrop_az.order = {}; // order object
		jcrop_az.countThumbs = 0; // counter on crops
		jcrop_az.allCrops = {}; // here we save all data with crops
		
		jcrop_az.azCropOp = {}; // save Jcrop options here

		// Read initial values for thumbs
		jQuery.aZcropEd.jCropInitSettings();
 		jQuery.aZcropEd.jCropHandleSelection();
 		
		// Optionally init a slider to list all crops in a handy way
		jQuery('#cropSlider').axZmThumbSlider({
			orientation: 'horizontal',
			btnOver: true,
			btnHidden: true,
			btnFwdStyle: {borderRadius: 0, height: 20, bottom: -1, lineHeight: '20px'},
			btnBwdStyle: {borderRadius: 0, height: 20, top: -1, lineHeight: '20px'},

			thumbLiStyle: {
				height: 90,
				width: 90,
				lineHeight: 90,
				borderRadius: 0,
				margin: 3
			}
		});
		
		// Init UI tabs
		jQuery('#aZcR_tabs').tabs();
		
		// Instantly update JSON
		setTimeout(function(){
			$('a[href$="#aZcR_tabs-import"]').bind('click.aZcR', function(){
				getAllThumbs();
			})
		}, 2000);
		
		// Make thumbs sortable
		jQuery('#aZcR_cropResults').sortable({
			helper: 'clone',
			revert: true,
			update: function( e, ui ) {
				jQuery.aZcropEd.sortUpdate();
			}
		});
	});
	
})(jQuery);