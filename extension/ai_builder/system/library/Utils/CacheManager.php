<?php
namespace Opencart\System\Library\Extension\AiBuilder\Utils;

class CacheManager {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function clear(): void {
		$cache = $this->registry->get('cache');

		if ($cache) {
			$cache->delete('*');
		}

		$image_dirs = [
			DIR_IMAGE . 'cache/',
		];

		foreach ($image_dirs as $dir) {
			if (is_dir($dir)) {
				$this->clearDirectory($dir);
			}
		}
	}

	private function clearDirectory(string $dir): void {
		$files = glob($dir . '*');

		if (!$files) {
			return;
		}

		foreach ($files as $file) {
			if (is_file($file)) {
				@unlink($file);
			} elseif (is_dir($file)) {
				$this->clearDirectory($file . '/');
				@rmdir($file);
			}
		}
	}
}
