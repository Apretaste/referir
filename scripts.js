"use strict";

$(document).ready(function(){
	$('.tabs').tabs();
});

function invite(theme) {
	var email = $('#email').val();
	remind(email, theme);
}

function remind(email, theme) {
	if (isEmail(email)) {
		apretaste.send({
			'command': 'REFERIR INVITAR',
			'data': {'email':email, 'theme':theme}
		});
	} else M.toast({'html':"Ingrese un email válido"});
}

function isEmail(email) {
	var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
	return regex.test(email);
}