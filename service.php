<?php

class Service
{
	private $profit_by_child = 0.25;
	private $profit_by_nieto = 0.05;

	/**
	 * Function excecuted once the service Letra is called
	 *
	 * @param Request
	 * @param Response
	 *
	 * @return void | Response
	 */
	public function _main(Request $request, Response &$response)
	{
		$response->setLayout('referir.ejs');

		// check if you haven been invited
		$res = Connection::query("SELECT COUNT(id) AS nbr FROM _referir WHERE user='{$request->person->email}'");
		if(empty($res[0]->nbr))
		{
			$response->setTemplate('home.ejs', ['profit_by_child' => $this->profit_by_child]);
			return;
		}

		// get your father's username
		$res = Connection::query("SELECT father FROM _referir WHERE user='{$request->person->email}'");
		$father = Utils::getPerson($res[0]->father)->username;

		// get your children and money earned by each
		$children = [];
		$res = Connection::query("SELECT user FROM _referir WHERE father='{$request->person->email}'");
		foreach($res as $child)
		{
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
	 *
	 * @return void
	 */
	public function _persona(Request $request, Response &$response)
	{
		// get the email for the user and father
		$user = $request->person->email;
		$person = Utils::getPerson($request->input->data->query);
		if($person !== false)
			$father = $request->input->data->query;
		else
		{
			// error if the father do not exist
			$response->setTemplate( "missing.ejs", ["request" => $request ]);
			return;
		}

		// if the was already invited, do not continue
		$res = Connection::query("SELECT COUNT(id) AS nbr FROM _referir WHERE user='$user'");
		if($res[0]->nbr) return;

		// add credit to you and your father
		Connection::query("UPDATE person SET credit=credit+{$this->profit_by_child} WHERE email='$user' OR email='$father'");

		// if you have a grandfather, give it credits and send notification
		$granpa = Connection::query("SELECT father FROM _referir WHERE user='$father'");
		if(isset($granpa[0]))
		{
			Connection::query("UPDATE person SET credit=credit+{$this->profit_by_nieto} WHERE email='{$granpa[0]->father}'");
			Utils::addNotification($granpa[0]->father, "referir", "Su referido {$request->username} ha invitado a alguien a usar Apretaste, y le hemos regalado §{$this->profit_by_nieto}", "REFERIR");
		}

		// insert invitation
		Connection::query("INSERT INTO _referir (user,father) VALUES ('$user','$father')");

		// mandar notificaciones a ambos
		Utils::addNotification($user, "referir", "Usted ha sido referido a Apretaste, y le hemos regalado §{$this->profit_by_child}", "REFERIR");
		Utils::addNotification($father, "referir", "Usted ha recibido §{$this->profit_by_child} por referir a {$request->username} a usar Apretaste", "REFERIR");

		// return the main response
		$this->_main($request, $response);
	}
}
