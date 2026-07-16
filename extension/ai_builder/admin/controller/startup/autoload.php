<?php
namespace Opencart\Admin\Controller\Extension\AiBuilder\Startup;

class Autoload extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->autoloader->register(
			'Opencart\System\Library\Extension\AiBuilder',
			DIR_EXTENSION . 'ai_builder/system/library/',
			true
		);
	}
}
