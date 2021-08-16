<?php
namespace VietnamWorks;

use VietnamWorks\AccessToken;

interface TokenStorageInterface
{
    public function save(AccessToken $token);

    public function get();
}