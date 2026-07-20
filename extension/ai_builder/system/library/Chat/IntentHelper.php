<?php
namespace Opencart\System\Library\Extension\AiBuilder\Chat;

class IntentHelper {
	public static function detectOperation(string $message, array $state = []): string {
		$text = strtolower(trim($message));

		if (preg_match('/\b(delete|remove|drop|unpublish)\b/', $text)) {
			return 'delete';
		}

		if (preg_match('/\b(create|add new|add a new|add product|add category|new product|new category|new banner|insert)\b/', $text)) {
			return 'create';
		}

		if (preg_match('/\b(edit|update|change|replace|modify)\b/', $text)) {
			return 'update';
		}

		if (preg_match('/\b(list|show|view|display|get)\b/', $text)) {
			return 'read';
		}

		return $state['operation'] ?? 'update';
	}

	public static function isBannerQuery(string $message): bool {
		$text = strtolower(trim($message));

		$keywords = [
			'banner', 'slider', 'slideshow', 'carousel', 'hero'
		];

		foreach ($keywords as $keyword) {
			if (str_contains($text, $keyword)) {
				return true;
			}
		}

		return false;
	}

	public static function isProductQuery(string $message): bool {
		$text = strtolower(trim($message));

		if (self::wantsProductImageUpdate($message)) {
			return true;
		}

		if (self::isCategoryQuery($message) && !str_contains($text, 'product')) {
			return false;
		}

		if (self::isProductStatusMessage($message)) {
			return true;
		}

		$keywords = ['product', 'sku', 'stock', 'low-stock', 'low stock'];

		foreach ($keywords as $keyword) {
			if (str_contains($text, $keyword)) {
				return true;
			}
		}

		return false;
	}

	public static function isCategoryQuery(string $message): bool {
		$text = strtolower(trim($message));

		if (preg_match('/\b(categor(?:y|ies)|catgeor(?:y|ies)|chetageor(?:y|ies))\b/', $text)) {
			return true;
		}

		if ((self::wantsCategoryStatusChange($message) || self::wantsCreate($message) || self::wantsDelete($message))
			&& preg_match('/\btest\s*\d+\b/i', $message)) {
			return true;
		}

		return false;
	}

	public static function detectEntityType(string $message, array $state = []): ?string {
		$has_product = self::isProductQuery($message);
		$has_category = self::isCategoryQuery($message);

		if ($has_product && !$has_category) {
			return 'product';
		}

		if ($has_category && !$has_product) {
			return 'category';
		}

		if (!empty($state['entity_type']) && in_array($state['entity_type'], ['product', 'category'], true)) {
			return $state['entity_type'];
		}

		if (!empty($state['selected_product_id']) && empty($state['selected_category_id'])) {
			return 'product';
		}

		if (!empty($state['selected_category_id'])) {
			return 'category';
		}

		if ($has_category) {
			return 'category';
		}

		if ($has_product) {
			return 'product';
		}

		return null;
	}

	public static function detectEntityAction(string $message, string $entity, array $state = []): ?string {
		if (preg_match('/\b(?:list|show|view|display|get)\b/i', $message) && !preg_match('/\bto\b/i', $message)) {
			return 'list';
		}

		if (self::wantsCreate($message)) {
			return 'create';
		}

		if (self::wantsDelete($message)) {
			return 'delete';
		}

		if ($entity === 'category' && self::isCategoryStatusMessage($message)) {
			return 'status';
		}

		if ($entity === 'product' && self::isProductStatusMessage($message)) {
			return 'status';
		}

		if (preg_match('/\b(?:rename|change\s+(?:the\s+)?name)\b/i', $message) || self::parseRename($message)['new_name'] !== '') {
			return 'rename';
		}

		if (preg_match('/\b(?:duplicate|copy)\b/i', $message)) {
			return 'duplicate';
		}

		if (preg_match('/\b(?:special|sale)\s*price\b/i', $message)) {
			return 'update_special';
		}

		if (preg_match('/\b(?:price)\b/i', $message) && self::parseMoneyValue($message) !== null) {
			return 'update_price';
		}

		if (preg_match('/\b(?:quantity|stock|qty)\b/i', $message) && self::parseIntegerValue($message) !== null) {
			return 'update_quantity';
		}

		if (self::wantsProductImageUpdate($message)) {
			return 'update_image';
		}

		if (self::detectOperation($message, $state) === 'update' && preg_match('/\b(?:edit|update|change|modify)\b/i', $message)) {
			return 'update';
		}

		return null;
	}

	public static function wantsCreate(string $message): bool {
		return (bool)preg_match('/\b(add|create|new|insert)\b/i', $message);
	}

	public static function wantsDelete(string $message): bool {
		return (bool)preg_match('/\b(delete|remove|drop)\b/i', $message);
	}

	public static function isProductStatusMessage(string $message): bool {
		if (self::isCategoryQuery($message) && !preg_match('/\bproducts?\b/i', $message)) {
			return false;
		}

		if (!self::wantsEnable($message) && !self::wantsDisable($message)
			&& !preg_match('/\b(?:change|update|toggle|set|switch)\s+(?:the\s+)?status\b/i', $message)) {
			return false;
		}

		$text = strtolower(trim($message));

		return self::wantsProductImageUpdate($message)
			|| (bool)preg_match('/\b(product|sku|stock|low-stock|low stock)\b/', $text)
			|| self::extractProductName($message) !== '';
	}

	public static function inferProductStatusAction(string $message, array $state = []): string {
		if (self::wantsEnable($message)) {
			return 'enable_product';
		}

		if (self::wantsDisable($message)) {
			return 'disable_product';
		}

		$name = self::extractProductName($message);

		foreach ($state['products'] ?? [] as $product) {
			if ($name && self::matchesEntityName($product['name'] ?? '', $name)) {
				return !empty($product['status']) ? 'disable_product' : 'enable_product';
			}
		}

		return 'disable_product';
	}

	public static function extractProductName(string $message): string {
		if (preg_match("/['\"]([^'\"]+)['\"]/", $message, $matches)) {
			return trim($matches[1]);
		}

		$text = trim($message);
		$text = preg_replace('/^(?:please\s+)?(?:i\s+)?(?:want\s+to\s+|want\s+)?/i', '', $text) ?? $text;
		$text = preg_replace('/\b(?:change|update|toggle|set|switch)\s+(?:the\s+)?status\s+of\s+/i', '', $text) ?? $text;
		$text = preg_replace('/\b(?:rename|duplicate|copy|delete|remove|disable|enable|edit|update)\s+(?:the\s+)?(?:product\s+)?/i', '', $text) ?? $text;
		$text = preg_replace('/\b(?:product|products)\s*/i', '', $text) ?? $text;
		$text = preg_replace('/\s+(?:product|products)\s*$/i', '', $text) ?? $text;
		$text = preg_replace('/\s+(?:price|quantity|stock|to\s+.+)$/i', '', $text) ?? $text;

		if (preg_match('/\bto\s+(.+)$/i', $text, $matches)) {
			$text = preg_replace('/\s+to\s+.+$/i', '', $text) ?? $text;
		}

		return trim($text, " \t\n\r\0\x0B\"'");
	}

	public static function extractCreateName(string $message, string $entity): string {
		if (preg_match("/(?:named|called)\s+['\"]?([^'\"\.]+?)['\"]?\.?$/i", $message, $matches)) {
			return trim($matches[1]);
		}

		$text = trim($message);
		$text = preg_replace('/^(?:please\s+)?(?:i\s+)?(?:want\s+to\s+|want\s+)?(?:add|create|new|insert)\s+(?:a\s+)?(?:new\s+)?/i', '', $text) ?? $text;
		$text = preg_replace('/\b(?:product|products|category|categories)\s*/i', '', $text, 1) ?? $text;
		$text = preg_replace('/\s+(?:product|products|category|categories)\s*$/i', '', $text) ?? $text;
		$text = preg_replace('/\b(?:with|price|quantity|stock)\b.+/i', '', $text) ?? $text;

		$name = trim($text, " \t\n\r\0\x0B\"'");

		if ($name === '' || preg_match('/^(?:product|category|products|categories)$/i', $name)) {
			return '';
		}

		return $name;
	}

	public static function parseRename(string $message): array {
		if (preg_match('/\b(?:rename|change\s+(?:the\s+)?name\s+of)\s+(?:the\s+)?(?:(?:product|category)\s+)?(.+?)\s+to\s+(.+)/i', $message, $matches)) {
			return [
				'old_name' => trim($matches[1], " \t\n\r\0\x0B\"'"),
				'new_name' => trim($matches[2], " \t\n\r\0\x0B\"'.")
			];
		}

		return ['old_name' => '', 'new_name' => ''];
	}

	public static function parseMoneyValue(string $message): ?float {
		if (preg_match('/(?:price|special|sale|cost)\s*(?:to|=|:)?\s*[\$₹]?\s*(\d+(?:\.\d+)?)/i', $message, $matches)) {
			return (float)$matches[1];
		}

		if (preg_match('/[\$₹]\s*(\d+(?:\.\d+)?)/', $message, $matches)) {
			return (float)$matches[1];
		}

		return null;
	}

	public static function parseIntegerValue(string $message, string $label_pattern = 'quantity|stock|qty|sort|order'): ?int {
		if (preg_match('/(?:' . $label_pattern . ')\s*(?:to|=|:)?\s*(\d+)/i', $message, $matches)) {
			return (int)$matches[1];
		}

		if (preg_match('/\b(\d+)\s*(?:units|pcs|items)?\s*$/i', trim($message), $matches) && preg_match('/(?:quantity|stock|qty|sort)/i', $message)) {
			return (int)$matches[1];
		}

		return null;
	}

	public static function isAffirmativeOnly(string $message): bool {
		return (bool)preg_match('/^(?:yes|y|yeah|yep|ok|okay|sure|confirm|proceed|go ahead)\.?$/i', trim($message));
	}

	public static function findProductId(array $products, string $query): int {
		foreach ($products as $product) {
			$name = $product['name'] ?? '';

			if (self::matchesEntityName($name, $query)) {
				return (int)($product['id'] ?? $product['product_id'] ?? 0);
			}
		}

		return 0;
	}

	public static function productListMessage(string $operation): string {
		return match ($operation) {
			'delete' => 'Select a product to delete:',
			'create' => 'Say "add product" with details, or upload a CSV to import.',
			'read'   => 'Here are your products:',
			default  => 'Select a product to update:',
		};
	}

	/**
	 * cards = image grid, table = text table without images
	 */
	public static function parseDisplayFormatPreference(string $message): ?string {
		$text = strtolower(trim($message));

		if (preg_match('/\bwithout\s+images?\b/', $text)) {
			return 'table';
		}

		if (preg_match('/\b(with\s+images?|show\s+images?|include\s+images?|image\s+view|card\s+view|grid\s+view|as\s+cards?|in\s+cards?)\b/', $text)) {
			return 'cards';
		}

		if (preg_match('/\btable\b|\btabular\b|\bin\s+table(?:\s+format|\s+form)?\b|\btable\s+form\b|\bas\s+list\b|\blist\s+format\b/', $text)) {
			return 'table';
		}

		return null;
	}

	public static function listDisplayFormat(string $message, string $default = 'cards'): string {
		return self::parseDisplayFormatPreference($message) ?? $default;
	}

	public static function productDisplayFormat(string $message): string {
		return self::listDisplayFormat($message, 'cards');
	}

	public static function categoryDisplayFormat(string $message): string {
		return self::listDisplayFormat($message, 'table');
	}

	public static function extractCategoryName(string $message): string {
		if (preg_match("/['\"]([^'\"]+)['\"]/", $message, $matches)) {
			return trim($matches[1]);
		}

		if (preg_match('/\bcategory\s+([^\.\?!]+)$/iu', $message, $matches)) {
			return trim($matches[1], " \t\n\r\0\x0B\"'");
		}

		$text = trim($message);
		$text = preg_replace('/^(?:please\s+)?(?:i\s+)?(?:want\s+to\s+|want\s+)?/i', '', $text) ?? $text;
		$text = preg_replace('/\b(?:change|chnage|update|toggle|set|switch)\s+(?:the\s+)?status\s+of\s+/i', '', $text) ?? $text;
		$text = preg_replace('/^(?:disable|enable|delete|edit|update|show|list|get|change|chnage)\s+(?:the\s+)?/i', '', $text) ?? $text;
		$text = preg_replace('/\b(?:the\s+)?(?:category|categories|catgeory|chetageory|catgeories)\b\s*/i', '', $text) ?? $text;
		$text = preg_replace('/\s+(?:category|categories|catgeory|chetageory|catgeories)\s*$/i', '', $text) ?? $text;
		$text = preg_replace('/\s+(?:please|thanks|thank\s+you)\.?$/i', '', $text) ?? $text;

		return trim($text, " \t\n\r\0\x0B\"'");
	}

	public static function isCategoryStatusMessage(string $message): bool {
		if (preg_match('/\bproducts?\b/i', $message)) {
			return false;
		}

		if (!self::wantsCategoryStatusChange($message)) {
			return false;
		}

		if (self::isCategoryQuery($message)) {
			return true;
		}

		return self::extractCategoryName($message) !== '';
	}

	public static function wantsCategoryStatusChange(string $message): bool {
		if (self::wantsDisable($message) || self::wantsEnable($message)) {
			return true;
		}

		return (bool)preg_match('/\b(?:change|chnage|update|toggle|set|switch)\s+(?:the\s+)?status\b/i', $message);
	}

	public static function inferCategoryStatusAction(string $message, array $state = []): string {
		if (self::wantsEnable($message)) {
			return 'enable_category';
		}

		if (self::wantsDisable($message)) {
			return 'disable_category';
		}

		$name = self::extractCategoryName($message);

		if ($name) {
			foreach ($state['categories'] ?? [] as $category) {
				if (self::matchesEntityName($category['name'] ?? '', $name)) {
					return !empty($category['status']) ? 'disable_category' : 'enable_category';
				}
			}

			$leaf = preg_replace('/^.*>\s*/', '', $name) ?: $name;

			if ($leaf !== $name) {
				foreach ($state['categories'] ?? [] as $category) {
					if (self::matchesEntityName($category['name'] ?? '', $leaf)) {
						return !empty($category['status']) ? 'disable_category' : 'enable_category';
					}
				}
			}
		}

		return 'disable_category';
	}

	public static function wantsDisable(string $message): bool {
		return (bool)preg_match('/\b(disable|deactivate|turn\s+off|hide)\b/i', $message);
	}

	public static function wantsEnable(string $message): bool {
		return (bool)preg_match('/\b(enable|activate|turn\s+on|publish)\b/i', $message);
	}

	public static function normalizeMatchText(string $text): string {
		$text = html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return preg_replace('/[\s_\-]+/', '', strtolower($text));
	}

	public static function matchesEntityName(string $entity_name, string $query): bool {
		$entity_name = html_entity_decode(trim($entity_name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$query = trim($query);

		if ($query === '') {
			return false;
		}

		if (strcasecmp($entity_name, $query) === 0) {
			return true;
		}

		$last_segment = preg_replace('/^.*>\s*/', '', $entity_name) ?: $entity_name;

		if (strcasecmp($last_segment, $query) === 0) {
			return true;
		}

		if (self::normalizeMatchText($last_segment) === self::normalizeMatchText($query)) {
			return true;
		}

		return stripos($entity_name, $query) !== false;
	}

	public static function findCategoryId(array $categories, string $query): int {
		foreach ($categories as $category) {
			$name = $category['name'] ?? '';

			if (self::matchesEntityName($name, $query)) {
				return (int)($category['id'] ?? 0);
			}
		}

		return 0;
	}

	public static function categoryListMessage(string $operation): string {
		return match ($operation) {
			'delete' => 'Select a category to delete:',
			'create' => 'Say "create category" with a name to add a new category.',
			'read'   => 'Here are your categories:',
			default  => 'Select a category to update:',
		};
	}

	public static function wantsDeleteEntireBanner(string $message): bool {
		$text = strtolower(trim($message));

		return (bool)preg_match('/\b(entire|whole|all slides|all|complete)\b/', $text);
	}

	public static function parseInsertPosition(string $message): ?int {
		$text = strtolower(trim($message));

		if (preg_match('/\b(first|1st|top|beginning|start)\b(?:\s*(position|place|spot|slide))?/', $text)) {
			return 0;
		}

		if (preg_match('/\b(second|2nd)\b(?:\s*(position|place|spot|slide))?/', $text)) {
			return 1;
		}

		if (preg_match('/\b(third|3rd)\b(?:\s*(position|place|spot|slide))?/', $text)) {
			return 2;
		}

		if (preg_match('/\bposition\s*#?\s*(\d+)\b/', $text, $matches)) {
			return max(0, (int)$matches[1] - 1);
		}

		if (preg_match('/\bat\s*(\d+)\b/', $text, $matches)) {
			return max(0, (int)$matches[1] - 1);
		}

		return null;
	}

	public static function positionLabel(int $position): string {
		return match ($position) {
			0       => 'first',
			1       => 'second',
			2       => 'third',
			default => ($position + 1) . 'th',
		};
	}

	public static function bannerListMessage(string $operation, int $insert_position = 0): string {
		return match ($operation) {
			'delete' => 'Select a banner, then choose a slide to delete:',
			'create' => 'Select a banner to add a new slide to. The new slide will appear in the '
				. self::positionLabel($insert_position) . ' position (existing slides shift down). Or say "create new banner":',
			'read'   => 'Here are your banners:',
			default  => 'Select a banner, then choose a slide to update:',
		};
	}

	public static function bannerSlidesMessage(string $operation, int $insert_position = 0): string {
		return match ($operation) {
			'delete' => 'Select a slide to delete:',
			'create' => 'Upload an image for the new slide. It will be placed in the '
				. self::positionLabel($insert_position) . ' position:',
			'read'   => 'Slides in this banner:',
			default  => 'Select a slide to replace:',
		};
	}

	public static function isHelpQuery(string $message): bool {
		$text = strtolower(trim($message));

		return (bool)preg_match('/\b(help|commands|capabilities|what can you do|show all commands|list commands|admin actions|available actions)\b/', $text);
	}

	public static function isAdminModulesQuery(string $message): bool {
		$text = strtolower(trim($message));

		return (bool)preg_match('/\b(list admin modules|admin panel access|admin model list|admin models|show admin routes)\b/', $text);
	}

	public static function wantsProductImageUpdate(string $message): bool {
		if (!preg_match('/\b(image|images|photo|photos|picture|pictures|thumbnail)\b/i', $message)) {
			return false;
		}

		return (bool)preg_match('/\b(change|chanege|chnage|chane|changes|update|replace|upload|set|new|add)\b/i', $message);
	}

	public static function extractProductNameFromImageRequest(string $message): string {
		$complaint = self::extractProductNameFromImageComplaint($message);

		if ($complaint !== '') {
			return $complaint;
		}

		$text = trim($message);
		$text = preg_replace('/\b(?:i\s+)?(?:want\s+to\s+|want\s+|please\s+)?(?:change|chanege|chnage|chane|update|replace|upload|set)\s+(?:the\s+)?(?:product\s+)?(?:image|images|photo|photos|picture|pictures)\s+(?:for|of)\s+/i', '', $text) ?? $text;
		$text = preg_replace('/\b(?:for|of)\s+(?:this\s+)?(?:product\s+)?(.+)$/i', '$1', $text) ?? $text;
		$text = preg_replace('/\b(?:change|update|replace|upload).*(?:image|photo|picture).*$/i', '', $text) ?? $text;

		return trim($text, " \t\n\r\0\x0B\"'");
	}

	public static function extractProductNameFromImageComplaint(string $message): string {
		$text = trim($message);

		if (preg_match('/^(.+?)\s+this\s+product\s+(?:image|photo|picture)\b/i', $text, $matches)) {
			return trim($matches[1], " \t\n\r\0\x0B\"'");
		}

		if (preg_match('/^(.+?)\s+(?:product\s+)?(?:image|photo|picture)\s+(?:not|didn\'t|doesn\'t|isn\'t|won\'t|hasn\'t)\s+/i', $text, $matches)) {
			return trim($matches[1], " \t\n\r\0\x0B\"'");
		}

		if (preg_match('/^(.+?)\s+(?:image|photo|picture)\s+(?:not|didn\'t|doesn\'t|isn\'t|won\'t|hasn\'t)\s+(?:updat|chang)/i', $text, $matches)) {
			return trim($matches[1], " \t\n\r\0\x0B\"'");
		}

		return '';
	}

	public static function isOrderQuery(string $message, array $state = []): bool {
		$text = strtolower(trim($message));

		if (self::isOrderStatusChangeQuery($message, $state)) {
			return false;
		}

		if (preg_match('/\b(pre[\s-]?order|reorder)\b/', $text)) {
			return false;
		}

		return (bool)preg_match('/\b(orders?|order[\s-]?detai\w*|order[\s-]?list)\b/', $text)
			|| (bool)preg_match('/\b(revenue|sales)\s+today\b/', $text)
			|| (bool)preg_match("/today'?s?\s+orders?/", $text);
	}

	public static function isOrderStatusChangeQuery(string $message, array $state = []): bool {
		$text = strtolower(trim($message));
		$in_order_context = !empty($state['selected_order_id'])
			|| !empty($state['orders'])
			|| ($state['entity_type'] ?? '') === 'order'
			|| ($state['step'] ?? '') === 'order_selected';

		if (preg_match('/\b(?:change|chnage|chnages|changes|update|set|mark)\s+(?:the\s+)?status\b/', $text)) {
			return true;
		}

		if (preg_match('/\bstatus\s+is\s+(?:chnage|chnages|change|changes|update|set)\b/', $text)) {
			return true;
		}

		if ($in_order_context
			&& preg_match('/\b(?:change|update|set|mark)\b/', $text)
			&& preg_match('/\b(?:to|as)\s+[a-z]/', $text)) {
			return true;
		}

		if (!preg_match('/\border\b/', $text)) {
			return false;
		}

		if (preg_match('/\b(status|statu)\b/', $text)
			&& preg_match('/\b(change|chnage|chnages|changes|update|set|mark)\b/', $text)) {
			return true;
		}

		return (bool)preg_match('/\b(?:mark|change|update|set)\s+order\b/', $text)
			&& (bool)preg_match('/\b(?:to|as)\s+[a-z]/', $text);
	}

	public static function parseOrderStatusChange(string $message, array $state = []): ?array {
		$params = [];

		if (preg_match('/\border\s+id\s*[:=]?\s*(\d+)\b/i', $message, $m)) {
			$params['order_id'] = (int)$m[1];
		} elseif (preg_match('/\border\s+(?:is\s+|#|id\s*[:=]?\s*)?(\d+)\b/i', $message, $m)) {
			$params['order_id'] = (int)$m[1];
		} elseif (preg_match('/\border\s+(\d+)\b/i', $message, $m)) {
			$params['order_id'] = (int)$m[1];
		} elseif (!empty($state['selected_order_id'])) {
			$params['order_id'] = (int)$state['selected_order_id'];
		} elseif (!empty($state['orders']) && count($state['orders']) === 1) {
			$params['order_id'] = (int)($state['orders'][0]['id'] ?? $state['orders'][0]['order_id'] ?? 0);
		}

		if (preg_match('/\b(?:status|statu)\s+is\s+(?:chnage|chnages|change|changes|update|set)\s+to\s+[\'"]?([^\'"]+)[\'"]?/i', $message, $m)) {
			$params['status'] = trim($m[1]);
		} elseif (preg_match('/\b(?:change|update|set|mark)\s+(?:the\s+)?status\s+(?:to|as)\s+[\'"]?([^\'"]+)[\'"]?/i', $message, $m)) {
			$params['status'] = trim($m[1]);
		} elseif (preg_match('/\b(?:change|update|set|mark)\s+(?:order\s+)?(?:status\s+)?(?:to|as)\s+[\'"]?([^\'"]+)[\'"]?/i', $message, $m)) {
			$params['status'] = trim($m[1]);
		} elseif (preg_match('/\bto\s+[\'"]?([a-z][a-z\s]*[a-z]|[a-z])[\'"]?\s*$/i', $message, $m)) {
			$params['status'] = trim($m[1]);
		}

		if (empty($params['order_id']) || empty($params['status'])) {
			return null;
		}

		return $params;
	}

	public static function isOrderSummaryQuery(string $message): bool {
		$text = strtolower(trim($message));

		if (preg_match('/\b(table|details?|detai\w*|list|view|show)\b/', $text)) {
			return false;
		}

		return (bool)preg_match('/\b(summary|revenue|how many|count|total sales|stats)\b/', $text);
	}

	public static function parseOrderListParams(string $message, array $state = []): array {
		$params = [
			'display_format' => 'table',
			'limit'          => 50
		];
		$text = strtolower(trim($message));

		if (preg_match('/\btoday\b/', $text) || preg_match("/today'?s/", $text)) {
			$params['date'] = 'today';
		}

		if (preg_match('/\b(cards?|card view)\b/', $text) && !preg_match('/\btable\b/', $text)) {
			$params['display_format'] = 'cards';
		}

		if (preg_match('/\border\s+#?(\d+)\b/i', $message, $m)) {
			$params['query'] = $m[1];
		} elseif (preg_match('/\border\s+id\s*[:=]?\s*(\d+)\b/i', $message, $m)) {
			$params['query'] = $m[1];
		} elseif (preg_match('/\b(?:for|by|customer)\s+(.+)$/i', $message, $m)) {
			$candidate = trim($m[1], " \t\n\r\0\x0B\"'");

			if (!preg_match('/\b(table|list|details?|today)\b/i', $candidate)) {
				$params['query'] = $candidate;
			}
		}

		if (!empty($state['display_format']) && empty($params['display_format'])) {
			$params['display_format'] = $state['display_format'];
		}

		return $params;
	}

	public static function isCustomerQuery(string $message): bool {
		$text = strtolower(trim($message));

		return (bool)preg_match('/\b(customer|customers|find customer|search customer|lookup customer)\b/', $text);
	}

	public static function isCouponQuery(string $message): bool {
		$text = strtolower(trim($message));

		if (preg_match('/\b(voucher|gift card)\b/', $text)) {
			return false;
		}

		return (bool)preg_match('/\b(coupon|discount code|promo code|promotional code)\b/', $text)
			|| (bool)preg_match('/\b(create|add|make|new)\b.*\b(\d+\s*%|percent|off)\b/', $text);
	}

	public static function isBulkPriceQuery(string $message): bool {
		$text = strtolower(trim($message));

		return (bool)preg_match('/\b(increase|decrease|raise|lower|reduce)\b.*\b(price|prices)\b/', $text)
			|| (bool)preg_match('/\bbulk\s+price\b/', $text);
	}

	public static function isDisableOutOfStockQuery(string $message): bool {
		return (bool)preg_match('/\bdisable\b.*\bout of stock\b/i', $message);
	}

	public static function isImageLibraryQuery(string $message): bool {
		$text = strtolower(trim($message));

		return (bool)preg_match('/\b(search|find|list|show)\b.*\b(image|images|photo|photos|media)\b/', $text)
			|| (bool)preg_match('/\b(image library|media library)\b/', $text);
	}

	public static function isLogoUpdateQuery(string $message): bool {
		return (bool)preg_match('/\b(change|update|replace|set)\b.*\b(store\s+)?logo\b/i', $message);
	}

	public static function isExportProductsQuery(string $message): bool {
		return (bool)preg_match('/\bexport\b.*\bproduct/i', $message);
	}

	public static function isLowStockQuery(string $message): bool {
		return (bool)preg_match('/\b(low[\s-]?stock|stock alert|low inventory)\b/i', $message);
	}

	public static function isProductsWithoutImagesQuery(string $message): bool {
		return (bool)preg_match('/\bproduct/i', $message)
			&& (bool)preg_match('/\bwithout\b.*\bimage/i', $message);
	}

	public static function parseCustomerSearchQuery(string $message): string {
		$text = trim($message);

		if (preg_match('/\b(?:find|search|lookup)\s+customer[s]?\s+(?:named?|called|with|email)?\s*(.+)$/i', $text, $m)) {
			return trim($m[1], " \t\n\r\0\x0B\"'");
		}

		if (preg_match('/\bcustomer[s]?\s+(?:named?|called)?\s*(.+)$/i', $text, $m)) {
			$candidate = trim($m[1], " \t\n\r\0\x0B\"'");

			if (!preg_match('/\b(list|show|all|search|find)\b/i', $candidate)) {
				return $candidate;
			}
		}

		if (preg_match('/[\w.+-]+@[\w.-]+\.\w+/', $text, $m)) {
			return $m[0];
		}

		return '';
	}

	public static function parseCouponParams(string $message): array {
		$params = [];
		$text = trim($message);

		if (preg_match('/\bcode\s+([A-Za-z0-9_-]+)/i', $text, $m)) {
			$params['code'] = strtoupper($m[1]);
		}

		if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $text, $m)) {
			$params['discount'] = (float)$m[1];
			$params['type'] = 'P';
		} elseif (preg_match('/\b(?:fixed|flat|amount)\s+(\d+(?:\.\d+)?)/i', $text, $m)) {
			$params['discount'] = (float)$m[1];
			$params['type'] = 'F';
		} elseif (preg_match('/\b(\d+(?:\.\d+)?)\s*(?:off|discount)\b/i', $text, $m)) {
			$params['discount'] = (float)$m[1];
			$params['type'] = 'P';
		} elseif (is_numeric($text)) {
			$params['discount'] = (float)$text;
			$params['type'] = 'P';
		}

		if (preg_match('/\bname\s+(.+)$/i', $text, $m)) {
			$params['name'] = trim($m[1]);
		}

		return $params;
	}

	public static function parseBulkPriceChange(string $message): ?array {
		if (!preg_match('/(\d+(?:\.\d+)?)\s*%/', $message, $m)) {
			return null;
		}

		$percentage = (float)$m[1];
		$operation = preg_match('/\b(decrease|lower|reduce|drop)\b/i', $message) ? 'decrease' : 'increase';

		return [
			'percentage' => $percentage,
			'operation'  => $operation
		];
	}

	public static function parseStoreSettingUpdate(string $message): ?array {
		if (preg_match('/\b(?:change|update|set)\s+store\s+name\s+to\s+(.+)$/i', $message, $m)) {
			return ['config_name' => trim($m[1], " \t\n\r\0\x0B\"'")];
		}

		if (preg_match('/\b(?:change|update|set)\s+(?:store\s+)?(?:meta\s+)?title\s+to\s+(.+)$/i', $message, $m)) {
			return ['config_meta_title' => trim($m[1], " \t\n\r\0\x0B\"'")];
		}

		if (preg_match('/\b(?:change|update|set)\s+meta\s+description\s+to\s+(.+)$/i', $message, $m)) {
			return ['config_meta_description' => trim($m[1], " \t\n\r\0\x0B\"'")];
		}

		return null;
	}

	public static function parseImageSearchQuery(string $message): string {
		if (preg_match('/\b(?:search|find|list|show)\s+(?:for\s+)?(?:image|images|photo|photos|media)\s+(?:named?|called|matching|with)?\s*(.+)$/i', $message, $m)) {
			return trim($m[1], " \t\n\r\0\x0B\"'");
		}

		if (preg_match('/\b(?:search|find)\s+(.+)\s+(?:in\s+)?(?:image|images|media)\b/i', $message, $m)) {
			return trim($m[1], " \t\n\r\0\x0B\"'");
		}

		return '';
	}
}
