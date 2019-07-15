<?php

class Service
{
	/**
	 * Show the form or the list of referals
	 *
	 * @param Request
	 * @param Response
	 * @return void | Response
	 */
	public function _main(Request $request, Response $response)
	{
		$invitations = q("SELECT * FROM _email_invitations WHERE id_from = '{$request->person->id}'");
		foreach ($invitations as $invitation){
			$invitation->send_date = date('m/d/Y', strtotime($invitation->send_date));
		}

		// create returning array
		$responseContent = [
			"invitations" => $invitations
		];

		// create the confirmation for the invitor
		$response->setTemplate("home.ejs", $responseContent);
	}

	public function _invitar(Request $request, Response $response){
		$email = $request->input->data->email;
		if(!Utils::getPerson($email)){
			$invitation = q("SELECT *, TIMESTAMPDIFF(DAY,send_date, NOW()) AS days FROM _email_invitations WHERE id_from='{$request->person->id}' AND email_to='$email'");
			$resend = false;
			if(!empty($invitation)){
				$resend = $invitation[0]->days >= 3;
				if(!$resend){
					$content = [
						"header" => "Lo sentimos",
						"icon" => "sentiment_very_dissatisfied",
						"text" => "Ya enviaste una invitación a $email hace menos de 3 dias, por favor espera antes de reenviar la invitación."
					];

					$response->setTemplate('message.ejs', $content);
					return;
				}
			}

			$supportEmail = q("SELECT email FROM delivery_input WHERE environment='support' ORDER BY received ASC")[0].'@gmail.com';
			$blogLink = "http://bit.ly/2YIF7wq";
			$downloadLinkApk = "http://bit.ly/2XVsT6y";
			$downloadLinkPlay = "http://bit.ly/32gPZns";

			$invitationEmail = new Email();
			$invitationEmail->to = $email;
			$invitationEmail->subject = "Invitacion de @{$request->person->username}";
			$invitationEmail->body = "<p>Has sido invitado por <b>@{$request->person->username}</b>, a ser parte de la emocionante comunidad de Ap!</p>
			
			<p>Somos la única app que ofrece internet en Cuba a través del correo nauta, y la que más ahorra tus datos móviles.<br>
			Es muy fácil, solo descarga la app en el siguiente enlace <a href='$downloadLinkPlay'>$downloadLinkPlay</a> e ingresa tu dirección de correo.</p>
			
			<p>Aprovecha y recarga tu celular con nuestro sistema de créditos: con solo aceptar esta invitación ganarás tú y quien te invita. Más de 21 servicios te esperan para hacer de tu vida en Cuba, un poco más fácil y muy entretenida.</p>
			
			<p>Si presentas alguna dificultad, inquietud, duda o sugerencia, escribe a Soporte al siguiente email: <a href='mailto:$supportEmail'>$supportEmail</a>. Siempre estamos atentos a ayudarte!!!</p>
			
			<p>Visita nuestro blog <a href='$blogLink'>$blogLink</a> para estar al tanto de las novedades de Ap! y noticias del acontecer nacional e internacional.</p>";

			$invitationEmail->send();

			if(!$resend) q("INSERT INTO _email_invitations(id_from, email_to) VALUES('{$request->person->id}','$email')");
			else q("UPDATE _email_invitations SET send_date = NOW() WHERE id_from = '{$request->person->id}' AND email_to = '$email'");

			$content = [
				"header" => "Su invitación ha sido enviada",
				"icon" => "sentiment_very_satisfied",
				"text" => "Gracias por invitar a $email a ser parte de nuestra comunidad, si se une seras notificado y recibiras §0.5 de credito."
			];

			$response->setTemplate('message.ejs', $content);
		}
		else{
			$extra = $email == $request->person->email ? ", es usted" : "";
			$content = [
				"header" => "Lo sentimos",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "El email $email ya forma parte de nuestros usuarios$extra."
			];

			$response->setTemplate('message.ejs', $content);
		}
	}
}
