<?php


namespace white\commerce\picqer\errors;

class PicqerApiException extends \Exception
{
    public const PRODUCT_DOES_NOT_EXIST = 24;
    public const ORDER_IS_BEING_PROCESSED = 30;
    public const ORDER_ALREADY_CLOSED = 32;
    
    protected mixed $picqerErrorCode;
    
    protected mixed $picqerErrorMessage;
    
    public function __construct(array $response)
    {
        if (isset($response['errormessage'])) {
            if (is_string($response['errormessage'])) {
                $data = \json_decode($response['errormessage'], true, 512, JSON_THROW_ON_ERROR);
                if ($data !== null) {
                    $response['errormessage'] = $data;
                }
            }
            
            if (isset($response['errormessage']['error_code'])) {
                $this->picqerErrorCode = $response['errormessage']['error_code'];
            }
            if (isset($response['errormessage']['error_message'])) {
                $this->picqerErrorMessage = $response['errormessage']['error_message'];
            }
        }
        
        $message = sprintf('Invalid Picqer API response: [%d] %s',
            $this->picqerErrorCode,
            $this->picqerErrorMessage ?? \json_encode($response, JSON_THROW_ON_ERROR));
        
        parent::__construct($message);
    }

    /**
     * @return mixed
     */
    public function getPicqerErrorCode(): mixed
    {
        return $this->picqerErrorCode;
    }

    /**
     * @return mixed
     */
    public function getPicqerErrorMessage(): mixed
    {
        return $this->picqerErrorMessage;
    }
}
