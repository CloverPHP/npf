<?php

require_once "base.php";

$cronjob = new Npf\Cronjob($env, $appName);
$cronjob();
