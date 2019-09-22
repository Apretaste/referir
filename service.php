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
		$invitations = q("SELECT *, TIMESTAMPDIFF(DAY,send_date, NOW()) AS days FROM _email_invitations WHERE id_from = '{$request->person->id}'");
		foreach ($invitations as $invitation) {
			$invitation->send_date = date('m/d/Y', strtotime($invitation->send_date));
		}

		// create returning array
		$responseContent = [
			"invitations" => $invitations,
			"service" => $request->service ?? null
		];

		// create the confirmation for the invitor
		$response->setTemplate("home.ejs", $responseContent);
	}

	public function _bolita(Request $request, Response $response)
	{
		$request->service = "bolita";
		return $this->_main($request, $response);
	}

	public function _invitar(Request $request, Response $response)
	{
		$email = $request->input->data->email;
		$service = $request->input->data->service;
		if (!Utils::getPerson($email)) {
			$invitation = q("SELECT *, TIMESTAMPDIFF(DAY,send_date, NOW()) AS days FROM _email_invitations WHERE id_from='{$request->person->id}' AND email_to='$email'");
			$resend = false;
			if (!empty($invitation)) {
				$resend = $invitation[0]->days >= 3;
				if (!$resend) {
					$content = [
						"header" => "Lo sentimos",
						"icon" => "sentiment_very_dissatisfied",
						"text" => "Ya enviaste una invitación a $email hace menos de 3 días, por favor espera antes de reenviar la invitación.",
						"service" => $service
					];

					$response->setTemplate('message.ejs', $content);
					return;
				}
			}

			$supportEmail = q("SELECT email FROM delivery_input WHERE environment='support' ORDER BY received ASC")[0]->email . '@gmail.com';
			$downloadLink = "http://bit.ly/32gPZns";
			$name = !empty($request->person->first_name) ? $request->person->first_name : '@' . $request->person->username;

			$invitationEmail = new Email();
			$invitationEmail->to = $email;

			if ($service == "bolita") {
				$downloadLink = "http://bit.ly/labolita";
				$invitationEmail->subject = "$name te ha invitado a la bolita";
				$invitationEmail->body = "<p>Algo debes tener, porque <b>@{$request->person->username}</b> te invitó a \"La Bolita\".</p>
			
			<p>La Bolita es nuestra app que te permite estar al tanto de los resultados de la bolita, aprender sobre la charada, predecir ganadores, sacar tu número de la suerte y más, todo hecho para el Cubano a través de Datos, WiFi y correo Nauta, y además, te ahorra datos de lo lindo, porque todas las peticiones son comprimidas al máximo.</p>
			
			<p>Descarga la app desde el siguiente enlace, entra usando este correo, y ambos $name y tú ganarán $0.50 de crédito para comprar dentro de la app.</p>
			
			<p>$downloadLink</p>
			
			<p>Si presentas alguna dificultad, escríbenos a $supportEmail y siempre estaremos atentos para ayudarte.</p>
			
			<p>Bienvenido a La Bolita!</p>";
			} else {
				$invitationEmail->subject = "$name te ha invitado a la app";
				$invitationEmail->body = "<p>Algo debes tener, porque <b>@{$request->person->username}</b> te invitó a ser parte nuestra vibrante comunidad en Ap!</p>
			
			<p>Somos la única app que ofrece docena de servicios útils en Cuba a través de Datos, WiFi y correo Nauta, y la que más ahorra tus megas. Además, cada semana hacemos rifas, concursos y encuestas, en las cuales te ganas teléfonos, tablets y recargas.</p>
			
			<p>Descarga la app desde el siguiente enlace, entra usando este correo, y ambos $name y tú ganarán $0.50 de crédito para comprar dentro de la app.</p>
			
			<p>$downloadLink</p>
			
			<p>Si presentas alguna dificultad, escríbenos a $supportEmail y siempre estaremos atentos para ayudarte.</p>
			
			<p>Bienvenido a nuestra familia!</p>";
			}

			$invitationEmail->send();

			if (!$resend) q("INSERT INTO _email_invitations(id_from, email_to) VALUES('{$request->person->id}','$email')");
			else q("UPDATE _email_invitations SET send_date = NOW() WHERE id_from = '{$request->person->id}' AND email_to = '$email'");

			$content = [
				"header" => "Su invitación ha sido enviada",
				"icon" => "sentiment_very_satisfied",
				"text" => "Gracias por invitar a $email a ser parte de nuestra comunidad, si se une serás notificado y recibirás §0.5 de crédito.",
				"service" => $service
			];

			$response->setTemplate('message.ejs', $content);
		} else {
			$extra = $email == $request->person->email ? ", es usted" : "";
			$content = [
				"header" => "Lo sentimos",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "El email $email ya forma parte de nuestros usuarios$extra.",
				"service" => $service
			];

			$response->setTemplate('message.ejs', $content);
		}
	}
}
