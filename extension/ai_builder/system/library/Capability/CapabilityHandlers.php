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
				$format = self::resolveProductDisplayFormat($params, $state);

				return [
					'success' => true,
					'message' => count($products) ? 'Select a product:' : 'No products found.',
					'data'    => $products,
					'ui'      => self::productListUi($products, $format),
					'state_update' => ['step' => 'product_selected', 'products' => $products, 'display_format' => $format]
				];

			case 'list_products':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$operation = $params['operation'] ?? $state['operation'] ?? 'read';
				$format = self::resolveProductDisplayFormat($params, $state);

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
					'ui'      => self::productListUi($products, $format),
					'state_update' => [
						'step'            => 'product_selected',
						'operation'       => $operation,
						'products'        => $products,
						'display_format'  => $format
					]
				];

			case 'get_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->get((int)($params['product_id'] ?? $state['selected_product_id'] ?? 0));

			case 'delete_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$product_id = self::resolveProductId($service, $params, $state);

				if (!$product_id) {
					return ['error' => 'Product not found. Please check the name and try again.'];
				}

				return $service->delete($product_id);

			case 'duplicate_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$product_id = self::resolveProductId($service, $params, $state);

				if (!$product_id) {
					return ['error' => 'Product not found. Please check the name and try again.'];
				}

				return $service->duplicate($product_id);

			case 'enable_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$product_id = self::resolveProductId($service, $params, $state);

				if (!$product_id) {
					return ['error' => 'Product not found. Please check the name and try again.'];
				}

				return $service->setStatus($product_id, 1);

			case 'disable_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$product_id = self::resolveProductId($service, $params, $state);

				if (!$product_id) {
					return ['error' => 'Product not found. Please check the name and try again.'];
				}

				return $service->setStatus($product_id, 0);

			case 'update_quantity':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$product_id = self::resolveProductId($service, $params, $state);

				if (!$product_id) {
					return ['error' => 'Product not found. Please check the name and try again.'];
				}

				return $service->updateQuantity(
					$product_id,
					(int)($params['quantity'] ?? 0)
				);

			case 'update_special_price':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$product_id = self::resolveProductId($service, $params, $state);

				if (!$product_id) {
					return ['error' => 'Product not found. Please check the name and try again.'];
				}

				return $service->updateSpecialPrice(
					$product_id,
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

				if (!empty($params['new_name']) && empty($params['name'])) {
					$params['name'] = $params['new_name'];
				}

				$product_id = self::resolveProductId($service, $params, $state);

				if (!$product_id) {
					return ['error' => 'Product not found. Please check the name and try again.'];
				}

				return $service->update(array_merge($params, [
					'product_id' => $product_id
				]));

			case 'export_products':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				return $service->exportCsv((int)($params['limit'] ?? 500));

			case 'low_stock_alerts':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$products = $service->findLowStock((int)($params['threshold'] ?? 5));
				$format = self::resolveProductDisplayFormat($params, $state);

				return [
					'success' => true,
					'message' => count($products) ? count($products) . ' low-stock products found.' : 'No low-stock products.',
					'data'    => $products,
					'ui'      => self::productListUi($products, $format)
				];

			case 'list_categories':
			case 'search_categories':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				$operation = $params['operation'] ?? $state['operation'] ?? 'read';
				$format = self::resolveCategoryDisplayFormat($params, $state);
				$categories = $service->list([
					'query' => $params['query'] ?? '',
					'limit' => (int)($params['limit'] ?? 100)
				]);

				return [
					'success' => true,
					'message' => count($categories)
						? IntentHelper::categoryListMessage($operation)
						: 'No categories found.',
					'data'    => $categories,
					'ui'      => self::categoryListUi($categories, $format),
					'state_update' => [
						'step'           => 'category_selected',
						'operation'      => $operation,
						'categories'     => $categories,
						'display_format' => $format
					]
				];

			case 'get_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				return $service->get((int)($params['category_id'] ?? $state['selected_category_id'] ?? 0));

			case 'edit_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);

				if (!empty($params['new_name']) && empty($params['name'])) {
					$params['name'] = $params['new_name'];
				}

				$category_id = (int)($params['category_id'] ?? $state['selected_category_id'] ?? 0);

				if (!$category_id) {
					$category_id = self::resolveCategoryId($service, $params, $state);
				}

				return $service->update(array_merge($params, [
					'category_id' => $category_id
				]));

			case 'delete_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				$category_id = self::resolveCategoryId($service, $params, $state);

				if (!$category_id) {
					return ['error' => 'Category not found. Please check the name and try again.'];
				}

				return $service->delete($category_id);

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
				$category_id = self::resolveCategoryId($service, $params, $state);

				if (!$category_id) {
					return ['error' => 'Category not found. Please check the name and try again.'];
				}

				return $service->setStatus($category_id, 1);

			case 'disable_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($registry);
				$category_id = self::resolveCategoryId($service, $params, $state);

				if (!$category_id) {
					return ['error' => 'Category not found. Please check the name and try again.'];
				}

				return $service->setStatus($category_id, 0);

			case 'update_product_price':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($registry);
				$product_id = self::resolveProductId($service, $params, $state);

				if (!$product_id) {
					return ['error' => 'Product not found. Please check the name and try again.'];
				}

				return $service->updatePrice(
					$product_id,
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

			case 'view_orders':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\OrderService($registry);
				$format = self::resolveOrderDisplayFormat($params, $state);
				$list_params = [
					'query' => $params['query'] ?? '',
					'limit' => (int)($params['limit'] ?? 50),
					'date'  => $params['date'] ?? ''
				];

				if (!empty($params['today'])) {
					$list_params['date'] = 'today';
				}

				$orders = $service->list($list_params);
				$scope = $list_params['date'] === 'today' ? "today's " : '';
				$selected_order_id = count($orders) === 1
					? (int)($orders[0]['id'] ?? 0)
					: (int)($state['selected_order_id'] ?? 0);

				if ($list_params['query'] !== '' && ctype_digit($list_params['query'])) {
					$selected_order_id = (int)$list_params['query'];
				}

				return [
					'success' => true,
					'message' => count($orders)
						? 'Order details (' . $scope . count($orders) . ' orders):'
						: 'No orders found.',
					'data'    => $orders,
					'ui'      => self::orderListUi($orders, $format),
					'state_update' => [
						'step'              => 'order_selected',
						'entity_type'       => 'order',
						'orders'            => $orders,
						'display_format'    => $format,
						'selected_order_id' => $selected_order_id ?: null
					]
				];

			case 'change_order_status':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\OrderService($registry);
				$order_id = (int)($params['order_id'] ?? $state['selected_order_id'] ?? 0);
				$status = trim((string)($params['status'] ?? $params['status_name'] ?? $params['order_status'] ?? ''));

				if (!$order_id || $status === '') {
					return ['error' => 'Order ID and new status are required. Example: change order 1 status to Processing'];
				}

				$result = $service->changeStatus($order_id, $status);

				if (!empty($result['error'])) {
					return $result;
				}

				$orders = $service->list(['query' => (string)$order_id, 'limit' => 1]);
				$result['data'] = $orders;
				$result['ui'] = self::orderListUi($orders, 'table');
				$result['state_update'] = [
					'step'              => 'order_selected',
					'entity_type'       => 'order',
					'selected_order_id' => $order_id,
					'orders'            => $orders,
					'display_format'    => 'table'
				];

				return $result;

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

			case 'admin_model_call':
				$bridge = new \Opencart\System\Library\Extension\AiBuilder\Admin\AdminBridge($registry);

				return $bridge->call(
					(string)($params['route'] ?? ''),
					(string)($params['method'] ?? ''),
					$params['args'] ?? []
				);

			case 'catalog_model_call':
				$bridge = new \Opencart\System\Library\Extension\AiBuilder\Admin\AdminBridge($registry);

				return $bridge->callCatalogModel(
					(string)($params['route'] ?? ''),
					(string)($params['method'] ?? ''),
					$params['args'] ?? []
				);

			case 'list_admin_modules':
				$bridge = new \Opencart\System\Library\Extension\AiBuilder\Admin\AdminBridge($registry);

				return $bridge->listModules();

			default:
				return ['error' => 'Handler not found for action: ' . $action];
		}
	}

	private static function resolveCategoryId(\Opencart\System\Library\Extension\AiBuilder\Services\CategoryService $service, array $params, array $state): int {
		$category_id = (int)($params['category_id'] ?? $state['selected_category_id'] ?? 0);

		if ($category_id) {
			return $category_id;
		}

		$name = trim((string)($params['category_name'] ?? $params['name'] ?? ''));

		if ($name === '' && !empty($state['pending_params'])) {
			$name = trim((string)($state['pending_params']['category_name'] ?? $state['pending_params']['name'] ?? ''));
		}

		if ($name) {
			$category_id = IntentHelper::findCategoryId($state['categories'] ?? [], $name);

			if ($category_id) {
				return $category_id;
			}

			$category_id = $service->resolveId($name);

			if ($category_id) {
				return $category_id;
			}

			$leaf = preg_replace('/^.*>\s*/', '', $name) ?: $name;

			if ($leaf !== $name) {
				$category_id = IntentHelper::findCategoryId($state['categories'] ?? [], $leaf);

				if ($category_id) {
					return $category_id;
				}

				return $service->resolveId($leaf);
			}
		}

		return 0;
	}

	private static function resolveProductId(\Opencart\System\Library\Extension\AiBuilder\Services\ProductService $service, array $params, array $state): int {
		$product_id = (int)($params['product_id'] ?? $state['selected_product_id'] ?? 0);

		if ($product_id) {
			return $product_id;
		}

		$name = trim((string)($params['product_name'] ?? $params['name'] ?? ''));

		if ($name === '' && !empty($state['pending_params'])) {
			$name = trim((string)($state['pending_params']['product_name'] ?? $state['pending_params']['name'] ?? ''));
		}

		if ($name) {
			$product_id = IntentHelper::findProductId($state['products'] ?? [], $name);

			if ($product_id) {
				return $product_id;
			}

			return $service->resolveId($name);
		}

		return 0;
	}

	private static function resolveProductDisplayFormat(array $params, array $state): string {
		$format = $params['display_format'] ?? $state['display_format'] ?? 'cards';

		return in_array($format, ['table', 'cards'], true) ? $format : 'cards';
	}

	private static function productListUi(array $products, string $format = 'cards'): array {
		if ($format === 'table') {
			return self::productTableUi($products);
		}

		return self::productCardsUi($products);
	}

	private static function productTableUi(array $products): array {
		return [
			'type'      => 'table',
			'item_type' => 'product',
			'columns'   => [
				['key' => 'id', 'label' => 'ID'],
				['key' => 'name', 'label' => 'Name'],
				['key' => 'model', 'label' => 'Model'],
				['key' => 'price', 'label' => 'Price'],
				['key' => 'quantity', 'label' => 'Qty'],
				['key' => 'status', 'label' => 'Status']
			],
			'items'     => array_map(fn($product) => [
				'id'       => $product['id'],
				'name'     => self::displayText($product['name']),
				'model'    => self::displayText($product['model'] ?? ''),
				'price'    => number_format((float)($product['price'] ?? 0), 2),
				'quantity' => (int)($product['quantity'] ?? 0),
				'status'   => !empty($product['status']) ? 'Enabled' : 'Disabled'
			], $products)
		];
	}

	private static function productCardsUi(array $products): array {
		return [
			'type'      => 'cards',
			'item_type' => 'product',
			'items'     => array_map(fn($product) => [
				'id'      => $product['id'],
				'title'   => self::displayText($product['name']),
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
				'title'   => self::displayText($category['name']),
				'preview' => $category['preview'] ?? '',
				'meta'    => ($category['status'] ?? 0) ? 'Enabled' : 'Disabled'
			], $categories)
		];
	}

	private static function resolveCategoryDisplayFormat(array $params, array $state): string {
		$format = $params['display_format'] ?? $state['display_format'] ?? 'table';

		return in_array($format, ['table', 'cards'], true) ? $format : 'table';
	}

	private static function categoryListUi(array $categories, string $format = 'table'): array {
		if ($format === 'cards') {
			return self::categoryCardsUi($categories);
		}

		return self::categoryTableUi($categories);
	}

	private static function categoryTableUi(array $categories): array {
		return [
			'type'      => 'table',
			'item_type' => 'category',
			'columns'   => [
				['key' => 'id', 'label' => 'ID'],
				['key' => 'name', 'label' => 'Category'],
				['key' => 'status', 'label' => 'Status'],
				['key' => 'sort_order', 'label' => 'Sort']
			],
			'items'     => array_map(fn($category) => [
				'id'         => $category['id'],
				'name'       => self::displayText($category['name']),
				'status'     => !empty($category['status']) ? 'Enabled' : 'Disabled',
				'sort_order' => (int)($category['sort_order'] ?? 0)
			], $categories)
		];
	}

	private static function resolveOrderDisplayFormat(array $params, array $state): string {
		$format = $params['display_format'] ?? $state['display_format'] ?? 'table';

		return in_array($format, ['table', 'cards'], true) ? $format : 'table';
	}

	private static function orderListUi(array $orders, string $format = 'table'): array {
		if ($format === 'cards') {
			return self::orderCardsUi($orders);
		}

		return self::orderTableUi($orders);
	}

	private static function orderTableUi(array $orders): array {
		return [
			'type'      => 'table',
			'item_type' => 'order',
			'columns'   => [
				['key' => 'id', 'label' => 'Order ID'],
				['key' => 'customer', 'label' => 'Customer'],
				['key' => 'email', 'label' => 'Email'],
				['key' => 'total', 'label' => 'Total'],
				['key' => 'status', 'label' => 'Status'],
				['key' => 'payment', 'label' => 'Payment'],
				['key' => 'date', 'label' => 'Date']
			],
			'items'     => array_map(fn(array $order): array => [
				'id'       => $order['id'],
				'customer' => self::displayText($order['customer'] ?? ''),
				'email'    => self::displayText($order['email'] ?? ''),
				'total'    => number_format((float)($order['total'] ?? 0), 2) . ' ' . ($order['currency'] ?? ''),
				'status'   => self::displayText($order['status'] ?? ''),
				'payment'  => self::formatOrderMethodLabel($order['payment'] ?? ''),
				'date'     => self::displayText($order['date'] ?? '')
			], $orders)
		];
	}

	private static function formatOrderMethodLabel(string $value): string {
		if ($value === '') {
			return '';
		}

		$decoded = json_decode($value, true);

		if (is_array($decoded)) {
			return self::displayText((string)($decoded['name'] ?? $decoded['code'] ?? $value));
		}

		return self::displayText($value);
	}

	private static function orderCardsUi(array $orders): array {
		return [
			'type'      => 'cards',
			'item_type' => 'order',
			'items'     => array_map(fn(array $order): array => [
				'id'    => $order['id'],
				'title' => 'Order #' . $order['id'] . ' — ' . self::displayText($order['customer'] ?? ''),
				'meta'  => number_format((float)($order['total'] ?? 0), 2) . ' | '
					. self::displayText($order['status'] ?? '') . ' | ' . self::displayText($order['date'] ?? '')
			], $orders)
		];
	}

	private static function displayText(string $text): string {
		return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}
