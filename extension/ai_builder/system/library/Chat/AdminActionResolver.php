<?php
namespace Opencart\System\Library\Extension\AiBuilder\Chat;

use Opencart\System\Library\Extension\AiBuilder\Prompt\AdminChatTraining;

class AdminActionResolver {
	private object $registry;
	private ActionExecutor $executor;

	public function __construct(object $registry, ActionExecutor $executor) {
		$this->registry = $registry;
		$this->executor = $executor;
	}

	public function tryResolve(string $message, array $state): ?array {
		if (IntentHelper::isProductQuery($message) || IntentHelper::isCategoryQuery($message)) {
			return null;
		}

		if (IntentHelper::isBannerQuery($message)) {
			return null;
		}

		if (IntentHelper::isHelpQuery($message)) {
			return [
				'success' => true,
				'message' => AdminChatTraining::buildHelpMessage() . "\n\n" . \Opencart\System\Library\Extension\AiBuilder\Admin\AdminPanelMap::toHelpMessage(),
				'ui'      => ['type' => 'text'],
				'intent'  => 'help'
			];
		}

		if (IntentHelper::isAdminModulesQuery($message)) {
			return $this->executor->execute('list_admin_modules', [], $state);
		}

		if (IntentHelper::isOrderStatusChangeQuery($message, $state)) {
			$params = IntentHelper::parseOrderStatusChange($message, $state);

			if ($params) {
				return $this->executor->execute('change_order_status', $params, $state);
			}

			return [
				'success'       => true,
				'message'       => 'Tell me the order number and new status. Example: change order 1 status to Processing',
				'needs_input'   => true,
				'pending_field' => 'order_status',
				'pending_action' => 'change_order_status',
				'ui'            => ['type' => 'text'],
				'state'         => array_merge($state, [
					'pending_action' => 'change_order_status',
					'pending_field'  => 'order_status',
					'entity_type'    => 'order'
				])
			];
		}

		if (IntentHelper::isOrderQuery($message)) {
			if (IntentHelper::isOrderSummaryQuery($message)) {
				return $this->executor->execute('get_orders_today', [], $state);
			}

			return $this->executor->execute(
				'view_orders',
				IntentHelper::parseOrderListParams($message, $state),
				$state
			);
		}

		if (IntentHelper::isCustomerQuery($message)) {
			return $this->resolveCustomerSearch($message, $state);
		}

		if (IntentHelper::isCouponQuery($message)) {
			return $this->resolveCouponCreate($message, $state);
		}

		if (IntentHelper::isBulkPriceQuery($message)) {
			$params = IntentHelper::parseBulkPriceChange($message);

			if ($params) {
				return $this->executor->execute('bulk_price_update', $params, $state);
			}
		}

		if (IntentHelper::isDisableOutOfStockQuery($message)) {
			return $this->executor->execute('disable_out_of_stock', [], $state);
		}

		if (IntentHelper::isImageLibraryQuery($message)) {
			$query = IntentHelper::parseImageSearchQuery($message);

			return $this->executor->execute('search_images', ['query' => $query], $state);
		}

		if ($setting = IntentHelper::parseStoreSettingUpdate($message)) {
			return $this->executor->execute('update_settings', $setting, $state);
		}

		if (IntentHelper::isLogoUpdateQuery($message)) {
			return [
				'success'     => true,
				'message'     => 'Upload the new store logo image.',
				'needs_input' => true,
				'pending_field' => 'image',
				'pending_action' => 'update_logo',
				'ui'          => ['type' => 'upload', 'accepted' => 'image/*'],
				'state'       => array_merge($state, [
					'step'           => 'awaiting_logo',
					'pending_action' => 'update_logo',
					'pending_field'  => 'image'
				])
			];
		}

		if (IntentHelper::isExportProductsQuery($message)) {
			return $this->executor->execute('export_products', [], $state);
		}

		if (IntentHelper::isLowStockQuery($message)) {
			return $this->executor->execute('low_stock_alerts', [], $state);
		}

		if (IntentHelper::isProductsWithoutImagesQuery($message)) {
			return $this->executor->execute('products_without_images', [], $state);
		}

		return null;
	}

	private function resolveCustomerSearch(string $message, array $state): ?array {
		$query = IntentHelper::parseCustomerSearchQuery($message);

		if ($query === '') {
			return [
				'success'       => true,
				'message'       => 'Enter a customer name or email to search.',
				'needs_input'   => true,
				'pending_field' => 'query',
				'pending_action' => 'search_customers',
				'ui'            => ['type' => 'text'],
				'state'         => array_merge($state, [
					'pending_action' => 'search_customers',
					'pending_field'  => 'query',
					'entity_type'    => 'customer'
				])
			];
		}

		return $this->executor->execute('search_customers', ['query' => $query], $state);
	}

	private function resolveCouponCreate(string $message, array $state): ?array {
		$params = IntentHelper::parseCouponParams($message);

		if (empty($params['discount']) && empty($params['code'])) {
			return [
				'success'       => true,
				'message'       => 'What discount should the coupon offer? For example: "10% off" or code "SAVE20" with 15% discount.',
				'needs_input'   => true,
				'pending_field' => 'discount',
				'pending_action' => 'create_coupon',
				'ui'            => ['type' => 'text'],
				'state'         => array_merge($state, [
					'pending_action' => 'create_coupon',
					'pending_field'  => 'discount',
					'entity_type'    => 'coupon'
				])
			];
		}

		return $this->executor->execute('create_coupon', $params, $state);
	}
}
