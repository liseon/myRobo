<?php

class ConfigHelper
{
    private $config;

    private $SECRET;

    private function __construct(){
        $this->config = include_once('.configuration/trader.config.php');
        $this->SECRET = include_once('.configuration/secret.config.php');
    }

    public static function getInstance() {
        static $self = null;
        if (is_null($self)) {
            $self = new self();
        }

        return $self;
    }

    public function get($name) {
        return $this->config[$name];
    }

    public function getSecret($name) {
        return $this->SECRET[$name];
    }
}