<?php

class Service
{
	/**
	 * Show the invitation form
	 *
	 * @param Request
	 * @param Response
	 * @return Response
	 */
	public function _main(Request $request, Response $response)
	{
		// get the theme
		$theme = empty($request->input->data->theme) ? "light" : $request->input->data->theme;

		// send response to the view
		$response->setCache("year");
		$response->setLayout("$theme.ejs");
		return $response->setTemplate("home.ejs");
	}

	/**
	 * Show the list of invitations
	 *
	 * @param Request
	 * @param Response
	 * @return Response
	 */
	public function _list(Request $request, Response $response)
	{
		// get the theme
		$theme = empty($request->input->data->theme) ? "light" : $request->input->data->theme;

		// get list of people invited
		$invitations = Connection::query("
			SELECT accepted, email_to, 
				TIMESTAMPDIFF(DAY, send_date, NOW()) AS days,
				DATE_FORMAT(send_date, '%e/%c/%Y') AS send_date
			FROM _email_invitations 
			WHERE id_from = '{$request->person->id}'");

		// send response to the view
		$response->setLayout("$theme.ejs");
		return $response->setTemplate("list.ejs", ["invitations" => $invitations]);
	}

	/**
	 * Show the invitar form for the service Bolita 
	 *
	 * @param Request
	 * @param Response
	 */
	public function _bolita(Request $request, Response $response)
	{
		$response = $this->_main($request, $response);
		$response->setLayout('dark.ejs');
	}

	/**
	 * Invite or remind a user to use the app
	 *
	 * @param Request
	 * @param Response
	 * @return void | Response
	 */
	public function _invitar(Request $request, Response $response)
	{
		// get the email of the host
		$email = $request->input->data->email;
		$theme = empty($request->input->data->theme) ? "light" : $request->input->data->theme;

		// set the layout
		$response->setLayout("$theme.ejs");

		// do not invite a user twice
		if (Utils::getPerson($email)) {
			$response->setTemplate('message.ejs', [
				"header" => "El usuario ya existe",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "El email $email ya forma parte de nuestros usuarios, por lo cual no lo podemos invitar a la app."
			]);
		}

		// get the days the invitation is due
		$invitation = Connection::query("
			SELECT TIMESTAMPDIFF(DAY,send_date, NOW()) AS days 
			FROM _email_invitations 
			WHERE id_from = '{$request->person->id}' 
			AND email_to = '$email'");

		// do not resend invitations before the three days
		$resend = false;
		if (!empty($invitation)) {
			$resend = $invitation[0]->days >= 3;
			if (!$resend) {
				return $response->setTemplate('message.ejs', [
					"header" => "Lo sentimos",
					"icon" => "sentiment_very_dissatisfied",
					"text" => "Ya enviaste una invitación a $email hace menos de 3 días, por favor espera antes de reenviar la invitación."
				]);
			}
		}

		// get support email
		$supportEmail = Utils::getSupportEmailAddress();

		// get host name or username if it does not exist
		$name = !empty($request->person->first_name) ? $request->person->first_name : '@' . $request->person->username;

		// create the invitation text
		if ($theme == "dark") {
			$link = "http://bit.ly/labolita";
			$subject = "$name te ha invitado a la bolita";
			$body = "
				<p>Algo debes tener, porque <b>@{$request->person->username}</b> te invitó a La Bolita.</p>
				<p>La Bolita es nuestra app que te permite estar al tanto de los resultados de la bolita, aprender sobre la charada, predecir ganadores, sacar tu número de la suerte y más, todo hecho para el Cubano a través de Datos, WiFi y correo Nauta, y además, te ahorra datos de lo lindo, porque todas las peticiones son comprimidas al máximo.</p>
				<p>Descarga la app desde el siguiente enlace, entra usando este correo, y ambos $name y tú ganarán $0.50 de crédito para comprar dentro de la app.</p>
				<p>$link</p>
				<p>Si presentas alguna dificultad, escríbenos a $supportEmail y siempre estaremos atentos para ayudarte.</p>
				<p>Bienvenido a La Bolita!</p>";
		} else {
			$link = "http://bit.ly/32gPZns";
			$subject = "$name te ha invitado a la app";
			$body = "
				<p>Algo debes tener, porque <b>@{$request->person->username}</b> te invitó a ser parte nuestra vibrante comunidad en Ap!</p>
				<p>Somos la única app que ofrece docena de servicios útils en Cuba a través de Datos, WiFi y correo Nauta, y la que más ahorra tus megas. Además, cada semana hacemos rifas, concursos y encuestas, en las cuales te ganas teléfonos, tablets y recargas.</p>
				<p>Descarga la app desde el siguiente enlace, entra usando este correo, y ambos $name y tú ganarán $0.50 de crédito para comprar dentro de la app.</p>
				<p>$link</p>
				<p>Si presentas alguna dificultad, escríbenos a $supportEmail y siempre estaremos atentos para ayudarte.</p>
				<p>Bienvenido a nuestra familia!</p>";
		}

		// send the email
		$invitationEmail = new Email();
		$invitationEmail->to = $email;
		$invitationEmail->subject = $subject;
		$invitationEmail->body = $body;
		$invitationEmail->service = "referir";
		$invitationEmail->send();

		// save invitation into the database
		if (!$resend) Connection::query("INSERT INTO _email_invitations(id_from, email_to) VALUES('{$request->person->id}','$email')");
		else Connection::query("UPDATE _email_invitations SET send_date = NOW() WHERE id_from = '{$request->person->id}' AND email_to = '$email'");

		// add the experience
		Level::setExperience('INVITE_FRIEND', $request->person->id);

		// success inviting the user
		$response->setTemplate('message.ejs', [
			"header" => "Su invitación ha sido enviada",
			"icon" => "sentiment_very_satisfied",
			"text" => "Gracias por invitar a $email a ser parte de nuestra comunidad, si se une serás notificado y recibirás §0.5 de crédito."
		]);
	}
}
