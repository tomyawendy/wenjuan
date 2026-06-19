# InfinityFree 测试部署说明

## 这个包能测试什么

可以测试：

- 问卷网页能否正常打开
- 客户填写后能否提交
- PHP 接口能否收到数据
- 服务器能否保存提交日志

可以继续测试：

- 如果填写 `api/config.php`，提交后会尝试写入钉钉 AI 表格

说明：InfinityFree 不能直接调用本机 MCP，所以这里走钉钉开放平台 Notable API。

## 上传方式

1. 登录 InfinityFree 控制台。
2. 打开 File Manager 或使用 FTP。
3. 进入站点的 `htdocs` 目录。
4. 上传本文件夹里的所有内容：
   - `index.html`
   - `assets/`
   - `api/`
   - `data/`
5. 访问你的 InfinityFree 域名即可看到问卷。

## 测试提交

1. 打开问卷页面。
2. 填写必填项并提交。
3. 提交成功后，访问：

```text
https://你的域名/api/submissions.php
```

如果能看到 JSONL 文本，说明网页到 PHP 接口已经跑通。

## 连接钉钉 AI 表格

1. 复制配置文件：

```text
api/config.example.php -> api/config.php
```

2. 填写：

```php
'appKey' => '你的 AppKey / Client ID',
'appSecret' => '你的 AppSecret / Client Secret',
'operatorId' => '操作人的 unionId',
```

## appKey / appSecret / operatorId 怎么拿

### appKey 和 appSecret

1. 登录钉钉开放平台。
2. 进入“应用开发”。
3. 创建或选择一个“企业内部应用”。
4. 在应用的“基础信息 / 凭证与基础信息”里找到：
   - Client ID，也就是 appKey
   - Client Secret，也就是 appSecret
5. 给应用申请需要的通讯录读取权限和 AI 表格/文档相关权限，并发布到企业。

### operatorId

`operatorId` 要填操作人的 unionId。这个人需要对“领航保客户体验调研数据表”有编辑权限。

推荐获取方式：

1. 在钉钉管理后台进入“通讯录 > 成员管理”。
2. 找到作为操作人的员工，拿到员工 UserId。
3. 上传本包并配置好 `api/config.php` 的 appKey/appSecret。
4. 访问：

```text
https://你的域名/api/get-unionid.php?userid=员工UserId
```

返回里的 `unionId` 就填到 `api/config.php` 的 `operatorId`。

安全提醒：拿到 unionId 后，请删除 `api/get-unionid.php`，或加密码/白名单保护。

3. 确认 `enableDingTalkWrite` 为 `true`。

4. 再提交问卷。成功后会同时：

- 保存本地日志
- 写入钉钉 AI 表格“客户调研反馈记录”

## 上线前必须处理

- 删除 `api/submissions.php`，或加密码保护。
- 给 `api/survey.php` 增加来源校验。
- 如果接钉钉开放接口，不要把 AppSecret 放在前端页面。
- 建议保留服务器日志，方便失败补录。
