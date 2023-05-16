<?php
declare(strict_types=1);

namespace App\Struct;

enum ApiError: int
{
    case UNEXPECTED_RESPONSE = -2;
    case LOCAL_ERROR         = -1;
    case UNKNOWN_ERROR       = 0;

    case USER_NOT_FOUND       = 1;
    case UNKNOWN_PANDA        = 2;
    case SSL_IS_REQUIRED      = 95;
    case INVALID_SIGNATURE    = 96;
    case MISSING_SIGNATURE    = 97;
    case LOGIN_FAILED         = 98;
    case USER_NOT_LOGGED_IN   = 99;
    case INVALID_API_KEY      = 100;
    case SERVICE_UNAVAILABLE  = 105;
    case WRITE_OP_FAILED      = 106;
    case FORMAT_NOT_FOUND     = 111;
    case METHOD_NOF_FOUND     = 112;
    case INVALID_SOAP_ENV     = 114;
    case INVALID_XML_RPC_CALL = 115;
    case BAD_URL              = 116;

    public function description(): string
    {
        return match($this) {
            self::UNEXPECTED_RESPONSE => 'Unexpected API response',
            self::LOCAL_ERROR => 'Local application error',
            self::UNKNOWN_ERROR => 'Unknown error',

            self::USER_NOT_FOUND => 'ID not found',
            self::UNKNOWN_PANDA => 'You requested a panda we haven\'t met yet',
            self::SSL_IS_REQUIRED => 'SSL is required to access the Flickr API',
            self::INVALID_SIGNATURE => 'The passed signature was invalid',
            self::MISSING_SIGNATURE => 'Missing signature',
            self::LOGIN_FAILED => 'The login details or auth token passed were invalid',
            self::USER_NOT_LOGGED_IN => 'The method requires user authentication but the user was not logged in, ' .
                                        'or the authenticated method call did not have the required permissions',
            self::INVALID_API_KEY => 'Invalid API Key (is your "FLICKR_API_KEYS" environment variable set properly?)',
            self::SERVICE_UNAVAILABLE => 'Service currently unavailable',
            self::WRITE_OP_FAILED => 'Write operation failed',
            self::FORMAT_NOT_FOUND => 'Format "xxx" not found',
            self::METHOD_NOF_FOUND => 'Method "xxx" not found',
            self::INVALID_SOAP_ENV => 'Invalid SOAP envelope',
            self::INVALID_XML_RPC_CALL => 'Invalid XML-RPC Method Call',
            self::BAD_URL => 'Bad URL found',
        };
    }
}
