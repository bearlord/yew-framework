<?php

namespace Yew\Framework\Exception;

class BadRequestException extends Exception
{

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Bad Request';
    }
}