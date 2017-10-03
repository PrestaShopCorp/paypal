<?php
var_dump(file_exists(dirname(__FILE__). '../../../../vendor/autoload.php'));die;
if(file_exists( dirname(__FILE__). '/vendor/autoload.php')) {
require 'vendor/autoload.php';
}