<?php
namespace Opencart\System\Library\Extension\AiBuilder\Services;

class CouponService {
	private object $registry;

	public function __construct(object $registry) {
		$this->registry = $registry;
	}

	public function create(array $data): array {
		$loader = $this->registry->get('load');
		$loader->model('marketing/coupon');
		$model = $this->registry->get('model_marketing_coupon');

		$coupon_id = $model->addCoupon([
			'name'          => $data['name'] ?? $data['code'] ?? 'AI Coupon',
			'code'          => $data['code'] ?? strtoupper(substr(md5(time()), 0, 8)),
			'discount'      => (float)($data['discount'] ?? 10),
			'type'          => $data['type'] ?? 'P',
			'total'         => (float)($data['total'] ?? 0),
			'logged'        => (int)($data['logged'] ?? 0),
			'shipping'      => (int)($data['shipping'] ?? 0),
			'date_start'    => $data['date_start'] ?? date('Y-m-d'),
			'date_end'      => $data['date_end'] ?? date('Y-m-d', strtotime('+30 days')),
			'uses_total'    => (int)($data['uses_total'] ?? 100),
			'uses_customer' => (int)($data['uses_customer'] ?? 1),
			'status'        => (int)($data['status'] ?? 1),
			'coupon_product' => [],
			'coupon_category' => []
		]);

		return ['success' => true, 'message' => 'Coupon created.', 'coupon_id' => $coupon_id];
	}
}
