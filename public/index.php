<?php

require_once dirname(__DIR__) . "/vendor/autoload.php";

echo json_encode([
    "status" => "success",
    "message" => "Cheer API running",
]);
