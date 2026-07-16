<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class CategoryService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function create(array $data): array {
		$loader = $this->registry->get('load');
		$loader->model('catalog/category');
		$model = $this->registry->get('model_catalog_category');
		$config = $this->registry->get('config');
		$language_id = (int)$config->get('config_language_id');

		$category_id = $model->addCategory([
			'parent_id' => (int)($data['parent_id'] ?? 0),
			'image'     => $data['image'] ?? '',
			'sort_order' => (int)($data['sort_order'] ?? 0),
			'status'    => (int)($data['status'] ?? 1),
			'category_description' => [
				$language_id => [
					'name'             => $data['name'] ?? '',
					'description'      => $data['description'] ?? '',
					'meta_title'       => $data['meta_title'] ?? $data['name'] ?? '',
					'meta_description' => $data['meta_description'] ?? '',
					'meta_keyword'     => ''
				]
			],
			'category_filter'    => [],
			'category_store'     => [0],
			'category_seo_url'   => !empty($data['seo_url']) ? [0 => [$language_id => $data['seo_url']]] : [],
			'category_layout'    => []
		]);

		return ['success' => true, 'message' => 'Category created.', 'category_id' => $category_id];
	}

	public function search(string $query, int $limit = 10): array {
		$db = $this->registry->get('db');
		$config = $this->registry->get('config');
		$language_id = (int)$config->get('config_language_id');

		$query_result = $db->query("SELECT c.category_id, cd.name, c.status, c.sort_order
			FROM `" . DB_PREFIX . "category` c
			LEFT JOIN `" . DB_PREFIX . "category_description` cd ON c.category_id = cd.category_id
			WHERE cd.language_id = '" . $language_id . "'
			AND cd.name LIKE '%" . $db->escape($query) . "%'
			LIMIT " . (int)$limit);

		return $query_result->rows;
	}
}
