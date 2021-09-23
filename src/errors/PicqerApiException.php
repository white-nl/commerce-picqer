<?php


namespace white\commerce\picqer\errors;

class PicqerApiException extends \Exception
{
    public const PRODUCT_DOES_NOT_EXIST = 24;
    public const ORDER_IS_BEING_PROCESSED = 30;
    public const ORDER_ALREADY_CLOSED = 32;
    
    protected $picqerErrorCode;
    
    protected $picqerErrorMessage;
    
    public function __construct(array $response)
    {
        if (isset($response['errormessage'])) {
            if (is_string($response['errormessage'])) {
                $data = \json_decode($response['errormessage'], true);
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
            $this->picqerErrorMessage ?? \json_encode($response));
        
        parent::__construct($message);
    }

    /**
     * @return mixed
     */
    public function getPicqerErrorCode()
    {
        return $this->picqerErrorCode;
    }

    /**
     * @return mixed
     */
    public function getPicqerErrorMessage()
    {
        return $this->picqerErrorMessage;
    }
}
