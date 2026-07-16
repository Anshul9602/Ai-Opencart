<?php
namespace Opencart\System\Library\Extension\AiBuilder\Capability;

class CapabilitiesCatalog {
	public static function registerAll(CapabilityRegistry $registry): void {
		self::storeManagement($registry);
		self::productManagement($registry);
		self::categoryManagement($registry);
		self::manufacturerManagement($registry);
		self::bannerManagement($registry);
		self::themeDesign($registry);
		self::customerManagement($registry);
		self::orderManagement($registry);
		self::inventory($registry);
		self::marketing($registry);
		self::seo($registry);
		self::blogCms($registry);
		self::fileManager($registry);
		self::settings($registry);
		self::extensions($registry);
		self::reports($registry);
		self::aiContent($registry);
		self::imageAi($registry);
		self::aiBulk($registry);
		self::themeBuilder($registry);
		self::storeBuilder($registry);
		self::databaseOps($registry);
		self::developerTools($registry);
		self::security($registry);
	}

	private static function add(
		CapabilityRegistry $registry,
		string $id,
		string $entity,
		string $action_type,
		string $category,
		string $description,
		array $extra = []
	): void {
		$registry->register(new CapabilityDefinition(array_merge([
			'id'          => $id,
			'entity'      => $entity,
			'action_type' => $action_type,
			'category'    => $category,
			'description' => $description,
			'status'      => 'planned',
		], $extra)));
	}

	private static function storeManagement(CapabilityRegistry $registry): void {
		$cat = 'Store Management';

		self::add($registry, 'dashboard_summary', 'Report', 'Read', $cat, 'Dashboard summary with key store metrics', ['triggers' => ['dashboard summary', 'store overview']]);
		self::add($registry, 'sales_analytics', 'Report', 'Read', $cat, 'Sales analytics and charts', ['triggers' => ['sales analytics', 'sales report']]);
		self::add($registry, 'visitor_statistics', 'Report', 'Read', $cat, 'Visitor and traffic statistics', ['triggers' => ['visitor stats', 'traffic report']]);
		self::add($registry, 'revenue_reports', 'Report', 'Read', $cat, 'Revenue reports by period', ['triggers' => ['revenue report', 'show revenue']]);
		self::add($registry, 'order_trends', 'Report', 'Read', $cat, 'Order trends over time', ['triggers' => ['order trends', 'order analytics']]);
		self::add($registry, 'best_selling_products', 'Report', 'Read', $cat, 'Best-selling products report', ['triggers' => ['best selling', 'top products']]);
		self::add($registry, 'low_stock_alerts', 'Report', 'Read', $cat, 'Low-stock product alerts', [
			'status' => 'implemented', 'triggers' => ['low stock', 'show low-stock products']
		]);
		self::add($registry, 'customer_growth', 'Report', 'Read', $cat, 'Customer growth statistics', ['triggers' => ['customer growth', 'new customers']]);
		self::add($registry, 'tax_reports', 'Report', 'Read', $cat, 'Tax reports', ['triggers' => ['tax report', 'show taxes']]);
		self::add($registry, 'export_reports', 'Report', 'Export', $cat, 'Export reports to CSV, Excel, or PDF', ['triggers' => ['export report', 'download report']]);
	}

	private static function productManagement(CapabilityRegistry $registry): void {
		$cat = 'Product Management';

		self::add($registry, 'create_product', 'Product', 'Create', $cat, 'Add a single product', [
			'status' => 'implemented', 'triggers' => ['add product', 'create product', 'new product'],
			'required_inputs' => ['name' => ['type' => 'string', 'required' => true]]
		]);
		self::add($registry, 'import_products_csv', 'Product', 'Import', $cat, 'Bulk import products from CSV', [
			'status' => 'implemented', 'triggers' => ['import csv', 'bulk import products']
		]);
		self::add($registry, 'validate_csv', 'Product', 'Import', $cat, 'Validate CSV before product import', [
			'status' => 'implemented', 'triggers' => ['validate csv', 'check csv']
		]);
		self::add($registry, 'search_products', 'Product', 'Search', $cat, 'Search products by name or SKU', [
			'status' => 'implemented', 'triggers' => ['find product', 'search product', 'change product price']
		]);
		self::add($registry, 'update_product_price', 'Product', 'Update', $cat, 'Update product price or special price', [
			'status' => 'implemented', 'triggers' => ['update price', 'change price', 'set price'],
			'required_inputs' => ['product_id' => ['type' => 'int', 'required' => true], 'price' => ['type' => 'float', 'required' => true]]
		]);
		self::add($registry, 'products_without_images', 'Product', 'Search', $cat, 'Find products without images', [
			'status' => 'implemented', 'triggers' => ['products without images', 'missing images']
		]);
		self::add($registry, 'bulk_price_update', 'Product', 'Bulk', $cat, 'Bulk price increase or decrease by percentage', [
			'status' => 'implemented', 'destructive' => true, 'requires_confirmation' => true,
			'triggers' => ['increase all prices', 'bulk price change', 'increase Havells prices by 5%'],
			'required_inputs' => ['percentage' => ['type' => 'float', 'required' => true]]
		]);
		self::add($registry, 'disable_out_of_stock', 'Product', 'Bulk', $cat, 'Disable all out-of-stock products', [
			'status' => 'implemented', 'destructive' => true, 'triggers' => ['disable out of stock']
		]);

		self::add($registry, 'list_products', 'Product', 'Read', $cat, 'List and browse products', [
			'status' => 'implemented', 'triggers' => ['list products', 'show products', 'all products']
		]);
		self::add($registry, 'get_product', 'Product', 'Read', $cat, 'Get product details', ['status' => 'implemented']);
		self::add($registry, 'update_product', 'Product', 'Update', $cat, 'Update product fields', ['status' => 'implemented']);
		self::add($registry, 'export_products', 'Product', 'Export', $cat, 'Export products to CSV or Excel', [
			'status' => 'implemented', 'triggers' => ['export products']
		]);
		self::add($registry, 'duplicate_product', 'Product', 'Duplicate', $cat, 'Duplicate an existing product', [
			'status' => 'implemented', 'triggers' => ['duplicate product', 'copy product']
		]);
		self::add($registry, 'delete_product', 'Product', 'Delete', $cat, 'Delete a product', [
			'status' => 'implemented', 'destructive' => true, 'requires_confirmation' => true, 'triggers' => ['delete product', 'remove product']
		]);
		self::add($registry, 'enable_product', 'Product', 'Enable', $cat, 'Enable a product', [
			'status' => 'implemented', 'triggers' => ['enable product']
		]);
		self::add($registry, 'disable_product', 'Product', 'Disable', $cat, 'Disable a product', [
			'status' => 'implemented', 'triggers' => ['disable product']
		]);
		self::add($registry, 'update_special_price', 'Product', 'Update', $cat, 'Update product special/sale price', [
			'status' => 'implemented', 'triggers' => ['special price', 'sale price']
		]);
		self::add($registry, 'update_quantity', 'Product', 'Update', $cat, 'Update product stock quantity', [
			'status' => 'implemented', 'triggers' => ['update quantity', 'change stock']
		]);
		self::add($registry, 'change_category', 'Product', 'Update', $cat, 'Change product category assignment', [
			'status' => 'implemented', 'triggers' => ['change category', 'move to category']
		]);
		self::add($registry, 'change_manufacturer', 'Product', 'Update', $cat, 'Change product manufacturer/brand', [
			'status' => 'implemented', 'triggers' => ['change manufacturer', 'change brand']
		]);
		self::add($registry, 'update_product_images', 'Product', 'Update', $cat, 'Update product images', [
			'status' => 'implemented', 'triggers' => ['update images', 'add product image']
		]);
		self::add($registry, 'replace_product_images', 'Product', 'Update', $cat, 'Replace existing product images', [
			'status' => 'implemented', 'triggers' => ['replace product image']
		]);

		self::add($registry, 'generate_seo_fields', 'Product', 'Generate', $cat, 'Generate SEO meta title and description for products', ['triggers' => ['generate seo', 'seo fields']]);
		self::add($registry, 'generate_descriptions', 'Product', 'Generate', $cat, 'Generate product descriptions with AI', ['triggers' => ['generate descriptions', 'write product description']]);
		self::add($registry, 'translate_descriptions', 'Product', 'Translate', $cat, 'Translate product descriptions to another language', ['triggers' => ['translate descriptions', 'translate to Hindi']]);
		self::add($registry, 'create_variants', 'Product', 'Create', $cat, 'Create product variants and options', ['triggers' => ['create variants', 'add options']]);
		self::add($registry, 'manage_attributes', 'Product', 'Update', $cat, 'Manage product attributes', ['triggers' => ['manage attributes']]);
		self::add($registry, 'manage_filters', 'Product', 'Update', $cat, 'Manage product filters', ['triggers' => ['manage filters']]);
		self::add($registry, 'related_products', 'Product', 'Update', $cat, 'Manage related products', ['triggers' => ['related products']]);
		self::add($registry, 'featured_products', 'Product', 'Update', $cat, 'Set featured products', ['triggers' => ['featured products']]);
		self::add($registry, 'product_tags', 'Product', 'Update', $cat, 'Manage product tags', ['triggers' => ['product tags']]);
		self::add($registry, 'reward_points', 'Product', 'Update', $cat, 'Set product reward points', ['triggers' => ['reward points']]);
		self::add($registry, 'product_downloads', 'Product', 'Update', $cat, 'Manage product downloads', ['triggers' => ['product downloads']]);
		self::add($registry, 'recurring_profiles', 'Product', 'Update', $cat, 'Manage recurring payment profiles', ['triggers' => ['recurring profiles']]);
	}

	private static function categoryManagement(CapabilityRegistry $registry): void {
		$cat = 'Category Management';

		self::add($registry, 'create_category', 'Category', 'Create', $cat, 'Create a new category', [
			'status' => 'implemented', 'triggers' => ['create category', 'add category', 'new category'],
			'required_inputs' => ['name' => ['type' => 'string', 'required' => true]]
		]);
		self::add($registry, 'list_categories', 'Category', 'Read', $cat, 'List all categories', [
			'status' => 'implemented', 'triggers' => ['list categories', 'show categories']
		]);
		self::add($registry, 'search_categories', 'Category', 'Search', $cat, 'Search categories by name', [
			'status' => 'implemented', 'triggers' => ['find category', 'search category']
		]);
		self::add($registry, 'get_category', 'Category', 'Read', $cat, 'Get category details', ['status' => 'implemented']);
		self::add($registry, 'edit_category', 'Category', 'Update', $cat, 'Edit category details', [
			'status' => 'implemented', 'triggers' => ['edit category', 'update category']
		]);
		self::add($registry, 'delete_category', 'Category', 'Delete', $cat, 'Delete a category', [
			'status' => 'implemented', 'destructive' => true, 'requires_confirmation' => true, 'triggers' => ['delete category']
		]);
		self::add($registry, 'parent_category', 'Category', 'Update', $cat, 'Set parent category', [
			'status' => 'implemented', 'triggers' => ['parent category', 'move category']
		]);
		self::add($registry, 'sort_category', 'Category', 'Update', $cat, 'Change category sort order', [
			'status' => 'implemented', 'triggers' => ['sort category', 'reorder categories']
		]);
		self::add($registry, 'category_image', 'Category', 'Update', $cat, 'Upload or update category image', [
			'status' => 'implemented', 'triggers' => ['category image']
		]);
		self::add($registry, 'category_seo_url', 'Category', 'Update', $cat, 'Set category SEO URL', [
			'status' => 'implemented', 'triggers' => ['category seo url']
		]);
		self::add($registry, 'category_meta', 'Category', 'Update', $cat, 'Update category meta title and description', [
			'status' => 'implemented', 'triggers' => ['category meta']
		]);
		self::add($registry, 'enable_category', 'Category', 'Enable', $cat, 'Enable or disable a category', [
			'status' => 'implemented', 'triggers' => ['enable category', 'disable category']
		]);
	}

	private static function manufacturerManagement(CapabilityRegistry $registry): void {
		$cat = 'Brand / Manufacturer';

		self::add($registry, 'create_manufacturer', 'Manufacturer', 'Create', $cat, 'Create a new manufacturer/brand', ['triggers' => ['add manufacturer', 'create brand', 'new manufacturer named']]);
		self::add($registry, 'edit_manufacturer', 'Manufacturer', 'Update', $cat, 'Edit manufacturer details', ['triggers' => ['edit manufacturer', 'update brand']]);
		self::add($registry, 'delete_manufacturer', 'Manufacturer', 'Delete', $cat, 'Delete a manufacturer', ['destructive' => true, 'requires_confirmation' => true, 'triggers' => ['delete manufacturer']]);
		self::add($registry, 'upload_manufacturer_logo', 'Manufacturer', 'Update', $cat, 'Upload manufacturer logo', ['triggers' => ['manufacturer logo', 'brand logo']]);
		self::add($registry, 'assign_products_manufacturer', 'Manufacturer', 'Update', $cat, 'Assign products to a manufacturer', ['triggers' => ['assign products to brand']]);
	}

	private static function bannerManagement(CapabilityRegistry $registry): void {
		$cat = 'Banner & Slider Management';

		self::add($registry, 'list_banners', 'Banner', 'Read', $cat, 'List all banners and slideshows', [
			'status' => 'implemented', 'triggers' => ['list banners', 'show banners', 'replace homepage banner', 'change homepage banner']
		]);
		self::add($registry, 'get_banner_slides', 'Banner', 'Read', $cat, 'Get slides for a selected banner', [
			'status' => 'implemented', 'required_inputs' => ['banner_id' => ['type' => 'int', 'required' => true]]
		]);
		self::add($registry, 'replace_banner_image', 'Banner', 'Update', $cat, 'Replace a banner slide image', [
			'status' => 'implemented', 'triggers' => ['replace banner', 'change banner image', 'update slider image']
		]);
		self::add($registry, 'add_banner_slide', 'Banner', 'Create', $cat, 'Upload and add a new banner slide', [
			'status' => 'implemented', 'triggers' => ['add banner', 'add slide', 'new banner on website', 'upload new slide']
		]);
		self::add($registry, 'delete_banner_slide', 'Banner', 'Delete', $cat, 'Delete a banner slide', [
			'status' => 'implemented', 'destructive' => true, 'requires_confirmation' => true, 'triggers' => ['delete slide', 'remove banner slide']
		]);
		self::add($registry, 'delete_banner', 'Banner', 'Delete', $cat, 'Delete entire banner and all slides', [
			'status' => 'implemented', 'destructive' => true, 'requires_confirmation' => true, 'triggers' => ['delete banner', 'remove home banner']
		]);

		self::add($registry, 'preview_banners', 'Banner', 'Read', $cat, 'Preview banners on storefront', ['triggers' => ['preview banner']]);
		self::add($registry, 'mobile_banner', 'Banner', 'Update', $cat, 'Set mobile-specific banner', ['triggers' => ['mobile banner']]);
		self::add($registry, 'desktop_banner', 'Banner', 'Update', $cat, 'Set desktop-specific banner', ['triggers' => ['desktop banner']]);
		self::add($registry, 'schedule_banners', 'Banner', 'Update', $cat, 'Schedule banner start and end dates', ['triggers' => ['schedule banner']]);
		self::add($registry, 'activate_banner', 'Banner', 'Enable', $cat, 'Activate or deactivate a banner', ['triggers' => ['activate banner', 'deactivate banner']]);
		self::add($registry, 'reorder_slides', 'Banner', 'Update', $cat, 'Reorder banner slides (first position, etc.)', ['triggers' => ['reorder slides', 'first position', 'move slide']]);
	}

	private static function themeDesign(CapabilityRegistry $registry): void {
		$cat = 'Theme & Design';

		self::add($registry, 'update_logo', 'Theme', 'Update', $cat, 'Update store logo', [
			'status' => 'implemented', 'triggers' => ['change logo', 'update logo', 'upload logo']
		]);
		self::add($registry, 'update_settings', 'Setting', 'Update', $cat, 'Update store settings (name, email, etc.)', [
			'status' => 'implemented', 'triggers' => ['update settings', 'change store name']
		]);

		self::add($registry, 'update_favicon', 'Theme', 'Update', $cat, 'Update favicon', ['triggers' => ['favicon']]);
		self::add($registry, 'update_colors', 'Theme', 'Update', $cat, 'Change primary and secondary colors', ['triggers' => ['change colors', 'primary color']]);
		self::add($registry, 'update_fonts', 'Theme', 'Update', $cat, 'Change store fonts', ['triggers' => ['change font']]);
		self::add($registry, 'update_header', 'Theme', 'Update', $cat, 'Edit header layout and content', ['triggers' => ['edit header']]);
		self::add($registry, 'update_footer', 'Theme', 'Update', $cat, 'Edit footer layout and content', ['triggers' => ['edit footer']]);
		self::add($registry, 'homepage_sections', 'Theme', 'Update', $cat, 'Manage homepage sections', ['triggers' => ['homepage sections']]);
		self::add($registry, 'hero_section', 'Theme', 'Update', $cat, 'Edit hero section', ['triggers' => ['hero section']]);
		self::add($registry, 'testimonials_section', 'Theme', 'Update', $cat, 'Manage testimonials section', ['triggers' => ['testimonials']]);
		self::add($registry, 'featured_products_section', 'Theme', 'Update', $cat, 'Configure featured products section', ['triggers' => ['featured section']]);
		self::add($registry, 'brands_section', 'Theme', 'Update', $cat, 'Configure brands section', ['triggers' => ['brands section']]);
		self::add($registry, 'video_section', 'Theme', 'Update', $cat, 'Add or edit video section', ['triggers' => ['video section']]);
		self::add($registry, 'newsletter_section', 'Theme', 'Update', $cat, 'Configure newsletter signup section', ['triggers' => ['newsletter section']]);
		self::add($registry, 'social_links', 'Theme', 'Update', $cat, 'Update social media links', ['triggers' => ['social links']]);
		self::add($registry, 'custom_css', 'Theme', 'Update', $cat, 'Add or edit custom CSS', ['triggers' => ['custom css']]);
		self::add($registry, 'custom_js', 'Theme', 'Update', $cat, 'Add or edit custom JavaScript', ['triggers' => ['custom js']]);
	}

	private static function customerManagement(CapabilityRegistry $registry): void {
		$cat = 'Customer Management';

		self::add($registry, 'search_customers', 'Customer', 'Search', $cat, 'Search customers by name or email', [
			'status' => 'implemented', 'triggers' => ['find customer', 'search customer']
		]);

		self::add($registry, 'edit_customer', 'Customer', 'Update', $cat, 'Edit customer profile', ['triggers' => ['edit customer', 'update customer']]);
		self::add($registry, 'reset_customer_password', 'Customer', 'Update', $cat, 'Reset customer password', ['triggers' => ['reset password']]);
		self::add($registry, 'assign_customer_group', 'Customer', 'Update', $cat, 'Assign customer to a group', ['triggers' => ['assign customer group']]);
		self::add($registry, 'customer_reward_points', 'Customer', 'Update', $cat, 'Manage customer reward points', ['triggers' => ['customer reward points']]);
		self::add($registry, 'customer_wallet', 'Customer', 'Read', $cat, 'View customer wallet balance', ['triggers' => ['customer wallet']]);
		self::add($registry, 'customer_wishlist', 'Customer', 'Read', $cat, 'View customer wishlist', ['triggers' => ['wishlist']]);
		self::add($registry, 'customer_addresses', 'Customer', 'Read', $cat, 'View or edit customer addresses', ['triggers' => ['customer addresses']]);
		self::add($registry, 'delete_customer', 'Customer', 'Delete', $cat, 'Delete customer account', ['destructive' => true, 'requires_confirmation' => true, 'triggers' => ['delete customer']]);
		self::add($registry, 'block_customer', 'Customer', 'Disable', $cat, 'Block or unblock a customer', ['triggers' => ['block customer', 'unblock customer']]);
	}

	private static function orderManagement(CapabilityRegistry $registry): void {
		$cat = 'Order Management';

		self::add($registry, 'get_orders_today', 'Order', 'Read', $cat, "Today's orders summary and revenue", [
			'status' => 'implemented', 'triggers' => ["today's orders", 'show orders today', "export today's orders"]
		]);

		self::add($registry, 'view_orders', 'Order', 'Read', $cat, 'View and search orders', ['triggers' => ['view orders', 'list orders']]);
		self::add($registry, 'create_manual_order', 'Order', 'Create', $cat, 'Create a manual order', ['triggers' => ['create order', 'manual order']]);
		self::add($registry, 'change_order_status', 'Order', 'Update', $cat, 'Change order status', ['triggers' => ['change order status', 'mark as shipped']]);
		self::add($registry, 'generate_invoice', 'Order', 'Export', $cat, 'Generate order invoice', ['triggers' => ['generate invoice', 'print invoice']]);
		self::add($registry, 'print_packing_slip', 'Order', 'Export', $cat, 'Print packing slip', ['triggers' => ['packing slip']]);
		self::add($registry, 'refund_order', 'Order', 'Update', $cat, 'Process order refund', ['destructive' => true, 'requires_confirmation' => true, 'triggers' => ['refund order']]);
		self::add($registry, 'cancel_order', 'Order', 'Update', $cat, 'Cancel an order', ['destructive' => true, 'requires_confirmation' => true, 'triggers' => ['cancel order']]);
		self::add($registry, 'shipment_tracking', 'Order', 'Update', $cat, 'Add shipment tracking number', ['triggers' => ['tracking number', 'shipment tracking']]);
		self::add($registry, 'notify_customer', 'Order', 'Update', $cat, 'Send notification email to customer', ['triggers' => ['notify customer']]);
	}

	private static function inventory(CapabilityRegistry $registry): void {
		$cat = 'Inventory';

		self::add($registry, 'stock_adjustment', 'Inventory', 'Update', $cat, 'Adjust product stock levels', ['triggers' => ['stock adjustment', 'adjust inventory']]);
		self::add($registry, 'purchase_orders', 'Inventory', 'Create', $cat, 'Create and manage purchase orders', ['triggers' => ['purchase order']]);
		self::add($registry, 'supplier_management', 'Inventory', 'Update', $cat, 'Manage suppliers', ['triggers' => ['supplier']]);
		self::add($registry, 'warehouse_management', 'Inventory', 'Update', $cat, 'Manage warehouses', ['triggers' => ['warehouse']]);
		self::add($registry, 'low_stock_report', 'Inventory', 'Read', $cat, 'Low-stock inventory report', ['triggers' => ['low stock report']]);
		self::add($registry, 'out_of_stock_report', 'Inventory', 'Read', $cat, 'Out-of-stock inventory report', ['triggers' => ['out of stock report']]);
	}

	private static function marketing(CapabilityRegistry $registry): void {
		$cat = 'Marketing';

		self::add($registry, 'create_coupon', 'Coupon', 'Create', $cat, 'Create a discount coupon', [
			'status' => 'implemented', 'triggers' => ['create coupon', '10% off coupon', 'discount code']
		]);

		self::add($registry, 'create_voucher', 'Coupon', 'Create', $cat, 'Create a gift voucher', ['triggers' => ['create voucher', 'gift voucher']]);
		self::add($registry, 'email_campaigns', 'Marketing', 'Create', $cat, 'Create email marketing campaigns', ['triggers' => ['email campaign']]);
		self::add($registry, 'newsletter', 'Marketing', 'Update', $cat, 'Manage newsletter subscribers', ['triggers' => ['newsletter']]);
		self::add($registry, 'affiliate_management', 'Marketing', 'Update', $cat, 'Manage affiliates', ['triggers' => ['affiliate']]);
		self::add($registry, 'promotions', 'Marketing', 'Create', $cat, 'Create store promotions', ['triggers' => ['promotion', 'Diwali sale']]);
		self::add($registry, 'flash_sales', 'Marketing', 'Create', $cat, 'Create flash sale campaigns', ['triggers' => ['flash sale']]);
	}

	private static function seo(CapabilityRegistry $registry): void {
		$cat = 'SEO';

		self::add($registry, 'meta_titles', 'SEO', 'Update', $cat, 'Update meta titles', ['triggers' => ['meta title']]);
		self::add($registry, 'meta_descriptions', 'SEO', 'Update', $cat, 'Update meta descriptions', ['triggers' => ['meta description']]);
		self::add($registry, 'seo_urls', 'SEO', 'Update', $cat, 'Manage SEO-friendly URLs', ['triggers' => ['seo url']]);
		self::add($registry, 'canonical_urls', 'SEO', 'Update', $cat, 'Set canonical URLs', ['triggers' => ['canonical url']]);
		self::add($registry, 'robots_config', 'SEO', 'Update', $cat, 'Configure robots.txt', ['triggers' => ['robots']]);
		self::add($registry, 'sitemap', 'SEO', 'Generate', $cat, 'Generate XML sitemap', ['triggers' => ['sitemap']]);
		self::add($registry, 'schema_markup', 'SEO', 'Generate', $cat, 'Add schema markup', ['triggers' => ['schema markup']]);
		self::add($registry, 'redirects', 'SEO', 'Update', $cat, 'Manage URL redirects', ['triggers' => ['redirect', '301 redirect']]);
		self::add($registry, 'broken_link_detection', 'SEO', 'Read', $cat, 'Detect broken links', ['triggers' => ['broken links']]);
	}

	private static function blogCms(CapabilityRegistry $registry): void {
		$cat = 'Blog / CMS';

		self::add($registry, 'update_information', 'Information', 'Update', $cat, 'Create or edit CMS pages (About, FAQ, Privacy, etc.)', [
			'status' => 'implemented', 'triggers' => ['edit page', 'update about us', 'privacy policy', 'create Diwali sale page']
		]);

		self::add($registry, 'create_page', 'Information', 'Create', $cat, 'Create a new CMS page', ['triggers' => ['create page', 'new page']]);
		self::add($registry, 'delete_page', 'Information', 'Delete', $cat, 'Delete a CMS page', ['destructive' => true, 'requires_confirmation' => true, 'triggers' => ['delete page']]);
		self::add($registry, 'generate_blog', 'Information', 'Generate', $cat, 'Generate blog content with AI', ['triggers' => ['generate blog', 'write blog post']]);
		self::add($registry, 'faq_page', 'Information', 'Create', $cat, 'Create or edit FAQ page', ['triggers' => ['faq', 'frequently asked']]);
		self::add($registry, 'about_page', 'Information', 'Update', $cat, 'Edit About Us page', ['triggers' => ['about us']]);
		self::add($registry, 'contact_page', 'Information', 'Update', $cat, 'Edit Contact page', ['triggers' => ['contact page']]);
		self::add($registry, 'privacy_policy', 'Information', 'Update', $cat, 'Edit Privacy Policy', ['triggers' => ['privacy policy']]);
		self::add($registry, 'terms_conditions', 'Information', 'Update', $cat, 'Edit Terms & Conditions', ['triggers' => ['terms and conditions']]);
	}

	private static function fileManager(CapabilityRegistry $registry): void {
		$cat = 'File Manager';

		self::add($registry, 'search_images', 'Image', 'Search', $cat, 'Search image files in catalog', [
			'status' => 'implemented', 'triggers' => ['search images', 'find image']
		]);

		self::add($registry, 'file_upload', 'File', 'Create', $cat, 'Upload files to file manager', ['triggers' => ['upload file']]);
		self::add($registry, 'file_rename', 'File', 'Update', $cat, 'Rename a file', ['triggers' => ['rename file']]);
		self::add($registry, 'file_delete', 'File', 'Delete', $cat, 'Delete a file', ['destructive' => true, 'triggers' => ['delete file']]);
		self::add($registry, 'file_compress', 'File', 'Update', $cat, 'Compress images', ['triggers' => ['compress image']]);
		self::add($registry, 'webp_conversion', 'File', 'Update', $cat, 'Convert images to WebP', ['triggers' => ['convert to webp']]);
		self::add($registry, 'image_resize', 'File', 'Update', $cat, 'Resize images', ['triggers' => ['resize image']]);
		self::add($registry, 'image_crop', 'File', 'Update', $cat, 'Crop images', ['triggers' => ['crop image']]);
	}

	private static function settings(CapabilityRegistry $registry): void {
		$cat = 'Settings';

		self::add($registry, 'payment_methods', 'Setting', 'Update', $cat, 'Configure payment methods', ['triggers' => ['payment methods', 'payment gateway']]);
		self::add($registry, 'shipping_methods', 'Setting', 'Update', $cat, 'Configure shipping methods', ['triggers' => ['shipping methods']]);
		self::add($registry, 'taxes', 'Setting', 'Update', $cat, 'Configure tax classes and rates', ['triggers' => ['tax settings', 'configure tax']]);
		self::add($registry, 'currency', 'Setting', 'Update', $cat, 'Manage currencies', ['triggers' => ['currency']]);
		self::add($registry, 'languages', 'Setting', 'Update', $cat, 'Manage store languages', ['triggers' => ['languages']]);
		self::add($registry, 'geo_zones', 'Setting', 'Update', $cat, 'Manage geo zones', ['triggers' => ['geo zones']]);
		self::add($registry, 'countries', 'Setting', 'Update', $cat, 'Manage countries', ['triggers' => ['countries']]);
		self::add($registry, 'store_email', 'Setting', 'Update', $cat, 'Configure store email settings', ['triggers' => ['store email']]);
		self::add($registry, 'smtp', 'Setting', 'Update', $cat, 'Configure SMTP mail server', ['triggers' => ['smtp', 'mail server']]);
		self::add($registry, 'cron_jobs', 'Setting', 'Update', $cat, 'Configure cron jobs', ['triggers' => ['cron jobs']]);
	}

	private static function extensions(CapabilityRegistry $registry): void {
		$cat = 'Extensions';

		self::add($registry, 'extension_install', 'Extension', 'Create', $cat, 'Install an extension', ['triggers' => ['install extension']]);
		self::add($registry, 'extension_uninstall', 'Extension', 'Delete', $cat, 'Uninstall an extension', ['destructive' => true, 'requires_confirmation' => true, 'triggers' => ['uninstall extension']]);
		self::add($registry, 'extension_enable', 'Extension', 'Enable', $cat, 'Enable an extension', ['triggers' => ['enable extension']]);
		self::add($registry, 'extension_disable', 'Extension', 'Disable', $cat, 'Disable an extension', ['triggers' => ['disable extension']]);
		self::add($registry, 'extension_configure', 'Extension', 'Update', $cat, 'Configure extension settings', ['triggers' => ['configure extension']]);
		self::add($registry, 'extension_update', 'Extension', 'Update', $cat, 'Update an extension', ['triggers' => ['update extension']]);
	}

	private static function reports(CapabilityRegistry $registry): void {
		$cat = 'Reports';

		self::add($registry, 'report_sales', 'Report', 'Read', $cat, 'Sales report', ['triggers' => ['sales report']]);
		self::add($registry, 'report_products', 'Report', 'Read', $cat, 'Products viewed/sold report', ['triggers' => ['product report']]);
		self::add($registry, 'report_customers', 'Report', 'Read', $cat, 'Customer activity report', ['triggers' => ['customer report']]);
		self::add($registry, 'report_coupons', 'Report', 'Read', $cat, 'Coupon usage report', ['triggers' => ['coupon report']]);
		self::add($registry, 'report_taxes', 'Report', 'Read', $cat, 'Tax report', ['triggers' => ['tax report']]);
		self::add($registry, 'report_shipping', 'Report', 'Read', $cat, 'Shipping report', ['triggers' => ['shipping report']]);
		self::add($registry, 'report_inventory', 'Report', 'Read', $cat, 'Inventory report', ['triggers' => ['inventory report']]);
		self::add($registry, 'report_profit', 'Report', 'Read', $cat, 'Profit margin report', ['triggers' => ['profit report']]);
	}

	private static function aiContent(CapabilityRegistry $registry): void {
		$cat = 'AI Content Tools';

		self::add($registry, 'generate_product_descriptions', 'AI', 'Generate', $cat, 'Generate product descriptions for products missing them', ['triggers' => ['generate descriptions for all products without descriptions']]);
		self::add($registry, 'rewrite_descriptions', 'AI', 'Generate', $cat, 'Rewrite existing product descriptions', ['triggers' => ['rewrite descriptions']]);
		self::add($registry, 'generate_faqs', 'AI', 'Generate', $cat, 'Generate FAQ content', ['triggers' => ['generate faq']]);
		self::add($registry, 'generate_seo_content', 'AI', 'Generate', $cat, 'Generate SEO meta content', ['triggers' => ['generate seo content']]);
		self::add($registry, 'generate_alt_text', 'AI', 'Generate', $cat, 'Generate image alt text', ['triggers' => ['alt text', 'generate alt']]);
		self::add($registry, 'translate_content', 'AI', 'Translate', $cat, 'Translate store content to another language', ['triggers' => ['translate all product descriptions', 'translate to Hindi']]);
		self::add($registry, 'summarize_reviews', 'AI', 'Generate', $cat, 'Summarize product reviews', ['triggers' => ['summarize reviews']]);
	}

	private static function imageAi(CapabilityRegistry $registry): void {
		$cat = 'Image AI';

		self::add($registry, 'generate_banners_ai', 'AI', 'Generate', $cat, 'Generate banner images with AI', ['triggers' => ['generate banner', 'ai banner']]);
		self::add($registry, 'remove_background', 'AI', 'Update', $cat, 'Remove image background', ['triggers' => ['remove background']]);
		self::add($registry, 'image_resize_ai', 'AI', 'Update', $cat, 'AI-assisted image resize', ['triggers' => ['resize image ai']]);
		self::add($registry, 'image_compress_ai', 'AI', 'Update', $cat, 'AI-assisted image compression', ['triggers' => ['compress image']]);
		self::add($registry, 'convert_image_format', 'AI', 'Update', $cat, 'Convert image format (PNG, JPG, WebP)', ['triggers' => ['convert image format']]);
		self::add($registry, 'ocr_extract_text', 'AI', 'Read', $cat, 'Extract text from images via OCR', ['triggers' => ['ocr', 'extract text from image']]);
		self::add($registry, 'replace_images_ai', 'AI', 'Update', $cat, 'AI-assisted image replacement', ['triggers' => ['replace existing images']]);
	}

	private static function aiBulk(CapabilityRegistry $registry): void {
		$cat = 'AI Bulk Operations';

		self::add($registry, 'import_products_excel', 'Product', 'Import', $cat, 'Import products from Excel file', ['triggers' => ['import excel', 'import from excel']]);
		self::add($registry, 'import_supplier_pdf', 'Product', 'Import', $cat, 'Import products from supplier PDF', ['triggers' => ['import from pdf', 'supplier pdf']]);
		self::add($registry, 'ocr_from_image', 'Product', 'Import', $cat, 'Extract and import product data from image via OCR', ['triggers' => ['ocr from image']]);
		self::add($registry, 'bulk_category_assignment', 'Product', 'Bulk', $cat, 'Bulk assign products to a category', ['triggers' => ['bulk category', 'assign all to category']]);
		self::add($registry, 'bulk_image_updates', 'Product', 'Bulk', $cat, 'Bulk update product images', ['triggers' => ['bulk image update']]);
		self::add($registry, 'bulk_stock_updates', 'Product', 'Bulk', $cat, 'Bulk update stock quantities', ['triggers' => ['bulk stock update']]);
	}

	private static function themeBuilder(CapabilityRegistry $registry): void {
		$cat = 'Theme Builder';

		self::add($registry, 'edit_homepage', 'Theme', 'Update', $cat, 'Edit homepage layout', ['triggers' => ['edit homepage']]);
		self::add($registry, 'add_section', 'Theme', 'Create', $cat, 'Add a homepage section', ['triggers' => ['add section']]);
		self::add($registry, 'remove_section', 'Theme', 'Delete', $cat, 'Remove a homepage section', ['triggers' => ['remove section']]);
		self::add($registry, 'rearrange_sections', 'Theme', 'Update', $cat, 'Rearrange homepage sections', ['triggers' => ['rearrange sections']]);
		self::add($registry, 'edit_menus', 'Theme', 'Update', $cat, 'Edit navigation menus', ['triggers' => ['edit menu', 'edit menus']]);
		self::add($registry, 'footer_builder', 'Theme', 'Update', $cat, 'Build footer with drag-and-drop', ['triggers' => ['footer builder']]);
		self::add($registry, 'landing_page_builder', 'Theme', 'Create', $cat, 'Create landing pages', ['triggers' => ['landing page', 'create landing page']]);
	}

	private static function storeBuilder(CapabilityRegistry $registry): void {
		$cat = 'Store Builder';

		self::add($registry, 'create_pages', 'Information', 'Create', $cat, 'Create custom store pages', ['triggers' => ['create pages']]);
		self::add($registry, 'create_forms', 'Theme', 'Create', $cat, 'Create custom forms', ['triggers' => ['create form']]);
		self::add($registry, 'create_menus', 'Theme', 'Create', $cat, 'Create navigation menus', ['triggers' => ['create menu']]);
		self::add($registry, 'create_banners', 'Banner', 'Create', $cat, 'Create new banner groups', ['triggers' => ['create new banner']]);
		self::add($registry, 'create_collections', 'Product', 'Create', $cat, 'Create product collections', ['triggers' => ['product collection']]);
		self::add($registry, 'create_sliders', 'Banner', 'Create', $cat, 'Create new sliders', ['triggers' => ['create slider']]);
		self::add($registry, 'create_popup_campaigns', 'Marketing', 'Create', $cat, 'Create popup campaigns', ['triggers' => ['popup campaign']]);
	}

	private static function databaseOps(CapabilityRegistry $registry): void {
		$cat = 'Database Operations';

		self::add($registry, 'backup_database', 'Database', 'Export', $cat, 'Backup the database', ['destructive' => false, 'triggers' => ['backup database']]);
		self::add($registry, 'restore_backup', 'Database', 'Import', $cat, 'Restore database from backup', ['destructive' => true, 'requires_confirmation' => true, 'triggers' => ['restore backup']]);
		self::add($registry, 'optimize_tables', 'Database', 'Update', $cat, 'Optimize database tables', ['triggers' => ['optimize tables']]);
		self::add($registry, 'repair_tables', 'Database', 'Update', $cat, 'Repair database tables', ['triggers' => ['repair tables']]);
		self::add($registry, 'search_records', 'Database', 'Search', $cat, 'Search database records', ['triggers' => ['search records']]);
		self::add($registry, 'update_records', 'Database', 'Update', $cat, 'Update database records', ['triggers' => ['update records']]);
		self::add($registry, 'export_data', 'Database', 'Export', $cat, 'Export database data', ['triggers' => ['export data']]);
	}

	private static function developerTools(CapabilityRegistry $registry): void {
		$cat = 'Developer Tools';

		self::add($registry, 'clear_cache', 'Developer', 'Update', $cat, 'Clear OpenCart cache', ['triggers' => ['clear cache']]);
		self::add($registry, 'refresh_modifications', 'Developer', 'Update', $cat, 'Refresh OCMOD modifications', ['triggers' => ['refresh modifications']]);
		self::add($registry, 'refresh_theme_cache', 'Developer', 'Update', $cat, 'Refresh theme cache', ['triggers' => ['refresh theme cache']]);
		self::add($registry, 'view_logs', 'Developer', 'Read', $cat, 'View system error logs', ['triggers' => ['view logs', 'error log']]);
		self::add($registry, 'php_info', 'Developer', 'Read', $cat, 'View PHP configuration info', ['triggers' => ['php info']]);
		self::add($registry, 'system_health', 'Developer', 'Read', $cat, 'System health check', ['triggers' => ['system health']]);
		self::add($registry, 'error_reports', 'Developer', 'Read', $cat, 'View error reports', ['triggers' => ['error reports']]);
	}

	private static function security(CapabilityRegistry $registry): void {
		$cat = 'Security';

		self::add($registry, 'user_permissions', 'Security', 'Update', $cat, 'Manage user permissions', ['triggers' => ['user permissions']]);
		self::add($registry, 'activity_logs', 'Security', 'Read', $cat, 'View activity logs', ['triggers' => ['activity logs']]);
		self::add($registry, 'login_history', 'Security', 'Read', $cat, 'View login history', ['triggers' => ['login history']]);
		self::add($registry, 'ip_restrictions', 'Security', 'Update', $cat, 'Configure IP restrictions', ['triggers' => ['ip restrictions']]);
		self::add($registry, 'backup_scheduling', 'Security', 'Update', $cat, 'Schedule automatic backups', ['triggers' => ['backup scheduling']]);
		self::add($registry, 'audit_logs', 'Security', 'Read', $cat, 'View AI and admin audit logs', ['triggers' => ['audit logs']]);
	}
}
