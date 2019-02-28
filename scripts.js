function send() {
	// get the values
	var checked = $('#check').prop('checked')
	var username = $('#username').val();

	// check username is not empty
	if( !checked && !username) {
		M.toast({html: 'Díganos quien le refirió'});
		return false;
	}

	// send the request
	apretaste.send({
		command: "REFERIR AMIGO", 
		data: {"username": username},
		redirect: true});
}

function toggle() {
	if($('#check').prop('checked')) $('#input').slideUp()
	else $('#input').slideDown()
}