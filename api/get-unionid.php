<?php
header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
  fail(500, '请先复制 config.example.php 为 config.php，并填写 appKey/appSecret');
}

$config = require $configFile;
$userId = $_GET['userid'] ?? '';

if (!$userId) {
  fail(400, '请在地址后加 ?userid=员工UserId');
}

foreach (['appKey', 'appSecret'] as $key) {
  if (empty($config[$key]) || strpos($config[$key], '填写') !== false) {
    fail(500, 'config.php 缺少配置：' . $key);
  }
}

try {
  $accessToken = get_access_token($config['appKey'], $config['appSecret']);
  $result = http_json(
    'POST',
    'https://oapi.dingtalk.com/topapi/v2/user/get?access_token=' . rawurlencode($accessToken),
    ['userid' => $userId]
  );

  echo json_encode([
    'ok' => true,
    'userid' => $userId,
    'unionId' => $result['result']['unionid'] ?? null,
    'raw' => $result
  ], JSON_UNESCAPED_UNICODE);
} catch (Exception $error) {
  fail(500, $error->getMessage());
}

function get_access_token($appKey, $appSecret) {
  $result = http_json('POST', 'https://api.dingtalk.com/v1.0/oauth2/accessToken', [
    'appKey' => $appKey,
    'appSecret' => $appSecret
  ]);

  if (empty($result['accessToken'])) {
    throw new Exception('未获取到 accessToken：' . json_encode($result, JSON_UNESCAPED_UNICODE));
  }

  return $result['accessToken'];
}

function http_json($method, $url, $body = null) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);

  if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
  }

  $response = curl_exec($ch);
  $error = curl_error($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($response === false) throw new Exception('网络请求失败：' . $error);
  if ($status < 200 || $status >= 300) throw new Exception("HTTP {$status}: " . $response);

  return json_decode($response, true) ?: ['raw' => $response];
}

function fail($status, $message) {
  http_response_code($status);
  echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

/*
  安全提醒：
  这个文件只用于部署调试。拿到 unionId 后请删除，或加登录/白名单保护。
*/
