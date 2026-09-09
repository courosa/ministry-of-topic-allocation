<?php
declare(strict_types=1);
// Deployment settings are intentionally excluded from the repository.
$chooserSettings=is_file(__DIR__.'/config.local.php') ? require __DIR__.'/config.local.php' : [];
function chooser_setting(string $key, $default=null) {
 global $chooserSettings;
 $env=getenv($key);
 return $env!==false && $env!=='' ? $env : ($chooserSettings[$key]??$default);
}
function chooser_private_directory(): string {
 $path=(string)chooser_setting('CHOOSER_DATA_DIR','');
 if($path==='' || $path[0]!=='/') {http_response_code(503);exit('Configure a private absolute CHOOSER_DATA_DIR before using the chooser.');}
 return rtrim($path,'/');
}
