<?php
namespace Opencart\System\Library\Extension\AiBuilder\Capability;

use Opencart\System\Library\Extension\AiBuilder\Chat\IntentHelper;

class CapabilityHandlers {
	public static function execute(string $action, array $params, array $state, object $registry): array {
		switch ($action) {
			case 'list_banners':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\BannerService($registry);
				$operation = $params['operation'] ?? $state['operation'] ?? 'update';
				$insert_position = isset($params['insert_position'])
					? (int)$params['insert_position']
					: ($operation === 'create' ? (int)($state['insert_position'] ?? 0) : 0);
				$banners = $service->listBanners($params['search'] ?? '');

				return [
					'success' => true,
					'message' => count($banners)
						? IntentHelper::bannerListMessage($operation, $insert_position)
						: 'No banners found.',
					'data'    => $banners,
					'ui'      => [
						'type'      => 'cards',
						'item_type' => 'banner',
						'items'     => array_map(fn($b) => [
							'id'      => $b['id'],
							'title'   => $b['name'],
							'preview' => $b['preview'],
							'meta'    => $b['slides'] . ' slides'
						], $banners)
					],
					'state_update' => [
						'step'            => 'banner_selected',
						'operation'       => $operation,
						'banners'         => $banners,
						'insert_position' => $operation === 'create' ? $insert_position : null
					]
				];

			case 'get_banner_slides':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\BannerService($registry);
				$operation = $params['operation'] ?? $state['operation'] ?? 'update';
				$insert_position = isset($params['insert_position'])
					? (int)$params['insert_position']
					: (int)($state['insert_position'] ?? 0);
				$banner_id = (int)($params['banner_id'] ?? $state['selected_banner_id'] ?? 0);
				$data = $service->getBannerSlides($banner_id);

				if ($operation === 'create') {
					return [
						'success' => true,
						'message' => IntentHelper::bannerSlidesMessage($operation, $insert_position),
						'ui'      => ['type' => 'upload', 'accept' => 'image/*'],
						'state_update' => [
							'step'               => 'awaiting_upload',
							'operation'          => 'create',
							'selected_banner_id' => $banner_id,
							'slides'             => $data['slides'] ?? [],
							'insert_position'    => $insert_position
						]
					];
				}

				return [
					'success' => true,
					'message' => IntentHelper::bannerSlidesMessage($operation, $insert_position),
					'data'    => $data,
					'ui'      => [
						'type'      => 'cards',
						'item_type' => 'slide',
						'items'     => array_map(fn($s) => [
							'id'      => $s['banner_image_id'],
							'title'   => $s['title'] ?: 'Slide',
							'preview' => $s['preview']
						], $data['slides'] ?? [])
					],
					'state_update' => [
						'step'               => 'slide_selected',
						'operation'          => $operation,
						'selected_banner_id' => $banner_id,
						'slides'             => $data['slides'] ?? []
					]
				];

			case 'replace_banner_image':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\BannerService($registry);
				return $service->replaceSlideImage(
					(int)($params['banner_id'] ?? $state['selected_banner_id'] ?? 0),
					(int)($params['banner_image_id'] ?? $state['selected_slide_id'] ?? 0),
					$params['image_path'] ?? $state['uploaded_image'] ?? ''
				);

			case 'delete_banner_slide':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\BannerService($registry);
				return $service->deleteSlide(
					(int)($params['banner_id'] ?? $state['selected_banner_id'] ?? 0),
					(int)($params['banner_image_id'] ?? $state['selected_slide_id'] ?? 0)
				);

			case 'delete_banner':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\BannerService($registry);
				return $service->deleteBanner((int)($params['banner_id'] ?? $state['selected_banner_id'] ?? 0));

			case 'add_banner_slide':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\BannerService($registry);
				$position = isset($params['position'])
					? (int)$params['position']
					: (isset($state['insert_position']) ? (int)$state['insert_position'] : -1);

				return $service->addSlide(
					(int)($params['banner_id'] ?? $state['selected_banner_id'] ?? 0),
					$params['image_path'] ?? $state['uploaded_image'] ?? '',
					$params['title'] ?? '',
					$params['link'] ?? '',
					0,
					$position
				);

			case 'search_products':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$products = $service->search($params['query'] ?? '');

				return [
					'success' => true,
					'message' => count($products) ? 'Select a product:' : 'No products found.',
					'data'    => $products,
					'ui'      => self::productCardsUi($products),
					'state_update' => ['step' => 'product_selected', 'products' => $products]
				];

			case 'list_products':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$operation = $params['operation'] ?? $state['operation'] ?? 'read';

				if (!empty($params['low_stock'])) {
					$products = $service->findLowStock((int)($params['threshold'] ?? 5));
					$message = count($products) ? 'Low-stock products:' : 'No low-stock products found.';
				} else {
					$products = $service->list([
						'query' => $params['query'] ?? '',
						'limit' => (int)($params['limit'] ?? 20)
					]);
					$message = count($products)
						? IntentHelper::productListMessage($operation)
						: 'No products found.';
				}

				return [
					'success' => true,
					'message' => $message,
					'data'    => $products,
					'ui'      => self::productCardsUi($products),
					'state_update' => [
						'step'      => 'product_selected',
						'operation' => $operation,
						'products'  => $products
					]
				];

			case 'get_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->get((int)($params['product_id'] ?? $state['selected_product_id'] ?? 0));

			case 'delete_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->delete((int)($params['product_id'] ?? $state['selected_product_id'] ?? 0));

			case 'duplicate_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->duplicate((int)($params['product_id'] ?? $state['selected_product_id'] ?? 0));

			case 'enable_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->setStatus((int)($params['product_id'] ?? $state['selected_product_id'] ?? 0), 1);

			case 'disable_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->setStatus((int)($params['product_id'] ?? $state['selected_product_id'] ?? 0), 0);

			case 'update_quantity':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->updateQuantity(
					(int)($params['product_id'] ?? $state['selected_product_id'] ?? 0),
					(int)($params['quantity'] ?? 0)
				);

			case 'update_special_price':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->updateSpecialPrice(
					(int)($params['product_id'] ?? $state['selected_product_id'] ?? 0),
					(float)($params['special'] ?? $params['price'] ?? 0)
				);

			case 'change_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$category_service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				$category_ids = $params['category_ids'] ?? $state['category_ids'] ?? [];

				if (!empty($params['category_name'])) {
					$category_id = $category_service->resolveId((string)$params['category_name']);

					if ($category_id) {
						$category_ids = [$category_id];
					}
				}

				return $service->changeCategory(
					(int)($params['product_id'] ?? $state['selected_product_id'] ?? 0),
					(array)$category_ids
				);

			case 'change_manufacturer':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->changeManufacturer(
					(int)($params['product_id'] ?? $state['selected_product_id'] ?? 0),
					(int)($params['manufacturer_id'] ?? 0)
				);

			case 'update_product_images':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->updateMainImage(
					(int)($params['product_id'] ?? $state['selected_product_id'] ?? 0),
					$params['image_path'] ?? $state['uploaded_image'] ?? ''
				);

			case 'replace_product_images':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->updateMainImage(
					(int)($params['product_id'] ?? $state['selected_product_id'] ?? 0),
					$params['image_path'] ?? $state['uploaded_image'] ?? ''
				);

			case 'update_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->update(array_merge($params, [
					'product_id' => (int)($params['product_id'] ?? $state['selected_product_id'] ?? 0)
				]));

			case 'export_products':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->exportCsv((int)($params['limit'] ?? 500));

			case 'low_stock_alerts':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$products = $service->findLowStock((int)($params['threshold'] ?? 5));

				return [
					'success' => true,
					'message' => count($products) ? count($products) . ' low-stock products found.' : 'No low-stock products.',
					'data'    => $products,
					'ui'      => self::productCardsUi($products)
				];

			case 'list_categories':
			case 'search_categories':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				$operation = $params['operation'] ?? $state['operation'] ?? 'read';
				$categories = $service->list([
					'query' => $params['query'] ?? '',
					'limit' => (int)($params['limit'] ?? 20)
				]);

				return [
					'success' => true,
					'message' => count($categories)
						? IntentHelper::categoryListMessage($operation)
						: 'No categories found.',
					'data'    => $categories,
					'ui'      => self::categoryCardsUi($categories),
					'state_update' => [
						'step'       => 'category_selected',
						'operation'  => $operation,
						'categories' => $categories
					]
				];

			case 'get_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->get((int)($params['category_id'] ?? $state['selected_category_id'] ?? 0));

			case 'edit_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->update(array_merge($params, [
					'category_id' => (int)($params['category_id'] ?? $state['selected_category_id'] ?? 0)
				]));

			case 'delete_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->delete((int)($params['category_id'] ?? $state['selected_category_id'] ?? 0));

			case 'parent_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				$parent_id = (int)($params['parent_id'] ?? 0);

				if (!empty($params['parent_name'])) {
					$parent_id = $service->resolveId((string)$params['parent_name']);
				}

				return $service->setParent(
					(int)($params['category_id'] ?? $state['selected_category_id'] ?? 0),
					$parent_id
				);

			case 'sort_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->setSortOrder(
					(int)($params['category_id'] ?? $state['selected_category_id'] ?? 0),
					(int)($params['sort_order'] ?? 0)
				);

			case 'category_image':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->updateImage(
					(int)($params['category_id'] ?? $state['selected_category_id'] ?? 0),
					$params['image_path'] ?? $state['uploaded_image'] ?? ''
				);

			case 'category_seo_url':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->updateSeoUrl(
					(int)($params['category_id'] ?? $state['selected_category_id'] ?? 0),
					(string)($params['seo_url'] ?? '')
				);

			case 'category_meta':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->updateMeta(
					(int)($params['category_id'] ?? $state['selected_category_id'] ?? 0),
					$params
				);

			case 'enable_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->setStatus(
					(int)($params['category_id'] ?? $state['selected_category_id'] ?? 0),
					(int)($params['status'] ?? 1)
				);

			case 'update_product_price':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->updatePrice(
					(int)($params['product_id'] ?? $state['selected_product_id'] ?? 0),
					(float)($params['price'] ?? 0),
					isset($params['special']) ? (float)$params['special'] : null
				);

			case 'create_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->create($params);

			case 'import_products_csv':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->importFromCsv($params['rows'] ?? $state['csv_rows'] ?? []);

			case 'create_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->create($params);

			case 'get_orders_today':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\OrderService($registry);
				$summary = $service->getTodaySummary();

				$message = "Today's Orders ({$summary['date']}):\n";
				$message .= "• Total: {$summary['total_orders']} orders\n";
				$message .= "• Revenue: " . number_format($summary['total_revenue'], 2) . "\n";

				foreach ($summary['summary'] as $status => $data) {
					$message .= "• " . ucfirst($status) . ": {$data['count']} (" . number_format($data['revenue'], 2) . ")\n";
				}

				return ['success' => true, 'message' => $message, 'data' => $summary];

			case 'search_customers':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CustomerService($registry);
				$customers = $service->search($params['query'] ?? '');

				return [
					'success' => true,
					'message' => count($customers) ? 'Customer results:' : 'No customers found.',
					'data'    => $customers,
					'ui'      => [
						'type'  => 'cards',
						'items' => array_map(fn($c) => [
							'id'    => $c['customer_id'],
							'title' => $c['firstname'] . ' ' . $c['lastname'],
							'meta'  => $c['email'] . ' | ' . ($c['status'] ? 'Active' : 'Blocked')
						], $customers)
					]
				];

			case 'create_coupon':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CouponService($registry);
				return $service->create($params);

			case 'update_settings':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\SettingsService($registry);
				return $service->updateStoreSettings($params);

			case 'update_logo':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\SettingsService($registry);
				return $service->updateLogo($params['image_path'] ?? $state['uploaded_image'] ?? '');

			case 'update_information':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\InformationService($registry);
				return $service->updatePage($params['title'] ?? '', $params['content'] ?? '');

			case 'bulk_price_update':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->bulkPriceUpdate(
					(float)($params['percentage'] ?? 0),
					$params['operation'] ?? 'increase'
				);

			case 'disable_out_of_stock':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->disableOutOfStock();

			case 'search_images':
				$processor = new \Opencart\System\Library\Extension\AiBuilder\Utils\ImageProcessor();
				$images = $processor->search($params['query'] ?? '');

				return [
					'success' => true,
					'message' => count($images) ? 'Found images:' : 'No images found.',
					'data'    => $images,
					'ui'      => [
						'type'  => 'cards',
						'items' => array_map(fn($i) => [
							'id'      => $i['path'],
							'title'   => $i['name'],
							'preview' => HTTP_CATALOG . $i['url']
						], $images)
					]
				];

			case 'products_without_images':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$products = $service->findWithoutImages();

				return [
					'success' => true,
					'message' => count($products) . ' products without images.',
					'data'    => $products
				];

			case 'validate_csv':
				$validator = new \Opencart\System\Library\Extension\AiBuilder\Utils\CsvValidator();
				$result = $validator->parse($params['filepath'] ?? $state['csv_filepath'] ?? '');

				if (!empty($result['error'])) {
					return $result;
				}

				return [
					'success' => true,
					'message' => "{$result['total']} Products Found\n{$result['valid']} Valid\n{$result['errors']} Errors",
					'data'    => $result,
					'ui'      => ['type' => 'progress', 'valid' => $result['valid'], 'errors' => $result['errors'], 'total' => $result['total']],
					'state_update' => ['csv_rows' => $result['rows'], 'csv_validated' => true]
				];

			default:
				return ['error' => 'Handler not found for action: ' . $action];
		}
	}

	private static function productCardsUi(array $products): array {
		return [
			'type'      => 'cards',
			'item_type' => 'product',
			'items'     => array_map(fn($product) => [
				'id'      => $product['id'],
				'title'   => $product['name'],
				'preview' => $product['preview'] ?? '',
				'meta'    => 'Price: ' . ($product['price'] ?? 0) . ' | Qty: ' . ($product['quantity'] ?? 0)
			], $products)
		];
	}

	private static function categoryCardsUi(array $categories): array {
		return [
			'type'      => 'cards',
			'item_type' => 'category',
			'items'     => array_map(fn($category) => [
				'id'      => $category['id'],
				'title'   => $category['name'],
				'preview' => $category['preview'] ?? '',
				'meta'    => ($category['status'] ?? 0) ? 'Enabled' : 'Disabled'
			], $categories)
		];
	}
}
