<?php
namespace Opencart\System\Library\Extension\AiBuilder\Admin;

class AdminPanelMap {
	public static function modules(): array {
		return [
			'Catalog' => [
				['route' => 'catalog/product', 'permission' => 'catalog/product', 'methods' => ['getProducts', 'getProduct', 'getTotalProducts', 'addProduct', 'editProduct', 'deleteProduct', 'copyProduct']],
				['route' => 'catalog/category', 'permission' => 'catalog/category', 'methods' => ['getCategories', 'getCategory', 'getTotalCategories', 'addCategory', 'editCategory', 'deleteCategory']],
				['route' => 'catalog/manufacturer', 'permission' => 'catalog/manufacturer', 'methods' => ['getManufacturers', 'getManufacturer', 'addManufacturer', 'editManufacturer', 'deleteManufacturer']],
				['route' => 'catalog/option', 'permission' => 'catalog/option', 'methods' => ['getOptions', 'getOption', 'addOption', 'editOption', 'deleteOption']],
				['route' => 'catalog/filter', 'permission' => 'catalog/filter', 'methods' => ['getFilters', 'getFilter', 'addFilter', 'editFilter', 'deleteFilter']],
				['route' => 'catalog/attribute', 'permission' => 'catalog/attribute', 'methods' => ['getAttributes', 'getAttribute', 'addAttribute', 'editAttribute', 'deleteAttribute']],
				['route' => 'catalog/review', 'permission' => 'catalog/review', 'methods' => ['getReviews', 'getReview', 'addReview', 'editReview', 'deleteReview']],
				['route' => 'catalog/information', 'permission' => 'catalog/information', 'methods' => ['getInformations', 'getInformation', 'addInformation', 'editInformation', 'deleteInformation']],
			],
			'Sales' => [
				['route' => 'sale/order', 'permission' => 'sale/order', 'methods' => ['getOrders', 'getOrder', 'getTotalOrders', 'getHistories', 'getProducts', 'getTotals']],
				['route' => 'sale/subscription', 'permission' => 'sale/subscription', 'methods' => ['getSubscriptions', 'getSubscription', 'getTotalSubscriptions']],
				['route' => 'sale/returns', 'permission' => 'sale/returns', 'methods' => ['getReturns', 'getReturn', 'getTotalReturns', 'addReturn', 'editReturn']],
			],
			'Customers' => [
				['route' => 'customer/customer', 'permission' => 'customer/customer', 'methods' => ['getCustomers', 'getCustomer', 'getTotalCustomers', 'addCustomer', 'editCustomer', 'deleteCustomer']],
				['route' => 'customer/customer_group', 'permission' => 'customer/customer_group', 'methods' => ['getCustomerGroups', 'getCustomerGroup', 'addCustomerGroup', 'editCustomerGroup', 'deleteCustomerGroup']],
				['route' => 'customer/custom_field', 'permission' => 'customer/custom_field', 'methods' => ['getCustomFields', 'getCustomField', 'addCustomField', 'editCustomField', 'deleteCustomField']],
			],
			'Marketing' => [
				['route' => 'marketing/coupon', 'permission' => 'marketing/coupon', 'methods' => ['getCoupons', 'getCoupon', 'getTotalCoupons', 'addCoupon', 'editCoupon', 'deleteCoupon']],
				['route' => 'marketing/marketing', 'permission' => 'marketing/marketing', 'methods' => ['getMarketings', 'getMarketing', 'addMarketing', 'editMarketing', 'deleteMarketing']],
				['route' => 'marketing/affiliate', 'permission' => 'marketing/affiliate', 'methods' => ['getAffiliates', 'getAffiliate', 'addAffiliate', 'editAffiliate', 'deleteAffiliate']],
			],
			'Design' => [
				['route' => 'design/banner', 'permission' => 'design/banner', 'methods' => ['getBanners', 'getBanner', 'addBanner', 'editBanner', 'deleteBanner']],
				['route' => 'design/layout', 'permission' => 'design/layout', 'methods' => ['getLayouts', 'getLayout', 'addLayout', 'editLayout', 'deleteLayout']],
				['route' => 'design/seo_url', 'permission' => 'design/seo_url', 'methods' => ['getSeoUrls', 'getSeoUrl', 'addSeoUrl', 'editSeoUrl', 'deleteSeoUrl']],
				['route' => 'design/theme', 'permission' => 'design/theme', 'methods' => ['getThemes', 'getTheme']],
			],
			'CMS' => [
				['route' => 'cms/article', 'permission' => 'cms/article', 'methods' => ['getArticles', 'getArticle', 'addArticle', 'editArticle', 'deleteArticle']],
				['route' => 'cms/topic', 'permission' => 'cms/topic', 'methods' => ['getTopics', 'getTopic', 'addTopic', 'editTopic', 'deleteTopic']],
			],
			'Localisation' => [
				['route' => 'localisation/order_status', 'permission' => 'localisation/order_status', 'methods' => ['getOrderStatuses', 'getOrderStatus']],
				['route' => 'localisation/stock_status', 'permission' => 'localisation/stock_status', 'methods' => ['getStockStatuses', 'getStockStatus']],
				['route' => 'localisation/country', 'permission' => 'localisation/country', 'methods' => ['getCountries', 'getCountry']],
				['route' => 'localisation/currency', 'permission' => 'localisation/currency', 'methods' => ['getCurrencies', 'getCurrency']],
				['route' => 'localisation/language', 'permission' => 'localisation/language', 'methods' => ['getLanguages', 'getLanguage']],
			],
			'Reports' => [
				['route' => 'report/statistics', 'permission' => 'report/statistics', 'methods' => ['getStatistics']],
				['route' => 'report/report', 'permission' => 'report/report', 'methods' => ['getReports']],
			],
			'Settings' => [
				['route' => 'setting/store', 'permission' => 'setting/store', 'methods' => ['getStores', 'getStore', 'getTotalStores']],
			],
		];
	}

	public static function toPromptSection(): string {
		$lines = ['ADMIN MODEL BRIDGE — call OpenCart admin models directly via action=admin_model_call'];
		$lines[] = 'params: { "route": "catalog/product", "method": "getProducts", "args": [{"limit": 20}] }';
		$lines[] = 'Use when no dedicated chat action exists. Respects admin user permissions.';
		$lines[] = '';

		foreach (self::modules() as $section => $items) {
			$lines[] = '## ' . $section;

			foreach ($items as $item) {
				$methods = implode(', ', array_slice($item['methods'], 0, 6));
				$lines[] = '- ' . $item['route'] . ' → ' . $methods;
			}

			$lines[] = '';
		}

		return implode("\n", $lines);
	}

	public static function toHelpMessage(): string {
		$lines = [
			'**Admin panel model access** — the chat can call OpenCart admin models (same as admin panel):',
			'',
			'Say **list admin modules** to see routes, or use natural language — dedicated shortcuts still work for products, orders, categories, etc.',
			'',
			'Advanced (AI uses automatically when needed):',
			'```',
			'route: catalog/product',
			'method: getProducts',
			'args: [{"limit": 20}]',
			'```',
			'',
			'**Available admin areas:**'
		];

		foreach (self::modules() as $section => $items) {
			$lines[] = '';
			$lines[] = '**' . $section . '**';

			foreach ($items as $item) {
				$lines[] = '• ' . $item['route'] . ' (' . implode(', ', array_slice($item['methods'], 0, 4)) . '...)';
			}
		}

		$lines[] = '';
		$lines[] = 'Write actions (add/edit/delete) require your admin account to have modify permission on that section.';

		return implode("\n", $lines);
	}

	public static function findRoute(string $route): ?array {
		foreach (self::modules() as $items) {
			foreach ($items as $item) {
				if ($item['route'] === $route) {
					return $item;
				}
			}
		}

		return null;
	}
}
