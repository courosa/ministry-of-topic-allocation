<?php
// Run only from cPanel Git deployment, never over HTTP.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function fail(string $message): never { fwrite(STDERR,$message."\n"); exit(1); }
function put(string $path,string $content): void {
 if (!is_dir(dirname($path)) && !mkdir(dirname($path),0755,true)) fail('Cannot create destination directory');
 $tmp=$path.'.deploy-'.bin2hex(random_bytes(6));
 if(file_put_contents($tmp,$content)===false) fail('Cannot stage '.$path);
 chmod($tmp,0644);
 if(!rename($tmp,$path)) fail('Cannot publish '.$path);
}
$home=$argv[1]??getenv('HOME');
if(!$home || !is_dir($home)) fail('Home directory unavailable');
$lock=fopen($home.'/.course-sites-deploy.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) fail('Another deployment is running');
$backup=$home.'/.course-site-backups/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
if(!mkdir($backup,0700,true)) fail('Cannot create backup');

$targets=[$home.'/eci833.ca/chooser',$home.'/eci833.ca/chooserdemo',$home.'/public_html/chooserdemo'];
$files=['index.html','style.css','app.js','api.php','admin.php','config.php','assets/ministry-hero.jpg','assets/topic-engravings.jpg'];
$plan=[];
foreach($targets as $i=>$target){
 if(!is_dir($target)) fail('Expected existing site missing: '.$target);
 // Capture legacy settings locally before the first deployment. Never publish credentials to GitHub.
 $settingsPath=$target.'/config.local.php';
 if(is_file($settingsPath)) $settings=require $settingsPath;
 else {
  $api=file_get_contents($target.'/api.php'); $admin=file_get_contents($target.'/admin.php');
  $settings=[];
  foreach(['CHOOSER_OPEN_AT','CHOOSER_DATA_DIR'] as $key){
   if(!preg_match("/getenv\('".$key."'\)\s*\?:\s*'([^']+)'/",$api,$m)) fail('Cannot preserve '.$key);
   $settings[$key]=$m[1];
  }
  if(!preg_match("/const PASSWORD_HASH\s*=\s*'([^']+)'/",$admin,$m)) fail('Cannot preserve administrator settings');
  $settings['CHOOSER_ADMIN_PASSWORD_HASH']=$m[1];
  if(!preg_match('/\$cookieName\s*=\s*\x27([^\x27]+)\x27/',$api,$m)) fail('Cannot preserve browser identity');
  $settings['CHOOSER_COOKIE_NAME']=$m[1];
  if(!preg_match("/session_name\('([^']+)'\)/",$admin,$m)) fail('Cannot preserve administrator session');
  $settings['CHOOSER_ADMIN_SESSION_NAME']=$m[1];
  if(!preg_match("/getenv\('CHOOSER_SESSION_DIR'\)\s*\?:\s*'([^']+)'/",$admin,$m)) fail('Cannot preserve session directory');
  $settings['CHOOSER_SESSION_DIR']=$m[1];
  $settings['CHOOSER_DEMO']=str_contains($admin,'const DEMO = true;');
 }
 if(empty($settings['CHOOSER_ADMIN_PASSWORD_HASH']) || empty($settings['CHOOSER_DATA_DIR'])) fail('Incomplete private settings');
 $oldHtml=file_get_contents($target.'/index.html');
 if(!preg_match('/<p class="launch-date">.*?<\/p>/s',$oldHtml,$date)) fail('Cannot preserve opening label');
 preg_match('/<p class="demo-banner">.*?<\/p>/s',$oldHtml,$banner);
 foreach($files as $file){
  if(!is_file(__DIR__.'/public/'.$file)) fail('Missing source '.$file);
  if(is_file($target.'/'.$file)) put($backup.'/chooser-'.$i.'/'.$file,file_get_contents($target.'/'.$file));
  $content=file_get_contents(__DIR__.'/public/'.$file);
  if($file==='index.html'){
   $content=preg_replace('/<p class="launch-date">.*?<\/p>/s',$date[0],$content);
   if(!empty($banner)) $content=str_replace('<main>','<main>'.$banner[0],$content);
  }
  $plan[$target.'/'.$file]=$content;
 }
 foreach(['LICENSE','CONTENT-LICENSE.md'] as $file){
  if(is_file($target.'/'.$file)) put($backup.'/chooser-'.$i.'/'.$file,file_get_contents($target.'/'.$file));
  $plan[$target.'/'.$file]=file_get_contents(__DIR__.'/'.$file);
 }
 // Preserve HTTPS rules and block requests to private configuration files.
 $htaccess=file_get_contents($target.'/.htaccess');
 if(!str_contains($htaccess,'config.*')) {
  put($backup.'/chooser-'.$i.'/.htaccess',$htaccess);
  $plan[$target.'/.htaccess']=$htaccess."\n<FilesMatch \"^config.*\\.php$\">\nRequire all denied\n</FilesMatch>\n";
 }
 // Existing topics, database files, and local settings are retained.
 if(!is_file($settingsPath)) $plan[$settingsPath]="<?php\nreturn ".var_export($settings,true).";\n";
}
// Private configuration must be present before replacing the application code.
foreach($plan as $path=>$content) if(str_ends_with($path,'/config.local.php')) {put($path,$content);chmod($path,0600);}
foreach($plan as $path=>$content) if(!str_ends_with($path,'/config.local.php')) put($path,$content);
echo "Published all three chooser sites. Dates, reservations, and credentials retained. Backup: $backup\n";
