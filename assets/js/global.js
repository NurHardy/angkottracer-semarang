/*
 * Global JavaScript files
 * ---------------------------------------
 */
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
function _ajax_send(_postdata, _finishcallback, _msg, _requesturl) {
	_ov_msg = _msg || 'Menyimpan...';
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
		type: "POST",
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
			if (typeof(errorCallback)=='function') {
				errorCallback({
					status: jqXHR.status,
					message: "Request failed.",
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