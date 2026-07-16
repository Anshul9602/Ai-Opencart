<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class OrderService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function getTodaySummary(): array {
		$db = $this->registry->get('db');

		$today = date('Y-m-d');

		$statuses = [
			'pending'    => [1],
			'processing' => [2, 3],
			'complete'   => [5],
			'cancelled'  => [7]
		];

		$summary = [];

		foreach ($statuses as $label => $ids) {
			$id_list = implode(',', array_map('intval', $ids));

			$query = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS revenue
				FROM `" . DB_PREFIX . "order`
				WHERE DATE(date_added) = '" . $db->escape($today) . "'
				AND order_status_id IN (" . $id_list . ")");

			$summary[$label] = [
				'count'   => (int)$query->row['total'],
				'revenue' => (float)$query->row['revenue']
			];
		}

		$total_query = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS revenue
			FROM `" . DB_PREFIX . "order`
			WHERE DATE(date_added) = '" . $db->escape($today) . "'");

		return [
			'date'    => $today,
			'summary' => $summary,
			'total_orders' => (int)$total_query->row['total'],
			'total_revenue' => (float)$total_query->row['revenue']
		];
	}

	public function listRecent(int $limit = 10): array {
		$db = $this->registry->get('db');

		$query = $db->query("SELECT o.order_id, o.firstname, o.lastname, o.total, o.order_status_id, os.name AS status, o.date_added
			FROM `" . DB_PREFIX . "order` o
			LEFT JOIN `" . DB_PREFIX . "order_status` os ON o.order_status_id = os.order_status_id
			ORDER BY o.order_id DESC
			LIMIT " . (int)$limit);

		return $query->rows;
	}
}
