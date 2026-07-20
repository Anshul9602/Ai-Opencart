<?php
namespace Opencart\System\Library\Extension\AiBuilder\Prompt;

use Opencart\System\Library\Extension\AiBuilder\Capability\CapabilityRegistry;

class AdminChatTraining {
	public static function build(): string {
		return <<<'TRAINING'

ADMIN PANEL CHAT TRAINING — map every admin task to a capability action ID.
The chat replaces clicking through OpenCart admin menus. Always set "action" for data-changing or data-fetching tasks.

=== OPENCart ADMIN MENU → CHAT COMMANDS ===

## Dashboard & Help
- "help" / "show all commands" / "what can you do" → reply with capability summary (no action)
- "today's orders" / "order summary" / "revenue today" → action=get_orders_today

## Catalog → Products
- "product list" / "show products" / "list products in table form" → action=list_products
- "add product [name]" → action=create_product params.name OR needs_input pending_action=create_product
- "update price of [name] to [amount]" → action=update_product_price
- "set stock of [name] to [qty]" → action=update_quantity
- "special price for [name] [amount]" → action=update_special_price
- "enable/disable product [name]" → action=enable_product|disable_product
- "delete product [name]" → action=delete_product (destructive, confirm)
- "duplicate product [name]" → action=duplicate_product
- "change image for [name]" / "update product image" → upload zone → update_product_images
- "export products" → action=export_products
- "products without images" → action=products_without_images
- "low stock products" → action=low_stock_alerts
- "disable out of stock products" → action=disable_out_of_stock
- "increase all prices by 5%" → action=bulk_price_update params.percentage=5 operation=increase
- "import products csv" → action=import_products_csv (needs file upload)

## Catalog → Categories
- "category list" / "list categories in table form" → action=list_categories
- "add category [name]" → action=create_category
- "rename category [old] to [new]" → action=edit_category
- "enable/disable category [name]" → action=enable_category|disable_category
- "delete category [name]" → action=delete_category (destructive, confirm)
- "change parent of category [name]" → action=parent_category
- "sort category [name]" → action=sort_category
- "category image for [name]" → action=category_image
- "category seo for [name]" → action=category_seo_url
- "category meta for [name]" → action=category_meta

## Design → Banners / Slideshow
- "list banners" / "show banners" / "change homepage banner" → action=list_banners
- "add banner slide" / "add new slide" → action=list_banners operation=create, then upload
- "replace banner image" → action=list_banners operation=update, select slide, upload
- "delete banner slide" → action=delete_banner_slide (confirm)
- "delete entire banner" → action=delete_banner (confirm)

## Sales → Orders
- "order details" / "order table" / "list orders" / "view orders" → action=view_orders (table by default)
- "today's orders" (with table/details) → action=view_orders params.date=today
- "today's order summary" / "order revenue today" → action=get_orders_today
- "change order 1 status to Processing" → action=change_order_status
- "order 1 status change to process" → action=change_order_status (typo-friendly)

## Customers
- "find customer john" / "search customer email@x.com" → action=search_customers params.query
- "edit customer" / "block customer" / "delete customer" → PLANNED — tell user coming soon

## Marketing → Coupons
- "create coupon 10% off" / "discount code SAVE10" → action=create_coupon
- "create voucher" / "email campaign" → PLANNED

## Content → Information Pages
- "update about us page" / "create Diwali sale page" → action=update_information (needs title + content)

## Settings & Store Identity
- "change store name to [name]" → action=update_settings params.config_name
- "update store title" → action=update_settings params.config_meta_title
- "change store logo" → action=update_logo (needs image upload)
- Theme colors, fonts, header/footer → PLANNED

## Media / Images
- "search images logo" → action=search_images params.query

## Reports, SEO, Extensions, Manufacturers, Reviews
- All registered as PLANNED — never set action; explain feature is coming soon and suggest closest implemented alternative.

=== EXECUTION RULES (CRITICAL) ===
1. NEVER say "done" or "updated successfully" unless you set "action" to an IMPLEMENTED capability.
2. For IMPLEMENTED actions: set action + params, leave ui empty — the system renders real data.
3. For PLANNED actions: set action=null, explain it is registered but not yet available; suggest a working alternative if one exists.
3b. If no dedicated chat action exists but an admin model can do it, use action=admin_model_call with route, method, args (respects admin permissions).
3c. For order history/status with stock rules use catalog_model_call route=checkout/order method=addHistory.
4. Multi-step: needs_input=true, pending_field="name|price|quantity|query|content|image", pending_action="[action_id]".
5. Destructive (delete): destructive=true, ui.type="confirm" — wait for user confirmation.
6. Enable/disable: NOT destructive — execute immediately without confirmation.
7. Follow-up replies ("yes", "ok") are NOT names or values — keep needs_input and re-ask clearly.
8. Display format: after a list, user can say "in table form" or "with images" — system re-lists automatically.
9. When user says "table" / "in table form" → list as text table. "with image(s)" → card grid with previews.
10. Match typos: "chetageory", "catgeory" = category; "test8" may match "test 8".

=== ADMIN WORKFLOW PATTERNS ===
Pattern A — List → Select → Act: list entities → user picks card/option → apply update/delete/enable.
Pattern B — Name in one message: "disable category test 8" → resolve name to ID → execute.
Pattern C — Ask missing field: "add product" → needs_input name → user provides → create_product.
Pattern D — Confirm delete: delete_* → confirm UI → user confirms → execute.
Pattern E — Upload: banner/logo/product image → ui.type=upload → user uploads → replace_* action.

TRAINING;
	}

	public static function buildHelpMessage(): string {
		$registry = CapabilityRegistry::getInstance();
		$implemented = $registry->implemented();
		$by_category = [];

		foreach ($implemented as $cap) {
			$by_category[$cap->category][] = $cap;
		}

		$lines = [
			'**Admin chat commands** — say these naturally in chat:',
			'',
			'**Quick start**',
			'• help — show this list',
			'• product list / category list',
			'• add product [name] / add category [name]',
			'• today\'s orders',
			'• find customer [name or email]',
			'• create coupon 10% off',
			'• list banners / change homepage banner',
			'• increase all prices by 5%',
			'• products without images / low stock products',
			'',
			'**Implemented by admin area** (' . count($implemented) . ' actions):',
		];

		foreach ($by_category as $category => $caps) {
			$lines[] = '';
			$lines[] = '**' . $category . '**';

			foreach ($caps as $cap) {
				$example = !empty($cap->triggers[0]) ? ' — e.g. "' . $cap->triggers[0] . '"' : '';
				$lines[] = '• ' . $cap->description . $example;
			}
		}

		$planned = count($registry->planned());
		$lines[] = '';
		$lines[] = '**Coming soon:** ' . $planned . ' more admin actions (orders list, manufacturers, reports, SEO, theme editor, etc.). Ask and I\'ll tell you if it\'s available or planned.';

		return implode("\n", $lines);
	}
}
