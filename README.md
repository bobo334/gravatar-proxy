# Gravatar Proxy

本地代理 Gravatar 头像，提升加载速度的 WordPress 插件。

## 功能特性

- 本地缓存 Gravatar 头像，减少外部请求
- 自动缓存管理，支持缓存过期时间设置
- 支持强制刷新头像缓存
- 后台管理页面，可查看缓存统计和清空缓存
- CDN 支持，可配置 CDN 加速
- 默认头像回退机制
- 简单易用，无需复杂配置

## 安装方法

### 方法一：手动安装

1. 下载插件压缩包
2. 解压到 WordPress 的 `wp-content/plugins/` 目录
3. 在 WordPress 后台启用插件

### 方法二：通过 WordPress 后台安装

1. 登录 WordPress 后台
2. 进入 插件 > 安装插件
3. 上传插件压缩包并安装
4. 启用插件

## 使用说明

插件启用后会自动工作，无需额外配置。

### 访问头像

使用以下格式访问缓存的头像：

```
https://yourdomain.com/gravatar-proxy/{gravatar_hash}/
```

其中 `{gravatar_hash}` 是用户的 Gravatar MD5 哈希值。

### 强制刷新头像

如果用户在 Gravatar 上更新了头像，可以通过添加 `refresh=1` 参数强制刷新缓存：

```
https://yourdomain.com/gravatar-proxy/{gravatar_hash}/?refresh=1
```

这会删除该头像的缓存并从 Gravatar 重新获取最新头像。

### 后台管理

插件在 WordPress 后台提供了管理页面，可以：

1. 进入 **设置 > Gravatar Proxy**
2. 查看缓存统计信息：
   - 缓存文件数
   - 缓存总大小
   - 缓存目录路径
3. 点击"清空所有缓存"按钮可以一键清空所有头像缓存
4. 配置 CDN URL 和缓存大小等设置

## 配置选项

插件提供以下配置常量，可在 `wp-config.php` 中自定义：

```php
// 缓存目录（默认：WP_CONTENT_DIR . '/cache/gravatar'）
define('GRAVATAR_CACHE_DIR', WP_CONTENT_DIR . '/cache/gravatar');

// 缓存过期时间（默认：7天，单位：秒）
define('GRAVATAR_CACHE_EXPIRY', 7 * 24 * 60 * 60);
```

## 系统要求

- WordPress 5.0 或更高版本
- PHP 7.0 或更高版本
- 服务器支持文件写入权限

## 文件结构

```
gravatar-proxy/
├── assets/
│   └── images/
│       └── default-avatar.jpg    # 默认头像
├── includes/
│   ├── class-cache-manager.php  # 缓存管理类
│   ├── class-cdn-manager.php    # CDN 管理类
│   └── class-gravatar-proxy.php # 主插件类
├── gravatar-proxy.php           # 插件主文件
└── README.md
```

## 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

## 许可证

本插件采用 GPL v2 或更高版本许可证。详见 [LICENSE](LICENSE) 文件。

## 作者

bobo

## 致谢

感谢所有为本项目做出贡献的开发者。

## 更新日志

### 1.2.1
- 安全加固：仅特定路径处理请求，新增重写规则与 query var
- 增强远程请求校验与回退逻辑
- 增加权限校验与设置项输入清理
- 修复后台页面 HTML

### 1.2.0
- 添加 WordPress 后台管理页面
- 新增缓存统计功能，显示缓存文件数和总大小
- 新增一键清空所有缓存功能
- 优化管理界面，提升用户体验

### 1.1.0
- 添加强制刷新头像缓存功能
- 优化缓存过期时间从30天缩短到7天
- 新增 delete_avatar 方法用于删除指定头像缓存

### 1.0.0
- 初始版本发布
- 支持 Gravatar 头像本地缓存
- 支持缓存过期管理
- 支持默认头像回退
