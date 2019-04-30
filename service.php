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

		// create returning array
		$responseContent = [
			"referred" => $father && $request->person->username != $father,
			"father" => $father,
			"children" => $children,
			"profit_by_child" => $this->profit_by_child,
			"profit_by_nieto" => $this->profit_by_nieto
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
		if(empty($father)) return $response->setTemplate('message.ejs', ["username"=>$request->input->data->username]);

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
}
