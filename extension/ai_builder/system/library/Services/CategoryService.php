<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class CategoryService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function create(array $data): array {
		try {
			$name = trim((string)($data['name'] ?? ''));

			if ($name === '') {
				return ['error' => 'Category name is required.'];
			}

			$model = $this->loadCategoryModel();
			$language_id = $this->getLanguageId();

			$category_id = $model->addCategory([
				'parent_id' => (int)($data['parent_id'] ?? 0),
				'image'     => $data['image'] ?? '',
				'sort_order' => (int)($data['sort_order'] ?? 0),
				'status'    => (int)($data['status'] ?? 1),
				'category_description' => [
					$language_id => [
						'name'             => $name,
						'description'      => $data['description'] ?? '',
						'meta_title'       => $data['meta_title'] ?? $name,
						'meta_description' => $data['meta_description'] ?? '',
						'meta_keyword'     => ''
					]
				],
				'category_filter'    => [],
				'category_store'     => [0],
				'category_seo_url'   => !empty($data['seo_url']) ? [0 => [$language_id => $data['seo_url']]] : [],
				'category_layout'    => []
			]);

			return [
				'success'     => true,
				'message'     => 'Category "' . $name . '" created successfully.',
				'category_id' => $category_id
			];
		} catch (\Throwable $e) {
			return ['error' => 'Could not create category: ' . $e->getMessage()];
		}
	}

	public function search(string $query, int $limit = 10): array {
		return $this->list(['query' => $query, 'limit' => $limit]);
	}

	public function list(array $params = []): array {
		$model = $this->loadCategoryModel();
		$filter = [
			'start' => 0,
			'limit' => (int)($params['limit'] ?? 20)
		];

		if (!empty($params['query'])) {
			$filter['filter_name'] = $params['query'];
		}

		if (isset($params['status']) && $params['status'] !== '') {
			$filter['filter_status'] = (int)$params['status'];
		}

		$rows = $model->getCategories($filter);

		return array_map(fn(array $row) => [
			'id'         => (int)$row['category_id'],
			'name'       => $row['name'],
			'status'     => (int)$row['status'],
			'sort_order' => (int)$row['sort_order'],
			'preview'    => !empty($row['image']) ? HTTP_CATALOG . 'image/' . $row['image'] : ''
		], $rows);
	}

	public function get(int $category_id): array {
		$model = $this->loadCategoryModel();
		$category = $model->getCategory($category_id);

		if (!$category) {
			return ['error' => 'Category not found'];
		}

		return [
			'success' => true,
			'data'    => [
				'id'         => (int)$category['category_id'],
				'name'       => $category['name'] ?? '',
				'path'       => $category['path'] ?? '',
				'parent_id'  => (int)($category['parent_id'] ?? 0),
				'status'     => (int)($category['status'] ?? 0),
				'sort_order' => (int)($category['sort_order'] ?? 0),
				'image'      => $category['image'] ?? '',
				'preview'    => !empty($category['image']) ? HTTP_CATALOG . 'image/' . $category['image'] : ''
			]
		];
	}

	public function update(array $changes): array {
		$category_id = (int)($changes['category_id'] ?? 0);
		$data = $this->getFormData($category_id);

		if (!$data) {
			return ['error' => 'Category not found'];
		}

		$language_id = $this->getLanguageId();

		if (isset($changes['name'])) {
			$data['category_description'][$language_id]['name'] = $changes['name'];
		}

		if (isset($changes['description'])) {
			$data['category_description'][$language_id]['description'] = $changes['description'];
		}

		if (isset($changes['meta_title'])) {
			$data['category_description'][$language_id]['meta_title'] = $changes['meta_title'];
		}

		if (isset($changes['meta_description'])) {
			$data['category_description'][$language_id]['meta_description'] = $changes['meta_description'];
		}

		if (isset($changes['parent_id'])) {
			$data['parent_id'] = (int)$changes['parent_id'];
		}

		if (isset($changes['sort_order'])) {
			$data['sort_order'] = (int)$changes['sort_order'];
		}

		if (isset($changes['status'])) {
			$data['status'] = (int)$changes['status'];
		}

		if (isset($changes['image'])) {
			$data['image'] = $changes['image'];
		}

		if (!empty($changes['seo_url'])) {
			$data['category_seo_url'] = [0 => [$language_id => $changes['seo_url']]];
		}

		$this->loadCategoryModel()->editCategory($category_id, $data);

		$name = html_entity_decode($changes['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$message = $name !== ''
			? 'Category renamed to "' . $name . '" successfully.'
			: 'Category updated successfully.';

		return ['success' => true, 'message' => $message];
	}

	public function delete(int $category_id): array {
		$model = $this->loadCategoryModel();
		$category = $model->getCategory($category_id);

		if (!$category) {
			return ['error' => 'Category not found'];
		}

		$model->deleteCategory($category_id);

		return [
			'success' => true,
			'message' => 'Category "' . ($category['name'] ?? '') . '" deleted successfully.'
		];
	}

	public function setStatus(int $category_id, int $status): array {
		$model = $this->loadCategoryModel();
		$category = $model->getCategory($category_id);

		if (!$category) {
			return ['error' => 'Category not found'];
		}

		$result = $this->update(['category_id' => $category_id, 'status' => $status]);

		if (!empty($result['error'])) {
			return $result;
		}

		$name = html_entity_decode($category['name'] ?? 'Category', ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$label = $status ? 'enabled' : 'disabled';

		return [
			'success' => true,
			'message' => 'Category "' . $name . '" ' . $label . ' successfully.'
		];
	}

	public function setParent(int $category_id, int $parent_id): array {
		return $this->update(['category_id' => $category_id, 'parent_id' => $parent_id]);
	}

	public function setSortOrder(int $category_id, int $sort_order): array {
		return $this->update(['category_id' => $category_id, 'sort_order' => $sort_order]);
	}

	public function updateImage(int $category_id, string $image_path): array {
		return $this->update(['category_id' => $category_id, 'image' => $image_path]);
	}

	public function updateMeta(int $category_id, array $meta): array {
		return $this->update(array_merge(['category_id' => $category_id], $meta));
	}

	public function updateSeoUrl(int $category_id, string $seo_url): array {
		return $this->update(['category_id' => $category_id, 'seo_url' => $seo_url]);
	}

	public function resolveId(string $name): int {
		$name = trim($name);

		if ($name === '') {
			return 0;
		}

		$id = $this->findIdInCategories($this->list(['query' => $name, 'limit' => 100]), $name);

		if ($id) {
			return $id;
		}

		$alpha = preg_replace('/[^a-z]/', '', strtolower($name));

		if ($alpha !== '') {
			$id = $this->findIdInCategories($this->list(['query' => $alpha, 'limit' => 200]), $name);

			if ($id) {
				return $id;
			}
		}

		return $this->findIdInCategories($this->list(['limit' => 500]), $name);
	}

	private function findIdInCategories(array $categories, string $name): int {
		return \Opencart\System\Library\Extension\AiBuilder\Chat\IntentHelper::findCategoryId($categories, $name);
	}

	private function loadCategoryModel(): object {
		$loader = $this->registry->get('load');
		$loader->model('catalog/category');

		return $this->registry->get('model_catalog_category');
	}

	private function getLanguageId(): int {
		$language_id = (int)$this->registry->get('config')->get('config_language_id');

		if ($language_id) {
			return $language_id;
		}

		$code = (string)($this->registry->get('config')->get('config_language_admin')
			?: $this->registry->get('config')->get('config_language_catalog')
			?: 'en-gb');

		$this->registry->get('load')->model('localisation/language');
		$language = $this->registry->get('model_localisation_language')->getLanguageByCode($code);

		return (int)($language['language_id'] ?? 1);
	}

	private function getFormData(int $category_id): ?array {
		$model = $this->loadCategoryModel();
		$category = $model->getCategory($category_id);

		if (!$category) {
			return null;
		}

		$loader = $this->registry->get('load');
		$loader->model('design/seo_url');
		$seo_model = $this->registry->get('model_design_seo_url');
		$seo_urls = [];
		$path = $model->getPath($category_id);
		$results = $seo_model->getSeoUrlsByKeyValue('path', $path);

		foreach ($results as $store_id => $languages) {
			foreach ($languages as $language_id => $keyword) {
				$pos = strrpos($keyword, '/');
				$seo_urls[$store_id][$language_id] = $pos !== false ? substr($keyword, $pos + 1) : $keyword;
			}
		}

		return [
			'parent_id'            => (int)($category['parent_id'] ?? 0),
			'image'                => $category['image'] ?? '',
			'sort_order'           => (int)($category['sort_order'] ?? 0),
			'status'               => (int)($category['status'] ?? 1),
			'category_description' => $model->getDescriptions($category_id),
			'category_filter'      => $model->getFilters($category_id),
			'category_store'       => $model->getStores($category_id) ?: [0],
			'category_seo_url'     => $seo_urls,
			'category_layout'      => $model->getLayouts($category_id)
		];
	}
}
