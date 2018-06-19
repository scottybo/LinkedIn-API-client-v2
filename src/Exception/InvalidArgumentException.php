<?php

namespace Scottybo\LinkedIn2\Exception;

class InvalidArgumentException extends LinkedInException
{
    /**
     * Treat this constructor as sprintf().
     */
    public function __construct()
    {
        parent::__construct(call_user_func_array('sprintf', func_get_args()));
    }
}
