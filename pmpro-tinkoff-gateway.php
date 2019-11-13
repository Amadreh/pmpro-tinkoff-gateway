<?php
/*
Plugin Name: Tinkoff Gateway for Paid Memberships Pro
Description: Tinkoff Gateway for Paid Memberships Pro
Version: .1
*/

define("PMPRO_TINKOFFGATEWAY_DIR", dirname(__FILE__));

//load payment gateway class
require_once(PMPRO_TINKOFFGATEWAY_DIR . "/classes/class.pmprogateway_tinkoff.php");