<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class SettingsService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function updateStoreSettings(array $data): array {
		$loader = $this->registry->get('load');
		$loader->model('setting/setting');
		$model = $this->registry->get('model_setting_setting');

		$allowed = ['config_name', 'config_meta_title', 'config_meta_description', 'config_logo', 'config_icon'];

		$settings = [];

		foreach ($allowed as $key) {
			if (isset($data[$key])) {
				$settings[$key] = $data[$key];
			}
		}

		if (empty($settings)) {
			return ['error' => 'No valid settings to update'];
		}

		$config = $this->registry->get('config');
		$store_id = (int)$config->get('config_store_id');

		foreach ($settings as $key => $value) {
			$model->editSetting($key, [$key => $value], $store_id);
		}

		$cache = new \Opencart\System\Library\Extension\AiBuilder\Utils\CacheManager($this->registry);
		$cache->clear();

		return ['success' => true, 'message' => 'Store settings updated.', 'updated' => array_keys($settings)];
	}

	public function updateLogo(string $image_path): array {
		return $this->updateStoreSettings(['config_logo' => $image_path]);
	}

	public function updateFavicon(string $image_path): array {
		return $this->updateStoreSettings(['config_icon' => $image_path]);
	}
}
