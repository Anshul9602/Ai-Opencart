<?php
namespace Opencart\System\Library\Extension\AiBuilder\Utils;

class CsvValidator {
	public function parse(string $filepath): array {
		if (!file_exists($filepath)) {
			return ['error' => 'File not found'];
		}

		$handle = fopen($filepath, 'r');

		if (!$handle) {
			return ['error' => 'Cannot read file'];
		}

		$headers = fgetcsv($handle);

		if (!$headers) {
			fclose($handle);
			return ['error' => 'Empty CSV file'];
		}

		$required = ['name', 'model', 'price'];
		$missing = array_diff($required, array_map('strtolower', $headers));

		$rows = [];
		$errors = [];
		$line = 1;

		while (($row = fgetcsv($handle)) !== false) {
			$line++;

			if (count($row) < count($headers)) {
				$errors[] = ['line' => $line, 'error' => 'Incomplete row'];
				continue;
			}

			$data = array_combine($headers, $row);

			if (empty($data['name']) || empty($data['model'])) {
				$errors[] = ['line' => $line, 'error' => 'Missing name or model'];
				continue;
			}

			if (!is_numeric($data['price'] ?? '')) {
				$errors[] = ['line' => $line, 'error' => 'Invalid price'];
				continue;
			}

			$rows[] = $data;
		}

		fclose($handle);

		return [
			'total'   => $line - 1,
			'valid'   => count($rows),
			'errors'  => count($errors),
			'rows'    => $rows,
			'details' => $errors,
			'missing_headers' => array_values($missing)
		];
	}

	public static function getTemplate(): string {
		return "name,model,sku,price,special_price,quantity,status,category,description,image,seo_url,meta_title,meta_description\n" .
			"Sample Product,MODEL-001,SKU-001,99.99,79.99,100,1,Electronics,Product description,catalog/sample.jpg,sample-product,Sample Product,Buy sample product online\n";
	}
}
