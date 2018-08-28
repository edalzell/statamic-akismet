<?php

namespace Statamic\Addons\Akismet\Exceptions;


class AkismetInvalidKeyException extends \Exception
{

    /**
     * @inheritdoc
     */
    public function __construct($message = null, $code = 0, $previous = null)
    {
        if (null === $message)
        {
            $message = 'Your API Key is not valid or has expired, please fix that.';
        }

        parent::__construct($message, $code, $previous);
    }
}