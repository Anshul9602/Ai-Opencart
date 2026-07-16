<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class BannerService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function listBanners(string $search = ''): array {
		$loader = $this->registry->get('load');
		$loader->model('design/banner');
		$model = $this->registry->get('model_design_banner');

		$banners = $model->getBanners();
		$results = [];

		foreach ($banners as $banner) {
			if ($search && stripos($banner['name'], $search) === false) {
				continue;
			}

			$images = $this->getImagesForLanguage($model->getImages($banner['banner_id']));
			$preview = '';

			if (!empty($images)) {
				$preview = $this->imageUrl($images[0]['image'] ?? '');
			}

			$results[] = [
				'id'      => (int)$banner['banner_id'],
				'name'    => $banner['name'],
				'status'  => $banner['status'],
				'preview' => $preview,
				'slides'  => count($images)
			];
		}

		return $results;
	}

	public function getBannerSlides(int $banner_id): array {
		$loader = $this->registry->get('load');
		$loader->model('design/banner');
		$model = $this->registry->get('model_design_banner');

		$banner = $model->getBanner($banner_id);

		if (!$banner) {
			return ['banner' => [], 'slides' => []];
		}

		$images = $this->getImagesForLanguage($model->getImages($banner_id));
		$slides = [];

		foreach ($images as $image) {
			if (!is_array($image) || empty($image['banner_image_id'])) {
				continue;
			}

			$slides[] = [
				'banner_image_id' => (int)$image['banner_image_id'],
				'title'           => $image['title'] ?? 'Slide',
				'image'           => $image['image'] ?? '',
				'preview'         => $this->imageUrl($image['image'] ?? ''),
				'link'            => $image['link'] ?? '',
				'sort_order'      => $image['sort_order'] ?? 0
			];
		}

		return ['banner' => $banner, 'slides' => $slides];
	}

	public function replaceSlideImage(int $banner_id, int $banner_image_id, string $new_image_path, int $language_id = 0): array {
		$loader = $this->registry->get('load');
		$loader->model('design/banner');
		$model = $this->registry->get('model_design_banner');

		$banner = $model->getBanner($banner_id);

		if (!$banner) {
			return ['error' => 'Banner not found'];
		}

		$images_by_language = $model->getImages($banner_id);
		$banner_images = [];

		foreach ($images_by_language as $lang_id => $language_images) {
			foreach ($language_images as $image) {
				if (!isset($banner_images[$lang_id])) {
					$banner_images[$lang_id] = [];
				}

				$img_path = $image['image'];

				if ((int)$image['banner_image_id'] === $banner_image_id) {
					$img_path = $new_image_path;
				}

				$banner_images[$lang_id][] = [
					'title'      => $image['title'] ?? '',
					'link'       => $image['link'] ?? '',
					'image'      => $img_path,
					'sort_order' => $image['sort_order'] ?? 0
				];
			}
		}

		$model->editBanner($banner_id, [
			'name'         => $banner['name'],
			'status'       => $banner['status'],
			'banner_image' => $banner_images
		]);

		$cache = new \Opencart\System\Library\Extension\AiBuilder\Utils\CacheManager($this->registry);
		$cache->clear();

		return [
			'success'   => true,
			'message'   => 'Banner slide image updated successfully. Refresh Design > Banners to verify.',
			'banner_id' => $banner_id,
			'slide_id'  => $banner_image_id
		];
	}

	public function addSlide(int $banner_id, string $image_path, string $title = '', string $link = '', int $language_id = 0, int $position = -1): array {
		$loader = $this->registry->get('load');
		$loader->model('design/banner');
		$model = $this->registry->get('model_design_banner');

		$banner = $model->getBanner($banner_id);

		if (!$banner) {
			return ['error' => 'Banner not found'];
		}

		$language_id = $language_id ?: $this->getLanguageId();
		$images_by_language = $model->getImages($banner_id);
		$banner_images = [];

		foreach ($images_by_language as $lang_id => $language_images) {
			foreach ($language_images as $image) {
				if (!isset($banner_images[$lang_id])) {
					$banner_images[$lang_id] = [];
				}

				$banner_images[$lang_id][] = [
					'title'      => $image['title'] ?? '',
					'link'       => $image['link'] ?? '',
					'image'      => $image['image'],
					'sort_order' => $image['sort_order'] ?? 0
				];
			}
		}

		if (!isset($banner_images[$language_id])) {
			$banner_images[$language_id] = [];
		}

		$slides = $banner_images[$language_id];
		$new_slide = [
			'title'      => $title,
			'link'       => $link,
			'image'      => $image_path,
			'sort_order' => 0
		];

		if ($position < 0) {
			$position = count($slides);
		} else {
			$position = max(0, min($position, count($slides)));
		}

		array_splice($slides, $position, 0, [$new_slide]);

		foreach ($slides as $index => &$slide) {
			$slide['sort_order'] = $index;
		}
		unset($slide);

		$banner_images[$language_id] = $slides;

		$model->editBanner($banner_id, [
			'name'         => $banner['name'],
			'status'       => $banner['status'],
			'banner_image' => $banner_images
		]);

		$position_label = $position + 1;

		return [
			'success' => true,
			'message' => 'Slide added at position ' . $position_label . '. Existing slides were shifted down.'
		];
	}

	public function deleteSlide(int $banner_id, int $banner_image_id): array {
		$loader = $this->registry->get('load');
		$loader->model('design/banner');
		$model = $this->registry->get('model_design_banner');

		$banner = $model->getBanner($banner_id);

		if (!$banner) {
			return ['error' => 'Banner not found'];
		}

		$images_by_language = $model->getImages($banner_id);
		$banner_images = [];
		$removed = false;

		foreach ($images_by_language as $lang_id => $language_images) {
			foreach ($language_images as $image) {
				if ((int)$image['banner_image_id'] === $banner_image_id) {
					$removed = true;
					continue;
				}

				if (!isset($banner_images[$lang_id])) {
					$banner_images[$lang_id] = [];
				}

				$banner_images[$lang_id][] = [
					'title'      => $image['title'] ?? '',
					'link'       => $image['link'] ?? '',
					'image'      => $image['image'],
					'sort_order' => $image['sort_order'] ?? 0
				];
			}
		}

		if (!$removed) {
			return ['error' => 'Slide not found'];
		}

		$model->editBanner($banner_id, [
			'name'         => $banner['name'],
			'status'       => $banner['status'],
			'banner_image' => $banner_images
		]);

		$cache = new \Opencart\System\Library\Extension\AiBuilder\Utils\CacheManager($this->registry);
		$cache->clear();

		return [
			'success' => true,
			'message' => 'Banner slide deleted successfully.'
		];
	}

	public function deleteBanner(int $banner_id): array {
		$loader = $this->registry->get('load');
		$loader->model('design/banner');
		$model = $this->registry->get('model_design_banner');

		$banner = $model->getBanner($banner_id);

		if (!$banner) {
			return ['error' => 'Banner not found'];
		}

		$model->deleteBanner($banner_id);

		$cache = new \Opencart\System\Library\Extension\AiBuilder\Utils\CacheManager($this->registry);
		$cache->clear();

		return [
			'success' => true,
			'message' => 'Banner "' . ($banner['name'] ?? '') . '" deleted successfully.'
		];
	}

	private function getLanguageId(): int {
		$config = $this->registry->get('config');

		return (int)($config->get('config_language_id') ?: 1);
	}

	private function getImagesForLanguage(array $images_by_language): array {
		if (!$images_by_language) {
			return [];
		}

		$language_id = $this->getLanguageId();

		foreach ($images_by_language as $lang_id => $language_images) {
			if ((int)$lang_id === $language_id && is_array($language_images)) {
				return $language_images;
			}
		}

		$flat = [];

		foreach ($images_by_language as $language_images) {
			if (!is_array($language_images)) {
				continue;
			}

			foreach ($language_images as $image) {
				if (is_array($image)) {
					$flat[] = $image;
				}
			}
		}

		return $flat;
	}

	private function imageUrl(string $path): string {
		if (!$path) {
			return '';
		}

		try {
			$loader = $this->registry->get('load');
			$loader->model('tool/image');

			$config = $this->registry->get('config');
			$model_image = $this->registry->get('model_tool_image');

			$url = $model_image->resize(
				$path,
				(int)($config->get('config_image_default_width') ?: 200),
				(int)($config->get('config_image_default_height') ?: 200)
			);

			if ($url) {
				return $url;
			}
		} catch (\Throwable $e) {
			// Fall back to direct image path.
		}

		return HTTP_CATALOG . 'image/' . $path;
	}
}
