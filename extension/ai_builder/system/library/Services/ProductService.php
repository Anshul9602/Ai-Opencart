<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class ProductService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function search(string $query, int $limit = 10): array {
		$db = $this->registry->get('db');
		$config = $this->registry->get('config');
		$language_id = (int)$config->get('config_language_id');

		$sql = "SELECT p.product_id, pd.name, p.model, p.price, p.quantity, p.status, p.image
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON p.product_id = pd.product_id
			WHERE pd.language_id = '" . $language_id . "'
			AND (pd.name LIKE '%" . $db->escape($query) . "%' OR p.model LIKE '%" . $db->escape($query) . "%')
			ORDER BY pd.name ASC
			LIMIT " . (int)$limit;

		$query_result = $db->query($sql);
		$products = [];

		foreach ($query_result->rows as $row) {
			$products[] = [
				'id'       => $row['product_id'],
				'name'     => $row['name'],
				'model'    => $row['model'],
				'price'    => $row['price'],
				'quantity' => $row['quantity'],
				'status'   => $row['status'],
				'preview'  => $row['image'] ? HTTP_CATALOG . 'image/' . $row['image'] : ''
			];
		}

		return $products;
	}

	public function updatePrice(int $product_id, float $price, ?float $special = null): array {
		$db = $this->registry->get('db');

		$db->query("UPDATE `" . DB_PREFIX . "product` SET `price` = '" . (float)$price . "' WHERE `product_id` = '" . (int)$product_id . "'");

		if ($special !== null) {
			$db->query("DELETE FROM `" . DB_PREFIX . "product_special`
				WHERE `product_id` = '" . (int)$product_id . "'");

			if ($special > 0) {
				$db->query("INSERT INTO `" . DB_PREFIX . "product_special` SET
					`product_id` = '" . (int)$product_id . "',
					`customer_group_id` = '1',
					`priority` = '1',
					`price` = '" . (float)$special . "',
					`date_start` = '0000-00-00',
					`date_end` = '0000-00-00'");
			}
		}

		return ['success' => true, 'message' => 'Product price updated.', 'product_id' => $product_id];
	}

	public function create(array $data): array {
		$loader = $this->registry->get('load');
		$loader->model('catalog/product');
		$model = $this->registry->get('model_catalog_product');
		$config = $this->registry->get('config');
		$language_id = (int)$config->get('config_language_id');

		$product_data = [
			'model'            => $data['model'] ?? '',
			'sku'              => $data['sku'] ?? '',
			'price'            => (float)($data['price'] ?? 0),
			'quantity'         => (int)($data['quantity'] ?? 0),
			'status'           => (int)($data['status'] ?? 1),
			'image'            => $data['image'] ?? '',
			'product_description' => [
				$language_id => [
					'name'             => $data['name'] ?? '',
					'description'      => $data['description'] ?? '',
					'meta_title'       => $data['meta_title'] ?? $data['name'] ?? '',
					'meta_description' => $data['meta_description'] ?? '',
					'meta_keyword'     => $data['meta_keyword'] ?? '',
					'tag'              => ''
				]
			],
			'product_store'    => [0],
			'product_category' => $data['categories'] ?? [],
			'manufacturer_id' => (int)($data['manufacturer_id'] ?? 0),
			'tax_class_id'     => 0,
			'minimum'          => 1,
			'subtract'         => 1,
			'stock_status_id'  => 7,
			'shipping'         => 1,
			'points'           => 0,
			'weight'           => 0,
			'weight_class_id'  => 1,
			'length'           => 0,
			'width'            => 0,
			'height'           => 0,
			'length_class_id'  => 1,
			'product_attribute' => [],
			'product_option'   => [],
			'product_discount' => [],
			'product_special'  => !empty($data['special_price']) ? [[
				'customer_group_id' => 1,
				'priority'          => 1,
				'price'             => (float)$data['special_price'],
				'date_start'        => '',
				'date_end'          => ''
			]] : [],
			'product_image'    => [],
			'product_download' => [],
			'product_filter'   => [],
			'product_related'  => [],
			'product_reward'   => [],
			'product_layout'   => [],
			'product_seo_url'  => !empty($data['seo_url']) ? [0 => [$language_id => $data['seo_url']]] : []
		];

		$product_id = $model->addProduct($product_data);

		return ['success' => true, 'message' => 'Product created successfully.', 'product_id' => $product_id];
	}

	public function importFromCsv(array $rows): array {
		$imported = 0;
		$errors = [];

		foreach ($rows as $i => $row) {
			$result = $this->create([
				'name'             => $row['name'] ?? '',
				'model'            => $row['model'] ?? '',
				'sku'              => $row['sku'] ?? '',
				'price'            => $row['price'] ?? 0,
				'special_price'    => $row['special_price'] ?? null,
				'quantity'         => $row['quantity'] ?? 0,
				'status'           => $row['status'] ?? 1,
				'description'      => $row['description'] ?? '',
				'image'            => $row['image'] ?? '',
				'seo_url'          => $row['seo_url'] ?? '',
				'meta_title'       => $row['meta_title'] ?? '',
				'meta_description' => $row['meta_description'] ?? ''
			]);

			if (!empty($result['success'])) {
				$imported++;
			} else {
				$errors[] = ['line' => $i + 2, 'error' => $result['error'] ?? 'Unknown error'];
			}
		}

		return [
			'success'  => true,
			'imported' => $imported,
			'errors'   => $errors,
			'message'  => "{$imported} products imported successfully."
		];
	}

	public function bulkPriceUpdate(float $percentage, string $operation = 'increase'): array {
		$db = $this->registry->get('db');
		$multiplier = $operation === 'increase' ? (1 + $percentage / 100) : (1 - $percentage / 100);

		$query = $db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product`");
		$total = (int)$query->row['total'];

		$db->query("UPDATE `" . DB_PREFIX . "product` SET `price` = ROUND(`price` * " . (float)$multiplier . ", 4)");

		return [
			'success' => true,
			'affected' => $total,
			'message' => "Updated prices for {$total} products ({$operation} by {$percentage}%)."
		];
	}

	public function disableOutOfStock(): array {
		$db = $this->registry->get('db');

		$query = $db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product` WHERE `quantity` <= 0 AND `status` = '1'");
		$total = (int)$query->row['total'];

		$db->query("UPDATE `" . DB_PREFIX . "product` SET `status` = '0' WHERE `quantity` <= 0");

		return [
			'success' => true,
			'affected' => $total,
			'message' => "Disabled {$total} out-of-stock products."
		];
	}

	public function findWithoutImages(int $limit = 20): array {
		$db = $this->registry->get('db');
		$config = $this->registry->get('config');
		$language_id = (int)$config->get('config_language_id');

		$query = $db->query("SELECT p.product_id, pd.name, p.model
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON p.product_id = pd.product_id
			WHERE pd.language_id = '" . $language_id . "'
			AND (p.image = '' OR p.image IS NULL)
			LIMIT " . (int)$limit);

		return $query->rows;
	}

	public function list(array $params = []): array {
		$model = $this->loadProductModel();
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

		if (!empty($params['low_stock'])) {
			$filter['filter_quantity_to'] = (int)($params['threshold'] ?? 5);
		}

		$rows = $model->getProducts($filter);

		return array_map(fn(array $row) => [
			'id'       => (int)$row['product_id'],
			'name'     => $row['name'],
			'model'    => $row['model'],
			'price'    => $row['price'],
			'quantity' => (int)$row['quantity'],
			'status'   => (int)$row['status'],
			'preview'  => !empty($row['image']) ? HTTP_CATALOG . 'image/' . $row['image'] : ''
		], $rows);
	}

	public function get(int $product_id): array {
		$model = $this->loadProductModel();
		$product = $model->getProduct($product_id);

		if (!$product) {
			return ['error' => 'Product not found'];
		}

		return [
			'success' => true,
			'data'    => [
				'id'       => (int)$product['product_id'],
				'name'     => $product['name'] ?? '',
				'model'    => $product['model'] ?? '',
				'price'    => $product['price'] ?? 0,
				'quantity' => (int)($product['quantity'] ?? 0),
				'status'   => (int)($product['status'] ?? 0),
				'image'    => $product['image'] ?? '',
				'preview'  => !empty($product['image']) ? HTTP_CATALOG . 'image/' . $product['image'] : ''
			]
		];
	}

	public function delete(int $product_id): array {
		$model = $this->loadProductModel();
		$product = $model->getProduct($product_id);

		if (!$product) {
			return ['error' => 'Product not found'];
		}

		$model->deleteProduct($product_id);

		return [
			'success' => true,
			'message' => 'Product "' . ($product['name'] ?? 'Product') . '" deleted successfully.'
		];
	}

	public function duplicate(int $product_id): array {
		$data = $this->getFormData($product_id);

		if (!$data) {
			return ['error' => 'Product not found'];
		}

		$language_id = $this->getLanguageId();

		if (isset($data['product_description'][$language_id]['name'])) {
			$data['product_description'][$language_id]['name'] .= ' (Copy)';
		}

		$data['model'] = ($data['model'] ?? 'model') . '-copy-' . time();
		$data['product_code'] = [];

		$model = $this->loadProductModel();
		$new_id = $model->addProduct($data);

		return [
			'success'    => true,
			'message'    => 'Product duplicated successfully.',
			'product_id' => $new_id
		];
	}

	public function setStatus(int $product_id, int $status): array {
		$db = $this->registry->get('db');
		$model = $this->loadProductModel();
		$product = $model->getProduct($product_id);

		if (!$product) {
			return ['error' => 'Product not found'];
		}

		$db->query("UPDATE `" . DB_PREFIX . "product` SET `status` = '" . (int)$status . "' WHERE `product_id` = '" . (int)$product_id . "'");

		$label = $status ? 'enabled' : 'disabled';

		return [
			'success' => true,
			'message' => 'Product "' . ($product['name'] ?? '') . '" ' . $label . '.'
		];
	}

	public function updateQuantity(int $product_id, int $quantity): array {
		$db = $this->registry->get('db');
		$model = $this->loadProductModel();
		$product = $model->getProduct($product_id);

		if (!$product) {
			return ['error' => 'Product not found'];
		}

		$db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . (int)$quantity . "' WHERE `product_id` = '" . (int)$product_id . "'");

		return [
			'success' => true,
			'message' => 'Stock updated to ' . $quantity . ' for "' . ($product['name'] ?? '') . '".'
		];
	}

	public function updateSpecialPrice(int $product_id, float $special): array {
		$model = $this->loadProductModel();
		$product = $model->getProduct($product_id);

		if (!$product) {
			return ['error' => 'Product not found'];
		}

		return $this->updatePrice($product_id, (float)$product['price'], $special);
	}

	public function changeCategory(int $product_id, array $category_ids): array {
		$data = $this->getFormData($product_id);

		if (!$data) {
			return ['error' => 'Product not found'];
		}

		$data['product_category'] = array_map('intval', $category_ids);
		$this->loadProductModel()->editProduct($product_id, $data);

		return ['success' => true, 'message' => 'Product categories updated.'];
	}

	public function changeManufacturer(int $product_id, int $manufacturer_id): array {
		$data = $this->getFormData($product_id);

		if (!$data) {
			return ['error' => 'Product not found'];
		}

		$data['manufacturer_id'] = $manufacturer_id;
		$this->loadProductModel()->editProduct($product_id, $data);

		return ['success' => true, 'message' => 'Product manufacturer updated.'];
	}

	public function updateMainImage(int $product_id, string $image_path): array {
		$data = $this->getFormData($product_id);

		if (!$data) {
			return ['error' => 'Product not found'];
		}

		$data['image'] = $image_path;
		$this->loadProductModel()->editProduct($product_id, $data);

		return [
			'success' => true,
			'message' => 'Product image updated.',
			'preview' => HTTP_CATALOG . 'image/' . $image_path
		];
	}

	public function update(array $changes): array {
		$product_id = (int)($changes['product_id'] ?? 0);
		$data = $this->getFormData($product_id);

		if (!$data) {
			return ['error' => 'Product not found'];
		}

		$language_id = $this->getLanguageId();

		if (isset($changes['name'])) {
			$data['product_description'][$language_id]['name'] = $changes['name'];
		}

		if (isset($changes['description'])) {
			$data['product_description'][$language_id]['description'] = $changes['description'];
		}

		if (isset($changes['meta_title'])) {
			$data['product_description'][$language_id]['meta_title'] = $changes['meta_title'];
		}

		if (isset($changes['meta_description'])) {
			$data['product_description'][$language_id]['meta_description'] = $changes['meta_description'];
		}

		if (isset($changes['model'])) {
			$data['model'] = $changes['model'];
		}

		if (isset($changes['price'])) {
			$data['price'] = (float)$changes['price'];
		}

		if (isset($changes['quantity'])) {
			$data['quantity'] = (int)$changes['quantity'];
		}

		if (isset($changes['status'])) {
			$data['status'] = (int)$changes['status'];
		}

		if (isset($changes['image'])) {
			$data['image'] = $changes['image'];
		}

		$this->loadProductModel()->editProduct($product_id, $data);

		return ['success' => true, 'message' => 'Product updated successfully.'];
	}

	public function exportCsv(int $limit = 500): array {
		$products = $this->list(['limit' => $limit]);
		$lines = ['product_id,name,model,price,quantity,status'];

		foreach ($products as $product) {
			$lines[] = implode(',', [
				$product['id'],
				'"' . str_replace('"', '""', $product['name']) . '"',
				'"' . str_replace('"', '""', $product['model']) . '"',
				$product['price'],
				$product['quantity'],
				$product['status']
			]);
		}

		$csv = implode("\n", $lines);

		return [
			'success' => true,
			'message' => count($products) . ' products exported.',
			'data'    => ['csv' => $csv, 'count' => count($products)],
			'ui'      => ['type' => 'text']
		];
	}

	public function findLowStock(int $threshold = 5, int $limit = 20): array {
		return $this->list(['low_stock' => true, 'threshold' => $threshold, 'limit' => $limit]);
	}

	private function loadProductModel(): object {
		$loader = $this->registry->get('load');
		$loader->model('catalog/product');

		return $this->registry->get('model_catalog_product');
	}

	private function getLanguageId(): int {
		return (int)$this->registry->get('config')->get('config_language_id');
	}

	private function getFormData(int $product_id): ?array {
		$model = $this->loadProductModel();
		$product = $model->getProduct($product_id);

		if (!$product) {
			return null;
		}

		return [
			'model'               => $product['model'] ?? '',
			'location'            => $product['location'] ?? '',
			'variant'             => $product['variant'] ?? [],
			'override'            => $product['override'] ?? [],
			'quantity'            => (int)($product['quantity'] ?? 0),
			'minimum'             => (int)($product['minimum'] ?? 1),
			'subtract'            => (int)($product['subtract'] ?? 1),
			'stock_status_id'     => (int)($product['stock_status_id'] ?? 7),
			'image'               => $product['image'] ?? '',
			'date_available'      => $product['date_available'] ?? date('Y-m-d'),
			'manufacturer_id'     => (int)($product['manufacturer_id'] ?? 0),
			'shipping'            => (int)($product['shipping'] ?? 1),
			'price'               => (float)($product['price'] ?? 0),
			'points'              => (int)($product['points'] ?? 0),
			'weight'              => (float)($product['weight'] ?? 0),
			'weight_class_id'     => (int)($product['weight_class_id'] ?? 1),
			'length'              => (float)($product['length'] ?? 0),
			'width'               => (float)($product['width'] ?? 0),
			'height'              => (float)($product['height'] ?? 0),
			'length_class_id'     => (int)($product['length_class_id'] ?? 1),
			'status'              => (int)($product['status'] ?? 1),
			'tax_class_id'        => (int)($product['tax_class_id'] ?? 0),
			'sort_order'          => (int)($product['sort_order'] ?? 0),
			'product_description' => $model->getDescriptions($product_id),
			'product_category'    => $model->getCategories($product_id),
			'product_store'       => $model->getStores($product_id) ?: [0],
			'product_filter'      => $model->getFilters($product_id),
			'product_image'       => $model->getImages($product_id),
			'product_download'    => $model->getDownloads($product_id),
			'product_related'     => $model->getRelated($product_id),
			'product_attribute'   => $model->getAttributes($product_id),
			'product_option'      => $model->getOptions($product_id),
			'product_discount'    => $model->getDiscounts($product_id),
			'product_reward'      => $model->getRewards($product_id),
			'product_layout'      => $model->getLayouts($product_id),
			'product_subscription'=> $model->getSubscriptions($product_id),
			'product_code'        => $model->getCodes($product_id),
			'product_seo_url'     => $model->getSeoUrls($product_id)
		];
	}
}
