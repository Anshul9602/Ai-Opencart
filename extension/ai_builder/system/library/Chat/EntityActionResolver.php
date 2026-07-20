<?php
namespace Opencart\System\Library\Extension\AiBuilder\Chat;

use Opencart\System\Library\Extension\AiBuilder\Services\CategoryService;
use Opencart\System\Library\Extension\AiBuilder\Services\ProductService;

class EntityActionResolver {
	private object $registry;
	private ActionExecutor $executor;

	public function __construct(object $registry, ActionExecutor $executor) {
		$this->registry = $registry;
		$this->executor = $executor;
	}

	public function tryResolve(string $message, array $state): ?array {
		if (IntentHelper::isBannerQuery($message)) {
			return null;
		}

		$entity = IntentHelper::detectEntityType($message, $state);

		if (!$entity) {
			return null;
		}

		$action = IntentHelper::detectEntityAction($message, $entity, $state);

		if (!$action) {
			return null;
		}

		return match ($entity) {
			'category' => $this->resolveCategoryAction($action, $message, $state),
			'product'  => $this->resolveProductAction($action, $message, $state),
			default    => null,
		};
	}

	public function enrichParams(string $action, array $params, array $state, string $user_message = '', string $context_message = ''): array {
		if ($this->isCategoryAction($action)) {
			return $this->enrichCategoryParams($params, $state, $user_message, $context_message);
		}

		if ($this->isProductAction($action)) {
			return $this->enrichProductParams($params, $state, $user_message, $context_message);
		}

		return $params;
	}

	public function isDirectAction(string $action): bool {
		return $this->isCategoryAction($action) || $this->isProductAction($action);
	}

	public function isNonConfirmDirectAction(string $action): bool {
		return in_array($action, [
			'enable_category', 'disable_category', 'edit_category', 'create_category',
			'parent_category', 'sort_category', 'category_image', 'category_seo_url', 'category_meta',
			'enable_product', 'disable_product', 'update_product', 'create_product',
			'update_product_price', 'update_quantity', 'update_special_price',
			'duplicate_product', 'change_category', 'change_manufacturer',
			'update_product_images', 'replace_product_images',
			'list_categories', 'list_products', 'search_categories', 'search_products', 'get_category', 'get_product',
		], true);
	}

	private function resolveCategoryAction(string $action, string $message, array $state): ?array {
		return match ($action) {
			'list'           => $this->executor->execute('list_categories', [
				'operation'      => 'read',
				'display_format' => IntentHelper::categoryDisplayFormat($message),
				'limit'          => 100
			], array_merge($state, ['operation' => 'read'])),
			'create'         => $this->resolveCreate('category', $message, $state),
			'delete'         => $this->resolveDelete('category', $message, $state),
			'status'         => $this->resolveCategoryStatus($message, $state),
			'rename'         => $this->resolveRename('category', $message, $state),
			'update'         => $this->resolveCategoryUpdate($message, $state),
			'duplicate'      => null,
			'update_price'   => null,
			'update_quantity'=> null,
			'update_special' => null,
			default          => null,
		};
	}

	private function resolveProductAction(string $action, string $message, array $state): ?array {
		return match ($action) {
			'list'            => $this->executor->execute('list_products', [
				'operation'      => IntentHelper::detectOperation($message, $state),
				'display_format' => IntentHelper::productDisplayFormat($message),
				'limit'          => 50
			], array_merge($state, ['operation' => IntentHelper::detectOperation($message, $state)])),
			'create'          => $this->resolveCreate('product', $message, $state),
			'delete'          => $this->resolveDelete('product', $message, $state),
			'status'          => $this->resolveProductStatus($message, $state),
			'rename'          => $this->resolveRename('product', $message, $state),
			'duplicate'       => $this->resolveProductDuplicate($message, $state),
			'update_price'    => $this->resolveProductPrice($message, $state),
			'update_quantity' => $this->resolveProductQuantity($message, $state),
			'update_special'  => $this->resolveProductSpecial($message, $state),
			'update_image'    => $this->resolveProductImage($message, $state),
			'update'          => $this->resolveProductUpdate($message, $state),
			default           => null,
		};
	}

	private function resolveCreate(string $entity, string $message, array $state): ?array {
		$name = $entity === 'category'
			? IntentHelper::extractCreateName($message, 'category')
			: IntentHelper::extractCreateName($message, 'product');

		if ($name === '') {
			return [
				'success'       => true,
				'message'       => 'What name should the new ' . $entity . ' have?',
				'needs_input'   => true,
				'pending_field' => 'name',
				'state_update'  => [
					'entity_type'    => $entity,
					'pending_action' => $entity === 'category' ? 'create_category' : 'create_product',
					'pending_field'  => 'name',
					'step'           => $entity === 'category' ? 'awaiting_category_name' : 'awaiting_product_name'
				]
			];
		}

		$params = ['name' => $name];

		if ($entity === 'product') {
			if ($price = IntentHelper::parseMoneyValue($message)) {
				$params['price'] = $price;
			}

			if ($qty = IntentHelper::parseIntegerValue($message, 'quantity|stock|qty')) {
				$params['quantity'] = $qty;
			}
		}

		return $this->executor->execute(
			$entity === 'category' ? 'create_category' : 'create_product',
			$params,
			$state
		);
	}

	private function resolveDelete(string $entity, string $message, array $state): ?array {
		if ($entity === 'category') {
			$category_id = $this->resolveCategoryId($message, $state);

			if (!$category_id) {
				return $this->executor->execute('list_categories', [
					'operation' => 'delete',
					'limit'     => 100
				], array_merge($state, ['operation' => 'delete']));
			}

			$name = $this->categoryName($state, $category_id, $message);

			return [
				'success'              => true,
				'message'              => 'Delete category "' . $name . '"?',
				'ui'                   => [
					'type'    => 'confirm',
					'message' => 'This category will be permanently removed.',
					'action'  => 'delete_category',
					'params'  => ['category_id' => $category_id]
				],
				'needs_confirmation'   => true,
				'state_update'         => [
					'selected_category_id' => $category_id,
					'operation'            => 'delete',
					'step'                 => 'category_action'
				]
			];
		}

		$product_id = $this->resolveProductId($message, $state);

		if (!$product_id) {
			return $this->executor->execute('list_products', [
				'operation' => 'delete',
				'limit'     => 50
			], array_merge($state, ['operation' => 'delete']));
		}

		$name = $this->productName($state, $product_id, $message);

		return [
			'success'              => true,
			'message'              => 'Delete product "' . $name . '"?',
			'ui'                   => [
				'type'    => 'confirm',
				'message' => 'This product will be permanently removed.',
				'action'  => 'delete_product',
				'params'  => ['product_id' => $product_id]
			],
			'needs_confirmation'   => true,
			'state_update'         => [
				'selected_product_id' => $product_id,
				'operation'           => 'delete',
				'step'                => 'product_action'
			]
		];
	}

	private function resolveCategoryStatus(string $message, array $state): ?array {
		$category_id = $this->resolveCategoryId($message, $state);

		if (!$category_id) {
			$name = IntentHelper::extractCategoryName($message);

			if ($name === '') {
				return null;
			}

			return [
				'error'   => true,
				'message' => 'Category "' . $name . '" not found. Say "category list" first or check the name.'
			];
		}

		$action = IntentHelper::inferCategoryStatusAction($message, $state);

		return $this->executor->execute($action, ['category_id' => $category_id], $state);
	}

	private function resolveProductStatus(string $message, array $state): ?array {
		$product_id = $this->resolveProductId($message, $state);

		if (!$product_id) {
			$name = IntentHelper::extractProductName($message);

			if ($name === '') {
				return null;
			}

			return [
				'error'   => true,
				'message' => 'Product "' . $name . '" not found. Say "product list" first or check the name.'
			];
		}

		$action = IntentHelper::inferProductStatusAction($message, $state);

		return $this->executor->execute($action, ['product_id' => $product_id], $state);
	}

	private function resolveRename(string $entity, string $message, array $state): ?array {
		$rename = IntentHelper::parseRename($message);

		if ($rename['new_name'] === '') {
			return null;
		}

		if ($entity === 'category') {
			$category_id = $this->resolveCategoryId($rename['old_name'] ?: $message, $state);

			if (!$category_id) {
				return null;
			}

			return $this->executor->execute('edit_category', [
				'category_id' => $category_id,
				'name'        => $rename['new_name']
			], $state);
		}

		$product_id = $this->resolveProductId($rename['old_name'] ?: $message, $state);

		if (!$product_id) {
			return null;
		}

		return $this->executor->execute('update_product', [
			'product_id' => $product_id,
			'name'       => $rename['new_name']
		], $state);
	}

	private function resolveCategoryUpdate(string $message, array $state): ?array {
		if ($rename = IntentHelper::parseRename($message)) {
			if ($rename['new_name'] !== '') {
				return $this->resolveRename('category', $message, $state);
			}
		}

		$category_id = $this->resolveCategoryId($message, $state);

		if (!$category_id) {
			return null;
		}

		$changes = [];

		if ($sort = IntentHelper::parseIntegerValue($message, 'sort|order')) {
			return $this->executor->execute('sort_category', [
				'category_id' => $category_id,
				'sort_order'  => $sort
			], $state);
		}

		return null;
	}

	private function resolveProductDuplicate(string $message, array $state): ?array {
		$product_id = $this->resolveProductId($message, $state);

		if (!$product_id) {
			return $this->executor->execute('list_products', [
				'operation' => 'update',
				'limit'     => 50
			], array_merge($state, ['operation' => 'update', 'pending_action' => 'duplicate_product']));
		}

		return $this->executor->execute('duplicate_product', ['product_id' => $product_id], $state);
	}

	private function resolveProductPrice(string $message, array $state): ?array {
		$product_id = $this->resolveProductId($message, $state);
		$price = IntentHelper::parseMoneyValue($message);

		if (!$product_id || $price === null) {
			return null;
		}

		return $this->executor->execute('update_product_price', [
			'product_id' => $product_id,
			'price'      => $price
		], $state);
	}

	private function resolveProductQuantity(string $message, array $state): ?array {
		$product_id = $this->resolveProductId($message, $state);
		$quantity = IntentHelper::parseIntegerValue($message, 'quantity|stock|qty');

		if (!$product_id || $quantity === null) {
			return null;
		}

		return $this->executor->execute('update_quantity', [
			'product_id' => $product_id,
			'quantity'   => $quantity
		], $state);
	}

	private function resolveProductSpecial(string $message, array $state): ?array {
		$product_id = $this->resolveProductId($message, $state);
		$special = IntentHelper::parseMoneyValue($message);

		if (!$product_id || $special === null) {
			return null;
		}

		return $this->executor->execute('update_special_price', [
			'product_id' => $product_id,
			'special'    => $special
		], $state);
	}

	private function resolveProductImage(string $message, array $state): ?array {
		$product_id = $this->resolveProductId($message, $state);

		if (!$product_id) {
			$name = IntentHelper::extractProductNameFromImageRequest($message);

			if ($name !== '') {
				$product_id = (new ProductService($this->registry))->resolveId($name);
			}
		}

		if (!$product_id) {
			return $this->executor->execute('list_products', [
				'operation'      => 'update',
				'display_format' => IntentHelper::productDisplayFormat($message),
				'limit'          => 50
			], array_merge($state, [
				'operation'     => 'update',
				'pending_field' => 'image',
				'entity_type'   => 'product'
			]));
		}

		return (new ProductService($this->registry))->buildImageUploadPrompt($product_id);
	}

	private function resolveProductUpdate(string $message, array $state): ?array {
		if ($rename = IntentHelper::parseRename($message)) {
			if ($rename['new_name'] !== '') {
				return $this->resolveRename('product', $message, $state);
			}
		}

		return null;
	}

	private function resolveCategoryId(string $message, array $state): int {
		$category_id = (int)($state['selected_category_id'] ?? 0);

		if ($category_id) {
			return $category_id;
		}

		$name = IntentHelper::extractCategoryName($message);

		if ($name === '') {
			return 0;
		}

		$category_id = IntentHelper::findCategoryId($state['categories'] ?? [], $name);

		if ($category_id) {
			return $category_id;
		}

		return (new CategoryService($this->registry))->resolveId($name);
	}

	private function resolveProductId(string $message, array $state): int {
		$product_id = (int)($state['selected_product_id'] ?? 0);

		if ($product_id) {
			return $product_id;
		}

		if (preg_match('/\bproduct\s+#?(\d+)\b/i', $message, $matches)) {
			return (int)$matches[1];
		}

		if (IntentHelper::wantsProductImageUpdate($message)) {
			foreach ($state['products'] ?? [] as $product) {
				$pname = trim((string)($product['name'] ?? ''));

				if ($pname !== '' && stripos($message, $pname) !== false) {
					return (int)($product['id'] ?? 0);
				}
			}
		}

		$name = IntentHelper::wantsProductImageUpdate($message)
			? IntentHelper::extractProductNameFromImageRequest($message)
			: IntentHelper::extractProductName($message);

		if ($name === '') {
			return 0;
		}

		$product_id = IntentHelper::findProductId($state['products'] ?? [], $name);

		if ($product_id) {
			return $product_id;
		}

		return (new ProductService($this->registry))->resolveId($name);
	}

	private function categoryName(array $state, int $category_id, string $message): string {
		foreach ($state['categories'] ?? [] as $category) {
			if ((int)($category['id'] ?? 0) === $category_id) {
				return $category['name'] ?? 'Category';
			}
		}

		$result = $this->executor->execute('get_category', ['category_id' => $category_id], $state);

		return $result['data']['name'] ?? IntentHelper::extractCategoryName($message) ?: 'Category';
	}

	private function productName(array $state, int $product_id, string $message): string {
		foreach ($state['products'] ?? [] as $product) {
			if ((int)($product['id'] ?? 0) === $product_id) {
				return $product['name'] ?? 'Product';
			}
		}

		$result = $this->executor->execute('get_product', ['product_id' => $product_id], $state);

		return $result['data']['name'] ?? IntentHelper::extractProductName($message) ?: 'Product';
	}

	private function enrichCategoryParams(array $params, array $state, string $user_message, string $context_message): array {
		if (!empty($params['category_id']) || !empty($params['category_name']) || !empty($params['name'])) {
			return $params;
		}

		if (!empty($state['selected_category_id'])) {
			$params['category_id'] = (int)$state['selected_category_id'];

			return $params;
		}

		foreach ([$user_message, $context_message, $state['last_user_message'] ?? '', $state['pending_confirm_message'] ?? ''] as $text) {
			$name = IntentHelper::extractCategoryName($text);

			if ($name !== '') {
				$params['category_name'] = $name;
				return $params;
			}
		}

		return $params;
	}

	private function enrichProductParams(array $params, array $state, string $user_message, string $context_message): array {
		if (!empty($params['product_id']) || !empty($params['product_name']) || !empty($params['name'])) {
			return $params;
		}

		if (!empty($state['selected_product_id'])) {
			$params['product_id'] = (int)$state['selected_product_id'];

			return $params;
		}

		foreach ([$user_message, $context_message, $state['last_user_message'] ?? ''] as $text) {
			$name = IntentHelper::extractProductName($text);

			if ($name !== '') {
				$params['product_name'] = $name;
				return $params;
			}
		}

		return $params;
	}

	private function isCategoryAction(string $action): bool {
		return str_contains($action, 'category') || in_array($action, ['list_categories', 'search_categories', 'get_category'], true);
	}

	private function isProductAction(string $action): bool {
		return str_contains($action, 'product') || in_array($action, [
			'list_products', 'search_products', 'get_product', 'update_quantity', 'change_category', 'change_manufacturer'
		], true);
	}
}
