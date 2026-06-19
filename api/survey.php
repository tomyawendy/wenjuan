<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fail(405, '只允许 POST 提交');
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!$payload || !isset($payload['recordId']) || !isset($payload['aiTableCells'])) {
  fail(400, '提交数据不完整');
}

$config = load_config();
$logResult = append_local_audit_log($payload);

if (!empty($config['enableDingTalkWrite'])) {
  try {
    $dingtalkResult = write_to_dingtalk($config, $payload);
  } catch (Exception $error) {
    append_error_log($payload, $error->getMessage());
    fail(500, '钉钉 AI 表格写入失败：' . $error->getMessage(), [
      'recordId' => $payload['recordId'],
      'localLog' => $logResult
    ]);
  }
} else {
  $dingtalkResult = [
    'enabled' => false,
    'message' => '已保存到本地日志，未启用钉钉写入'
  ];
}

echo json_encode([
  'ok' => true,
  'recordId' => $payload['recordId'],
  'localLog' => $logResult,
  'dingtalk' => $dingtalkResult
], JSON_UNESCAPED_UNICODE);

function load_config() {
  $configFile = __DIR__ . '/config.php';
  if (!file_exists($configFile)) {
    return ['enableDingTalkWrite' => false];
  }

  $config = require $configFile;
  if (empty($config['enableDingTalkWrite'])) {
    $config['enableDingTalkWrite'] = false;
    return $config;
  }

  foreach (['appKey', 'appSecret', 'operatorId', 'baseId', 'sheetIdOrName'] as $key) {
    if (empty($config[$key]) || strpos($config[$key], '填写') !== false) {
      throw new Exception('config.php 缺少配置：' . $key);
    }
  }

  return $config;
}

function write_to_dingtalk($config, $payload) {
  $accessToken = get_dingtalk_access_token($config['appKey'], $config['appSecret']);
  $operatorId = rawurlencode($config['operatorId']);
  $baseId = rawurlencode($config['baseId']);
  $sheet = rawurlencode($config['sheetIdOrName']);
  $clientToken = rawurlencode(uuid_v4());

  $url = "https://api.dingtalk.com/v1.0/notable/bases/{$baseId}/sheets/{$sheet}/records?operatorId={$operatorId}&clientToken={$clientToken}";
  $body = [
    'records' => [
      [
        'fields' => clean_record_fields($payload['aiTableCells'])
      ]
    ]
  ];

  return http_json('POST', $url, $body, [
    'x-acs-dingtalk-access-token: ' . $accessToken
  ]);
}

function get_dingtalk_access_token($appKey, $appSecret) {
  $cacheFile = dirname(__DIR__) . '/data/dingtalk-token-cache.json';
  if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
    if ($cache && !empty($cache['accessToken']) && !empty($cache['expiresAt']) && $cache['expiresAt'] > time() + 120) {
      return $cache['accessToken'];
    }
  }

  $result = http_json('POST', 'https://api.dingtalk.com/v1.0/oauth2/accessToken', [
    'appKey' => $appKey,
    'appSecret' => $appSecret
  ]);

  if (empty($result['accessToken'])) {
    throw new Exception('未获取到 accessToken：' . json_encode($result, JSON_UNESCAPED_UNICODE));
  }

  $expireIn = isset($result['expireIn']) ? intval($result['expireIn']) : 7200;
  file_put_contents($cacheFile, json_encode([
    'accessToken' => $result['accessToken'],
    'expiresAt' => time() + $expireIn
  ], JSON_UNESCAPED_UNICODE));

  return $result['accessToken'];
}

function clean_record_fields($fields) {
  $clean = [];
  foreach ($fields as $key => $value) {
    if ($value === '' || $value === null) continue;
    if (is_array($value) && count($value) === 0) continue;
    $clean[$key] = $value;
  }
  return $clean;
}

function uuid_v4() {
  $data = random_bytes(16);
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function http_json($method, $url, $body = null, $extraHeaders = []) {
  $headers = array_merge([
    'Content-Type: application/json; charset=utf-8'
  ], $extraHeaders);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);

  if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
  }

  $response = curl_exec($ch);
  $error = curl_error($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($response === false) {
    throw new Exception('网络请求失败：' . $error);
  }

  $decoded = json_decode($response, true);
  if ($status < 200 || $status >= 300) {
    throw new Exception("HTTP {$status}: " . $response);
  }

  return $decoded ?: ['raw' => $response];
}

function append_local_audit_log($payload) {
  $dataDir = dirname(__DIR__) . '/data';
  if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
  }

  $logFile = $dataDir . '/survey-submissions.jsonl';
  $line = json_encode([
    'receivedAt' => date('c'),
    'recordId' => $payload['recordId'],
    'tableRecord' => $payload['tableRecord'] ?? null,
    'apiRecordFields' => $payload['apiRecordFields'] ?? null,
    'aiTableCells' => $payload['aiTableCells'] ?? null,
    'tags' => $payload['tags'] ?? [],
    'raw' => $payload
  ], JSON_UNESCAPED_UNICODE);

  file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

  return [
    'saved' => true,
    'file' => 'data/survey-submissions.jsonl'
  ];
}

function append_error_log($payload, $message) {
  $dataDir = dirname(__DIR__) . '/data';
  if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
  }

  $line = json_encode([
    'time' => date('c'),
    'recordId' => $payload['recordId'] ?? '',
    'message' => $message
  ], JSON_UNESCAPED_UNICODE);

  file_put_contents($dataDir . '/survey-errors.jsonl', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function fail($status, $message, $extra = []) {
  http_response_code($status);
  echo json_encode(array_merge([
    'ok' => false,
    'message' => $message
  ], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}
