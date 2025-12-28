<?php declare(strict_types=1);

namespace Base3IliasLab\MissionBay;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Resource\AbstractAgentResource;

/**
 * QualitusAiChatProxyAgentResource
 *
 * Connects to Qualitus AI Chat Proxy:
 * POST https://qki-proto1.qualitus.net/base3.php?name=aichatproxy
 *
 * Headers:
 * - Content-Type: application/json
 * - X-Proxy-Token: <apikey>
 * - (stream) Accept: text/event-stream
 *
 * Supports:
 * - chat() non-stream
 * - raw() non-stream
 * - stream() SSE streaming with token callbacks
 *
 * Important:
 * - Tool messages must only be sent if they respond to a preceding assistant message
 *	 that declared matching tool_calls in the SAME outgoing payload.
 *	 Otherwise the backend may reject (OpenAI-compatible behavior).
 */
class QualitusAiChatProxyAgentResource extends AbstractAgentResource implements IAiChatModel {

	protected IAgentConfigValueResolver $resolver;

	protected array|string|null $modelConfig = null;
	protected array|string|null $apikeyConfig = null;
	protected array|string|null $endpointConfig = null;
	protected array|string|null $temperatureConfig = null;
	protected array|string|null $maxTokensConfig = null;

	protected array $resolvedOptions = [];

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'qualitusaichatproxyagentresource';
	}

	public function getDescription(): string {
		return 'Connects to Qualitus AI Chat Proxy. Supports streaming (SSE) and non-streaming chat.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? ($config['proxy_token'] ?? ($config['token'] ?? null));
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxTokensConfig   = $config['max_tokens'] ?? null;

		$this->resolvedOptions = [
			'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'Qwen/Qwen2.5-14B-Instruct-AWQ',
			'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig) ?? 'https://qki-proto1.qualitus.net/base3.php?name=aichatproxy',
			'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.7),
			'max_tokens'  => (int)($this->resolver->resolveValue($this->maxTokensConfig) ?? 256),
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
	}

	public function chat(array $messages): string {
		$result = $this->raw($messages);

		// Prefer OpenAI-like response shape.
		$content = $result['choices'][0]['message']['content'] ?? null;

		// Fallbacks for proxy-specific shapes.
		if (!is_string($content) || $content === '') {
			$content = $result['message']['content'] ?? ($result['content'] ?? null);
		}

		if (!is_string($content)) {
			throw new \RuntimeException("Malformed proxy chat response: " . json_encode($result));
		}

		return $content;
	}

	public function raw(array $messages, array $tools = []): mixed {
		$model     = $this->resolvedOptions['model'] ?? 'Qwen/Qwen2.5-14B-Instruct-AWQ';
		$apikey    = $this->resolvedOptions['apikey'] ?? null;
		$endpoint  = (string)($this->resolvedOptions['endpoint'] ?? '');
		$temp      = $this->resolvedOptions['temperature'] ?? 0.7;
		$maxTokens = $this->resolvedOptions['max_tokens'] ?? 256;

		if (!$apikey) {
			throw new \RuntimeException("Missing API key for Qualitus chat proxy (X-Proxy-Token).");
		}
		if ($endpoint === '') {
			throw new \RuntimeException("Missing endpoint for Qualitus chat proxy.");
		}

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'model'       => $model,
			'messages'    => $normalized,
			'temperature' => $temp,
			'max_tokens'  => (int)$maxTokens,
		];

		if (!empty($tools)) {
			$payload['tools'] = $tools;
			$payload['tool_choice'] = 'auto';
		}

		$jsonPayload = json_encode($payload);

		$headers = [
			'Content-Type: application/json',
			'X-Proxy-Token: ' . $apikey,
		];

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new \RuntimeException('Proxy request failed: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode < 200 || $httpCode >= 300) {
			throw new \RuntimeException("Proxy request failed with status $httpCode: $result");
		}

		$data = json_decode((string)$result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid JSON response from proxy: " . substr((string)$result, 0, 200));
		}

		return $data;
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
		$model     = $this->resolvedOptions['model'] ?? 'Qwen/Qwen2.5-14B-Instruct-AWQ';
		$apikey    = $this->resolvedOptions['apikey'] ?? null;
		$endpoint  = (string)($this->resolvedOptions['endpoint'] ?? '');
		$temp      = $this->resolvedOptions['temperature'] ?? 0.7;
		$maxTokens = $this->resolvedOptions['max_tokens'] ?? 256;

		if (!$apikey) {
			throw new \RuntimeException("Missing API key for Qualitus chat proxy (X-Proxy-Token).");
		}
		if ($endpoint === '') {
			throw new \RuntimeException("Missing endpoint for Qualitus chat proxy.");
		}

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'model'       => $model,
			'messages'    => $normalized,
			'stream'      => true,
			'temperature' => $temp,
			'max_tokens'  => (int)$maxTokens,
		];

		if (!empty($tools)) {
			$payload['tools'] = $tools;
			$payload['tool_choice'] = 'auto';
		}

		$jsonPayload = json_encode($payload);

		$headers = [
			'Content-Type: application/json',
			'Accept: text/event-stream',
			'X-Proxy-Token: ' . $apikey,
		];

		$buffer = '';

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);

		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$buffer, $onData, $onMeta) {
			$buffer .= $chunk;

			while (($pos = strpos($buffer, "\n")) !== false) {
				$line = substr($buffer, 0, $pos);
				$buffer = substr($buffer, $pos + 1);

				$line = trim($line);
				if ($line === '' || !str_starts_with($line, 'data:')) {
					continue;
				}

				$data = trim(substr($line, 5));

				if ($data === '[DONE]') {
					if ($onMeta !== null) {
						$onMeta(['event' => 'done']);
					}
					continue;
				}

				$json = json_decode($data, true);
				if (!is_array($json)) {
					continue;
				}

				$choice = $json['choices'][0] ?? [];

				if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
					$onMeta([
						'event'         => 'meta',
						'finish_reason' => $choice['finish_reason'],
						'full'          => $json,
					]);
				}

				$delta = $choice['delta']['content'] ?? null;

				if ($delta === null) {
					$delta = $choice['delta']['text'] ?? ($json['delta']['content'] ?? null);
				}

				if (is_string($delta) && $delta !== '') {
					$onData($delta);
				}

				if (!empty($choice['delta']['tool_calls']) && $onMeta !== null) {
					$onMeta([
						'event'      => 'toolcall',
						'tool_calls' => $choice['delta']['tool_calls'],
					]);
				}
			}

			return strlen($chunk);
		});

		curl_exec($ch);

		if (curl_errno($ch)) {
			$err = curl_error($ch);
			curl_close($ch);
			throw new \RuntimeException('Proxy stream request failed: ' . $err);
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode < 200 || $httpCode >= 300) {
			throw new \RuntimeException("Proxy stream request failed with status $httpCode.");
		}
	}

	/**
	 * Normalize "rich" message objects into plain role/content messages.
	 *
	 * Critical invariant:
	 * - A tool message is only allowed if we have seen a preceding assistant tool_calls
	 *	 message that declared the same tool_call_id in THIS outgoing payload.
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];
		$validToolCallIds = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) {
				continue;
			}

			$role = (string)$m['role'];
			$content = $m['content'] ?? '';

			if ($role === 'assistant' && !empty($m['tool_calls']) && is_array($m['tool_calls'])) {
				$toolCalls = [];

				foreach ($m['tool_calls'] as $call) {
					if (!isset($call['id'], $call['function']['name'])) {
						continue;
					}

					$callId = (string)$call['id'];
					$args = $call['function']['arguments'] ?? '{}';

					if (!is_string($args)) {
						$args = json_encode($args);
					}

					$toolCalls[] = [
						'id' => $callId,
						'type' => 'function',
						'function' => [
							'name' => (string)$call['function']['name'],
							'arguments' => $args,
						],
					];

					$validToolCallIds[$callId] = true;
				}

				$out[] = [
					'role' => 'assistant',
					'content' => is_string($content) ? $content : json_encode($content),
					'tool_calls' => $toolCalls,
				];

				continue;
			}

			if ($role === 'tool') {
				$toolCallId = (string)($m['tool_call_id'] ?? '');

				// Skip orphaned tool messages to avoid backend 400 errors.
				if ($toolCallId === '' || empty($validToolCallIds[$toolCallId])) {
					continue;
				}

				$out[] = [
					'role' => 'tool',
					'tool_call_id' => $toolCallId,
					'content' => is_string($content) ? $content : json_encode($content),
				];

				// Consume to avoid accidental duplicates.
				unset($validToolCallIds[$toolCallId]);

				continue;
			}

			$out[] = [
				'role' => $role,
				'content' => is_string($content) ? $content : json_encode($content),
			];

			if (!empty($m['feedback']) && is_string($m['feedback'])) {
				$fb = trim($m['feedback']);
				if ($fb !== '') {
					$out[] = [
						'role' => 'user',
						'content' => $fb,
					];
				}
			}
		}

		return $out;
	}
}
