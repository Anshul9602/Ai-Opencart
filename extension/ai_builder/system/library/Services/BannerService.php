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

			$images = $model->getImages($banner['banner_id']);
			$preview = '';

			if (!empty($images)) {
				$first = reset($images);
				$preview = HTTP_CATALOG . 'image/' . ($first['image'] ?? '');
			}

			$results[] = [
				'id'      => $banner['banner_id'],
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
		$images = $model->getImages($banner_id);
		$slides = [];

		foreach ($images as $image) {
			$slides[] = [
				'banner_image_id' => $image['banner_image_id'],
				'title'           => $image['title'] ?? '',
				'image'           => $image['image'],
				'preview'         => HTTP_CATALOG . 'image/' . $image['image'],
				'link'            => $image['link'] ?? '',
				'sort_order'      => $image['sort_order'] ?? 0
			];
		}

		return ['banner' => $banner, 'slides' => $slides];
	}

	public function replaceSlideImage(int $banner_id, int $banner_image_id, string $new_image_path, int $language_id = 1): array {
		$loader = $this->registry->get('load');
		$loader->model('design/banner');
		$model = $this->registry->get('model_design_banner');

		$banner = $model->getBanner($banner_id);

		if (!$banner) {
			return ['error' => 'Banner not found'];
		}

		$images = $model->getImages($banner_id);
		$banner_images = [];

		foreach ($images as $image) {
			$lang_id = $image['language_id'] ?? $language_id;

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

		$model->editBanner($banner_id, [
			'name'          => $banner['name'],
			'status'        => $banner['status'],
			'banner_image'  => $banner_images
		]);

		$cache = new \Opencart\System\Library\Extension\AiBuilder\Utils\CacheManager($this->registry);
		$cache->clear();

		return [
			'success' => true,
			'message' => 'Banner image updated successfully.',
			'banner_id' => $banner_id
		];
	}

	public function addSlide(int $banner_id, string $image_path, string $title = '', string $link = '', int $language_id = 1): array {
		$loader = $this->registry->get('load');
		$loader->model('design/banner');
		$model = $this->registry->get('model_design_banner');

		$banner = $model->getBanner($banner_id);

		if (!$banner) {
			return ['error' => 'Banner not found'];
		}

		$images = $model->getImages($banner_id);
		$banner_images = [];

		foreach ($images as $image) {
			$lang_id = $image['language_id'] ?? $language_id;

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

		if (!isset($banner_images[$language_id])) {
			$banner_images[$language_id] = [];
		}

		$banner_images[$language_id][] = [
			'title'      => $title,
			'link'       => $link,
			'image'      => $image_path,
			'sort_order' => count($banner_images[$language_id])
		];

		$model->editBanner($banner_id, [
			'name'         => $banner['name'],
			'status'       => $banner['status'],
			'banner_image' => $banner_images
		]);

		return ['success' => true, 'message' => 'Slide added successfully.'];
	}
}
