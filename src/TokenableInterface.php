<?php

namespace Alterway\Component\Workflow;

interface TokenableInterface
{
    /**
     * @return string
     */
    public function getToken();

    /**
     * @param string $token
     */
    public function setToken($token);
}
