# 大成町二丁目地图系统数据库设计

## 目标

把当前 `大成町二丁目地図.html` 中硬编码的会員名簿、賛助会員、SVG 地图区域、图层开关和行号映射，拆成可以由 Laravel + MySQL 管理的数据结构。

当前 HTML 里的关键问题是：

- 会员数据写死在 `SHEETS` JavaScript 对象里。
- 地图区域写死在 SVG 的 `area01_A1`、`area10_B2` 等元素 ID 里。
- 会员和地图区域的关系靠 `memberRanges` / `supportRanges` 的行号范围维护。
- 没有登录、权限、导入记录、编辑历史。

系统化后，应该用数据库表达这些关系，而不是继续把业务规则写在前端。

## 推荐技术前提

- Frontend: Vue 3 + TypeScript + Vite
- Backend: Laravel
- Database: MySQL 8
- Auth: Laravel session 或 Sanctum
- ORM: Laravel Eloquent

## 核心表

### `users`

系统用户表。用于管理员、编辑者、只读用户登录。

主要字段：

- `name`
- `email`
- `password`
- `role`: `admin` / `editor` / `viewer`
- `is_active`
- `last_login_at`

后期如果权限变复杂，可以再引入 `roles`、`permissions`，或使用 Spatie Laravel Permission。

### `districts`

区班主数据，例如：

- `1区A1`
- `4区C3`
- `10区B2`

主要字段：

- `code`: 原始区班代码
- `district_no`: 数字区，例如 `10`
- `block_code`: 班，例如 `B2`
- `display_name`
- `sort_order`

这个表是会员、赞助会员、地图区域之间的核心连接点。

### `map_versions`

地图版本表。当前 HTML 只有一张地图，但系统化后建议支持版本。

用途：

- 保存当前 SVG 地图文件路径
- 支持以后替换新版地图
- 标记当前启用版本

主要字段：

- `name`
- `svg_path`
- `view_box`
- `is_active`

### `map_areas`

地图上的可点击区域。对应 SVG 里的元素 ID。

例如：

- `area01_A1`
- `area06_E`
- `area10_B4`

主要字段：

- `map_version_id`
- `district_id`
- `svg_element_id`
- `area_type`
- `display_name`
- `default_fill_color`
- `highlight_fill_color`
- `is_clickable`

重点：以后 Vue 前端点击 SVG 区域时，不应该靠写死逻辑，而是拿 `svg_element_id` 去 API 查询这个区域对应的区班和会员。

### `map_layers`

地图图层配置。对应当前 HTML 里的：

- `home`
- `apartment`
- `boundary`
- `comercial`
- `map_1914`

主要字段：

- `key_name`
- `svg_group_id`
- `display_name`
- `is_default_visible`
- `sort_order`

Vue 前端读取这个表后生成图层开关。

### `households`

住户/地址单位表。一个地址可能有多个会员，备注里也出现了“2世帯”之类的信息，所以建议把地址单位单独建表。

主要字段：

- `district_id`
- `address_lot`: 番地
- `building_name`
- `room_no`
- `note`

如果第一版想简单，也可以先不做复杂 household 管理，但保留这个表会让后续扩展更稳。

### `members`

普通会員名簿。

主要字段：

- `household_id`
- `district_id`
- `member_no`: 原始表格 No.
- `name`
- `name_kana`
- `phone`
- `note`
- `publication_status`: `public` / `private` / `unlisted`
- `membership_status`: `active` / `inactive` / `moved` / `unknown`
- `source_row_no`

注意：电话号码是个人信息。Laravel 侧建议使用 encrypted cast，或至少限制 API 输出权限。

### `sponsor_members`

賛助会員。

主要字段：

- `district_id`
- `sponsor_no`
- `address_lot`
- `company_name`
- `contact_name`
- `phone`
- `business_description`
- `note`
- `membership_status`
- `source_row_no`

普通会员和赞助会员字段不完全一样，所以建议分表，不要硬塞进同一张 `members` 表。

### `map_area_member_links`

地图区域和会员/赞助会员的绑定表。

为什么需要这张表：

- 当前 HTML 是靠行号范围映射区域，维护成本高。
- 系统里应该允许手动调整“这个会员属于哪个地图区域”。
- 有些区域可能对应多个会员或多个赞助会员。

主要字段：

- `map_area_id`
- `member_id`
- `sponsor_member_id`
- `link_type`

第一版可以通过 `district_id` 自动推导区域；遇到特殊情况再使用这张表手动覆盖。

### `import_jobs`

导入任务表。用于记录 Excel/CSV 导入。

主要字段：

- `import_type`: `members` / `sponsors` / `map_areas`
- `original_filename`
- `stored_path`
- `status`
- `total_rows`
- `success_rows`
- `failed_rows`
- `error_message`
- `imported_by`
- `started_at`
- `finished_at`

### `import_rows`

导入明细表。用于追踪每一行是否成功。

主要字段：

- `import_job_id`
- `row_no`
- `raw_payload`
- `status`
- `error_message`
- `target_table`
- `target_id`

这样导入失败时可以告诉用户具体哪一行有问题。

### `audit_logs`

操作日志。

记录：

- 谁改了资料
- 改了哪张表
- 改了哪条记录
- 修改前后内容
- IP 和 user agent

因为系统包含个人姓名和电话，审计日志很重要。

## 关系设计

核心关系如下：

- `districts` 关联 `members`
- `districts` 关联 `sponsor_members`
- `districts` 关联 `map_areas`
- `map_versions` 包含多个 `map_areas`
- `map_versions` 包含多个 `map_layers`
- `members` 可以属于一个 `household`
- `map_area_member_links` 可以把地图区域直接绑定到普通会员或赞助会员

实际查询时：

1. 用户搜索会员。
2. 后端返回会员资料和 `district_id`。
3. 前端通过 `district_id` 找到对应 `map_area.svg_element_id`。
4. Vue 高亮对应 SVG 元素。

或者：

1. 用户点击 SVG 的 `area10_B2`。
2. 前端把 `svg_element_id=area10_B2` 发给 API。
3. 后端查询 `map_areas`、`districts`、`members`、`sponsor_members`。
4. 前端展示该区域的会员列表。

## 第一版迁移策略

建议分三步导入：

1. 从 HTML 抽出 `SHEETS`，生成 `members.csv` 和 `sponsor_members.csv`。
2. 从 HTML 抽出 `memberRanges` / `supportRanges`，生成 `districts`、`map_areas`、`map_area_member_links` 的初始数据。
3. 从 HTML 抽出 SVG，保存成独立 `map.svg`，由 `map_versions.svg_path` 引用。

## 后续 Laravel Migration 顺序

推荐 migration 顺序：

1. `users`
2. `districts`
3. `map_versions`
4. `map_areas`
5. `map_layers`
6. `households`
7. `members`
8. `sponsor_members`
9. `map_area_member_links`
10. `import_jobs`
11. `import_rows`
12. `audit_logs`

## 暂不建议第一版加入的复杂设计

这些可以以后再做：

- 真正 GIS 坐标系统
- PostGIS 等空间查询
- 多自治会/多地区 SaaS 化
- 复杂角色权限矩阵
- 住户成员家庭关系图
- 地图区域在线绘制器

第一版目标应该是：把当前 HTML 的硬编码数据搬进数据库，并让 Vue 前端通过 Laravel API 动态渲染。
