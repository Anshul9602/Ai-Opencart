<?php
namespace Opencart\System\Library\Extension\AiBuilder\Chat;

class ActionExecutor {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function execute(string $action, array $params, array $state = []): array {
		switch ($action) {
			case 'list_banners':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\BannerService($this->registry);
				$banners = $service->listBanners($params['search'] ?? '');

				return [
					'success' => true,
					'message' => count($banners) ? 'Here are your banners. Click one to select:' : 'No banners found.',
					'data'    => $banners,
					'ui'      => [
						'type'  => 'cards',
						'items' => array_map(fn($b) => [
							'id'      => $b['id'],
							'title'   => $b['name'],
							'preview' => $b['preview'],
							'meta'    => $b['slides'] . ' slides'
						], $banners)
					],
					'state_update' => ['step' => 'banner_selected', 'banners' => $banners]
				];

			case 'get_banner_slides':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\BannerService($this->registry);
				$data = $service->getBannerSlides((int)($params['banner_id'] ?? $state['selected_banner_id'] ?? 0));

				return [
					'success' => true,
					'message' => 'Select a slide to replace:',
					'data'    => $data,
					'ui'      => [
						'type'  => 'cards',
						'items' => array_map(fn($s) => [
							'id'      => $s['banner_image_id'],
							'title'   => $s['title'] ?: 'Slide',
							'preview' => $s['preview']
						], $data['slides'] ?? [])
					],
					'state_update' => [
						'step' => 'slide_selected',
						'selected_banner_id' => (int)($params['banner_id'] ?? 0),
						'slides' => $data['slides'] ?? []
					]
				];

			case 'replace_banner_image':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\BannerService($this->registry);
				return $service->replaceSlideImage(
					(int)($params['banner_id'] ?? $state['selected_banner_id'] ?? 0),
					(int)($params['banner_image_id'] ?? $state['selected_slide_id'] ?? 0),
					$params['image_path'] ?? $state['uploaded_image'] ?? ''
				);

			case 'search_products':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry);
				$products = $service->search($params['query'] ?? '');

				return [
					'success' => true,
					'message' => count($products) ? 'Select a product:' : 'No products found.',
					'data'    => $products,
					'ui'      => [
						'type'  => 'cards',
						'items' => array_map(fn($p) => [
							'id'      => $p['id'],
							'title'   => $p['name'],
							'preview' => $p['preview'],
							'meta'    => 'Price: ' . $p['price']
						], $products)
					],
					'state_update' => ['step' => 'product_selected', 'products' => $products]
				];

			case 'update_product_price':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry);
				return $service->updatePrice(
					(int)($params['product_id'] ?? $state['selected_product_id'] ?? 0),
					(float)($params['price'] ?? 0),
					isset($params['special']) ? (float)$params['special'] : null
				);

			case 'create_product':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry);
				return $service->create($params);

			case 'import_products_csv':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry);
				return $service->importFromCsv($params['rows'] ?? $state['csv_rows'] ?? []);

			case 'create_category':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($this->registry);
				return $service->create($params);

			case 'get_orders_today':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\OrderService($this->registry);
				$summary = $service->getTodaySummary();

				$message = "Today's Orders ({$summary['date']}):\n";
				$message .= "• Total: {$summary['total_orders']} orders\n";
				$message .= "• Revenue: " . number_format($summary['total_revenue'], 2) . "\n";

				foreach ($summary['summary'] as $status => $data) {
					$message .= "• " . ucfirst($status) . ": {$data['count']} (" . number_format($data['revenue'], 2) . ")\n";
				}

				return ['success' => true, 'message' => $message, 'data' => $summary];

			case 'search_customers':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CustomerService($this->registry);
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
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CouponService($this->registry);
				return $service->create($params);

			case 'update_settings':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\SettingsService($this->registry);
				return $service->updateStoreSettings($params);

			case 'update_logo':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\SettingsService($this->registry);
				return $service->updateLogo($params['image_path'] ?? $state['uploaded_image'] ?? '');

			case 'update_information':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\InformationService($this->registry);
				return $service->updatePage($params['title'] ?? '', $params['content'] ?? '');

			case 'bulk_price_update':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry);
				return $service->bulkPriceUpdate(
					(float)($params['percentage'] ?? 0),
					$params['operation'] ?? 'increase'
				);

			case 'disable_out_of_stock':
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry);
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
				$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry);
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
				return ['error' => 'Unknown action: ' . $action];
		}
	}
}
