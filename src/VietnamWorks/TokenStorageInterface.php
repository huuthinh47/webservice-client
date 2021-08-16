<?php
/**
 * Created by PhpStorm.
 * User: khoalengoc
 * Date: 6/10/16
 * Time: 4:09 PM
 */

namespace VietnamWorks;


interface TokenStorageInterface
{
    public function save(AccessToken $token);

    public function get();
}