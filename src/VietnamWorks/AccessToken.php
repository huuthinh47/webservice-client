<?php
/**
 * Created by PhpStorm.
 * User: khoalengoc
 * Date: 6/10/16
 * Time: 4:09 PM
 */

namespace VietnamWorks;


class AccessToken
{

    protected $token;

    protected $refresh_token;

    public function __construct($token, $refreshToken)
    {
        $this->token = $token;
        $this->refresh_token = $refreshToken;
    }


    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @return mixed
     */
    public function getRefreshToken()
    {
        return $this->refresh_token;
    }

    /**
     * @param mixed $refresh_token
     */
    public function setRefreshToken($refresh_token)
    {
        $this->refresh_token = $refresh_token;
    }
}