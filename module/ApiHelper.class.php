<?php

class ApiHelper extends BTCeApi
{
    protected function __construct(){
        parent::__construct(
            ConfigHelper::getInstance()->getSecret('KEY'),
            ConfigHelper::getInstance()->getSecret('SECRET')
        );
    }

    public static function getInstance() {
        static $self = null;
        if (is_null($self)) {
            $self = new self();
        }

        return $self;
    }
}