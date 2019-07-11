<?php

class Service
{
	private $profit_by_child = 0.50;
	private $profit_by_nieto = 0.05;

	/**
	 * Show the form or the list of referals
	 *
	 * @param Request
	 * @param Response
	 * @return void | Response
	 */
	public function _main(Request $request, Response $response)
	{
		// check if you haven been invited
		$res = Connection::query("SELECT COUNT(id) AS nbr FROM _referir WHERE user='{$request->person->email}'");
		if(empty($res[0]->nbr)) return $response->setTemplate('home.ejs', ['profit_by_child'=>$this->profit_by_child]);

		// get your father's username
		$res = Connection::query("SELECT father FROM _referir WHERE user='{$request->person->email}'");

		$father = false;
		if (isset($res[0]))
		{
			$father = Connection::query("SELECT username FROM person WHERE email='{$res[0]->father}'");
			$father = !empty($father) ? $father[0]->username: false;
		}
		
		// get your children and money earned by each
		$children = [];
		$res = Connection::query("SELECT user FROM _referir WHERE father='{$request->person->email}'");
		foreach($res as $child) {
			// calculate number of Grandsons
			$count = Connection::query("SELECT COUNT(id) AS nbr FROM _referir WHERE father='{$child->user}'")[0]->nbr;

			$referred = new stdClass();
			$referred->person = Utils::getUsernameFromEmail($child->user);
			$referred->referred = $count;
			$referred->earnings = $this->profit_by_child + ($count * $this->profit_by_nieto);
			$children[] = $referred;
		}

		$invitations = q("SELECT * FROM _email_invitations WHERE id_from = '{$request->person->id}'");
		foreach ($invitations as $invitation){
			$invitation->send_date = date('d/m/Y h:i a', strtotime($invitation->send_date));
		}

		// create returning array
		$responseContent = [
			"referred" => $father && $request->person->username != $father,
			"father" => $father,
			"children" => $children,
			"profit_by_child" => $this->profit_by_child,
			"profit_by_nieto" => $this->profit_by_nieto,
			"invitations" => $invitations
		];

		// create the confirmation for the invitor
		$response->setTemplate("referidos.ejs", $responseContent);
	}

	/**
	 * Add your father to the tree
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return void
	 */
	public function _amigo(Request $request, Response $response)
	{
		// get the email for the user and father
		$father = empty($request->input->data->username) ? $request->person : Utils::getPerson($request->input->data->username);

		// display message if the father do not exist
		if(empty($father)) {
			$content = [
				"header" => "No pudimos referirle",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "La persona a referir no existe en Apretaste o está intentando hacer una referencia inválida. Por favor compruebe que <b>{$request->input->data->username}</b> es un @username o email correcto e intente nuevamente"
			];

			$response->setTemplate('message.ejs', $content);
			return;
		}

		// if the was already invited, do not continue
		$res = Connection::query("SELECT COUNT(id) AS nbr FROM _referir WHERE user='{$request->person->email}'");
		if($res[0]->nbr) return;

		// add credit to you and your father
		Connection::query("UPDATE person SET credit=credit+{$this->profit_by_child} WHERE id='{$request->person->id}' OR id='{$father->id}'");

		// if you have a grandfather
		$granpa = Connection::query("SELECT father FROM _referir WHERE user='{$father->email}'");
		if(!empty($granpa)) {
			$granpa = $granpa[0]->father;
			// get the ID of the grandfather
			$granpaId = Connection::query("SELECT id FROM person WHERE email='$granpa'")[0]->id;

			// give credits to the grandfather
			Connection::query("UPDATE person SET credit=credit+{$this->profit_by_nieto} WHERE id='$granpaId'");

			// send the grandfather a notification
			if ($granpaId * 1 > 0)
				Utils::addNotification($granpaId, "Su referido @{$request->person->username} ha invitado a alguien a usar Apretaste, y le hemos regalado §{$this->profit_by_nieto}", '{"command":"REFERIR"}');
		}

		// insert the invitation
		Connection::query("INSERT INTO _referir (user,father) VALUES ('{$request->person->email}','{$father->email}')");

		// mandar notificaciones a ambos
		Utils::addNotification($request->person->id, "Usted ha sido referido a Apretaste, y le hemos regalado §{$this->profit_by_child}", '{"command":"REFERIR"}');
		Utils::addNotification($father->id, "Usted ha recibido §{$this->profit_by_child} por referir a @{$request->person->username} a usar Apretaste", '{"command":"REFERIR"}');

		// return the main response
		$this->_main($request, $response);
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
				"text" => "Gracias por invitar a $email a ser parte de nuestra comunidad, si se une seras notificado y recibiras §1 de credito."
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
