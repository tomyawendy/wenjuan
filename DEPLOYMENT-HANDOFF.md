# 领航保客户调研问卷部署交接说明

## 一、部署目标

这套问卷部署到领航保自有网站后，客户填写问卷，网页调用后端接口，后端继续写入同一个钉钉 AI 表格。

部署后链路为：

客户访问问卷页 -> 提交问卷 -> `api/survey.php` 接收 -> 钉钉开放接口 -> 钉钉 AI 表格新增记录 -> AI 表格内部字段继续做客户画像、跟进建议、风险原因、产品需求归纳和质量评分。

## 二、需要部署的文件

生产包里核心文件如下：

| 路径 | 用途 |
|---|---|
| `index.html` | 问卷前端页面 |
| `assets/logo.svg` | 领航保 logo |
| `api/survey.php` | 后端提交接口 |
| `api/config.example.php` | 后端配置模板 |
| `.htaccess` | 阻止访问本地日志目录 |
| `api/.htaccess` | 阻止访问后端密钥配置 |
| `AI-EVALUATION-FIELDS.md` | 钉钉 AI 表格内部字段说明 |

`api/get-unionid.php` 和 `api/submissions.php` 是早期测试工具，现在已经关闭，不建议部署到正式网站。

## 三、服务器要求

数字化部同事需要确认领航保网站服务器满足：

- 支持 PHP 7.4 或以上。
- PHP 开启 `curl`。
- 网站使用 HTTPS。
- `api/survey.php` 所在目录允许 PHP 执行。
- 网站程序有权限在问卷目录旁边创建或写入 `data/` 目录，用于本地审计日志。

## 四、推荐部署目录

建议部署到一个独立路径，例如：

```text
https://www.linghangbao.com/survey/
```

对应服务器目录示例：

```text
/www/wwwroot/linghangbao.com/survey/
```

把生产包里的文件放进去后，目录结构应类似：

```text
survey/
  index.html
  .htaccess
  assets/
    logo.svg
  api/
    survey.php
    config.php
    config.example.php
    .htaccess
```

## 五、配置后端接口

在服务器上复制：

```text
api/config.example.php -> api/config.php
```

然后填写钉钉配置：

```php
return [
  'appKey' => '钉钉企业内部应用 AppKey',
  'appSecret' => '钉钉企业内部应用 AppSecret',
  'operatorId' => '操作人的 unionId',
  'baseId' => '钉钉 AI 表格 Base ID',
  'sheetIdOrName' => '钉钉 AI 表格数据表 ID',
  'enableDingTalkWrite' => true
];
```

注意：

- `config.php` 不能提交到公开仓库。
- `config.php` 不能发给无关人员。
- 正式上线前建议重新生成钉钉应用密钥，并使用最小权限。

## 六、接口是否需要改

如果采用推荐目录结构，前端无需改接口。

当前 `index.html` 内的提交地址是：

```js
const SURVEY_API_ENDPOINT = 'api/survey.php';
```

也就是说，只要 `index.html` 和 `api/survey.php` 保持同级相对关系：

```text
survey/index.html
survey/api/survey.php
```

后端接口就不用改。

如果数字化部要把问卷嵌入到现有网站页面，而不是直接使用 `index.html`，需要确保提交接口路径仍然指向实际地址，例如：

```js
const SURVEY_API_ENDPOINT = '/survey/api/survey.php';
```

## 七、上线前测试流程

1. 打开问卷页面。
2. 填写一条测试数据，建议公司名写“上线测试-请删除”。
3. 提交后，客户页面应只显示感谢语，不应出现客户分层、JSON、内部标签等内容。
4. 打开钉钉 AI 表格，确认新增一条记录。
5. 确认中文内容正常，不出现问号乱码。
6. 等待钉钉 AI 字段运行，确认 `AI客户画像`、`AI跟进建议` 等字段有结果。
7. 测试完成后，在钉钉表格里删除测试记录。

## 八、上线后维护

- 前端问题主要改 `index.html`。
- 提交接口问题主要看 `api/survey.php`。
- 钉钉密钥或表格 ID 变更，只改 `api/config.php`。
- 钉钉 AI 字段是表格内部能力，不依赖网页前端，不需要改接口。
- 如需查看接口错误，可在服务器日志或 `data/survey-errors.jsonl` 中排查；`data/` 目录已通过 `.htaccess` 阻止外部访问。

## 九、钉钉 AI 表格内部字段

目前已在钉钉 AI 表格末尾新增 5 个 AI 自评估字段：

- `AI客户画像`
- `AI跟进建议`
- `AI风险原因`
- `AI产品需求归纳`
- `AI质量评分`

这些字段都放在表格最后，作为内部分析区。客户不会看到这些内容，网页也不会读取这些字段。

字段与网页接口无关，属于钉钉 AI 表格内部能力。客户提交后，接口仍然只负责新增记录；AI 字段由钉钉表格根据该行已有字段自动分析或手动触发运行。
