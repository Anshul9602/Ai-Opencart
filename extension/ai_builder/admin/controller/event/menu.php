<?php
namespace Opencart\Admin\Controller\Extension\AiBuilder\Event;

class Menu extends \Opencart\System\Engine\Controller {
	public function index(string &$route, array &$args): void {
		// No-op. Menu is added via OCMOD in column_left.php.
	}

	public function before(string &$route, array &$args): void {
		$this->index($route, $args);
	}
}
