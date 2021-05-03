<?php
declare(strict_types=1);

namespace App\Exception\Api;

class ApiCallException extends \DomainException
{
    const UNEXPECTED_RESPONSE = -2;
    const LOCAL_ERROR = -1;
    const UNKNOWN_ERROR = 0;

    const USER_NOT_FOUND = 1;
    const INVALID_API_KEY = 100;
    const SERVICE_UNAVILABLE = 105;
    const WRITE_OP_FAILED = 106;
    const FORMAT_NOT_FOUND = 111;
    const METHOD_NOF_FOUND = 112;
    const INVALID_SOAP_ENV = 114;
    const INVALID_XML_RPC_CALL = 115;
    const BAD_URL = 116;

    private const CODE_TO_NAME = [
        self::UNEXPECTED_RESPONSE => 'Unexpected API response',
        self::LOCAL_ERROR => 'Local application error',
        self::UNKNOWN_ERROR => 'Unknown error',

        self::USER_NOT_FOUND => 'ID not found',
        self::INVALID_API_KEY => 'Invalid API Key',
        self::SERVICE_UNAVILABLE => 'Service currently unavailable',
        self::WRITE_OP_FAILED => 'Write operation failed',
        self::FORMAT_NOT_FOUND => 'Format "xxx" not found',
        self::METHOD_NOF_FOUND => 'Method "xxx" not found',
        self::INVALID_SOAP_ENV => 'Invalid SOAP envelope',
        self::INVALID_XML_RPC_CALL => 'Invalid XML-RPC Method Call',
        self::BAD_URL => 'Bad URL found',
    ];

    private ?string $data;

    private function __construct(?string $message = null, int $code = self::UNKNOWN_ERROR, \Throwable $previous = null, ?string $data = null)
    {
        $this->data = $data;

        parent::__construct($message, $code, $previous);
    }

    static function codeToName(int $code): string
    {
        return self::CODE_TO_NAME[$code] ?? \sprintf('%s (%d)', self::codeToName(self::UNKNOWN_ERROR), $code);
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    static public function createFromCode(int $code, ?string $detailedMessage = null, \Throwable $previous = null, ?string $data = null): static
    {
        $codeName = self::codeToName($code);
        $message = $detailedMessage === null ? $codeName : \sprintf('%s (#%d: %s)', $detailedMessage, $code, $codeName);

        return new static($message, $code, $previous, $data);
    }
}
