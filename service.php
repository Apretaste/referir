<?php

class Referir extends Service
{
	private $profit_by_child = 0.25;
	private $profit_by_nieto = 0.05;

	/**
	 * Function excecuted once the service Letra is called
	 *
	 * @param Request
	 * @return Response
	 */
	public function _main(Request $request)
	{
		// check if you haven been invited
		$connection = new Connection();
		$res = $connection->query("SELECT COUNT(id) AS nbr FROM _referir WHERE user='{$request->email}'");
		if(empty($res[0]->nbr)) {
			$response = new Response();
			$response->setResponseSubject("Empiece a ganar saldo");
			$response->createFromTemplate("home.tpl", array('profit_by_child'=>$this->profit_by_child));
			return $response;
		}

		// get your father's username
		$res = $connection->query("SELECT father FROM _referir WHERE user='{$request->email}'");
		$father = $this->utils->getUsernameFromEmail($res[0]->father);

		// get your children and money earned by each
		$children = array();
		$res = $connection->query("SELECT user FROM _referir WHERE father='{$request->email}'");
		foreach ($res as $child) {
			// calculate amount earned
			$count = $connection->query("SELECT COUNT(id) AS nbr FROM _referir WHERE father='{$child->user}'");

			$refered = new stdClass();
			$refered->person = $this->utils->getUsernameFromEmail($child->user);
			$refered->referred = $count[0]->nbr;
			$refered->earnings = $count[0]->nbr * $this->profit_by_nieto;
			$children[] = $refered;
		}

		// create returning array
		$responseContent = array(
			"father" => $father,
			"children" => $children,
			"profit_by_child" => $this->profit_by_child,
			"profit_by_nieto" => $this->profit_by_nieto
		);

		// create the confirmation for the invitor
		$response = new Response();
		$response->setResponseSubject("Sus referidos");
		$response->createFromTemplate("referidos.tpl", $responseContent);
		return $response;
	}

	/**
	 * Add your father to the tree
	 *
	 * @param Request
	 * @return Response
	 */
	public function _persona(Request $request)
	{
		// get the email for the user and father
		$user = $request->email;
		if($this->utils->personExist($request->query)) $father = $request->query;
		else $father = $this->utils->getEmailFromUsername($request->query);

		// error if the father do not exist
		if(empty($father) || $user == $father) {
			$response = new Response();
			$response->setResponseSubject("No pudimos crear la referencia");
			$response->createFromText("La persona a referir no existe en Apretaste o esta intentando hacer una referencia invalida. Por favor compruebe que <b>{$request->query}</b> es un @username o email correcto e intente nuevamente");
			return $response;
		}

		// if the was already invited, do not continue
		$connection = new Connection();
		$res = $connection->query("SELECT COUNT(id) AS nbr FROM _referir WHERE user='$user'");
		if($res[0]->nbr) return new Response();

		// add credit to you and your father
		$connection->query("UPDATE person SET credit=credit+{$this->profit_by_child} WHERE email='$user' OR email='$father'");

		// if you have a grandfather, give it credits and send notification
		$granpa = $connection->query("SELECT father FROM _referir WHERE user='$father'");
		if (isset($granpa[0])) {
			$connection->query("UPDATE person SET credit=credit+{$this->profit_by_nieto} WHERE email='{$granpa[0]->father}'");
			$this->utils->addNotification($granpa[0]->father, "referir", "Su referido {$request->username} ha invitado a alguien a usar Apretaste, y le hemos regalado §{$this->profit_by_nieto}", "REFERIR");
		}

		// insert invitation
		$connection->query("INSERT INTO _referir (user,father) VALUES ('$user','$father')");

		// mandar notificaciones a ambos
		$this->utils->addNotification($user, "referir", "Usted ha sido referido a Apretaste, y le hemos regalado §{$this->profit_by_child}", "REFERIR");
		$this->utils->addNotification($father, "referir", "Usted ha recibido §{$this->profit_by_child} por referir a {$request->username} a usar Apretaste", "REFERIR");

		// return the main response
		return $this->_main($request);
	}
}
