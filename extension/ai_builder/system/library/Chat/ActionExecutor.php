<?php
namespace Opencart\System\Library\Extension\AiBuilder\Chat;

use Opencart\System\Library\Extension\AiBuilder\Capability\CapabilityRegistry;

class ActionExecutor {
	private object $registry;
	private CapabilityRegistry $capabilities;

	public function __construct(object $registry) {
		$this->registry = $registry;
		$this->capabilities = CapabilityRegistry::getInstance();
	}

	public function execute(string $action, array $params, array $state = []): array {
		return $this->capabilities->execute($action, $params, $state, $this->registry);
	}

	public function getCapability(string $action): ?\Opencart\System\Library\Extension\AiBuilder\Capability\CapabilityDefinition {
		return $this->capabilities->get($action);
	}

	public function getRegistry(): CapabilityRegistry {
		return $this->capabilities;
	}
}
