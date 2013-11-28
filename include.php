<?php
require_once('module/Api.class.php');
require_once('module/Exceptions.class.php');

function __autoload($class_name) {
    require_once("module/{$class_name}.class.php");
}