<?php

namespace Kitar\Dynamodb\Model;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as BaseUserProvider;
use Illuminate\Support\Facades\Hash;

class AuthUserProvider implements BaseUserProvider
{
    protected $model;

    public function __construct(Authenticatable $model)
    {
        $this->model = $model;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $identifierName = $this->model->getAuthIdentifierName();

        $this->model->$identifierName = $identifier;

        $user = $this->model->getItem(
            $this->model->getKey()
        );

        return $user;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $user = $this->retrieveById($identifier);

        if ($user && $user->getRememberToken() == $token) {
            return $user;
        }
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        return $this->retrieveById($credentials['email']);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return Hash::check($credentials['password'], $user->password);
    }
}
