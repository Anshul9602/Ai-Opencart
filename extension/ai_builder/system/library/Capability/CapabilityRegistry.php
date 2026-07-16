<?php
namespace Opencart\System\Library\Extension\AiBuilder\Capability;

class CapabilityRegistry {
	private static ?self $instance = null;

	/** @var array<string, CapabilityDefinition> */
	private array $capabilities = [];

	public static function getInstance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
			CapabilitiesCatalog::registerAll(self::$instance);
		}

		return self::$instance;
	}

	public function register(CapabilityDefinition $capability): void {
		$this->capabilities[$capability->id] = $capability;
	}

	public function get(string $id): ?CapabilityDefinition {
		return $this->capabilities[$id] ?? null;
	}

	/** @return array<string, CapabilityDefinition> */
	public function all(): array {
		return $this->capabilities;
	}

	/** @return array<string, CapabilityDefinition> */
	public function implemented(): array {
		return array_filter($this->capabilities, fn(CapabilityDefinition $cap) => $cap->isImplemented());
	}

	/** @return array<string, CapabilityDefinition> */
	public function planned(): array {
		return array_filter($this->capabilities, fn(CapabilityDefinition $cap) => !$cap->isImplemented());
	}

	/** @return array<string, CapabilityDefinition> */
	public function byCategory(string $category): array {
		return array_filter(
			$this->capabilities,
			fn(CapabilityDefinition $cap) => $cap->category === $category
		);
	}

	/** @return array<string, CapabilityDefinition> */
	public function byEntity(string $entity): array {
		return array_filter(
			$this->capabilities,
			fn(CapabilityDefinition $cap) => $cap->entity === $entity
		);
	}

	public function execute(string $action, array $params, array $state, object $registry): array {
		$capability = $this->get($action);

		if (!$capability) {
			return ['error' => 'Unknown action: ' . $action];
		}

		if (!$capability->isImplemented()) {
			return [
				'success'  => false,
				'planned'  => true,
				'message'  => '"' . $capability->description . '" is registered in the capability system but not yet implemented. '
					. 'Category: ' . $capability->category . '. This feature will be enabled in a future update.',
				'capability' => $capability->toArray()
			];
		}

		$validation = $this->validateParams($capability, $params, $state);

		if (!empty($validation['error'])) {
			return $validation;
		}

		$result = CapabilityHandlers::execute($action, $params, $state, $registry);

		if (!empty($result['error'])) {
			$result['message'] = $result['error'];
		} elseif (!isset($result['message']) && $capability->response_success) {
			$result['message'] = $capability->response_success;
		}

		if ($capability->destructive && empty($result['needs_confirmation'])) {
			$result['destructive'] = true;
		}

		return $result;
	}

	public function toPromptSection(): string {
		$sections = [];
		$sections[] = 'CAPABILITY REGISTRY — use these action IDs. Do NOT invent actions.';
		$sections[] = 'Entity types: Product, Category, Banner, Customer, Order, Setting, Manufacturer, Coupon, Information, Image, Report, Marketing, SEO, Theme, Inventory, File, Extension, Database, Security, AI.';
		$sections[] = 'Action types: Create, Read, Update, Delete, Search, Import, Export, Duplicate, Enable, Disable, Generate, Translate, Bulk.';
		$sections[] = '';

		$sections[] = '=== IMPLEMENTED CAPABILITIES (set action to these) ===';

		$by_category = [];

		foreach ($this->implemented() as $cap) {
			$by_category[$cap->category][] = $cap;
		}

		foreach ($by_category as $category => $caps) {
			$sections[] = '## ' . $category;

			foreach ($caps as $cap) {
				$line = '- action="' . $cap->id . '" | ' . $cap->entity . '.' . $cap->action_type . ' | ' . $cap->description;

				if (!empty($cap->required_inputs)) {
					$inputs = [];

					foreach ($cap->required_inputs as $key => $rule) {
						$inputs[] = $key . '(' . ($rule['type'] ?? 'string') . ($rule['required'] ?? false ? ', required' : '') . ')';
					}

					$line .= ' | params: ' . implode(', ', $inputs);
				}

				if ($cap->destructive) {
					$line .= ' | DESTRUCTIVE — set destructive=true';
				}

				if (!empty($cap->triggers)) {
					$line .= ' | examples: "' . implode('", "', array_slice($cap->triggers, 0, 3)) . '"';
				}

				$sections[] = $line;
			}

			$sections[] = '';
		}

		$sections[] = '=== PLANNED CAPABILITIES (do NOT set action — tell user it is coming soon) ===';

		$planned_by_category = [];

		foreach ($this->planned() as $cap) {
			$planned_by_category[$cap->category][] = $cap->description;
		}

		foreach ($planned_by_category as $category => $descriptions) {
			$sections[] = '## ' . $category;
			$sections[] = implode('; ', array_slice($descriptions, 0, 12)) . (count($descriptions) > 12 ? '; ...' : '');
			$sections[] = '';
		}

		$sections[] = 'REGISTRY RULES:';
		$sections[] = '1. Match user intent to the closest IMPLEMENTED capability action ID.';
		$sections[] = '2. For planned features, explain they are registered but not yet available — do not set action.';
		$sections[] = '3. Collect required_inputs via needs_input=true before executing.';
		$sections[] = '4. Never invent store data — always fetch via implemented actions.';
		$sections[] = '5. Destructive actions require destructive=true and user confirmation.';

		return implode("\n", $sections);
	}

	public function toJson(): array {
		return array_map(fn(CapabilityDefinition $cap) => $cap->toArray(), array_values($this->capabilities));
	}

	private function validateParams(CapabilityDefinition $capability, array $params, array $state): array {
		$merged = array_merge($state, $params);
		$missing = [];

		foreach ($capability->required_inputs as $field => $rule) {
			if (!($rule['required'] ?? false)) {
				continue;
			}

			if (!array_key_exists($field, $merged) || $merged[$field] === '' || $merged[$field] === null) {
				$missing[] = $field;
			}
		}

		if (!empty($missing)) {
			return [
				'error'   => 'Missing required fields: ' . implode(', ', $missing),
				'missing' => $missing
			];
		}

		return [];
	}
}
