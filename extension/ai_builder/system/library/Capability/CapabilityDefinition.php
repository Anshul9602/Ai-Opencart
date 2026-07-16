<?php
namespace Opencart\System\Library\Extension\AiBuilder\Capability;

class CapabilityDefinition {
	public string $id;
	public string $entity;
	public string $action_type;
	public string $category;
	public string $description;
	public array $triggers;
	public array $required_inputs;
	public array $validation_rules;
	public bool $destructive;
	public bool $requires_confirmation;
	public string $status;
	public string $response_success;
	public string $response_error;

	public function __construct(array $config) {
		$this->id = $config['id'];
		$this->entity = $config['entity'];
		$this->action_type = $config['action_type'];
		$this->category = $config['category'];
		$this->description = $config['description'];
		$this->triggers = $config['triggers'] ?? [];
		$this->required_inputs = $config['required_inputs'] ?? [];
		$this->validation_rules = $config['validation_rules'] ?? [];
		$this->destructive = $config['destructive'] ?? false;
		$this->requires_confirmation = $config['requires_confirmation'] ?? false;
		$this->status = $config['status'] ?? 'planned';
		$this->response_success = $config['response_success'] ?? 'Operation completed successfully.';
		$this->response_error = $config['response_error'] ?? 'Operation failed.';
	}

	public function isImplemented(): bool {
		return $this->status === 'implemented';
	}

	public function toArray(): array {
		return [
			'id'                    => $this->id,
			'entity'                => $this->entity,
			'action_type'           => $this->action_type,
			'category'              => $this->category,
			'description'           => $this->description,
			'triggers'              => $this->triggers,
			'required_inputs'       => $this->required_inputs,
			'validation_rules'      => $this->validation_rules,
			'destructive'           => $this->destructive,
			'requires_confirmation' => $this->requires_confirmation,
			'status'                => $this->status,
		];
	}
}
