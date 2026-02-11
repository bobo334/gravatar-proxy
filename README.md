# Gravatar Proxy

本地代理 Gravatar 头像的 WordPress 插件，用于提升加载速度并降低外部请求。

## 功能

- 本地缓存 Gravatar 头像，减少外部请求
- 支持缓存过期与清理
- 支持强制刷新头像缓存
- 后台设置页：缓存统计、清空缓存、CDN 与缓存策略配置
- 支持 CDN 加速
- 支持默认头像回退

## 安装

### 方法一：手动安装

1. 下载插件压缩包
2. 解压到 `wp-content/plugins/`
3. 在 WordPress 后台启用插件

### 方法二：后台上传安装

1. 登录 WordPress 后台
2. 进入“插件 > 安装插件”
3. 上传插件压缩包并安装
4. 启用插件

## 使用

插件启用后会自动接管 Gravatar 头像地址。

### 访问头像

```
https://yourdomain.com/gravatar-proxy/{gravatar_hash}/
```

其中 `{gravatar_hash}` 是 Gravatar 的 MD5 值。

### 强制刷新

```
https://yourdomain.com/gravatar-proxy/{gravatar_hash}/?refresh=1
```

### 后台设置

在后台“设置 > Gravatar Proxy”中可以：

- 查看缓存统计（数量、大小、目录）
- 一键清空缓存
- 配置 CDN URL
- 配置缓存目录、缓存过期时间、缓存最大文件数

插件列表页也提供“设置”快捷入口。

> 说明：启用插件或修改固定链接结构后，需要到“设置 > 固定链接”点击保存，以刷新重写规则。

## 配置选项（可选）

可以在 `wp-config.php` 中预定义默认值：

```php
define('GRAVATAR_CACHE_DIR', WP_CONTENT_DIR . '/cache/gravatar');
define('GRAVATAR_CACHE_EXPIRY', 7 * 24 * 60 * 60);
```

后台设置项优先级更高。

## 系统要求

- WordPress 5.0+
- PHP 7.0+
- 服务器允许写入缓存目录

## 文件结构

```
gravatar-proxy/
├─ assets/
│  └─ images/
│     └─ default-avatar.jpg
├─ includes/
│  ├─ class-cache-manager.php
│  ├─ class-cdn-manager.php
│  └─ class-gravatar-proxy.php
├─ gravatar-proxy.php
└─ README.md
```

## 贡献

欢迎提交 Issue 或 Pull Request。

## 许可

GPL v2 or later

## 项目主页

https://github.com/bobotechnology/gravatar-proxy

## 更新日志

### 1.2.4
- 后台新增一键刷新重写规则按钮与提示
- 更新插件主页链接

### 1.2.3
- 插件列表新增“设置”快捷入口
- 优化设置页 UI

### 1.2.2
- 新增缓存目录/过期时间配置项与设置页优化

### 1.2.1
- 安全加固：仅特定路径处理请求，新增重写规则与 query var
- 增强远程请求校验与回退逻辑
- 增加权限校验与设置项输入清理
- 修复后台页面 HTML

### 1.2.0
- 新增后台管理页与缓存统计
- 一键清空缓存

### 1.1.0
- 新增强制刷新缓存
- 优化缓存过期时间
- 新增 delete_avatar 方法

### 1.0.0
- 初始版本发布
