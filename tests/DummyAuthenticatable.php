<?php

namespace Sofa\History\Tests;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;

class DummyAuthenticatable implements Authenticatable
{
    use AuthenticatableTrait;

    public User $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function getAuthIdentifier()
    {
        return $this->user->id;
    }
}
