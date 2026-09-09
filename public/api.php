<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
function reply(int $status, array $data): void { http_response_code($status); echo json_encode($data); exit; }
$opening = new DateTimeImmutable(chooser_setting('CHOOSER_OPEN_AT','2100-01-01T00:00:00-06:00'));
$open = time() >= $opening->getTimestamp();
$cookieName = chooser_setting('CHOOSER_COOKIE_NAME','chooser_browser_'.substr(hash('sha256',__DIR__),0,12));
$cookiePath=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/').'/';
$browserId = $_COOKIE[$cookieName] ?? '';
$knownBrowser = is_string($browserId) && preg_match('/^[a-f0-9]{64}$/D', $browserId);
if (!$knownBrowser) {
    $browserId = bin2hex(random_bytes(32));
    setcookie($cookieName, $browserId, ['expires'=>time()+15552000,'path'=>$cookiePath,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
}
$browserKey=hash('sha256',$browserId);
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET','POST'], true)) { header('Allow: GET, POST'); reply(405,['message'=>'Method not allowed.']); }
try {
    $topics = json_decode(file_get_contents(__DIR__.'/topics.json'), true, 512, JSON_THROW_ON_ERROR);
    // Set CHOOSER_DATA_DIR to a private directory outside all public document roots.
    $directory = chooser_private_directory();
    if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Storage unavailable');
    $db = new PDO('sqlite:'.$directory.'/reservations.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('CREATE TABLE IF NOT EXISTS reservations (topic_id TEXT PRIMARY KEY, name TEXT NOT NULL, name_key TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL, request_id TEXT NOT NULL UNIQUE)');
    $db->exec('CREATE TABLE IF NOT EXISTS browser_claims (browser_key TEXT PRIMARY KEY, topic_id TEXT NOT NULL, request_id TEXT NOT NULL)');
    $browserLookup=$db->prepare('SELECT r.topic_id FROM browser_claims b JOIN reservations r ON r.topic_id=b.topic_id AND r.request_id=b.request_id WHERE b.browser_key=?');
    if ($method === 'GET') {
        $browserLookup->execute([$browserKey]);$ownTopic=$browserLookup->fetchColumn();
        reply(200,['ownTopicId'=>$ownTopic===false?null:$ownTopic,'open'=>$open,'opensAt'=>$opening->format(DATE_ATOM),'serverTime'=>gmdate(DATE_ATOM),'serverTimeMs'=>(int)round(microtime(true)*1000),'taken'=>$db->query('SELECT topic_id FROM reservations')->fetchAll(PDO::FETCH_COLUMN)]);
    }
    if (!$knownBrowser) reply(400,['message'=>'Please allow cookies, reload this page, and try again.']);
    if (!$open) reply(403,['message'=>'Signup has not opened yet. Check the countdown for the opening time.']);
    if (isset($_SERVER['HTTP_ORIGIN']) && parse_url($_SERVER['HTTP_ORIGIN'],PHP_URL_HOST) !== explode(':',$_SERVER['HTTP_HOST'])[0]) reply(403,['message'=>'Please use the course chooser page.']);
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) reply(415,['message'=>'Please use the course chooser page.']);
    $raw=file_get_contents('php://input',false,null,0,4097);
    if(strlen($raw)>4096) reply(413,['message'=>'Your entry is too long.']);
    try { $input=json_decode($raw,true,512,JSON_THROW_ON_ERROR); } catch(Throwable $e) { reply(400,['message'=>'Please enter your name and choose a topic.']); }
    $name=$input['name']??null;$topic=$input['topicId']??null;$requestId=$input['requestId']??null;
    if(!is_string($requestId)||!preg_match('/^[a-f0-9-]{36}$/i',$requestId)) reply(400,['message'=>'Please refresh the chooser and try again.']);
    if(!is_string($name)||!is_string($topic)) reply(400,['message'=>'Please enter your name and choose a topic.']);
    $name=preg_replace('/\s+/u',' ',trim($name));
    if(!$name || strlen($name)>200 || preg_match('/[\x00-\x1f\x7f]/',$name)) reply(400,['message'=>'Please enter a name your instructor can recognize.']);
    if(!in_array($topic,array_column($topics,'id'),true)) reply(400,['message'=>'Please select one of the listed topics.']);
    $key=function_exists('mb_strtolower')?mb_strtolower($name,'UTF-8'):strtolower($name);
    $key=rtrim($key, '. ');
    $db->exec('BEGIN IMMEDIATE');
    $retry=$db->prepare('SELECT topic_id,name_key FROM reservations WHERE request_id=?');$retry->execute([$requestId]);$retryRow=$retry->fetch(PDO::FETCH_ASSOC);
    if($retryRow){$db->exec('ROLLBACK');if($retryRow['topic_id']===$topic && $retryRow['name_key']===$key)reply(200,['confirmed'=>true,'topicId'=>$topic]);reply(409,['message'=>'A topic was already confirmed in this session. Contact your instructor if you need a change.']);}
    $browserLookup->execute([$browserKey]);
    if($browserLookup->fetchColumn()!==false){$db->exec('ROLLBACK');reply(409,['message'=>'This browser already has a presentation reserved. You cannot choose another topic. Contact your instructor if you need a change.']);}
    $existing=$db->prepare('SELECT topic_id FROM reservations WHERE name_key=?');$existing->execute([$key]);$previous=$existing->fetchColumn();
    if($previous!==false){$db->exec('ROLLBACK');reply(409,['message'=>'This name already has a topic. If it is not you, add a distinguishing initial or nickname. If it is you, contact your instructor to change your topic.']);}
    $taken=$db->prepare('SELECT 1 FROM reservations WHERE topic_id=?');$taken->execute([$topic]);
    if($taken->fetchColumn()){$db->exec('ROLLBACK');reply(409,['message'=>'Someone just chose this topic. Go back to see what is available and choose another.']);}
    $insert=$db->prepare('INSERT INTO reservations VALUES (?,?,?,?,?)');$insert->execute([$topic,$name,$key,gmdate(DATE_ATOM),$requestId]);$claim=$db->prepare('INSERT OR REPLACE INTO browser_claims (browser_key,topic_id,request_id) VALUES (?,?,?)');$claim->execute([$browserKey,$topic,$requestId]);$db->exec('COMMIT');
    reply(201,['confirmed'=>true,'topicId'=>$topic]);
} catch(Throwable $e) {
    error_log('ECI833 chooser: '.$e->getMessage());
    reply(503,['message'=>'Signup is temporarily unavailable. Please try again shortly.']);
}
