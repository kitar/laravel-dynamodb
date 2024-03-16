<?php

namespace Kitar\Dynamodb\Model;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as BaseUserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class AuthUserProvider implements BaseUserProvider
{
    /**
     * The hasher implementation.
     *
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * The Eloquent user model.
     *
     * @var string
     */
    protected $model;

    /**
     * The column name of the api token.
     *
     * @var string
     */
    protected $apiTokenName;

    /**
     * The index name to use when querying by api token.
     *
     * @var string
     */
    protected $apiTokenIndex;

    public function __construct(HasherContract $hasher, $model, $apiTokenName = null, $apiTokenIndex = null)
    {
        $this->model = $model;
        $this->hasher = $hasher;
        $this->apiTokenName = $apiTokenName;
        $this->apiTokenIndex = $apiTokenIndex;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $model = $this->createModel();

        return $model->find($identifier);
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

        if (! $user) {
            return;
        }

        $rememberToken = $user->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token)
                        ? $user : null;
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

        $timestamps = $user->timestamps;

        $user->timestamps = false;

        $user->save();

        $user->timestamps = $timestamps;
    }

    /**
     * Retrieve a user by the given credentials.
     * Identifier or API Token are supported.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (isset($credentials['password'])) {
            unset($credentials['password']);
        }

        if (count($credentials) !== 1) {
            return;
        }

        $model = $this->createModel();

        $id = $credentials[$model->getAuthIdentifierName()] ?? null;

        if ($id) {
            return $this->retrieveById($id);
        }

        $apiToken = $this->apiTokenName ? $credentials[$this->apiTokenName] ?? null : null;

        if ($apiToken && $this->apiTokenIndex) {
            return $model->index($this->apiTokenIndex)
                         ->keyCondition($this->apiTokenName, '=', $apiToken)
                         ->query()
                         ->first();
        }
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
        $plain = $credentials['password'];

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

    /**
     * Rehash the user's password if required and supported.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @param  bool  $force
     * @return void
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        if (! $this->hasher->needsRehash($user->getAuthPassword()) && ! $force) {
            return;
        }

        $user->forceFill([
            $user->getAuthPasswordName() => $this->hasher->make($credentials['password']),
        ])->save();
    }

    /**
     * Create a new instance of the model.
     *
     * @return \Kitar\Dynamodb\Model\Model
     */
    public function createModel()
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }
}
