<?php
namespace Opencart\System\Library\Extension\AiBuilder\Utils;

class ImageProcessor {
	public function upload(array $file, string $subdir = 'catalog'): array {
		if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			return ['error' => 'Invalid upload'];
		}

		$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

		if (!in_array($file['type'], $allowed)) {
			return ['error' => 'Invalid image type. Allowed: JPG, PNG, GIF, WebP, SVG'];
		}

		$filename = 'ai_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
		$path = $subdir . '/' . $filename;
		$full_path = DIR_IMAGE . $path;

		$dir = dirname($full_path);

		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		if (!move_uploaded_file($file['tmp_name'], $full_path)) {
			return ['error' => 'Failed to save image'];
		}

		$this->optimize($full_path);

		return [
			'success'  => true,
			'path'     => $path,
			'filename' => $filename,
			'url'      => 'image/' . $path
		];
	}

	public function optimize(string $filepath): void {
		if (!file_exists($filepath)) {
			return;
		}

		$info = getimagesize($filepath);

		if (!$info) {
			return;
		}

		$max_width = 2000;

		if ($info[0] <= $max_width) {
			return;
		}

		$ratio = $max_width / $info[0];
		$new_width = $max_width;
		$new_height = (int)($info[1] * $ratio);

		switch ($info[2]) {
			case IMAGETYPE_JPEG:
				$src = imagecreatefromjpeg($filepath);
				break;
			case IMAGETYPE_PNG:
				$src = imagecreatefrompng($filepath);
				break;
			case IMAGETYPE_WEBP:
				$src = imagecreatefromwebp($filepath);
				break;
			default:
				return;
		}

		if (!$src) {
			return;
		}

		$dst = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $info[0], $info[1]);

		switch ($info[2]) {
			case IMAGETYPE_JPEG:
				imagejpeg($dst, $filepath, 85);
				break;
			case IMAGETYPE_PNG:
				imagepng($dst, $filepath, 8);
				break;
			case IMAGETYPE_WEBP:
				imagewebp($dst, $filepath, 85);
				break;
		}

		imagedestroy($src);
		imagedestroy($dst);
	}

	public function resize(string $path, int $width, int $height): string {
		$full_path = DIR_IMAGE . $path;

		if (!file_exists($full_path)) {
			return $path;
		}

		$info = getimagesize($full_path);

		if (!$info) {
			return $path;
		}

		$ext = pathinfo($path, PATHINFO_EXTENSION);
		$resized_name = pathinfo($path, PATHINFO_FILENAME) . "_{$width}x{$height}." . $ext;
		$resized_path = dirname($path) . '/' . $resized_name;
		$resized_full = DIR_IMAGE . $resized_path;

		if (file_exists($resized_full)) {
			return $resized_path;
		}

		switch ($info[2]) {
			case IMAGETYPE_JPEG:
				$src = imagecreatefromjpeg($full_path);
				break;
			case IMAGETYPE_PNG:
				$src = imagecreatefrompng($full_path);
				break;
			case IMAGETYPE_WEBP:
				$src = imagecreatefromwebp($full_path);
				break;
			default:
				return $path;
		}

		$dst = imagecreatetruecolor($width, $height);
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);

		switch ($info[2]) {
			case IMAGETYPE_JPEG:
				imagejpeg($dst, $resized_full, 90);
				break;
			case IMAGETYPE_PNG:
				imagepng($dst, $resized_full, 8);
				break;
			case IMAGETYPE_WEBP:
				imagewebp($dst, $resized_full, 90);
				break;
		}

		imagedestroy($src);
		imagedestroy($dst);

		return $resized_path;
	}

	public function search(string $query, int $limit = 20): array {
		$results = [];
		$dirs = [DIR_IMAGE . 'catalog/', DIR_IMAGE . 'banner/'];

		foreach ($dirs as $dir) {
			if (!is_dir($dir)) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

			foreach ($iterator as $file) {
				if (!$file->isFile()) {
					continue;
				}

				$name = $file->getFilename();

				if (stripos($name, $query) !== false) {
					$relative = str_replace(DIR_IMAGE, '', $file->getPathname());
					$relative = str_replace('\\', '/', $relative);

					$results[] = [
						'name' => $name,
						'path' => $relative,
						'url'  => 'image/' . $relative,
						'size' => $file->getSize()
					];
				}

				if (count($results) >= $limit) {
					break 2;
				}
			}
		}

		return $results;
	}
}
