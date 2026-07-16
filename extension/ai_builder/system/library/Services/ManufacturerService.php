<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class ManufacturerService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function create(array $data): array {
		$loader = $this->registry->get('load');
		$loader->model('catalog/manufacturer');
		$model = $this->registry->get('model_catalog_manufacturer');

		$id = $model->addManufacturer([
			'name' => $data['name'] ?? '',
			'manufacturer_store' => [0],
			'image' => $data['image'] ?? '',
			'manufacturer_seo_url' => [],
			'manufacturer_layout' => []
		]);

		return ['success' => true, 'message' => 'Manufacturer created.', 'manufacturer_id' => $id];
	}

	public function search(string $query, int $limit = 10): array {
		$db = $this->registry->get('db');

		return $db->query("SELECT manufacturer_id, name FROM `" . DB_PREFIX . "manufacturer`
			WHERE name LIKE '%" . $db->escape($query) . "%'
			LIMIT " . (int)$limit)->rows;
	}

	public function delete(int $manufacturer_id): array {
		$loader = $this->registry->get('load');
		$loader->model('catalog/manufacturer');
		$this->registry->get('model_catalog_manufacturer')->deleteManufacturer($manufacturer_id);

		return ['success' => true, 'message' => 'Manufacturer deleted.'];
	}
}
