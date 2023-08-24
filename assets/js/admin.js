jQuery(document).ready(function(){    
	jQuery('.legal-cg').click(function(){
		jQuery('.legal-content-cg').toggleClass('visible');	
	})
    jQuery('ul.tabs li').click(function(){
        var tab_id = jQuery(this).attr('data-tab');
        jQuery('ul.tabs li').removeClass('current');
        jQuery('.tab-content').removeClass('current');
        jQuery(this).addClass('current');
        jQuery("#"+tab_id).addClass('current');
        window.location.hash = tab_id;
		window.scrollTo(0, 0);
    })
	
    if (window.location.hash!=''){
        jQuery('ul.tabs li').removeClass('current');
        jQuery('.tab-content').removeClass('current');
        jQuery(window.location.hash).addClass('current');
        jQuery(window.location.hash+'-tab').addClass('current');
    };

})
function view_log() {
	var data = {
		'action': 'creditguard_view_log'
	};
	jQuery.post(ajaxurl, data, function(response) {
		var logDisplay=document.getElementById('see-log');
		logDisplay.innerHTML=response;
	});
}
function delete_log() {
	var data = {
		'action': 'creditguard_delete_log'
	};
	jQuery.post(ajaxurl, data, function() {
		var logDisplay=document.getElementById('see-log');
		logDisplay.innerHTML='';
	});
}