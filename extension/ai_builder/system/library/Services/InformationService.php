<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class InformationService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function updatePage(string $title, string $content): array {
		$db = $this->registry->get('db');
		$config = $this->registry->get('config');
		$language_id = (int)$config->get('config_language_id');

		$query = $db->query("SELECT i.information_id FROM `" . DB_PREFIX . "information` i
			LEFT JOIN `" . DB_PREFIX . "information_description` id ON i.information_id = id.information_id
			WHERE id.language_id = '" . $language_id . "'
			AND id.title LIKE '%" . $db->escape($title) . "%'
			LIMIT 1");

		if (!$query->num_rows) {
			$loader = $this->registry->get('load');
			$loader->model('catalog/information');
			$model = $this->registry->get('model_catalog_information');

			$info_id = $model->addInformation([
				'sort_order' => 0,
				'status'     => 1,
				'information_description' => [
					$language_id => [
						'title'            => $title,
						'description'      => $content,
						'meta_title'       => $title,
						'meta_description' => ''
					]
				],
				'information_store' => [0],
				'information_seo_url' => [],
				'information_layout'  => []
			]);

			return ['success' => true, 'message' => "Page '{$title}' created.", 'information_id' => $info_id];
		}

		$information_id = (int)$query->row['information_id'];

		$db->query("UPDATE `" . DB_PREFIX . "information_description`
			SET `description` = '" . $db->escape($content) . "'
			WHERE `information_id` = '" . $information_id . "'
			AND `language_id` = '" . $language_id . "'");

		return ['success' => true, 'message' => "Page '{$title}' updated.", 'information_id' => $information_id];
	}
}
