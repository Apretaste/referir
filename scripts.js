"use strict";

function showToast(text) {
  M.toast({
    html: text
  });
}

function send() {
  // get the values
  var checked = $('#check').prop('checked');
  var username = $('#username').val(); // check username is not empty

  if (!checked && !username) {
    showToast('Díganos quien le refirió');
    return false;
  } // send the request


  apretaste.send({
    command: "REFERIR AMIGO",
    data: {
      username: username
    },
    redirect: true
  });
}

function sendInvitation() {
  var email = $('#email').val();

  if (isEmail(email)) {
    apretaste.send({
      'command': 'REFERIR INVITAR',
      'data': {
        'email': email
      }
    });
  } else showToast("Ingrese un email válido");
}

function toggle() {
  if ($('#check').prop('checked')) $('#input').slideUp();else $('#input').slideDown();
}

function isEmail(email) {
  var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;

  if (!regex.test(email)) {
    return false;
  } else {
    return true;
  }
}

