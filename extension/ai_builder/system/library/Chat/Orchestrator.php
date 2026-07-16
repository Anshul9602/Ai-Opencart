<?php
namespace Opencart\System\Library\Extension\AiBuilder\Chat;

use Opencart\System\Library\Extension\AiBuilder\Ai\OpenAiClient;
use Opencart\System\Library\Extension\AiBuilder\Prompt\SystemPrompt;
use Opencart\System\Library\Extension\AiBuilder\Chat\IntentHelper;

class Orchestrator {
	private object $registry;
	private OpenAiClient $ai;
	private ActionExecutor $executor;

	public function __construct(object $registry, string $api_key, string $model = 'gpt-4o-mini', float $temperature = 0.3) {
		$this->registry = $registry;
		$this->ai = new OpenAiClient($api_key, $model, $temperature);
		$this->executor = new ActionExecutor($registry);
	}

	public function process(string $user_message, array $history, array $state = [], bool $confirmed = false, string $selection_id = '', string $selection_type = ''): array {
		$start = microtime(true);

		if ($selection_id !== '' && ctype_digit($selection_id)) {
			if ($selection_type === 'slide') {
				return $this->handleSlideSelection((int)$selection_id, $state, $start);
			}

			if ($selection_type === 'product') {
				return $this->handleProductSelection((int)$selection_id, $state, $start);
			}

			if ($selection_type === 'category') {
				return $this->handleCategorySelection((int)$selection_id, $state, $start);
			}

			if ($selection_type === 'banner' || ($state['step'] ?? '') === 'banner_selected') {
				$operation = $state['operation'] ?? 'update';

				if ($operation === 'delete' && IntentHelper::wantsDeleteEntireBanner($user_message)) {
					return $this->finalizeActionResult([
						'message' => 'Delete entire banner and all its slides?',
						'ui'      => [
							'type'    => 'confirm',
							'message' => 'This cannot be undone.',
							'action'  => 'delete_banner',
							'params'  => ['banner_id' => (int)$selection_id]
						],
						'needs_confirmation' => true,
						'state'   => array_merge($state, [
							'selected_banner_id' => (int)$selection_id,
							'operation'          => 'delete'
						])
					], $state, $start, 'banner_delete');
				}

				return $this->finalizeActionResult(
					$this->executor->execute('get_banner_slides', [
						'banner_id' => (int)$selection_id,
						'operation' => $operation
					], $state),
					$state,
					$start,
					'banner_slides'
				);
			}
		}

		$selection_result = $this->handleSelection($user_message, $state, $selection_id);

		if ($selection_result) {
			return $this->finalizeActionResult($selection_result, $state, $start);
		}

		$pre_action = $this->resolvePreAiAction($user_message, $state);

		if ($pre_action) {
			return $this->finalizeActionResult($pre_action, $state, $start, 'banner_list');
		}

		$messages = [
			['role' => 'system', 'content' => SystemPrompt::build() . SystemPrompt::buildContext($state)]
		];

		foreach ($history as $msg) {
			$messages[] = [
				'role'    => $msg['role'],
				'content' => $msg['content']
			];
		}

		$messages[] = ['role' => 'user', 'content' => $user_message];

		$ai_response = $this->ai->chat($messages);

		if (!empty($ai_response['error'])) {
			return [
				'message' => $ai_response['error'],
				'error'   => true,
				'execution_time' => microtime(true) - $start
			];
		}

		$result = [
			'message'        => $ai_response['message'] ?? 'I can help with that.',
			'intent'         => $ai_response['intent'] ?? 'conversation',
			'ui'             => $ai_response['ui'] ?? ['type' => 'text'],
			'needs_input'    => $ai_response['needs_input'] ?? false,
			'pending_field'  => $ai_response['pending_field'] ?? null,
			'destructive'    => $ai_response['destructive'] ?? false,
			'execution_time' => microtime(true) - $start,
			'state'          => $state
		];

		if (!empty($ai_response['destructive']) && !$confirmed) {
			$result['ui'] = [
				'type'    => 'confirm',
				'message' => $ai_response['message'],
				'action'  => $ai_response['action'] ?? null,
				'params'  => $ai_response['params'] ?? []
			];
			$result['needs_confirmation'] = true;
			$result['pending_action'] = $ai_response['action'] ?? null;
			$result['pending_params'] = $ai_response['params'] ?? [];

			return $result;
		}

		$action_executed = false;

		if (!empty($ai_response['action']) && empty($ai_response['needs_input'])) {
			$capability = $this->executor->getCapability($ai_response['action']);

			if ($capability && $capability->requires_confirmation && !$confirmed) {
				$result['ui'] = [
					'type'    => 'confirm',
					'message' => $ai_response['message'],
					'action'  => $ai_response['action'],
					'params'  => $ai_response['params'] ?? []
				];
				$result['needs_confirmation'] = true;
				$result['pending_action'] = $ai_response['action'];
				$result['pending_params'] = $ai_response['params'] ?? [];
				$result['destructive'] = true;

				return $result;
			}

			$action_result = $this->executor->execute(
				$ai_response['action'],
				$ai_response['params'] ?? [],
				$state
			);

			if (!empty($action_result['error'])) {
				$result['message'] = $action_result['error'];
				$result['error'] = true;
			} else {
				$result['message'] = $action_result['message'] ?? $result['message'];
				$result['data'] = $action_result['data'] ?? null;

				if (!empty($action_result['ui'])) {
					$result['ui'] = $action_result['ui'];
				}

				if (!empty($action_result['state_update'])) {
					$result['state'] = array_merge($state, $action_result['state_update']);
				}

				$action_executed = true;
			}
		} elseif (!empty($ai_response['needs_input'])) {
			$result['state'] = array_merge($state, [
				'pending_field' => $ai_response['pending_field'] ?? null,
				'intent'        => $ai_response['intent'] ?? '',
				'collected'     => array_merge($state['collected'] ?? [], $ai_response['params'] ?? [])
			]);
		}

		if (!$action_executed && !empty($ai_response['ui'])) {
			$result['ui'] = $ai_response['ui'];
		}

		return $result;
	}

	public function executeConfirmedAction(string $action, array $params, array $state = []): array {
		return $this->executor->execute($action, $params, $state);
	}

	public function handleUpload(array $file, array $state): array {
		$processor = new \Opencart\System\Library\Extension\AiBuilder\Utils\ImageProcessor();
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

		if (in_array($ext, ['csv', 'xlsx', 'xls'])) {
			$upload_dir = DIR_UPLOAD . 'ai_builder/';

			if (!is_dir($upload_dir)) {
				mkdir($upload_dir, 0755, true);
			}

			$filepath = $upload_dir . 'import_' . time() . '.' . $ext;

			if (!move_uploaded_file($file['tmp_name'], $filepath)) {
				return ['error' => 'Failed to save file'];
			}

			if ($ext === 'csv') {
				$validator = new \Opencart\System\Library\Extension\AiBuilder\Utils\CsvValidator();
				$result = $validator->parse($filepath);

				return [
					'success' => true,
					'message' => "{$result['valid']} valid products, {$result['errors']} errors found.",
					'type'    => 'csv',
					'data'    => $result,
					'ui'      => ['type' => 'progress', 'valid' => $result['valid'], 'errors' => $result['errors'], 'total' => $result['total']],
					'state'   => array_merge($state, ['csv_rows' => $result['rows'], 'csv_filepath' => $filepath, 'step' => 'csv_validated'])
				];
			}

			return ['error' => 'Excel import coming soon. Please use CSV format.'];
		}

		$result = $processor->upload($file, 'catalog');

		if (!empty($result['error'])) {
			return $result;
		}

		$new_state = array_merge($state, [
			'uploaded_image' => $result['path'],
			'step'           => 'image_uploaded'
		]);

		if ($this->shouldUpdateProductImage($state)) {
			$action_result = $this->executor->execute('update_product_images', [
				'image_path' => $result['path']
			], $new_state);

			if (!empty($action_result['error'])) {
				return array_merge($action_result, ['state' => $state]);
			}

			return array_merge($action_result, [
				'state'   => array_merge($new_state, ['step' => 'completed']),
				'preview' => HTTP_CATALOG . 'image/' . $result['path']
			]);
		}

		if ($this->shouldUpdateCategoryImage($state)) {
			$action_result = $this->executor->execute('category_image', [
				'image_path' => $result['path']
			], $new_state);

			if (!empty($action_result['error'])) {
				return array_merge($action_result, ['state' => $state]);
			}

			return array_merge($action_result, [
				'state'   => array_merge($new_state, ['step' => 'completed']),
				'preview' => HTTP_CATALOG . 'image/' . $result['path']
			]);
		}

		if ($this->shouldReplaceBannerSlide($state)) {
			$action_result = $this->executor->execute('replace_banner_image', [
				'image_path' => $result['path']
			], $new_state);

			if (!empty($action_result['error'])) {
				return array_merge($action_result, ['state' => $state]);
			}

			return array_merge($action_result, [
				'state'   => array_merge($new_state, ['step' => 'completed']),
				'preview' => HTTP_CATALOG . 'image/' . $result['path']
			]);
		}

		if ($this->shouldAddBannerSlide($state)) {
			$action_result = $this->executor->execute('add_banner_slide', [
				'image_path' => $result['path']
			], $new_state);

			if (!empty($action_result['error'])) {
				return array_merge($action_result, ['state' => $state]);
			}

			return array_merge($action_result, [
				'state'   => array_merge($new_state, ['step' => 'completed']),
				'preview' => HTTP_CATALOG . 'image/' . $result['path']
			]);
		}

		if (($state['intent'] ?? '') === 'settings_update' || ($state['step'] ?? '') === 'upload_logo') {
			$settings = new \Opencart\System\Library\Extension\AiBuilder\Services\SettingsService($this->registry);
			$action_result = $settings->updateLogo($result['path']);

			return array_merge($action_result, ['state' => $new_state, 'preview' => HTTP_CATALOG . 'image/' . $result['path']]);
		}

		return [
			'success' => true,
			'message' => 'Image uploaded successfully.',
			'preview' => HTTP_CATALOG . 'image/' . $result['path'],
			'state'   => $new_state
		];
	}

	private function handleSelection(string $message, array $state, string $selection_id = ''): ?array {
		if ($selection_id !== '') {
			$banner_match = $this->matchBannerSelection($selection_id, $message, $state);

			if ($banner_match) {
				return $this->executor->execute('get_banner_slides', ['banner_id' => $banner_match], $state);
			}

			if (ctype_digit($selection_id)) {
				return $this->executor->execute('get_banner_slides', ['banner_id' => (int)$selection_id], $state);
			}
		}

		$step = $state['step'] ?? '';
		$operation = $state['operation'] ?? '';

		if ($operation === 'create' && in_array($step, ['banner_selected', 'awaiting_upload'], true)) {
			$position = IntentHelper::parseInsertPosition($message);

			if ($position !== null) {
				$position_label = IntentHelper::positionLabel($position);

				if ($step === 'awaiting_upload' && !empty($state['selected_banner_id'])) {
					return [
						'success' => true,
						'message' => 'Updated — the new slide will be placed in the ' . $position_label . ' position. Upload your image:',
						'ui'      => ['type' => 'upload', 'accept' => 'image/*'],
						'state_update' => [
							'insert_position' => $position
						]
					];
				}

				if (!empty($state['banners'])) {
					return [
						'success' => true,
						'message' => 'Got it — the new slide will be placed in the ' . $position_label
							. ' position. Select which banner to add it to:',
						'ui'      => $this->buildBannerCardsUi($state['banners']),
						'state_update' => [
							'insert_position' => $position
						]
					];
				}
			}
		}

		if (!empty($state['banners'])) {
			foreach ($state['banners'] as $banner) {
				if (stripos($message, $banner['name']) !== false || $message === (string)$banner['id']) {
					return $this->executor->execute('get_banner_slides', ['banner_id' => (int)$banner['id']], $state);
				}
			}
		}

		if ($step === 'slide_selected' && !empty($state['slides'])) {
			foreach ($state['slides'] as $slide) {
				if ($message === (string)$slide['banner_image_id'] || stripos($message, $slide['title']) !== false) {
					return $this->buildSlideSelectionResponse((int)$slide['banner_image_id'], $state);
				}
			}
		}

		if ($step === 'product_selected' && !empty($state['products'])) {
			foreach ($state['products'] as $product) {
				if (stripos($message, $product['name']) !== false || $message === (string)$product['id']) {
					return $this->buildProductSelectionResponse((int)$product['id'], $state);
				}
			}
		}

		if ($step === 'category_selected' && !empty($state['categories'])) {
			foreach ($state['categories'] as $category) {
				if (stripos($message, $category['name']) !== false || $message === (string)$category['id']) {
					return $this->buildCategorySelectionResponse((int)$category['id'], $state);
				}
			}
		}

		if ($step === 'awaiting_price' && is_numeric($message)) {
			return $this->executor->execute('update_product_price', [
				'price' => (float)$message
			], $state);
		}

		if ($step === 'awaiting_quantity' && is_numeric($message)) {
			return $this->executor->execute('update_quantity', [
				'quantity' => (int)$message
			], $state);
		}

		if ($step === 'awaiting_special_price' && is_numeric($message)) {
			return $this->executor->execute('update_special_price', [
				'special' => (float)$message
			], $state);
		}

		if (($state['step'] ?? '') === 'csv_validated' && in_array(strtolower($message), ['import', 'yes', 'proceed', 'confirm'])) {
			return $this->executor->execute('import_products_csv', [], $state);
		}

		return null;
	}

	private function shouldReplaceBannerSlide(array $state): bool {
		if (($state['operation'] ?? 'update') !== 'update') {
			return false;
		}

		if (($state['intent'] ?? '') === 'banner_replace') {
			return !empty($state['selected_slide_id']) || !empty($state['selected_banner_id']);
		}

		if (empty($state['selected_slide_id']) || empty($state['selected_banner_id'])) {
			return false;
		}

		return in_array($state['step'] ?? '', ['awaiting_upload', 'slide_selected'], true);
	}

	private function shouldAddBannerSlide(array $state): bool {
		if (($state['operation'] ?? '') !== 'create') {
			return false;
		}

		return !empty($state['selected_banner_id'])
			&& in_array($state['step'] ?? '', ['awaiting_upload', 'slide_selected'], true);
	}

	private function handleProductSelection(int $product_id, array $state, float $start): array {
		return $this->finalizeActionResult(
			$this->buildProductSelectionResponse($product_id, $state),
			$state,
			$start,
			'product_action'
		);
	}

	private function handleCategorySelection(int $category_id, array $state, float $start): array {
		return $this->finalizeActionResult(
			$this->buildCategorySelectionResponse($category_id, $state),
			$state,
			$start,
			'category_action'
		);
	}

	private function buildProductSelectionResponse(int $product_id, array $state): array {
		$operation = $state['operation'] ?? 'update';
		$product_name = $this->findProductName($state, $product_id);
		$new_state = array_merge($state, [
			'selected_product_id' => $product_id,
			'step'                => $operation === 'delete' ? 'awaiting_delete_confirm' : 'product_action'
		]);

		if ($operation === 'delete') {
			return [
				'message' => 'Delete product "' . $product_name . '"?',
				'ui'      => [
					'type'    => 'confirm',
					'message' => 'This product will be permanently removed.',
					'action'  => 'delete_product',
					'params'  => ['product_id' => $product_id]
				],
				'needs_confirmation' => true,
				'state'              => $new_state
			];
		}

		if ($operation === 'read') {
			$result = $this->executor->execute('get_product', ['product_id' => $product_id], $state);
			$data = $result['data'] ?? [];

			return [
				'message' => 'Product: ' . ($data['name'] ?? $product_name)
					. ' | Price: ' . ($data['price'] ?? 0)
					. ' | Qty: ' . ($data['quantity'] ?? 0)
					. ' | Status: ' . (!empty($data['status']) ? 'Enabled' : 'Disabled'),
				'state'   => $new_state
			];
		}

		$pending_field = $state['pending_field'] ?? '';

		if ($pending_field === 'price') {
			return [
				'message'      => 'What is the new price for "' . $product_name . '"?',
				'needs_input'  => true,
				'pending_field'=> 'price',
				'state'        => array_merge($new_state, ['step' => 'awaiting_price'])
			];
		}

		if ($pending_field === 'quantity') {
			return [
				'message'      => 'What is the new quantity for "' . $product_name . '"?',
				'needs_input'  => true,
				'pending_field'=> 'quantity',
				'state'        => array_merge($new_state, ['step' => 'awaiting_quantity'])
			];
		}

		if ($pending_field === 'special') {
			return [
				'message'      => 'What is the new special/sale price for "' . $product_name . '"?',
				'needs_input'  => true,
				'pending_field'=> 'special',
				'state'        => array_merge($new_state, ['step' => 'awaiting_special_price'])
			];
		}

		if ($pending_field === 'image') {
			return [
				'message' => 'Upload the new image for "' . $product_name . '":',
				'ui'      => ['type' => 'upload', 'accept' => 'image/*'],
				'state'   => array_merge($new_state, ['step' => 'awaiting_product_image'])
			];
		}

		return [
			'message' => 'Product "' . $product_name . '" selected. You can update price, quantity, special price, image, category, enable/disable, duplicate, or delete.',
			'state'   => $new_state
		];
	}

	private function buildCategorySelectionResponse(int $category_id, array $state): array {
		$operation = $state['operation'] ?? 'update';
		$category_name = $this->findCategoryName($state, $category_id);
		$new_state = array_merge($state, [
			'selected_category_id' => $category_id,
			'step'                 => $operation === 'delete' ? 'awaiting_delete_confirm' : 'category_action'
		]);

		if ($operation === 'delete') {
			return [
				'message' => 'Delete category "' . $category_name . '"?',
				'ui'      => [
					'type'    => 'confirm',
					'message' => 'This category will be permanently removed.',
					'action'  => 'delete_category',
					'params'  => ['category_id' => $category_id]
				],
				'needs_confirmation' => true,
				'state'              => $new_state
			];
		}

		if ($operation === 'read') {
			$result = $this->executor->execute('get_category', ['category_id' => $category_id], $state);
			$data = $result['data'] ?? [];

			return [
				'message' => 'Category: ' . ($data['name'] ?? $category_name)
					. ' | Sort: ' . ($data['sort_order'] ?? 0)
					. ' | Status: ' . (!empty($data['status']) ? 'Enabled' : 'Disabled'),
				'state'   => $new_state
			];
		}

		if (($state['pending_field'] ?? '') === 'image') {
			return [
				'message' => 'Upload the new image for category "' . $category_name . '":',
				'ui'      => ['type' => 'upload', 'accept' => 'image/*'],
				'state'   => array_merge($new_state, ['step' => 'awaiting_category_image'])
			];
		}

		return [
			'message' => 'Category "' . $category_name . '" selected. You can edit name, parent, sort order, image, SEO, meta, enable/disable, or delete.',
			'state'   => $new_state
		];
	}

	private function findProductName(array $state, int $product_id): string {
		foreach ($state['products'] ?? [] as $product) {
			if ((int)($product['id'] ?? 0) === $product_id) {
				return $product['name'] ?: 'Product';
			}
		}

		return 'Product';
	}

	private function findCategoryName(array $state, int $category_id): string {
		foreach ($state['categories'] ?? [] as $category) {
			if ((int)($category['id'] ?? 0) === $category_id) {
				return $category['name'] ?: 'Category';
			}
		}

		return 'Category';
	}

	private function shouldUpdateProductImage(array $state): bool {
		return !empty($state['selected_product_id'])
			&& ($state['step'] ?? '') === 'awaiting_product_image';
	}

	private function shouldUpdateCategoryImage(array $state): bool {
		return !empty($state['selected_category_id'])
			&& ($state['step'] ?? '') === 'awaiting_category_image';
	}

	private function handleSlideSelection(int $slide_id, array $state, float $start): array {
		$response = $this->buildSlideSelectionResponse($slide_id, $state);

		return $this->finalizeActionResult($response, $state, $start, 'banner_action');
	}

	private function buildSlideSelectionResponse(int $slide_id, array $state): array {
		$operation = $state['operation'] ?? 'update';
		$slide_title = $this->findSlideTitle($state, $slide_id);
		$new_state = array_merge($state, [
			'selected_slide_id' => $slide_id,
			'step'              => $operation === 'delete' ? 'awaiting_delete_confirm' : 'awaiting_upload'
		]);

		if ($operation === 'delete') {
			return [
				'message' => 'Delete slide "' . $slide_title . '"?',
				'ui'      => [
					'type'    => 'confirm',
					'message' => 'This slide will be permanently removed.',
					'action'  => 'delete_banner_slide',
					'params'  => [
						'banner_id'       => (int)($state['selected_banner_id'] ?? 0),
						'banner_image_id' => $slide_id
					]
				],
				'needs_confirmation' => true,
				'state'              => $new_state
			];
		}

		if ($operation === 'create') {
			return [
				'message' => 'Upload an image for the new slide:',
				'ui'      => ['type' => 'upload', 'accept' => 'image/*'],
				'state'   => $new_state
			];
		}

		if ($operation === 'read') {
			return [
				'message' => 'Slide "' . $slide_title . '" details loaded. Say "update" or "delete" to modify it.',
				'state'   => $new_state
			];
		}

		return [
			'message' => 'Please upload the new image for "' . $slide_title . '":',
			'ui'      => ['type' => 'upload', 'accept' => 'image/*'],
			'state'   => $new_state
		];
	}

	private function findSlideTitle(array $state, int $slide_id): string {
		foreach ($state['slides'] ?? [] as $slide) {
			if ((int)($slide['banner_image_id'] ?? 0) === $slide_id) {
				return $slide['title'] ?: 'Slide';
			}
		}

		return 'Slide';
	}

	private function matchBannerSelection(string $selection_id, string $message, array $state): int {
		if (ctype_digit($selection_id)) {
			return (int)$selection_id;
		}

		if (!empty($state['banners'])) {
			foreach ($state['banners'] as $banner) {
				if ((string)$banner['id'] === $selection_id || strcasecmp($banner['name'], $message) === 0) {
					return (int)$banner['id'];
				}
			}
		}

		return 0;
	}

	private function finalizeActionResult(array $action_result, array $state, float $start, string $intent = 'action'): array {
		$result = [
			'message'        => $action_result['message'] ?? 'Done.',
			'intent'         => $action_result['intent'] ?? $intent,
			'ui'             => $action_result['ui'] ?? ['type' => 'text'],
			'execution_time' => microtime(true) - $start,
			'state'          => $state,
			'data'           => $action_result['data'] ?? null
		];

		if (!empty($action_result['error'])) {
			$result['error'] = true;
			$result['message'] = $action_result['error'];
		}

		if (!empty($action_result['state'])) {
			$result['state'] = $action_result['state'];
		} elseif (!empty($action_result['state_update'])) {
			$result['state'] = array_merge($state, $action_result['state_update']);
		}

		foreach (['needs_input', 'pending_field', 'needs_confirmation', 'pending_action', 'pending_params', 'preview'] as $field) {
			if (isset($action_result[$field])) {
				$result[$field] = $action_result[$field];
			}
		}

		return $result;
	}

	private function resolvePreAiAction(string $user_message, array $state): ?array {
		$step = $state['step'] ?? '';

		if (in_array($step, [
			'banner_selected', 'slide_selected', 'awaiting_upload',
			'product_selected', 'category_selected', 'product_action', 'category_action',
			'awaiting_price', 'awaiting_quantity', 'awaiting_special_price',
			'awaiting_product_image', 'awaiting_category_image'
		], true)) {
			return null;
		}

		if (!empty($state['banners'])) {
			foreach ($state['banners'] as $banner) {
				if (strcasecmp(trim($user_message), $banner['name']) === 0) {
					return null;
				}
			}
		}

		if (IntentHelper::isProductQuery($user_message) && !$this->isBannerQuery($user_message)) {
			$operation = IntentHelper::detectOperation($user_message, $state);
			$params = ['operation' => $operation];

			if (preg_match('/\b(low[\s-]?stock|out of stock)\b/i', $user_message)) {
				return $this->executor->execute('low_stock_alerts', $params, array_merge($state, ['operation' => 'read']));
			}

			if (preg_match('/\bexport\b/i', $user_message)) {
				return $this->executor->execute('export_products', $params, $state);
			}

			if (preg_match('/\bwithout images?\b/i', $user_message)) {
				return $this->executor->execute('products_without_images', $params, $state);
			}

			if (preg_match('/\bduplicate\b/i', $user_message)) {
				$params['operation'] = 'update';
			}

			$extra_state = ['operation' => $operation];

			if (preg_match('/\bprice\b/i', $user_message) && !preg_match('/\bspecial\b/i', $user_message)) {
				$extra_state['pending_field'] = 'price';
			} elseif (preg_match('/\b(special|sale)\s*price\b/i', $user_message)) {
				$extra_state['pending_field'] = 'special';
			} elseif (preg_match('/\b(quantity|stock)\b/i', $user_message)) {
				$extra_state['pending_field'] = 'quantity';
			} elseif (preg_match('/\b(image|photo)\b/i', $user_message)) {
				$extra_state['pending_field'] = 'image';
			}

			return $this->executor->execute('list_products', $params, array_merge($state, $extra_state));
		}

		if (IntentHelper::isCategoryQuery($user_message)) {
			$operation = IntentHelper::detectOperation($user_message, $state);

			return $this->executor->execute('list_categories', [
				'operation' => $operation
			], array_merge($state, ['operation' => $operation]));
		}

		if ($this->isBannerQuery($user_message)) {
			$operation = IntentHelper::detectOperation($user_message, $state);
			$insert_position = IntentHelper::parseInsertPosition($user_message);

			if ($insert_position === null && $operation === 'create') {
				$insert_position = (int)($state['insert_position'] ?? 0);
			}

			$params = ['operation' => $operation];

			if ($insert_position !== null) {
				$params['insert_position'] = $insert_position;
			}

			return $this->executor->execute('list_banners', $params, array_merge($state, [
				'operation' => $operation,
				'insert_position' => $insert_position ?? ($operation === 'create' ? 0 : null)
			]));
		}

		return null;
	}

	private function isBannerQuery(string $message): bool {
		return IntentHelper::isBannerQuery($message);
	}

	private function buildBannerCardsUi(array $banners): array {
		return [
			'type'      => 'cards',
			'item_type' => 'banner',
			'items'     => array_map(fn($banner) => [
				'id'      => $banner['id'],
				'title'   => $banner['name'],
				'preview' => $banner['preview'],
				'meta'    => $banner['slides'] . ' slides'
			], $banners)
		];
	}

	private function formatActionResult(array $action_result, array $state, float $start): array {
		return $this->finalizeActionResult($action_result, $state, $start, 'banner_list');
	}
}
