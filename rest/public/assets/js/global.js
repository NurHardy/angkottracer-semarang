/*
 * Global JavaScript files
 * ---------------------------------------
 */

	//-- STATE CONSTANTS ----------------------
	var STATE_DEFAULT = 0;
	var STATE_NODESELECTED = 1;
	var STATE_EDGESELECTED = 2;
	var STATE_PLACENODE = 51;
	var STATE_SELECTNODE = 52;
	var STATE_MOVENODE = 53;

	var STATE_ROUTEEDITOR = 100;
	var STATE_DRAWROUTE = 101;
	
	var STATE_ROUTEDEBUG = 200;
	
	//-- SYSTEM CONSTANTS ---------------------
	var SYS_MULTIDIR_POLYLINE_COLOR = '#0000FF';
	var SYS_SINGLEDIR_POLYLINE_COLOR = '#00A2E8';
	
	var SYS_SINGLEDIR_POLYLINE_ICONS = [];
	var SYS_NODEMARKER_ICON = null;
	var SYS_NODEMARKER_BUSSHELTER = null;
	var SYS_NODEMARKER_TRAFFIC = null;
	
	var SYS_EDGEEDITOR_DEF_OPACITY = 0.75;
	
	$(document).ready(function() {
		
	});
	function goToTop() {
		$('html, body').animate({scrollTop:'0px'},750,'easeInOutQuint');
		return false;
	}
	
	function show_overlay(_msg) {
		if (_msg === '') {$("#site_ov_msg_process").html('Sedang memproses... Mohon tunggu...');}
		else $("#site_ov_msg_process").html(_msg);
		$("#site_overlay_process").show();
	}
	function hide_overlay() {
		$("#site_overlay_process").fadeOut(100);
	}
	function ov_change_msg(_msg) {
		$("#site_ov_msg").html(_msg);
	}
	
	var _on_modal_cancelled = null;
	
	function show_modal(fetchUrl, fetchData, onSubmit, onCancel, preOnSubmit) {
		$.ajax({
			type: "POST",
			url: fetchUrl,
			data: fetchData,
			dataType: 'html',
			beforeSend: function( xhr ) {
				$("#site_overlay_modal").fadeIn(250);
				$('#site_modal_loader').show();
				$('#site_modal_content').html("-").hide();
			},
			success: function(response){
				$('#site_modal_loader').hide();
				$('#site_modal_content').html(response);
				if (typeof(init_modal) === 'function') {
					init_modal(onSubmit, onCancel, preOnSubmit);
				}
				_on_modal_cancelled = function(){
					hide_modal();
					if (typeof(onCancel) === 'function') {
						onCancel();
					}
				};
				$('#site_overlay_modal .modal-closebtn').click(_on_modal_cancelled);
				$('#site_modal_content').show();
				if (typeof(postinit_modal) === 'function') {
					postinit_modal();
				}
			},
			error: function(jqXHR){
				$('#site_modal_content').html("Fetch error. Please try again.<br /><a href='#' onclick='return hide_modal();'>OK</a>").show();
			}
		}).always(function() {
			
		});
	}
	function hide_modal() {
		$("#site_overlay_modal").hide();
		return false;
	}
	function _ajax_send(_postdata, _finishcallback, _msg, _requesturl, _strReqType) {
		var _reqType = _strReqType || 'POST';
		var _ov_msg = _msg || 'Menyimpan...';
		var _reqURL = _requesturl || AJAX_REQ_URL;
		var alwaysCallback = null;
		var okCallback = null;
		var errorCallback = function(response) {
			alert("Error returned: "+response.message);
		};
		
		if (typeof(_finishcallback)=='function') {
			okCallback = _finishcallback;
		} else if (typeof(_finishcallback)=='object') {
			if ('success' in _finishcallback)
				okCallback = _finishcallback.success;
			if ('error' in _finishcallback)
				errorCallback = _finishcallback.error;
			if ('always' in _finishcallback)
				alwaysCallback = _finishcallback.always;
		}
		$.ajax({
			type: _reqType,
			url: _reqURL,
			data: _postdata,
			dataType: 'json',
			beforeSend: function( xhr ) {
				is_processing = true;
				show_overlay(_ov_msg);
			},
			success: function(response){
				if (response.status != 'ok') {
					if (typeof(errorCallback)=='function')
						errorCallback(response);
				} else {
					if (typeof(okCallback)=='function')
						okCallback(response);
				}
			},
			error: function(jqXHR){
				var errMessage = "Request failed.";
				var respBody = [];
				try {
			        respBody = JSON.parse(jqXHR.responseText);
			        if ('message' in respBody) {
			        	errMessage = errMessage + " Message: " + respBody.message;
			        }
			    } catch (e) {
			    	//-- Unparseable JSON response
			    }
			   
				
				if (typeof(errorCallback)=='function') {
					errorCallback({
						status: jqXHR.status,
						message: errMessage,
						xhr: jqXHR
					});
				} else {
					alert("Request error: "+jqXHR.status + " " + jqXHR.statusText);
				}
			}
		}).always(function() {
			if (typeof(alwaysCallback)=='function')
				alwaysCallback();
			is_processing = false;
			hide_overlay();
		});
	}
	
	function loadScripts(sources, callback) {
		var loadedScripts = 0;
		var numScripts = 0;
		// get num of sources
		for(var src in sources) {
			numScripts++;
		}
		for(var src in sources) {
			var resource = document.createElement('script'); 
			resource.onload = function() {
				if(++loadedScripts >= numScripts) {
					callback();
				}
			};
			resource.src = sources[src];
			
			var script = document.getElementsByTagName('script')[0];
			script.parentNode.insertBefore(resource, script);
	    }
	}