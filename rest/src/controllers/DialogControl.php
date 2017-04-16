<?php

use Slim\Container;

/**
 * 
 * Controller dialog/modal
 * @author Nur Hardyanto
 *
 */
class DialogControl {
	
	private $_data;
	protected $container;
	protected $renderer;

	// constructor receives container instance
	public function __construct(Container $container) {
		$this->container = $container;
		$this->renderer = $this->container->get('renderer');
		$this->_data = [];
	}
	
	public function get_modal($request, $response, $args) {
		// Render index view
		$postData = $request->getParsedBody();
		
		$modalHtml = '';
		if ($postData['name'] == 'node.add') {
			require_once SRCPATH . '/models/NodeModel.php';
			$nodeModel = new NodeModel($this->container->get('db'));
			
			$this->_data['formId'] = 'modal_form';
			$this->_data['nodeData'] = json_encode($postData['data']);
			$this->_data['idNodeToConnect'] = 0;
			if (!empty($postData['connect_to'])) {
				$this->_data['idNodeToConnect'] = intval($postData['connect_to']);
			}
			$this->_data['nodeTypeList'] = $nodeModel->get_node_types();
			
			$this->_data['formContent'] = $this->renderer->fetch('forms/form_node.php', $this->_data);
			$modalHtml = $this->renderer->render($response, 'modal/add_node.php', $this->_data);
			
		} else if ($postData['name'] == 'edge.add') {
			$this->_data['edgeData'] = json_encode($postData['data']);
			
			// TODO: Validation!
			if (!isset($postData['data']['id_node_1']) || !isset($postData['data']['id_node_2'])) {
				return "Incomplete parameter";
			}
			
			require_once SRCPATH . '/models/NodeModel.php';
			$nodeModel = new NodeModel($this->container->get('db'));
			
			$this->_data['dataNode1'] = $nodeModel->get_node_by_id(intval($postData['data']['id_node_1']));
			$this->_data['dataNode2'] = $nodeModel->get_node_by_id(intval($postData['data']['id_node_2']));
			
			$modalHtml = $this->renderer->render($response, 'modal/add_edge.php', $this->_data);
			
		} else if ($postData['name'] == 'route.load') {
			require_once SRCPATH . '/models/RouteModel.php';
			$routeModel = new RouteModel($this->container->get('db'));
			
			$this->_data['routeList'] = $routeModel->get_routes();
			$modalHtml = $this->renderer->render($response, 'modal/load_route.php', $this->_data);
			
		} else {
			$app->notFoundHandler($request, $response);
		}
		return $modalHtml;
	}
}