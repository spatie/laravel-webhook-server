<?php

namespace Spatie\WebhookServer\Events;

use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionProperty;
use Spatie\WebhookServer\Concerns\SerializesWebhookData;
use WeakMap;

/**
 * @phpstan-type ResponseData array{
 *     status: int,
 *     headers: array<array-key, array<array-key, string>>,
 *     body: string,
 *     protocol: string,
 *     reason: string
 * }
 * @phpstan-type TransferData array{
 *     method: string,
 *     uri: string,
 *     headers: array<array-key, array<array-key, string>>,
 *     body: string,
 *     protocol: string,
 *     target: string,
 *     response: ResponseData|null,
 *     time: float|null,
 *     error: string,
 *     stats: array<array-key, string>
 * }
 * @phpstan-type HttpResponseData array{
 *     message: ResponseData,
 *     cookies: string,
 *     transferStats: TransferData|null
 * }
 */
abstract class WebhookCallEvent
{
    use SerializesWebhookData {
        __serialize as protected serializeProperties;
        __unserialize as protected restoreProperties;
    }

    /** @var WeakMap<StreamInterface, string>|null */
    protected static ?WeakMap $nonSeekableBodies = null;

    /**
     * @param string $httpVerb
     * @param string $webhookUrl
     * @param array<array-key, mixed>|string $payload
     * @param array<string, string|list<string>> $headers
     * @param array<array-key, mixed> $meta
     * @param array<array-key, string> $tags
     * @param int $attempt
     * @param Response|null $response
     * @param string|null $errorType
     * @param string|null $errorMessage
     * @param string $uuid
     * @param TransferStats|null $transferStats
     */
    public function __construct(
        public string $httpVerb,
        public string $webhookUrl,
        public array|string $payload,
        public array $headers,
        public array $meta,
        public array $tags,
        public int $attempt,
        public ?Response $response,
        public ?string $errorType,
        public ?string $errorMessage,
        public string $uuid,
        public ?TransferStats $transferStats
    ) {
    }

    /** @return array<array-key, mixed> */
    public function __serialize(): array
    {
        $properties = $this->serializeProperties();
        $properties['response'] = $this->response === null ? null : [
            'message' => $this->serializeResponse($this->response->toPsrResponse()),
            'cookies' => base64_encode(serialize($this->response->cookies)),
            'transferStats' => $this->serializeTransferStats($this->response->transferStats),
        ];
        $properties['transferStats'] = $this->serializeTransferStats($this->transferStats);

        return $properties;
    }

    /** @param array<array-key, mixed>&array{response: HttpResponseData|null, transferStats: TransferData|null} $values */
    public function __unserialize(array $values): void
    {
        $responseData = $values['response'];

        if ($responseData !== null) {
            $response = new Response($this->restoreResponse($responseData['message']));
            // Laravel's untyped property also holds false when cookies are disabled.
            (new ReflectionProperty(Response::class, 'cookies'))->setValue($response, unserialize(base64_decode($responseData['cookies'])));
            $response->transferStats = $this->restoreTransferStats($responseData['transferStats']);
            $values['response'] = $response;
        }

        $values['transferStats'] = $this->restoreTransferStats($values['transferStats']);

        $this->restoreProperties($values);
    }

    /** @return ResponseData */
    protected function serializeResponse(ResponseInterface $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'headers' => $this->serializeHeaders($response->getHeaders()),
            'body' => $this->serializeBody($response->getBody()),
            'protocol' => base64_encode($response->getProtocolVersion()),
            'reason' => base64_encode($response->getReasonPhrase()),
        ];
    }

    /** @param ResponseData $data */
    protected function restoreResponse(array $data): ResponseInterface
    {
        return new PsrResponse(
            $data['status'],
            $this->restoreHeaders($data['headers']),
            base64_decode($data['body']),
            base64_decode($data['protocol']),
            base64_decode($data['reason']),
        );
    }

    /** @return TransferData|null */
    protected function serializeTransferStats(?TransferStats $stats): ?array
    {
        if ($stats === null) {
            return null;
        }

        $request = $stats->getRequest();
        $response = $stats->getResponse();

        return [
            'method' => base64_encode($request->getMethod()),
            'uri' => base64_encode((string) $request->getUri()),
            'headers' => $this->serializeHeaders($request->getHeaders()),
            'body' => $this->serializeBody($request->getBody()),
            'protocol' => base64_encode($request->getProtocolVersion()),
            'target' => base64_encode($request->getRequestTarget()),
            'response' => $response === null ? null : $this->serializeResponse($response),
            'time' => $stats->getTransferTime(),
            'error' => base64_encode(serialize($stats->getHandlerErrorData())),
            'stats' => array_map(fn (mixed $value): string => base64_encode(serialize($value)), $stats->getHandlerStats()),
        ];
    }

    /** @param TransferData|null $data */
    protected function restoreTransferStats(?array $data): ?TransferStats
    {
        if ($data === null) {
            return null;
        }

        $request = new PsrRequest(
            base64_decode($data['method']),
            base64_decode($data['uri']),
            $this->restoreHeaders($data['headers']),
            base64_decode($data['body']),
            base64_decode($data['protocol']),
        );

        return new TransferStats(
            $request->withRequestTarget(base64_decode($data['target'])),
            $data['response'] === null ? null : $this->restoreResponse($data['response']),
            $data['time'],
            unserialize(base64_decode($data['error'])),
            array_map(fn (string $value): mixed => unserialize(base64_decode($value)), $data['stats']),
        );
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     * @return array<array-key, array<array-key, string>>
     */
    protected function serializeHeaders(array $headers): array
    {
        return array_map(fn (array $values): array => array_map(base64_encode(...), $values), $headers);
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     * @return array<array-key, array<array-key, string>>
     */
    protected function restoreHeaders(array $headers): array
    {
        return array_map(fn (array $values): array => array_map(base64_decode(...), $values), $headers);
    }

    protected function serializeBody(StreamInterface $body): string
    {
        if (! $body->isSeekable()) {
            self::$nonSeekableBodies ??= new WeakMap();

            return self::$nonSeekableBodies[$body] ??= base64_encode((string) $body);
        }

        $position = $body->tell();

        try {
            return base64_encode((string) $body);
        } finally {
            $body->seek($position);
        }
    }
}
