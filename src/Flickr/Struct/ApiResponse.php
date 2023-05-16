<?php
declare(strict_types=1);

namespace App\Flickr\Struct;

use App\Exception\Api\ApiCallException;
use App\Exception\Api\UnexpectedResponseException;
use App\Struct\ApiError;
use Symfony\Contracts\HttpClient\ResponseInterface;

readonly final class ApiResponse
{
    public function __construct(private array $data, private ?string $envelope, private ResponseInterface $response)
    {
    }

    public function isSuccessful(): bool
    {
        return isset($this->data['stat']) && $this->data['stat'] === 'ok';
    }

    public function getContent(): array
    {
        if (!$this->isSuccessful()) {
            throw $this->createApiError();
        }

        if ($this->envelope !== null) {
            if (!isset($this->data[$this->envelope])) {
                throw UnexpectedResponseException::create(
                    \sprintf('API returned data but not in an expected "%s" envelope', $this->envelope),
                    $this->data
                );
            }

            return $this->data[$this->envelope];
        }

        return $this->data;
    }

    /**
     * See \Symfony\Contracts\HttpClient\ResponseInterface::getInfo
     */
    public function getHttpInfo(string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    private function createApiError(): ApiCallException
    {
        if (!isset($this->data['code'])) {
            return UnexpectedResponseException::createFromError(
                ApiError::UNKNOWN_ERROR,
                'API responded with failure without providing reason code',
                null,
                \json_encode($this->data, \JSON_THROW_ON_ERROR)
            );
        }

        if ((string)(int)$this->data['code'] !== (string)$this->data['code']) {
            return UnexpectedResponseException::create(
                \sprintf(
                    'API responded with failure with non-integer error code (%s)',
                    (string)$this->data['code']
                ),
                $this->data
            );
        }

        return ApiCallException::createFromError(
            (int)$this->data['code'],
            $decodedContent['message'] ?? null,
            null,
            \json_encode($this->data, \JSON_THROW_ON_ERROR)
        );
    }
}
