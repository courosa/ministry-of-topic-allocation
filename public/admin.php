<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
ini_set('session.use_strict_mode','1');
// Use a private writable session directory independent of the host defaults.
$sessionDirectory=chooser_setting('CHOOSER_SESSION_DIR',chooser_private_directory().'/sessions');
if(!is_dir($sessionDirectory))mkdir($sessionDirectory,0700,true);
session_save_path($sessionDirectory);
session_name(chooser_setting('CHOOSER_ADMIN_SESSION_NAME','chooser_admin_'.substr(hash('sha256',__DIR__),0,12)));
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);session_start();
define('PASSWORD_HASH',(string)chooser_setting('CHOOSER_ADMIN_PASSWORD_HASH',''));
define('DEMO',(bool)chooser_setting('CHOOSER_DEMO',false));
function h($s): string {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function redirect(): void {header('Location: admin.php',true,303);exit;}
$_SESSION['csrf']=$_SESSION['csrf']??bin2hex(random_bytes(32));
$error='';$notice=$_SESSION['notice']??'';unset($_SESSION['notice']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Session check failed. Reload this page and try again.');}
 if(($_POST['action']??'')==='login'){
  if(($_SESSION['retry_after']??0)>time())$error='Please wait a minute before trying again.';
  elseif(is_string($_POST['password']??null)&&password_verify($_POST['password'],PASSWORD_HASH)){session_regenerate_id(true);$_SESSION['instructor']=true;$_SESSION['last_active']=time();$_SESSION['failures']=0;redirect();}
  else{$_SESSION['failures']=($_SESSION['failures']??0)+1;if($_SESSION['failures']>=5)$_SESSION['retry_after']=time()+60;$error='That password did not match.';}
 }
 if(($_POST['action']??'')==='logout'){$_SESSION=[];session_destroy();redirect();}
}
$authenticated=($_SESSION['instructor']??false)&&($_SESSION['last_active']??0)>time()-7200;
if($authenticated)$_SESSION['last_active']=time();
$topics=json_decode(file_get_contents(__DIR__.'/topics.json'),true);
$byId=array_column($topics,null,'id');$reservations=[];$pending=null;
if($authenticated){
 try{
  $directory=chooser_private_directory();
  if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('Storage unavailable');
  $db=new PDO('sqlite:'.$directory.'/reservations.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('PRAGMA busy_timeout=5000');
  $db->exec('CREATE TABLE IF NOT EXISTS reservations (topic_id TEXT PRIMARY KEY,name TEXT NOT NULL,name_key TEXT NOT NULL UNIQUE,created_at TEXT NOT NULL,request_id TEXT NOT NULL UNIQUE)');
  if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='release'){
   $q=$db->prepare('DELETE FROM reservations WHERE topic_id=? AND request_id=?');$q->execute([$_POST['topic_id']??'',$_POST['request_id']??'']);
   $_SESSION['notice']=$q->rowCount()?'The reservation was released. This topic is available again.':'That reservation has already changed. No other reservation was removed.';redirect();
  }
  $reservations=array_column($db->query('SELECT topic_id,name,created_at,request_id FROM reservations')->fetchAll(PDO::FETCH_ASSOC),null,'topic_id');
  if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='review-release')$pending=$reservations[$_POST['topic_id']??'']??null;
  if(($_GET['download']??'')==='csv'){
   header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="eci833-'.(DEMO?'demo-':'').'signups.csv"');$out=fopen('php://output','w');
   fputcsv($out,['Topic','Presentation date','Student name','Signed up (Saskatchewan time)','Status'],',','"','');
   foreach($topics as $t){$r=$reservations[$t['id']]??null;$cells=[$t['title'],$t['date'],$r['name']??'',$r?(new DateTimeImmutable($r['created_at']))->setTimezone(new DateTimeZone('America/Regina'))->format('Y-m-d H:i:s'):'',$r?'Reserved':'Available'];foreach($cells as &$cell){if(preg_match('/^[\s]*[=+@-]/u',$cell))$cell="'".$cell;}unset($cell);fputcsv($out,$cells,',','"','');}fclose($out);exit;
  }
 }catch(Throwable $e){error_log('Chooser admin: '.$e->getMessage());$error='Results are temporarily unavailable. Please try again.';}
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=DEMO?'Demo':'Real'?> signup results | EC&I 833</title><link rel="stylesheet" href="style.css?v=mission3"><style>
main{max-width:1200px}h1{font-size:2.8rem}.panel{padding:24px;background:white;border:1px solid var(--line);border-radius:10px;margin:24px 0}.login{max-width:480px}.toolbar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:20px 0}.toolbar a{color:var(--teal);font-weight:700}.toolbar form{margin-left:auto}.scroll{overflow-x:auto}table{border-collapse:collapse;width:100%;font-size:.95rem}th,td{text-align:left;vertical-align:top;padding:16px 12px;border-bottom:1px solid var(--line)}th{font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;background:#eaf2ee}td:first-child{min-width:240px}td button{font-size:.8rem;padding:8px 12px}.muted{color:#63716d}.warning{border-left:5px solid #bb7833;background:#fff7e8;padding:20px}.error{color:#9b2727}.stats{font-size:1.4rem}.badge{display:inline-block;padding:5px 12px;background:<?=DEMO?'#fff0cf':'#e2f1e6'?>;font-weight:750}.login button{margin-top:20px}label{margin-top:16px}a{color:var(--teal)}
</style></head><body><main><p class="eyebrow">EC&I 833 · Instructor dashboard</p><span class="badge"><?=DEMO?'DEMO RESULTS · practice reservations only':'REAL COURSE RESULTS'?></span><h1>Topic signup results</h1>
<?php if($error):?><p class="panel error" role="alert"><?=h($error)?></p><?php endif;?>
<?php if(!$authenticated):?><section class="panel login"><h2>Instructor sign-in</h2><p>Use the dashboard password supplied with the chooser. This is separate from your cPanel password.</p><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="login"><label for="password">Dashboard password</label><input id="password" type="password" name="password" autocomplete="current-password" required><button>Sign in</button></form></section>
<?php else:?>
<?php if($notice):?><p class="panel" role="status"><?=h($notice)?></p><?php endif;?>
<p class="stats"><strong><?=count($reservations)?> of <?=count($topics)?></strong> topics selected</p>
<div class="toolbar"><a href="admin.php">Refresh results</a><a href="admin.php?download=csv">Download CSV</a><a href="./">View signup page</a><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button class="secondary" name="action" value="logout">Sign out</button></form></div>
<p class="muted">Times below are Saskatchewan time. Use Refresh results to see new signups.</p>
<?php if($pending):?><section class="warning"><h2>Release this topic?</h2><p>This removes <strong><?=h($pending['name'])?></strong> from <strong><?=h($byId[$pending['topic_id']]['title'])?></strong> and lets someone else choose it.</p><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="topic_id" value="<?=h($pending['topic_id'])?>"><input type="hidden" name="request_id" value="<?=h($pending['request_id'])?>"><button name="action" value="release">Yes, release this topic</button> <a href="admin.php">Cancel</a></form></section><?php endif;?>
<div class="panel scroll"><table><thead><tr><th scope="col">Topic</th><th scope="col">Class date</th><th scope="col">Student</th><th scope="col">Signup time</th><th scope="col">Action</th></tr></thead><tbody>
<?php foreach($topics as $t):$r=$reservations[$t['id']]??null;?><tr><td><?=h($t['title'])?></td><td><?=h((new DateTimeImmutable($t['date']))->format('M j, Y'))?></td><td><?=$r?h($r['name']):'<span class="muted">Available</span>'?></td><td><?=$r?h((new DateTimeImmutable($r['created_at']))->setTimezone(new DateTimeZone('America/Regina'))->format('M j, g:i:s a')):'-'?></td><td><?php if($r):?><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="topic_id" value="<?=h($t['id'])?>"><button class="secondary" name="action" value="review-release">Release…</button></form><?php endif;?></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?></main></body></html>
