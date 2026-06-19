<?php
/*
  复制本文件为 config.php，然后填写真实配置。
  config.php 不要提交到公开仓库，不要给无关人员。
*/

return [
  // 钉钉企业内部应用凭证
  'appKey' => '填写你的 AppKey / Client ID',
  'appSecret' => '填写你的 AppSecret / Client Secret',

  // 操作人 unionId。Notable API 要求 operatorId 使用 unionId。
  'operatorId' => '填写操作人的 unionId，例如刚查到的 8CbnW23l2iPfKNt4gMWPrHwiEiE',

  // 已创建的领航保 AI 表格
  'baseId' => 'Y1OQX0akWm6NGrjMCbRn7wkvVGlDd3mE',
  'sheetIdOrName' => '5JkjRIY',

  // 如果暂时只想测试 PHP 收数，不写钉钉，改成 false。
  'enableDingTalkWrite' => true
];
