<?php
namespace Opencart\System\Library\Extension\AiBuilder\Ai;

class OpenAiClient {
	private string $api_key;
	private string $model;
	private float $temperature;
	private int $max_tokens;

	public function __construct(string $api_key, string $model = 'gpt-4o-mini', float $temperature = 0.3, int $max_tokens = 4096) {
		$this->api_key = $api_key;
		$this->model = $model;
		$this->temperature = $temperature;
		$this->max_tokens = $max_tokens;
	}

	public function chat(array $messages, array $tools = []): array {
		if (!$this->api_key) {
			return [
				'error' => 'OpenAI API key is not configured. Go to Extensions > Other > AI Website Builder to add your API key.'
			];
		}

		$payload = [
			'model'       => $this->model,
			'messages'    => $messages,
			'temperature' => $this->temperature,
			'max_tokens'  => $this->max_tokens,
			'response_format' => ['type' => 'json_object']
		];

		$ch = curl_init('https://api.openai.com/v1/chat/completions');

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->api_key
			],
			CURLOPT_POSTFIELDS     => json_encode($payload),
			CURLOPT_TIMEOUT        => 120
		]);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error) {
			return ['error' => 'API connection failed: ' . $error];
		}

		$data = json_decode($response, true);

		if ($http_code !== 200) {
			$message = $data['error']['message'] ?? 'Unknown API error';
			return ['error' => $message];
		}

		$content = $data['choices'][0]['message']['content'] ?? '';

		$parsed = json_decode($content, true);

		if (!is_array($parsed)) {
			return [
				'message' => $content,
				'intent'  => 'conversation',
				'action'  => null,
				'ui'      => ['type' => 'text']
			];
		}

		return $parsed;
	}
}
