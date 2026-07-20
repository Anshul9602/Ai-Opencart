<?php
namespace Opencart\System\Library\Extension\AiBuilder\Chat;

use Opencart\System\Library\Extension\AiBuilder\Ai\OpenAiClient;
use Opencart\System\Library\Extension\AiBuilder\Prompt\SystemPrompt;
use Opencart\System\Library\Extension\AiBuilder\Chat\IntentHelper;

class Orchestrator {
	private object $registry;
	private OpenAiClient $ai;
	private ActionExecutor $executor;
	private EntityActionResolver $entityActions;
	private AdminActionResolver $adminActions;

	public function __construct(object $registry, string $api_key, string $model = 'gpt-4o-mini', float $temperature = 0.3) {
		$this->registry = $registry;
		$this->ai = new OpenAiClient($api_key, $model, $temperature);
		$this->executor = new ActionExecutor($registry);
		$this->entityActions = new EntityActionResolver($registry, $this->executor);
		$this->adminActions = new AdminActionResolver($registry, $this->executor);
	}

	public function process(string $user_message, array $history, array $state = [], bool $confirmed = false, string $selection_id = '', string $selection_type = ''): array {
		$start = microtime(true);
		$state['last_user_message'] = $user_message;

		$admin_action = $this->adminActions->tryResolve($user_message, $state);

		if ($admin_action) {
			return $this->finalizeActionResult($admin_action, $state, $start, 'admin_action');
		}

		$direct_action = $this->entityActions->tryResolve($user_message, $state);

		if ($direct_action) {
			return $this->finalizeActionResult($direct_action, $state, $start, 'entity_action');
		}

		$pending_input = $this->tryPendingInputAction($user_message, $state);

		if ($pending_input) {
			return $this->finalizeActionResult($pending_input, $state, $start, 'pending_input');
		}

		$display_change = $this->tryDisplayFormatChange($user_message, $state);

		if ($display_change) {
			return $this->finalizeActionResult($display_change, $state, $start, 'display_format');
		}

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

			if ($selection_type === 'order') {
				return $this->finalizeActionResult([
					'success'      => true,
					'message'      => 'Order #' . (int)$selection_id . ' selected. Say something like: change status to Processing',
					'ui'           => ['type' => 'text'],
					'state_update' => [
						'selected_order_id' => (int)$selection_id,
						'step'              => 'order_selected',
						'entity_type'       => 'order'
					]
				], $state, $start, 'order_selected');
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

		$ai_response = $this->normalizeAiResponse($ai_response);

		$forced_action = $this->executeDirectEntityActionFromAi($user_message, $ai_response, $state);

		if ($forced_action) {
			return $this->finalizeActionResult($forced_action, $state, $start, 'entity_action');
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
			$action = $ai_response['action'] ?? '';

			if (!$this->entityActions->isNonConfirmDirectAction($action)) {
				$result['ui'] = [
					'type'    => 'confirm',
					'message' => $ai_response['message'],
					'action'  => $action,
					'params'  => $ai_response['params'] ?? []
				];
				$result['needs_confirmation'] = true;
				$result['pending_action'] = $action;
				$result['pending_params'] = $ai_response['params'] ?? [];
				$result['state'] = array_merge($state, [
					'pending_action' => $action,
					'pending_params' => $ai_response['params'] ?? [],
					'pending_confirm_message' => $ai_response['message'] ?? ''
				]);

				return $result;
			}
		}

		$action_executed = false;

		if (!empty($ai_response['action']) && empty($ai_response['needs_input'])) {
			$capability = $this->executor->getCapability($ai_response['action']);

			if ($capability && $capability->requires_confirmation && !$confirmed && !$this->entityActions->isNonConfirmDirectAction($ai_response['action'])) {
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
			$result['state'] = array_merge($state, $this->buildNeedsInputState($ai_response, $state));
		}

		if (!$action_executed && !empty($ai_response['ui'])) {
			$ui = $ai_response['ui'];

			if (($ui['type'] ?? '') === 'confirm' && $this->entityActions->isDirectAction($ui['action'] ?? $ai_response['action'] ?? '')) {
				$retry = $this->executeDirectEntityActionFromAi($user_message, $ai_response, $state);

				if ($retry && empty($retry['error'])) {
					return $this->finalizeActionResult($retry, $state, $start, 'category_status');
				}
			}

			$result['ui'] = $ui;
		}

		return $result;
	}

	public function executeConfirmedAction(string $action, array $params, array $state = []): array {
		if ($this->entityActions->isDirectAction($action)) {
			$params = $this->entityActions->enrichParams($action, $params, $state, '', $state['pending_confirm_message'] ?? '');

			if ($this->entityActions->isNonConfirmDirectAction($action)) {
				return $this->executor->execute($action, $params, $state);
			}
		}

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
			$product_id = $this->resolveUploadProductId($state);
			$new_state = array_merge($state, [
				'uploaded_image'      => $result['path'],
				'selected_product_id' => $product_id,
				'step'                => 'image_uploaded'
			]);

			$action_result = $this->executor->execute('update_product_images', [
				'product_id' => $product_id,
				'image_path' => $result['path']
			], $new_state);

			if (!empty($action_result['error'])) {
				return array_merge($action_result, ['state' => $state]);
			}

			return array_merge($action_result, [
				'state'   => $this->clearCompletedImageUploadState(array_merge($new_state, ['step' => 'completed'])),
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
					if (IntentHelper::wantsProductImageUpdate($message)) {
						return $this->buildProductImageUploadResponse((int)$product['id'], $state);
					}

					return $this->buildProductSelectionResponse((int)$product['id'], $state);
				}
			}
		}

		if ($step === 'category_selected' && !empty($state['categories'])) {
			foreach ($state['categories'] as $category) {
				if (IntentHelper::matchesEntityName($category['name'] ?? '', $message) || $message === (string)$category['id']) {
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

		if (in_array($step, ['category_action', 'awaiting_category_name', 'product_action', 'awaiting_product_name'], true)) {
			$pending = $this->tryPendingInputAction($message, $state);

			if ($pending) {
				return $pending;
			}
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
		$user_message = trim((string)($state['last_user_message'] ?? ''));

		if (IntentHelper::wantsProductImageUpdate($user_message)) {
			return $this->finalizeActionResult(
				$this->buildProductImageUploadResponse($product_id, $state),
				$state,
				$start,
				'product_image'
			);
		}

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
			$new_state = array_merge($new_state, [
				'operation' => 'update',
				'step'      => 'product_action'
			]);
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
			return $this->buildProductImageUploadResponse($product_id, $state);
		}

		return [
			'message' => 'Product "' . $product_name . '" selected. You can update price, quantity, special price, image, category, enable/disable, duplicate, or delete.',
			'state_update' => $new_state
		];
	}

	private function buildProductImageUploadResponse(int $product_id, array $state): array {
		$prompt = (new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry))
			->buildImageUploadPrompt($product_id);

		if (!empty($prompt['error'])) {
			return [
				'message' => $prompt['error'],
				'error'   => true
			];
		}

		return $prompt;
	}

	private function resolveUploadProductId(array $state): int {
		$product_id = (int)($state['awaiting_product_image_for'] ?? $state['selected_product_id'] ?? 0);

		if ($product_id) {
			return $product_id;
		}

		if (!empty($state['target_product_name'])) {
			return (new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry))
				->resolveId((string)$state['target_product_name']);
		}

		return 0;
	}

	private function clearCompletedImageUploadState(array $state): array {
		unset(
			$state['awaiting_product_image_for'],
			$state['pending_field'],
			$state['pending_action'],
			$state['needs_input'],
			$state['current_product_image'],
			$state['target_product_name']
		);

		return $state;
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
		$product_id = $this->resolveUploadProductId($state);

		if (!$product_id) {
			return false;
		}

		if (!empty($state['awaiting_product_image_for'])) {
			return true;
		}

		if (($state['step'] ?? '') === 'awaiting_product_image') {
			return true;
		}

		if (($state['pending_action'] ?? '') === 'update_product_images') {
			return true;
		}

		return ($state['pending_field'] ?? '') === 'image'
			&& ($state['entity_type'] ?? '') === 'product';
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
				if (in_array($field, ['pending_field', 'pending_action', 'entity_type'], true)) {
					$result['state'][$field] = $action_result[$field];
				}
			}
		}

		if (!empty($action_result['needs_input'])) {
			$result['state']['needs_input'] = true;
		}

		if (!empty($action_result['state_update']['step'])) {
			$result['state']['step'] = $action_result['state_update']['step'];
		}

		return $result;
	}

	private function tryDisplayFormatChange(string $message, array $state): ?array {
		$format = IntentHelper::parseDisplayFormatPreference($message);

		if ($format === null) {
			return null;
		}

		$step = $state['step'] ?? '';
		$entity = IntentHelper::detectEntityType($message, $state);

		if ($entity === null && in_array($step, ['product_selected', 'product_action'], true)) {
			$entity = 'product';
		}

		if ($entity === null && in_array($step, ['category_selected', 'category_action'], true)) {
			$entity = 'category';
		}

		if ($entity === 'category' || (!empty($state['categories']) && $entity !== 'product')) {
			return $this->executor->execute('list_categories', [
				'operation'      => $state['operation'] ?? 'read',
				'display_format' => $format,
				'limit'          => 100
			], array_merge($state, [
				'operation'      => $state['operation'] ?? 'read',
				'display_format' => $format
			]));
		}

		if ($entity === 'product' || !empty($state['products'])) {
			return $this->executor->execute('list_products', [
				'operation'      => $state['operation'] ?? 'read',
				'display_format' => $format,
				'limit'          => 50
			], array_merge($state, [
				'operation'      => $state['operation'] ?? 'read',
				'display_format' => $format
			]));
		}

		if (($state['entity_type'] ?? '') === 'order' || !empty($state['orders']) || ($state['step'] ?? '') === 'order_selected') {
			$params = IntentHelper::parseOrderListParams($message, $state);
			$params['display_format'] = $format;

			return $this->executor->execute('view_orders', $params, array_merge($state, [
				'display_format' => $format
			]));
		}

		return null;
	}

	private function resolvePreAiAction(string $user_message, array $state): ?array {
		$step = $state['step'] ?? '';

		if (in_array($step, [
			'banner_selected', 'slide_selected', 'awaiting_upload',
			'product_selected', 'category_selected', 'product_action', 'category_action',
			'awaiting_price', 'awaiting_quantity', 'awaiting_special_price',
			'awaiting_product_image', 'awaiting_category_image',
			'awaiting_category_name', 'awaiting_product_name'
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
			$params = [
				'operation'      => $operation,
				'display_format' => IntentHelper::productDisplayFormat($user_message)
			];

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
			} elseif (preg_match('/\b(update|change|chanege|chnage|chane|set|replace)\b/i', $user_message) && preg_match('/\b(image|photo|picture)\b/i', $user_message)) {
				$extra_state['pending_field'] = 'image';
				$extra_state['operation'] = 'update';
			}

			$extra_state['display_format'] = $params['display_format'];

			return $this->executor->execute('list_products', $params, array_merge($state, $extra_state));
		}

		if (IntentHelper::isCategoryQuery($user_message)) {
			$operation = IntentHelper::detectOperation($user_message, $state);

			return $this->executor->execute('list_categories', [
				'operation'      => $operation,
				'display_format' => IntentHelper::categoryDisplayFormat($user_message),
				'limit'          => 100
			], array_merge($state, [
				'operation'      => $operation,
				'display_format' => IntentHelper::categoryDisplayFormat($user_message)
			]));
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

	private function normalizeAiResponse(array $ai_response): array {
		if (empty($ai_response['action']) && !empty($ai_response['ui']['action'])) {
			$ai_response['action'] = $ai_response['ui']['action'];
		}

		if (empty($ai_response['params']) && !empty($ai_response['ui']['params'])) {
			$ai_response['params'] = $ai_response['ui']['params'];
		}

		return $ai_response;
	}

	private function tryPendingInputAction(string $message, array $state): ?array {
		if ($this->isNewCommandMessage($message)) {
			return null;
		}

		$pending_field = $state['pending_field'] ?? '';
		$step = $state['step'] ?? '';
		$entity_type = $state['entity_type'] ?? '';
		$pending_action = $state['pending_action'] ?? '';
		$value = trim($message);

		if ($value === '') {
			return null;
		}

		$awaiting_steps = [
			'awaiting_category_name', 'awaiting_product_name',
			'awaiting_price', 'awaiting_quantity', 'awaiting_special_price'
		];

		$admin_pending = in_array($pending_action, ['search_customers', 'create_coupon', 'change_order_status'], true);

		if ($pending_field === '' && !in_array($step, array_merge($awaiting_steps, ['category_action', 'product_action']), true) && !$admin_pending) {
			return null;
		}

		if ($pending_action === 'search_customers' && in_array($pending_field, ['query', ''], true)) {
			return $this->executor->execute('search_customers', ['query' => $value], $state);
		}

		if ($pending_action === 'create_coupon') {
			$params = IntentHelper::parseCouponParams($value);

			if (empty($params['discount']) && empty($params['code'])) {
				if (is_numeric($value)) {
					$params = ['discount' => (float)$value, 'type' => 'P'];
				} else {
					return [
						'success' => true,
						'message' => 'Enter a discount amount, for example: 10% or 15.',
						'needs_input' => true,
						'pending_field' => 'discount',
						'pending_action' => 'create_coupon'
					];
				}
			}

			return $this->executor->execute('create_coupon', $params, $state);
		}

		if ($pending_action === 'change_order_status') {
			$params = IntentHelper::parseOrderStatusChange($value, $state);

			if (!$params) {
				if (preg_match('/^\d+$/', $value)) {
					$params = [
						'order_id' => (int)$value,
						'status'   => trim((string)($state['pending_status'] ?? ''))
					];
				} elseif (!empty($state['selected_order_id']) || (!empty($state['orders']) && count($state['orders']) === 1)) {
					$params = IntentHelper::parseOrderStatusChange('change status to ' . $value, $state);
				}
			}

			if ($params && !empty($params['order_id']) && !empty($params['status'])) {
				return $this->executor->execute('change_order_status', $params, $state);
			}

			if (!empty($params['order_id']) && empty($params['status'])) {
				return [
					'success' => true,
					'message' => 'What status should order #' . $params['order_id'] . ' be set to? (e.g. Processing, Shipped, Complete)',
					'needs_input' => true,
					'pending_field' => 'order_status',
					'pending_action' => 'change_order_status',
					'state_update' => [
						'selected_order_id' => (int)$params['order_id'],
						'pending_status'    => null
					]
				];
			}

			return [
				'success' => true,
				'message' => 'Example: change order 1 status to Processing',
				'needs_input' => true,
				'pending_field' => 'order_status',
				'pending_action' => 'change_order_status'
			];
		}

		if ($pending_action === 'create_category' && in_array($pending_field, ['name', 'category_name', 'new_name', ''], true)) {
			if (IntentHelper::isAffirmativeOnly($value)) {
				return [
					'success' => true,
					'message' => 'Please type the category name (for example: Electronics).',
					'needs_input' => true,
					'pending_field' => 'name'
				];
			}

			return $this->executor->execute('create_category', ['name' => $value], $state);
		}

		if ($pending_action === 'create_product' && in_array($pending_field, ['name', 'product_name', 'new_name', ''], true)) {
			if (IntentHelper::isAffirmativeOnly($value)) {
				return [
					'success' => true,
					'message' => 'Please type the product name (for example: iPhone 16).',
					'needs_input' => true,
					'pending_field' => 'name'
				];
			}

			return $this->executor->execute('create_product', ['name' => $value], $state);
		}

		$category_id = (int)($state['selected_category_id'] ?? 0);

		if (!$category_id) {
			$category_id = $this->resolvePendingCategoryId($state);
		}

		if (in_array($pending_field, ['name', 'category_name', 'new_name'], true)
			|| ($pending_field === '' && $step === 'awaiting_category_name' && $category_id)) {
			if (!$category_id) {
				return null;
			}

			return $this->completePendingCategoryEdit($category_id, ['name' => $value], $state);
		}

		if ($category_id && $pending_field === 'sort_order' && is_numeric($value)) {
			return $this->completePendingCategoryEdit($category_id, ['sort_order' => (int)$value], $state);
		}

		if ($category_id && in_array($pending_field, ['parent', 'parent_name', 'parent_id'], true)) {
			$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($this->registry);
			$parent_id = is_numeric($value) ? (int)$value : $service->resolveId($value);

			return $this->executor->execute('parent_category', [
				'category_id' => $category_id,
				'parent_id'   => $parent_id
			], $state);
		}

		if ($category_id && $pending_field === 'seo_url') {
			return $this->completePendingCategoryEdit($category_id, ['seo_url' => $value], $state);
		}

		$product_id = (int)($state['selected_product_id'] ?? 0);

		if (!$product_id) {
			$product_id = $this->resolvePendingProductId($state);
		}

		if ($product_id && in_array($pending_field, ['name', 'product_name', 'new_name'], true)) {
			return $this->completePendingProductEdit($product_id, ['name' => $value], $state);
		}

		if ($product_id && in_array($pending_field, ['price'], true) && is_numeric($value)) {
			return $this->executor->execute('update_product_price', [
				'product_id' => $product_id,
				'price'      => (float)$value
			], $state);
		}

		if ($product_id && in_array($pending_field, ['quantity', 'stock'], true) && is_numeric($value)) {
			return $this->executor->execute('update_quantity', [
				'product_id' => $product_id,
				'quantity'   => (int)$value
			], $state);
		}

		if ($product_id && in_array($pending_field, ['special', 'special_price'], true) && is_numeric($value)) {
			return $this->executor->execute('update_special_price', [
				'product_id' => $product_id,
				'special'    => (float)$value
			], $state);
		}

		if ($step === 'awaiting_price' && is_numeric($value)) {
			return $this->executor->execute('update_product_price', ['price' => (float)$value], $state);
		}

		if ($step === 'awaiting_quantity' && is_numeric($value)) {
			return $this->executor->execute('update_quantity', ['quantity' => (int)$value], $state);
		}

		if ($step === 'awaiting_special_price' && is_numeric($value)) {
			return $this->executor->execute('update_special_price', ['special' => (float)$value], $state);
		}

		return null;
	}

	private function completePendingProductEdit(int $product_id, array $changes, array $state): array {
		$result = $this->executor->execute('update_product', array_merge($changes, [
			'product_id' => $product_id
		]), $state);

		if (empty($result['error'])) {
			$result['state_update'] = array_merge($result['state_update'] ?? [], [
				'selected_product_id' => $product_id,
				'pending_field'       => null,
				'step'                => 'product_action'
			]);
		}

		return $result;
	}

	private function resolvePendingProductId(array $state): int {
		$product_id = (int)($state['selected_product_id'] ?? 0);

		if ($product_id) {
			return $product_id;
		}

		$collected = $state['collected'] ?? [];
		$product_id = (int)($collected['product_id'] ?? 0);

		if ($product_id) {
			return $product_id;
		}

		foreach ([
			$collected['product_name'] ?? '',
			$collected['name'] ?? '',
			$state['pending_confirm_message'] ?? '',
			$state['last_user_message'] ?? ''
		] as $text) {
			$name = IntentHelper::extractProductName((string)$text);

			if ($name === '') {
				continue;
			}

			$product_id = IntentHelper::findProductId($state['products'] ?? [], $name);

			if ($product_id) {
				return $product_id;
			}

			$service = new \Opencart\System\Library\Extension\AiBuilder\Services\ProductService($this->registry);

			$product_id = $service->resolveId($name);

			if ($product_id) {
				return $product_id;
			}
		}

		return 0;
	}

	private function executeDirectEntityActionFromAi(string $user_message, array $ai_response, array $state): ?array {
		if (!empty($ai_response['needs_input'])) {
			return null;
		}

		$action = $ai_response['action'] ?? '';

		if (!$action || !$this->entityActions->isDirectAction($action)) {
			return null;
		}

		if (!$this->entityActions->isNonConfirmDirectAction($action)) {
			return null;
		}

		$params = $this->entityActions->enrichParams(
			$action,
			$ai_response['params'] ?? [],
			$state,
			$user_message,
			$ai_response['message'] ?? ''
		);

		return $this->executor->execute($action, $params, $state);
	}

	private function completePendingCategoryEdit(int $category_id, array $changes, array $state): array {
		$result = $this->executor->execute('edit_category', array_merge($changes, [
			'category_id' => $category_id
		]), $state);

		if (empty($result['error'])) {
			$result['state_update'] = array_merge($result['state_update'] ?? [], [
				'selected_category_id' => $category_id,
				'pending_field'        => null,
				'step'                 => 'category_action'
			]);
		}

		return $result;
	}

	private function buildNeedsInputState(array $ai_response, array $state): array {
		$pending_field = $ai_response['pending_field'] ?? null;
		$action = $ai_response['action'] ?? '';
		$message = $ai_response['message'] ?? '';

		if (!$pending_field && preg_match('/\bnew\s+name\b/i', $message)) {
			$pending_field = 'name';
		}

		if (!$pending_field && preg_match('/\b(?:price|quantity|stock|special)\b/i', $message)) {
			if (preg_match('/\bprice\b/i', $message)) {
				$pending_field = 'price';
			} elseif (preg_match('/\b(?:quantity|stock)\b/i', $message)) {
				$pending_field = 'quantity';
			} elseif (preg_match('/\bspecial\b/i', $message)) {
				$pending_field = 'special';
			}
		}

		$updates = [
			'pending_field'  => $pending_field,
			'pending_action' => $action ?: ($state['pending_action'] ?? ''),
			'intent'         => $ai_response['intent'] ?? '',
			'collected'      => array_merge($state['collected'] ?? [], $ai_response['params'] ?? []),
			'pending_confirm_message' => $message
		];

		$params = $ai_response['params'] ?? [];
		$entity = IntentHelper::detectEntityType($message, $state) ?? ($state['entity_type'] ?? '');

		if ($entity === '' && str_contains($action, 'category')) {
			$entity = 'category';
		}

		if ($entity === '' && str_contains($action, 'product')) {
			$entity = 'product';
		}

		if ($entity !== '') {
			$updates['entity_type'] = $entity;
		}

		$category_id = (int)($params['category_id'] ?? $state['selected_category_id'] ?? 0);

		if (!$category_id) {
			$category_id = $this->resolvePendingCategoryId(array_merge($state, [
				'collected' => $updates['collected'],
				'pending_confirm_message' => $message
			]));
		}

		if ($category_id) {
			$updates['selected_category_id'] = $category_id;
		}

		$product_id = (int)($params['product_id'] ?? $state['selected_product_id'] ?? 0);

		if (!$product_id) {
			$product_id = $this->resolvePendingProductId(array_merge($state, [
				'collected' => $updates['collected'],
				'pending_confirm_message' => $message
			]));
		}

		if ($product_id) {
			$updates['selected_product_id'] = $product_id;
		}

		if (in_array($pending_field, ['name', 'category_name', 'new_name'], true) && ($entity === 'category' || $category_id)) {
			$updates['step'] = 'awaiting_category_name';
		}

		if (in_array($pending_field, ['name', 'product_name', 'new_name'], true) && ($entity === 'product' || $product_id)) {
			$updates['step'] = 'awaiting_product_name';
		}

		if ($pending_field === 'price') {
			$updates['step'] = 'awaiting_price';
		}

		if ($pending_field === 'quantity') {
			$updates['step'] = 'awaiting_quantity';
		}

		if ($pending_field === 'special') {
			$updates['step'] = 'awaiting_special_price';
		}

		return $updates;
	}

	private function resolvePendingCategoryId(array $state): int {
		$category_id = (int)($state['selected_category_id'] ?? 0);

		if ($category_id) {
			return $category_id;
		}

		$collected = $state['collected'] ?? [];
		$category_id = (int)($collected['category_id'] ?? 0);

		if ($category_id) {
			return $category_id;
		}

		foreach ([
			$collected['category_name'] ?? '',
			$collected['name'] ?? '',
			$state['pending_confirm_message'] ?? '',
			$state['last_user_message'] ?? ''
		] as $text) {
			$name = IntentHelper::extractCategoryName((string)$text);

			if ($name === '') {
				continue;
			}

			$category_id = IntentHelper::findCategoryId($state['categories'] ?? [], $name);

			if ($category_id) {
				return $category_id;
			}

			$service = new \Opencart\System\Library\Extension\AiBuilder\Services\CategoryService($this->registry);

			$category_id = $service->resolveId($name);

			if ($category_id) {
				return $category_id;
			}
		}

		return 0;
	}

	private function isNewCommandMessage(string $message): bool {
		$entity = IntentHelper::detectEntityType($message, []);

		if (IntentHelper::parseDisplayFormatPreference($message) !== null) {
			return false;
		}

		if ($entity && IntentHelper::detectEntityAction($message, $entity, [])) {
			return true;
		}

		if (IntentHelper::isBannerQuery($message)) {
			return true;
		}

		return false;
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
