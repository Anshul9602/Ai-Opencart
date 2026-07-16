<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class CustomerService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function search(string $query, int $limit = 10): array {
		$db = $this->registry->get('db');

		$sql = "SELECT customer_id, firstname, lastname, email, telephone, status, customer_group_id, date_added
			FROM `" . DB_PREFIX . "customer`
			WHERE firstname LIKE '%" . $db->escape($query) . "%'
			OR lastname LIKE '%" . $db->escape($query) . "%'
			OR email LIKE '%" . $db->escape($query) . "%'
			OR telephone LIKE '%" . $db->escape($query) . "%'
			ORDER BY firstname ASC
			LIMIT " . (int)$limit;

		return $db->query($sql)->rows;
	}

	public function updateStatus(int $customer_id, int $status): array {
		$db = $this->registry->get('db');

		$db->query("UPDATE `" . DB_PREFIX . "customer` SET `status` = '" . (int)$status . "'
			WHERE `customer_id` = '" . (int)$customer_id . "'");

		$action = $status ? 'activated' : 'blocked';

		return ['success' => true, 'message' => "Customer {$action} successfully."];
	}

	public function updateGroup(int $customer_id, int $group_id): array {
		$db = $this->registry->get('db');

		$db->query("UPDATE `" . DB_PREFIX . "customer` SET `customer_group_id` = '" . (int)$group_id . "'
			WHERE `customer_id` = '" . (int)$customer_id . "'");

		return ['success' => true, 'message' => 'Customer group updated.'];
	}
}
