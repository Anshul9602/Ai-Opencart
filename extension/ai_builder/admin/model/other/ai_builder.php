<?php
namespace Opencart\Admin\Model\Extension\AiBuilder\Other;

class AiBuilder extends \Opencart\System\Engine\Model {
	public function install(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ai_builder_session` (
			`session_id` int(11) NOT NULL AUTO_INCREMENT,
			`user_id` int(11) NOT NULL,
			`title` varchar(255) NOT NULL DEFAULT 'New Chat',
			`state` mediumtext,
			`date_added` datetime NOT NULL,
			`date_modified` datetime NOT NULL,
			PRIMARY KEY (`session_id`),
			KEY `user_id` (`user_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ai_builder_message` (
			`message_id` int(11) NOT NULL AUTO_INCREMENT,
			`session_id` int(11) NOT NULL,
			`role` varchar(20) NOT NULL,
			`content` mediumtext NOT NULL,
			`metadata` mediumtext,
			`date_added` datetime NOT NULL,
			PRIMARY KEY (`message_id`),
			KEY `session_id` (`session_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ai_builder_audit` (
			`audit_id` int(11) NOT NULL AUTO_INCREMENT,
			`user_id` int(11) NOT NULL,
			`session_id` int(11) NOT NULL DEFAULT 0,
			`action` varchar(128) NOT NULL,
			`intent` varchar(128) NOT NULL DEFAULT '',
			`request` mediumtext,
			`response` mediumtext,
			`affected_records` mediumtext,
			`execution_time` decimal(10,4) NOT NULL DEFAULT 0.0000,
			`status` varchar(32) NOT NULL DEFAULT 'success',
			`ip` varchar(40) NOT NULL DEFAULT '',
			`date_added` datetime NOT NULL,
			PRIMARY KEY (`audit_id`),
			KEY `user_id` (`user_id`),
			KEY `action` (`action`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->load->model('setting/startup');
		$this->model_setting_startup->deleteStartupByCode('ai_builder_autoload');
		$this->model_setting_startup->addStartup([
			'code'        => 'ai_builder_autoload',
			'description' => 'AI Builder library autoloader',
			'action'      => 'admin/extension/ai_builder/startup/autoload',
			'status'      => 1,
			'sort_order'  => 0
		]);

		// Sidebar menu is injected via OCMOD (ocmod/ai_builder.ocmod.xml) after System tab.
	}

	public function uninstall(): void {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ai_builder_session`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ai_builder_message`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ai_builder_audit`");

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('ai_builder_menu');

		$this->load->model('setting/startup');
		$this->model_setting_startup->deleteStartupByCode('ai_builder_autoload');
	}

	public function addSession(int $user_id, string $title = 'New Chat'): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "ai_builder_session` SET
			`user_id` = '" . (int)$user_id . "',
			`title` = '" . $this->db->escape($title) . "',
			`state` = '',
			`date_added` = NOW(),
			`date_modified` = NOW()");

		return $this->db->getLastId();
	}

	public function getSession(int $session_id, int $user_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ai_builder_session`
			WHERE `session_id` = '" . (int)$session_id . "' AND `user_id` = '" . (int)$user_id . "'");

		return $query->row;
	}

	public function getSessions(int $user_id, int $start = 0, int $limit = 20): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ai_builder_session`
			WHERE `user_id` = '" . (int)$user_id . "'
			ORDER BY `date_modified` DESC
			LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	public function updateSessionState(int $session_id, array $state, string $title = ''): void {
		$sql = "UPDATE `" . DB_PREFIX . "ai_builder_session` SET
			`state` = '" . $this->db->escape(json_encode($state)) . "',
			`date_modified` = NOW()";

		if ($title) {
			$sql .= ", `title` = '" . $this->db->escape($title) . "'";
		}

		$sql .= " WHERE `session_id` = '" . (int)$session_id . "'";

		$this->db->query($sql);
	}

	public function deleteSession(int $session_id, int $user_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "ai_builder_message`
			WHERE `session_id` = '" . (int)$session_id . "'");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "ai_builder_session`
			WHERE `session_id` = '" . (int)$session_id . "' AND `user_id` = '" . (int)$user_id . "'");
	}

	public function addMessage(int $session_id, string $role, string $content, array $metadata = []): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "ai_builder_message` SET
			`session_id` = '" . (int)$session_id . "',
			`role` = '" . $this->db->escape($role) . "',
			`content` = '" . $this->db->escape($content) . "',
			`metadata` = '" . $this->db->escape(json_encode($metadata)) . "',
			`date_added` = NOW()");

		$this->db->query("UPDATE `" . DB_PREFIX . "ai_builder_session`
			SET `date_modified` = NOW() WHERE `session_id` = '" . (int)$session_id . "'");

		return $this->db->getLastId();
	}

	public function getMessages(int $session_id, int $limit = 50): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ai_builder_message`
			WHERE `session_id` = '" . (int)$session_id . "'
			ORDER BY `message_id` ASC
			LIMIT " . (int)$limit);

		$messages = [];

		foreach ($query->rows as $row) {
			$messages[] = [
				'message_id' => $row['message_id'],
				'role'       => $row['role'],
				'content'    => $row['content'],
				'metadata'   => json_decode($row['metadata'] ?: '{}', true),
				'date_added' => $row['date_added']
			];
		}

		return $messages;
	}

	public function addAudit(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "ai_builder_audit` SET
			`user_id` = '" . (int)($data['user_id'] ?? 0) . "',
			`session_id` = '" . (int)($data['session_id'] ?? 0) . "',
			`action` = '" . $this->db->escape($data['action'] ?? '') . "',
			`intent` = '" . $this->db->escape($data['intent'] ?? '') . "',
			`request` = '" . $this->db->escape($data['request'] ?? '') . "',
			`response` = '" . $this->db->escape($data['response'] ?? '') . "',
			`affected_records` = '" . $this->db->escape($data['affected_records'] ?? '') . "',
			`execution_time` = '" . (float)($data['execution_time'] ?? 0) . "',
			`status` = '" . $this->db->escape($data['status'] ?? 'success') . "',
			`ip` = '" . $this->db->escape($data['ip'] ?? '') . "',
			`date_added` = NOW()");

		return $this->db->getLastId();
	}

	public function getAudits(int $start = 0, int $limit = 50): array {
		$query = $this->db->query("SELECT a.*, u.username FROM `" . DB_PREFIX . "ai_builder_audit` a
			LEFT JOIN `" . DB_PREFIX . "user` u ON a.user_id = u.user_id
			ORDER BY a.audit_id DESC
			LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}
}
