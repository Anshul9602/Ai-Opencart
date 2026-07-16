<?php
namespace Opencart\Admin\Controller\Extension\AiBuilder\Other;

use Opencart\System\Library\Extension\AiBuilder\Chat\Orchestrator;
use Opencart\System\Library\Extension\AiBuilder\Utils\CsvValidator;

class AiBuilder extends \Opencart\System\Engine\Controller {
	private function ensureLibraryAutoloader(): void {
		static $registered = false;

		if (!$registered) {
			$this->autoloader->register(
				'Opencart\System\Library\Extension\AiBuilder',
				DIR_EXTENSION . 'ai_builder/system/library/',
				true
			);
			$registered = true;
		}
	}

	public function index(): void {
		$this->load->language('extension/ai_builder/other/ai_builder');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
			['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other')],
			['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/ai_builder/other/ai_builder', 'user_token=' . $this->session->data['user_token'])]
		];

		$data['save'] = $this->url->link('extension/ai_builder/other/ai_builder.save', 'user_token=' . $this->session->data['user_token'], true);
		$data['chat'] = $this->url->link('extension/ai_builder/other/ai_builder.chat', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=other');

		$data['other_ai_builder_status'] = $this->config->get('other_ai_builder_status');
		$data['other_ai_builder_api_key'] = $this->config->get('other_ai_builder_api_key');
		$data['other_ai_builder_model'] = $this->config->get('other_ai_builder_model') ?: 'gpt-4o-mini';
		$data['other_ai_builder_temperature'] = $this->config->get('other_ai_builder_temperature') ?: '0.3';
		$data['other_ai_builder_confirm'] = $this->config->get('other_ai_builder_confirm') ?? '1';

		$data['user_token'] = $this->session->data['user_token'];
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/ai_builder/other/ai_builder', $data));
	}

	public function chat(): void {
		if (!$this->user->hasPermission('access', 'extension/ai_builder/other/ai_builder')) {
			$this->response->redirect($this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']));
			return;
		}

		$this->load->language('extension/ai_builder/other/ai_builder');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addStyle('../extension/ai_builder/admin/view/stylesheet/chat.css');
		$this->document->addLink('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', 'stylesheet');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_settings'] = $this->language->get('text_settings');
		$data['text_placeholder'] = $this->language->get('text_placeholder');
		$data['text_typing'] = $this->language->get('text_typing');

		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
			['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/ai_builder/other/ai_builder.chat', 'user_token=' . $this->session->data['user_token'])]
		];

		$data['user_token'] = $this->session->data['user_token'];

		$data['url_send'] = $this->url->link('extension/ai_builder/other/ai_builder.send', 'user_token=' . $this->session->data['user_token'], true);
		$data['url_upload'] = $this->url->link('extension/ai_builder/other/ai_builder.upload', 'user_token=' . $this->session->data['user_token'], true);
		$data['url_confirm'] = $this->url->link('extension/ai_builder/other/ai_builder.confirm', 'user_token=' . $this->session->data['user_token'], true);
		$data['url_sessions'] = $this->url->link('extension/ai_builder/other/ai_builder.sessions', 'user_token=' . $this->session->data['user_token'], true);
		$data['url_history'] = $this->url->link('extension/ai_builder/other/ai_builder.history', 'user_token=' . $this->session->data['user_token'], true);
		$data['url_new_session'] = $this->url->link('extension/ai_builder/other/ai_builder.newSession', 'user_token=' . $this->session->data['user_token'], true);
		$data['url_csv_template'] = $this->url->link('extension/ai_builder/other/ai_builder.csvTemplate', 'user_token=' . $this->session->data['user_token'], true);
		$data['url_settings'] = $this->url->link('extension/ai_builder/other/ai_builder', 'user_token=' . $this->session->data['user_token']);

		$data['api_configured'] = (bool)$this->config->get('other_ai_builder_api_key');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/ai_builder/other/chat', $data));
	}

	public function send(): void {
		$this->ensureLibraryAutoloader();
		$this->load->language('extension/ai_builder/other/ai_builder');
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ai_builder/other/ai_builder')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$message = trim($this->request->post['message'] ?? '');
			$session_id = (int)($this->request->post['session_id'] ?? 0);
			$confirmed = (bool)($this->request->post['confirmed'] ?? false);

			if (!$message) {
				$json['error'] = 'Message is required';
			}

			if (!$json) {
				try {
					$this->load->model('extension/ai_builder/other/ai_builder');

					if (!$session_id) {
						$session_id = $this->model_extension_ai_builder_other_ai_builder->addSession(
							$this->user->getId(),
							substr($message, 0, 50)
						);
					}

					$session = $this->model_extension_ai_builder_other_ai_builder->getSession($session_id, $this->user->getId());
					$state = json_decode($session['state'] ?? '{}', true) ?: [];

					$this->model_extension_ai_builder_other_ai_builder->addMessage($session_id, 'user', $message);

					$history = $this->model_extension_ai_builder_other_ai_builder->getMessages($session_id);
					$history = array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $history);

					$orchestrator = new Orchestrator(
						$this->registry,
						$this->config->get('other_ai_builder_api_key') ?: '',
						$this->config->get('other_ai_builder_model') ?: 'gpt-4o-mini',
						(float)($this->config->get('other_ai_builder_temperature') ?: 0.3)
					);

					$result = $orchestrator->process($message, $history, $state, $confirmed);

					$this->model_extension_ai_builder_other_ai_builder->addMessage(
						$session_id,
						'assistant',
						$result['message'] ?? '',
						['ui' => $result['ui'] ?? [], 'intent' => $result['intent'] ?? '']
					);

					if (!empty($result['state'])) {
						$this->model_extension_ai_builder_other_ai_builder->updateSessionState($session_id, $result['state']);
					}

					$this->model_extension_ai_builder_other_ai_builder->addAudit([
						'user_id'          => $this->user->getId(),
						'session_id'       => $session_id,
						'action'           => $result['intent'] ?? 'chat',
						'intent'           => $result['intent'] ?? '',
						'request'          => $message,
						'response'         => $result['message'] ?? '',
						'affected_records' => json_encode($result['data'] ?? []),
						'execution_time'   => $result['execution_time'] ?? 0,
						'status'           => !empty($result['error']) ? 'error' : 'success',
						'ip'               => $this->request->server['REMOTE_ADDR'] ?? ''
					]);

					$json = array_merge($json, $result, ['session_id' => $session_id]);
				} catch (\Throwable $e) {
					$json['error'] = true;
					$json['message'] = $e->getMessage();
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function upload(): void {
		$this->ensureLibraryAutoloader();
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ai_builder/other/ai_builder')) {
			$json['error'] = 'Permission denied';
		}

		if (!$json && empty($this->request->files['file'])) {
			$json['error'] = 'No file uploaded';
		}

		if (!$json) {
			$session_id = (int)($this->request->post['session_id'] ?? 0);

			$this->load->model('extension/ai_builder/other/ai_builder');

			$state = [];

			if ($session_id) {
				$session = $this->model_extension_ai_builder_other_ai_builder->getSession($session_id, $this->user->getId());
				$state = json_decode($session['state'] ?? '{}', true) ?: [];
			}

			$orchestrator = new Orchestrator(
				$this->registry,
				$this->config->get('other_ai_builder_api_key') ?: ''
			);

			$result = $orchestrator->handleUpload($this->request->files['file'], $state);

			if ($session_id && !empty($result['state'])) {
				$this->model_extension_ai_builder_other_ai_builder->updateSessionState($session_id, $result['state']);
				$this->model_extension_ai_builder_other_ai_builder->addMessage($session_id, 'user', '[Uploaded file: ' . $this->request->files['file']['name'] . ']');
				$this->model_extension_ai_builder_other_ai_builder->addMessage($session_id, 'assistant', $result['message'] ?? 'File processed.');
			}

			$json = $result;
			$json['session_id'] = $session_id;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function confirm(): void {
		$this->ensureLibraryAutoloader();
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ai_builder/other/ai_builder')) {
			$json['error'] = 'Permission denied';
		}

		if (!$json) {
			$action = $this->request->post['action'] ?? '';
			$params = json_decode($this->request->post['params'] ?? '{}', true) ?: [];
			$session_id = (int)($this->request->post['session_id'] ?? 0);
			$confirmed = ($this->request->post['confirmed'] ?? '') === 'yes';

			if (!$confirmed) {
				$json['message'] = 'Action cancelled.';
			} else {
				$this->load->model('extension/ai_builder/other/ai_builder');

				$state = [];

				if ($session_id) {
					$session = $this->model_extension_ai_builder_other_ai_builder->getSession($session_id, $this->user->getId());
					$state = json_decode($session['state'] ?? '{}', true) ?: [];
				}

				$orchestrator = new Orchestrator($this->registry, $this->config->get('other_ai_builder_api_key') ?: '');
				$result = $orchestrator->executeConfirmedAction($action, $params, $state);

				if ($session_id) {
					$this->model_extension_ai_builder_other_ai_builder->addMessage($session_id, 'assistant', $result['message'] ?? 'Action completed.');
				}

				$json = $result;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function sessions(): void {
		$json = [];

		$this->load->model('extension/ai_builder/other/ai_builder');
		$sessions = $this->model_extension_ai_builder_other_ai_builder->getSessions($this->user->getId());

		$json['sessions'] = $sessions;

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function history(): void {
		$json = [];
		$session_id = (int)($this->request->get['session_id'] ?? 0);

		$this->load->model('extension/ai_builder/other/ai_builder');
		$session = $this->model_extension_ai_builder_other_ai_builder->getSession($session_id, $this->user->getId());

		if ($session) {
			$json['messages'] = $this->model_extension_ai_builder_other_ai_builder->getMessages($session_id);
			$json['state'] = json_decode($session['state'] ?? '{}', true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function newSession(): void {
		$json = [];

		$this->load->model('extension/ai_builder/other/ai_builder');
		$session_id = $this->model_extension_ai_builder_other_ai_builder->addSession($this->user->getId());

		$json['session_id'] = $session_id;

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function csvTemplate(): void {
		$this->ensureLibraryAutoloader();
		$this->response->addHeader('Content-Type: text/csv');
		$this->response->addHeader('Content-Disposition: attachment; filename="product_import_template.csv"');
		$this->response->setOutput(CsvValidator::getTemplate());
	}

	public function save(): void {
		$this->load->language('extension/ai_builder/other/ai_builder');
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ai_builder/other/ai_builder')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('other_ai_builder', $this->request->post);
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		if ($this->user->hasPermission('modify', 'extension/other')) {
			$this->load->model('extension/ai_builder/other/ai_builder');
			$this->model_extension_ai_builder_other_ai_builder->install();

			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('other_ai_builder', [
				'other_ai_builder_status'      => 1,
				'other_ai_builder_model'       => 'gpt-4o-mini',
				'other_ai_builder_temperature' => '0.3',
				'other_ai_builder_confirm'     => 1
			]);
		}
	}

	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/other')) {
			$this->load->model('extension/ai_builder/other/ai_builder');
			$this->model_extension_ai_builder_other_ai_builder->uninstall();
		}
	}
}
