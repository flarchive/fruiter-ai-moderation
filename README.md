# AI Moderation（AI 内容合规审核）

一个用于 [Flarum](https://flarum.org) 1.8.x 的扩展：使用**任意 OpenAI 兼容 API**（OpenAI、DeepSeek、Moonshot、智谱、通义、Ollama、LM Studio、vLLM、OneAPI 等）在用户发布内容时自动进行合规审核，支持**隐藏**、**标记待审核**、**拒绝发布**三种处理方式。

- 兼容环境：**Flarum 1.8.12 / PHP 7.4.33 / MySQL 8.4**（已按该组合开发验证，PHP 7.4+、8.x 均可）
- AI 来源完全由你自己配置：只需填写 `API 基础地址`、`API 密钥`、`模型名称` 三项
- 纯 PHP 后端 + 轻量管理后台（含"测试连接"按钮），无需改动论坛前端
- 开源协议：MIT

---

## 功能特性

- ✅ 覆盖所有用户发布场景：
  - 新建讨论（**标题 + 首帖内容**一起检查）
  - 回复帖子（检查内容）
  - 编辑帖子（检查修改后的内容）
  - 重命名讨论（单独检查新标题）
- ✅ 内置中文合规审核提示词（政治敏感、色情、暴力、赌博诈骗、人身攻击、广告引流等），并支持在后台追加自定义规则
- ✅ 三种违规处理动作（后台可切换）：
  | 动作 | 行为 |
  |---|---|
  | `hide_and_flag`（推荐） | 立即隐藏，并在"标记"（Flags）里生成 AI 审核标记供管理员复核 |
  | `hide` | 仅隐藏内容 |
  | `flag` | 仅生成标记，内容保持可见 |
  | `reject` | 直接拒绝发布，作者看到错误提示 |
- ✅ AI 服务不可用时**默认放行**（避免 AI 故障影响社区正常发帖），可切换为拒绝发布
- ✅ 帖子被 AI 隐藏后，作者将其修改为合规内容会自动恢复并清除标记
- ✅ 管理后台"测试连接"按钮 + `php flarum ai-moderation:test` 命令，方便配置自己的 AI 来源

---

## 环境要求

- Flarum `^1.8.0`（在 1.8.12 上验证）
- PHP `>= 7.4`（兼容 8.x）
- 一个 OpenAI 兼容的 Chat Completions 接口（见下方兼容列表）

---

## 安装

### 方式一：通过 Packagist 安装（推荐）

等扩展提交到 Packagist 后，在你的 Flarum 根目录执行：

```bash
composer require fruiter/ai-moderation
php flarum extension:enable fruiter-ai-moderation
php flarum assets:publish
php flarum cache:clear
```

### 方式二：通过 VCS 仓库安装（Packagist 未收录时）

```bash
# 把 GitHub 仓库加入 composer 源
composer config repositories.fruiter vcs https://github.com/CNFruiter/flarum-ai-moderation
composer require fruiter/ai-moderation:@dev

php flarum extension:enable fruiter-ai-moderation
php flarum assets:publish
php flarum cache:clear
```

> 若需锁定稳定版本，可在仓库打 tag（如 `v1.0.0`），然后 `composer require fruiter/ai-moderation:^1.0`。

### 方式三：本地路径安装（开发调试）

```bash
composer config repositories.ai-moderation path "<扩展所在路径>"
composer require fruiter/ai-moderation:@dev
php flarum extension:enable fruiter-ai-moderation
php flarum assets:publish && php flarum cache:clear
```

---

## 配置

### 后台配置（推荐）

管理后台 → 扩展 → **AI Moderation**，主要设置：

| 设置项 | 说明 | 示例 |
|---|---|---|
| API 基础地址 | OpenAI 兼容接口地址，**不含** `/chat/completions` | `https://api.deepseek.com/v1` |
| API 密钥 | 服务商提供的 Key | `sk-xxxx` |
| 模型名称 | 服务商支持的模型 ID | `deepseek-chat`、`gpt-4o-mini`、`moonshot-v1-8k` |
| 违规处理方式 | 见上表 | `hide_and_flag` |
| 自定义指令 | 追加审核规则（选填） | 如：`禁止发布包含微信号的内容` |
| 严格 JSON 模式 | 仅服务商支持 `response_format` 时开启 | 默认关 |
| AI 服务出错时放行 | 默认开（放行） | — |

配置完成后点击**测试连接**，确认能正常返回审核结果。

### CLI 配置（无后台时使用）

```bash
# 配置 OpenAI 兼容接口
php flarum ai-moderation:configure \
  --base-url=https://api.deepseek.com/v1 \
  --api-key=sk-xxxx \
  --model=deepseek-chat \
  --action=hide_and_flag

# 测试连接
php flarum ai-moderation:test

# 手动检查一段文本（可用真实模型试一下内置提示词的效果）
php flarum ai-moderation:check "这段话是否违规？"
php flarum ai-moderation:check "我这里有赌博网站，大家快来下注"
php flarum ai-moderation:check "根据新闻报道，最近警方打击了一批网络赌博团伙"

# 停用 / 启用
php flarum ai-moderation:configure --disabled
php flarum ai-moderation:configure --enabled
```

---

## 安全说明

- **API 密钥存储**：默认保存在 Flarum 的 `settings` 表（仅管理员可见）。**生产环境建议把密钥放到 `config.php`，避免密钥进入数据库**：
  ```php
  // config.php
  return [
      // ... 其他配置
      'ai-moderation' => [
          'api_key' => 'sk-xxxx',        // 优先于后台设置
          'api_base_url' => 'https://api.deepseek.com/v1', // 可选
          'model' => 'deepseek-chat',    // 可选
      ],
  ];
  ```
  config.php 中的值优先于后台设置，此时后台的密钥/地址/模型字段留空即可。
- **密钥不对外暴露**：`ai-moderation.*` 设置不会被序列化到论坛前端，普通用户无法读取；日志中也不会记录密钥。
- **测试接口鉴权**：`/api/ai-moderation/test` 仅管理员可访问，未登录或非管理员一律 403，避免匿名用户滥用你的 AI 额度。
- **失败放行策略**：默认 AI 服务异常时放行内容（不阻塞发帖）；如需严格模式，关闭"AI 服务出错时放行"。
- **建议**：管理后台使用 HTTPS 访问，避免密钥在传输中被窃取。

---

## OpenAI 兼容服务配置示例

| 服务商 | API 基础地址 | 示例模型 | 备注 |
|---|---|---|---|
| OpenAI | `https://api.openai.com/v1` | `gpt-4o-mini` | 官方 |
| DeepSeek | `https://api.deepseek.com/v1` | `deepseek-chat` | 国内直连，价格低 |
| Moonshot（月之暗面） | `https://api.moonshot.cn/v1` | `moonshot-v1-8k` | |
| 智谱 GLM | `https://open.bigmodel.cn/api/paas/v4` | `glm-4-flash` | |
| 通义千问 | `https://dashscope.aliyuncs.com/compatible-mode/v1` | `qwen-plus` | 兼容模式 |
| Ollama（本地） | `http://localhost:11434/v1` | `qwen2.5:7b` | 无需密钥；需 `OLLAMA_HOST` 允许局域网时注意地址 |
| LM Studio（本地） | `http://localhost:1234/v1` | 本地模型 ID | 无需密钥 |
| vLLM / OneAPI / NewAPI 等 | 服务商提供的 OpenAI 兼容地址 | 其模型 ID | |

> 提示：若某个服务商报错，优先检查 `API 基础地址` 是否以 `/v1` 结尾（按服务商文档），以及是否需要在后台开启"严格 JSON 模式"。

---

## 内置审核提示词说明

扩展内置了一份中文审核提示词，覆盖：政治敏感、色情淫秽、暴力血腥、违法活动（赌博/毒品/诈骗等）、侮辱攻击、垃圾信息等类别。提示词设计上：

- 要求模型**结合上下文判断**——新闻报道、科普、讨论、举报语境下提及敏感词不算违规；
- 要求识别**谐音字、变体词、隐晦暗示**等规避手段；
- 无明确违规证据时**判定为合规**，降低误杀；
- 严格输出 JSON，供程序解析。

你可以通过后台"自定义指令"追加规则，或用 `php flarum ai-moderation:check "..."` 配合你的真实模型验证效果。

---

## 工作原理

```
用户发帖/回复/编辑
      │
      ▼
Flarum Post\Event\Saving（保存前）
      │  读取内容（首帖附带标题），发送给 OpenAI 兼容接口
      │  {"compliant": true/false, "category": "...", "reason": "..."}
      ▼
┌─ 合规 ───────────────► 正常发布
│
└─ 违规（按动作处理）
     ├─ reject ─────────► 抛 ValidationException，发帖失败并提示作者
     ├─ hide/hide_and_flag ► 帖子保存时即处于隐藏状态（作者和管理员可见）
     └─ flag/hide_and_flag ► 在"标记"中生成 AI 审核标记（管理员复核）
```

要点：

- 基于 `Flarum\Post\Event\Saving` 与 `Flarum\Discussion\Event\Saving` 事件，在**内容入库前**完成审核，违规内容不会对普通用户可见。
- 新建讨论时，Flarum 会先保存讨论再通过 `PostReply` 创建首帖并派发 `Post\Event\Saving`，因此"标题 + 首帖内容"会一起提交审核。
- 帖子在保存前还没有 ID，无法直接写 `flags` 表；扩展会在帖子保存完成（`Posted` / `Revised` 事件）后自动补建 AI 标记。
- 审核请求是**同步**的：每次发帖会多一次 AI 调用（通常 1~3 秒）。请合理设置"请求超时"；如内容量大，建议使用延迟低的模型。

---

## 常见问题

**Q：AI 误判把正常内容隐藏了怎么办？**
管理员在后台"标记"页查看 AI 标记及原因，可一键恢复；作者把内容改为合规后再次保存也会自动恢复。

**Q：flags 扩展没启用会怎样？**
`hide_and_flag` 中的"标记"部分会被自动跳过（仅隐藏），不会报错；建议启用自带的 `flarum/flags` 扩展以获得完整的标记审核流程。

**Q：AI 服务挂了/超时，用户还能发帖吗？**
默认可以（失败放行）。如需严格模式，关闭"AI 服务出错时放行"，此时 AI 不可用会拒绝发帖并提示"审核服务暂时不可用"。

**Q：后台"测试连接"报错怎么办？**
先确认基础地址、密钥、模型三项正确；再运行 `php flarum ai-moderation:test` 查看详细错误；Ollama/LM Studio 等本地服务请确认已开启 OpenAI 兼容端点。

**Q：如何避免重复审核？**
编辑时未修改内容、仅隐藏/恢复帖子的操作不会触发审核；每次"内容变化"只调用一次 AI。

---

## 目录结构

```
ai-moderation/
├── composer.json
├── extend.php                 # 扩展注册
├── locale/                    # 语言包（en / zh）
├── src/
│   ├── Api/Controller/        # 后台测试连接 API（仅管理员）
│   ├── Console/               # ai-moderation:configure / test / check
│   ├── Listener/              # 审核监听器（帖子、讨论标题、补建标记）
│   ├── Moderation/            # AI 客户端、提示词协调器、结果解析、标记创建
│   └── Support/               # 日志工具
└── js/
    ├── admin.ts               # 后台 JS 入口
    ├── webpack.config.js
    └── src/admin/index.tsx    # 后台设置页（含测试连接按钮）
```

重新构建后台 JS（修改 `js/` 后）：

```bash
cd js
npm install
npm run build
```

---

## 许可

[MIT](LICENSE)
