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

		if (self::isCategoryQuery($message) && !str_contains($text, 'product')) {
			return false;
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

		return (bool)preg_match('/\b(categor(y|ies))\b/', $text);
	}

	public static function productListMessage(string $operation): string {
		return match ($operation) {
			'delete' => 'Select a product to delete:',
			'create' => 'Say "add product" with details, or upload a CSV to import.',
			'read'   => 'Here are your products:',
			default  => 'Select a product to update:',
		};
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
}
