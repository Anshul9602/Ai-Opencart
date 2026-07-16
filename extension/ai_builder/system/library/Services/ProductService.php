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
}
