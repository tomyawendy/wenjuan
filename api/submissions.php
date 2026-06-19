<?php
header('Content-Type: text/plain; charset=utf-8');

$logFile = dirname(__DIR__) . '/data/survey-submissions.jsonl';

if (!file_exists($logFile)) {
  echo "暂无提交记录";
  exit;
}

echo file_get_contents($logFile);

/*
  注意：
  这个文件只用于测试期查看提交数据。
  正式上线前请删除，或改成登录后才允许访问。
*/
