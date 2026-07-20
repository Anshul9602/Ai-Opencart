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
			'date'          => $today,
			'summary'       => $summary,
			'total_orders'  => (int)$total_query->row['total'],
			'total_revenue' => (float)$total_query->row['revenue']
		];
	}

	public function list(array $options = []): array {
		$db = $this->registry->get('db');
		$language_id = $this->getLanguageId();
		$limit = max(1, min((int)($options['limit'] ?? 50), 100));
		$query_str = trim((string)($options['query'] ?? ''));
		$date = trim((string)($options['date'] ?? ''));

		$sql = "SELECT o.order_id, o.firstname, o.lastname, o.email, o.total, o.currency_code,
			o.payment_method, o.shipping_method, o.date_added, o.order_status_id,
			(SELECT os.name FROM `" . DB_PREFIX . "order_status` os
			 WHERE os.order_status_id = o.order_status_id AND os.language_id = '" . (int)$language_id . "') AS status
			FROM `" . DB_PREFIX . "order` o WHERE o.order_status_id > '0'";

		if ($date === 'today') {
			$sql .= " AND DATE(o.date_added) = '" . $db->escape(date('Y-m-d')) . "'";
		} elseif ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			$sql .= " AND DATE(o.date_added) = '" . $db->escape($date) . "'";
		}

		if ($query_str !== '') {
			if (ctype_digit($query_str)) {
				$sql .= " AND o.order_id = '" . (int)$query_str . "'";
			} else {
				$esc = $db->escape('%' . $query_str . '%');
				$sql .= " AND (CONCAT(o.firstname, ' ', o.lastname) LIKE '" . $esc . "'"
					. " OR o.email LIKE '" . $esc . "'"
					. " OR o.telephone LIKE '" . $esc . "')";
			}
		}

		$sql .= " ORDER BY o.order_id DESC LIMIT " . $limit;

		$query = $db->query($sql);

		return array_map(fn(array $row): array => [
			'id'       => (int)$row['order_id'],
			'order_id' => (int)$row['order_id'],
			'customer' => trim($row['firstname'] . ' ' . $row['lastname']),
			'email'    => $row['email'] ?? '',
			'total'    => (float)$row['total'],
			'currency' => $row['currency_code'] ?? '',
			'status'   => $row['status'] ?? 'Unknown',
			'payment'  => $row['payment_method'] ?? '',
			'shipping' => $row['shipping_method'] ?? '',
			'date'     => $row['date_added'] ?? ''
		], $query->rows);
	}

	public function listRecent(int $limit = 10): array {
		return $this->list(['limit' => $limit]);
	}

	public function changeStatus(int $order_id, string $status_name, string $comment = 'Updated via AI Website Builder'): array {
		$order_id = (int)$order_id;

		if ($order_id <= 0) {
			return ['error' => 'Invalid order ID.'];
		}

		$status_id = $this->resolveStatusId($status_name);

		if (!$status_id) {
			return [
				'error' => 'Order status "' . $status_name . '" not found. Available: '
					. implode(', ', array_values($this->getOrderStatusNames()))
			];
		}

		$bridge = new \Opencart\System\Library\Extension\AiBuilder\Admin\AdminBridge($this->registry);
		$result = $bridge->callCatalogModel('checkout/order', 'addHistory', [
			$order_id,
			$status_id,
			$comment,
			false,
			true
		]);

		if (!empty($result['error'])) {
			return $this->changeStatusDirect($order_id, $status_id, $status_name, $comment);
		}

		$resolved_name = $this->getOrderStatusNames()[$status_id] ?? $status_name;

		return [
			'success'         => true,
			'message'         => 'Order #' . $order_id . ' status updated to ' . $resolved_name . '.',
			'order_id'        => $order_id,
			'order_status_id' => $status_id,
			'status'          => $resolved_name
		];
	}

	private function changeStatusDirect(int $order_id, int $status_id, string $status_name, string $comment): array {
		$db = $this->registry->get('db');

		$query = $db->query("SELECT `order_id` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . $order_id . "'");

		if (!$query->num_rows) {
			return ['error' => 'Order #' . $order_id . ' not found.'];
		}

		$resolved_name = $this->getOrderStatusNames()[$status_id] ?? $status_name;

		$db->query("UPDATE `" . DB_PREFIX . "order` SET
			`order_status_id` = '" . (int)$status_id . "',
			`date_modified` = NOW()
			WHERE `order_id` = '" . $order_id . "'");

		$db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET
			`order_id` = '" . $order_id . "',
			`order_status_id` = '" . (int)$status_id . "',
			`notify` = '0',
			`comment` = '" . $db->escape($comment) . "',
			`date_added` = NOW()");

		return [
			'success'         => true,
			'message'         => 'Order #' . $order_id . ' status updated to ' . $resolved_name . '.',
			'order_id'        => $order_id,
			'order_status_id' => $status_id,
			'status'          => $resolved_name
		];
	}

	/** @return array<int, string> */
	private function getOrderStatusNames(): array {
		$db = $this->registry->get('db');
		$language_id = $this->getLanguageId();
		$query = $db->query("SELECT `order_status_id`, `name` FROM `" . DB_PREFIX . "order_status`
			WHERE `language_id` = '" . (int)$language_id . "' ORDER BY `name`");

		$statuses = [];

		foreach ($query->rows as $row) {
			$statuses[(int)$row['order_status_id']] = $row['name'];
		}

		return $statuses;
	}

	private function resolveStatusId(string $status_name): int {
		$needle = strtolower(trim($status_name));
		$needle = trim($needle, " '\"");
		$aliases = [
			'process'    => 'processing',
			'processed'  => 'processing',
			'ship'       => 'shipped',
			'shipping'   => 'shipped',
			'complete'   => 'complete',
			'completed'  => 'complete',
			'done'       => 'complete',
			'cancel'     => 'cancelled',
			'canceled'   => 'cancelled',
			'cancelled'  => 'cancelled',
			'pending'    => 'pending',
		];

		if (isset($aliases[$needle])) {
			$needle = $aliases[$needle];
		}

		$statuses = $this->getOrderStatusNames();
		$lower_map = [];

		foreach ($statuses as $id => $name) {
			$lower_map[$id] = strtolower($name);
		}

		foreach ($lower_map as $id => $name) {
			if ($name === $needle) {
				return (int)$id;
			}
		}

		foreach ($lower_map as $id => $name) {
			if (str_contains($name, $needle) || str_contains($needle, $name)) {
				return (int)$id;
			}
		}

		$best_id = 0;
		$best_distance = PHP_INT_MAX;

		foreach ($lower_map as $id => $name) {
			$distance = levenshtein($needle, $name);

			if ($distance < $best_distance) {
				$best_distance = $distance;
				$best_id = (int)$id;
			}
		}

		return $best_distance <= 3 ? $best_id : 0;
	}

	private function getLanguageId(): int {
		$config = $this->registry->get('config');
		$language_id = (int)$config->get('config_language_id');

		return $language_id > 0 ? $language_id : 1;
	}
}
