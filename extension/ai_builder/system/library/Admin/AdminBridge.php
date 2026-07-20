<?php
namespace Opencart\System\Library\Extension\AiBuilder\Admin;

class AdminBridge {
	private object $registry;

	/** @var string[] */
	private array $allowed_prefixes = [
		'catalog/',
		'sale/',
		'customer/',
		'marketing/',
		'design/',
		'cms/',
		'localisation/',
		'report/',
		'setting/store',
	];

	/** @var string[] */
	private array $blocked_routes = [
		'user/user',
		'user/user_permission',
		'user/api',
		'marketplace/marketplace',
		'marketplace/install',
		'marketplace/modification',
		'marketplace/cron',
		'tool/backup',
		'tool/log',
		'tool/upload',
		'extension/extension',
		'extension/promotion',
		'extension/module',
		'setting/setting',
		'setting/event',
	];

	/** @var string[] */
	private array $write_prefixes = ['add', 'edit', 'delete', 'copy', 'remove', 'install', 'uninstall', 'enable', 'disable'];

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function call(string $route, string $method, array $args = []): array {
		$route = $this->sanitizeRoute($route);
		$method = $this->sanitizeMethod($method);

		if ($route === '' || $method === '') {
			return ['error' => 'Model route and method are required.'];
		}

		if (!$this->isRouteAllowed($route)) {
			return ['error' => 'Admin model route is not allowed: ' . $route];
		}

		if (!$this->isMethodAllowed($method)) {
			return ['error' => 'Model method is not allowed: ' . $method];
		}

		$permission_route = $this->permissionRoute($route);
		$access = $this->isWriteMethod($method) ? 'modify' : 'access';

		if (!$this->hasPermission($access, $permission_route)) {
			return ['error' => 'Permission denied. Your admin user needs ' . $access . ' permission on ' . $permission_route . '.'];
		}

		$mapped = AdminPanelMap::findRoute($route);

		if ($mapped && !in_array($method, $mapped['methods'], true) && !$this->isGenericReadMethod($method)) {
			return [
				'error' => 'Method ' . $method . ' is not registered for ' . $route . '. Allowed: ' . implode(', ', $mapped['methods'])
			];
		}

		try {
			$loader = $this->registry->get('load');
			$loader->model($route);
		} catch (\Throwable $e) {
			return ['error' => 'Could not load admin model ' . $route . ': ' . $e->getMessage()];
		}

		$key = 'model_' . str_replace('/', '_', $route);

		if (!$this->registry->has($key)) {
			return ['error' => 'Admin model not available: ' . $route];
		}

		$model = $this->registry->get($key);

		if (!is_object($model) || !method_exists($model, $method)) {
			return ['error' => 'Method not found on model: ' . $route . '::' . $method];
		}

		try {
			$result = $model->{$method}(...$this->normalizeArgs($args));
		} catch (\Throwable $e) {
			return ['error' => 'Admin model call failed: ' . $e->getMessage()];
		}

		return $this->formatResult($result, $route, $method);
	}

	public function listModules(): array {
		return [
			'success' => true,
			'message' => AdminPanelMap::toHelpMessage(),
			'data'    => AdminPanelMap::modules(),
			'ui'      => ['type' => 'text']
		];
	}

	public function callCatalogModel(string $route, string $method, array $args = []): array {
		$this->registry->get('load')->model('setting/store');
		$store_model = $this->registry->get('model_setting_store');

		$config = $this->registry->get('config');
		$store = $store_model->createStoreInstance(
			(int)$config->get('config_store_id'),
			(string)$config->get('config_language'),
			(string)$config->get('config_currency')
		);

		$bridge = new self($store);
		$store->get('load')->model($route);
		$key = 'model_' . str_replace('/', '_', $route);

		if (!$store->has($key)) {
			return ['error' => 'Catalog model not available: ' . $route];
		}

		$model = $store->get($key);

		if (!is_object($model) || !method_exists($model, $method)) {
			return ['error' => 'Catalog method not found: ' . $route . '::' . $method];
		}

		try {
			$result = $model->{$method}(...$this->normalizeArgs($args));

			return $this->formatResult($result, 'catalog:' . $route, $method);
		} catch (\Throwable $e) {
			return ['error' => 'Catalog model call failed: ' . $e->getMessage()];
		}
	}

	private function formatResult(mixed $result, string $route, string $method): array {
		if ($result === null || $result === true || $result === false) {
			return [
				'success' => true,
				'message' => $route . '::' . $method . ' completed successfully.',
				'data'    => $result
			];
		}

		if (!is_array($result)) {
			return [
				'success' => true,
				'message' => (string)$result,
				'data'    => $result
			];
		}

		if ($this->isListOfRecords($result)) {
			$table = $this->buildTableUi($result);

			return [
				'success' => true,
				'message' => count($result) . ' record(s) from ' . $route . '::' . $method,
				'data'    => $result,
				'ui'      => $table
			];
		}

		return [
			'success' => true,
			'message' => 'Result from ' . $route . '::' . $method,
			'data'    => $result
		];
	}

	private function isListOfRecords(array $result): bool {
		if ($result === []) {
			return false;
		}

		if (!array_is_list($result)) {
			return false;
		}

		return is_array($result[0] ?? null);
	}

	private function buildTableUi(array $rows): array {
		$first = $rows[0];
		$preferred = ['order_id', 'product_id', 'category_id', 'customer_id', 'name', 'firstname', 'email', 'total', 'status', 'order_status', 'date_added', 'model', 'quantity', 'price'];
		$keys = [];

		foreach ($preferred as $key) {
			if (array_key_exists($key, $first)) {
				$keys[] = $key;
			}
		}

		if (count($keys) < 3) {
			$keys = array_slice(array_keys($first), 0, 8);
		}

		$columns = array_map(fn(string $key): array => [
			'key'   => $key,
			'label' => ucwords(str_replace('_', ' ', $key))
		], $keys);

		$items = [];

		foreach ($rows as $row) {
			$item = ['id' => $row['order_id'] ?? $row['product_id'] ?? $row['category_id'] ?? $row['customer_id'] ?? $row['id'] ?? ''];

			foreach ($keys as $key) {
				$value = $row[$key] ?? '';
				$item[$key] = is_scalar($value) ? (string)$value : json_encode($value);
			}

			$items[] = $item;
		}

		return [
			'type'      => 'table',
			'item_type' => 'admin',
			'columns'   => $columns,
			'items'     => $items
		];
	}

	private function sanitizeRoute(string $route): string {
		return preg_replace('/[^a-z0-9_\/]/', '', strtolower(trim($route))) ?? '';
	}

	private function sanitizeMethod(string $method): string {
		return preg_replace('/[^a-z0-9_]/', '', trim($method)) ?? '';
	}

	private function isRouteAllowed(string $route): bool {
		if (in_array($route, $this->blocked_routes, true)) {
			return false;
		}

		foreach ($this->allowed_prefixes as $prefix) {
			if (str_starts_with($route, $prefix)) {
				return true;
			}
		}

		return AdminPanelMap::findRoute($route) !== null;
	}

	private function isMethodAllowed(string $method): bool {
		if (str_starts_with($method, '__')) {
			return false;
		}

		if ($this->isWriteMethod($method)) {
			return true;
		}

		return $this->isGenericReadMethod($method);
	}

	private function isGenericReadMethod(string $method): bool {
		return (bool)preg_match('/^(get|is|has|total|find|search|list)/i', $method);
	}

	private function isWriteMethod(string $method): bool {
		foreach ($this->write_prefixes as $prefix) {
			if (str_starts_with(strtolower($method), $prefix)) {
				return true;
			}
		}

		return false;
	}

	private function permissionRoute(string $route): string {
		$mapped = AdminPanelMap::findRoute($route);

		return $mapped['permission'] ?? $route;
	}

	private function hasPermission(string $type, string $route): bool {
		if (!$this->registry->has('user')) {
			return true;
		}

		$user = $this->registry->get('user');

		if (!is_object($user) || !method_exists($user, 'hasPermission')) {
			return true;
		}

		return (bool)$user->hasPermission($type, $route);
	}

	/** @param mixed $args */
	private function normalizeArgs($args): array {
		if (!is_array($args)) {
			return [];
		}

		if ($args === []) {
			return [];
		}

		if (array_is_list($args)) {
			return $args;
		}

		return [$args];
	}
}
