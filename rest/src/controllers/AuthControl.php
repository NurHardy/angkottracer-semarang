<?php

use Slim\Container;

/**
 * 
 * Controller authorization
 * @author Nur Hardyanto
 *
 */
class AuthControl {
	
	private $_data;
	protected $container;
	protected $renderer;

	// constructor receives container instance
	public function __construct(Container $container) {
		$this->container = $container;
		$this->renderer = $this->container->get('renderer');
		$this->_data = [];
	}
	
	public function login_form($request, $response, $args) {
		$args['pageTitle'] = "Contributor Login";
		
		$mysqli = $this->container->get('db');
		
		require_once SRCPATH . '/models/UserModel.php';
		$userModel = new UserModel($mysqli);
		
		if ($request->isPost()) {
			$postData = $request->getParsedBody();
			
			$args['uName'] = @$postData['sys_uname'];
			$userPassw = @$postData['sys_passw'];
			//$inputUserEmail = $postData[''];
			
			if ($userData = $this->_authenticate($args['uName'], $userPassw)) {
				//-- Redirect ke halaman editor
				$baseUrl = $this->container->get('settings')['url']['base'];
				$currentTimestamp = strtotime('now');
				return $response->withStatus(302)->withHeader('Location',
						$baseUrl.'/editor?token='.$userData['token'].
						'&timestamp='.$currentTimestamp);
			} else {
				$args['submitError'] = "Invalid email or password.";
			}
		}
		
		// Render index view
		return $this->renderer->render($response, 'login.phtml', $args);
	}
	
	public function logout($request, $response, $args) {
		//-- Redirect ke halaman login
		$baseUrl = $this->container->get('settings')['url']['base'];
		
		return $response->withStatus(302)->withHeader('Location',
				$baseUrl.'/auth/login?ref=editorlogout');
	}
	private function _generate_token($idUser) {
		
		echo "<pre>";
		print_r($postData);
		echo "</pre>";
	}
	
	private function _authenticate($userEmail, $userPassw) {
		$mysqli = $this->container->get('db');
		require_once SRCPATH . '/models/UserModel.php';
		$userModel = new UserModel($mysqli);
		
		$userData = $userModel->get_user_by_email($userEmail);
		
		if (!$userData) return false;
		
		// Jika password benar...
		if ((crypt($userPassw, $userData['passw'])) === $userData['passw']) {
			return $userData;
		}
		
		return false;
	}
	
	private function _destroy_session() {
		
	}
}