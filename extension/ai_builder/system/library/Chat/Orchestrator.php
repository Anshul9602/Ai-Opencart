<?php
namespace Opencart\System\Library\Extension\AiBuilder\Chat;

use Opencart\System\Library\Extension\AiBuilder\Ai\OpenAiClient;
use Opencart\System\Library\Extension\AiBuilder\Prompt\SystemPrompt;

class Orchestrator {
	private object $registry;
	private OpenAiClient $ai;
	private ActionExecutor $executor;

	public function __construct(object $registry, string $api_key, string $model = 'gpt-4o-mini', float $temperature = 0.3) {
		$this->registry = $registry;
		$this->ai = new OpenAiClient($api_key, $model, $temperature);
		$this->executor = new ActionExecutor($registry);
	}

	public function process(string $user_message, array $history, array $state = [], bool $confirmed = false): array {
		$start = microtime(true);

		$selection_result = $this->handleSelection($user_message, $state);

		if ($selection_result) {
			return $selection_result;
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

		if (!empty($ai_response['action']) && empty($ai_response['needs_input'])) {
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
			}
		} elseif (!empty($ai_response['needs_input'])) {
			$result['state'] = array_merge($state, [
				'pending_field' => $ai_response['pending_field'] ?? null,
				'intent'        => $ai_response['intent'] ?? '',
				'collected'     => array_merge($state['collected'] ?? [], $ai_response['params'] ?? [])
			]);
		}

		if (!empty($ai_response['ui']) && ($ai_response['ui']['type'] ?? '') === 'options') {
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

		if (($state['step'] ?? '') === 'slide_selected' || ($state['intent'] ?? '') === 'banner_replace') {
			$action_result = $this->executor->execute('replace_banner_image', [
				'image_path' => $result['path']
			], $new_state);

			return array_merge($action_result, ['state' => $new_state, 'preview' => HTTP_CATALOG . 'image/' . $result['path']]);
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

	private function handleSelection(string $message, array $state): ?array {
		$step = $state['step'] ?? '';

		if ($step === 'banner_selected' && !empty($state['banners'])) {
			foreach ($state['banners'] as $banner) {
				if (stripos($message, $banner['name']) !== false || $message === (string)$banner['id']) {
					return $this->executor->execute('get_banner_slides', ['banner_id' => $banner['id']], $state);
				}
			}
		}

		if ($step === 'slide_selected' && !empty($state['slides'])) {
			foreach ($state['slides'] as $slide) {
				if ($message === (string)$slide['banner_image_id'] || stripos($message, $slide['title']) !== false) {
					return [
						'message' => 'Please upload the new image.',
						'ui'      => ['type' => 'upload', 'accept' => 'image/*'],
						'state'   => array_merge($state, [
							'selected_slide_id' => $slide['banner_image_id'],
							'step' => 'awaiting_upload'
						])
					];
				}
			}
		}

		if ($step === 'product_selected' && !empty($state['products'])) {
			foreach ($state['products'] as $product) {
				if (stripos($message, $product['name']) !== false || $message === (string)$product['id']) {
					return [
						'message' => 'What is the new price?',
						'needs_input' => true,
						'pending_field' => 'price',
						'state'   => array_merge($state, [
							'selected_product_id' => $product['id'],
							'step' => 'awaiting_price'
						])
					];
				}
			}
		}

		if ($step === 'awaiting_price' && is_numeric($message)) {
			return $this->executor->execute('update_product_price', [
				'price' => (float)$message
			], $state);
		}

		if (($state['step'] ?? '') === 'csv_validated' && in_array(strtolower($message), ['import', 'yes', 'proceed', 'confirm'])) {
			return $this->executor->execute('import_products_csv', [], $state);
		}

		return null;
	}
}
