<?php

require_once "base.php";

$daemon = new Npf\Daemon($env, $appName);
$daemon();
