# LiQiaoStudio

- DB定义：DBML

## Data extraction

当前原始资料在 `大成町二丁目地図.html` 中。运行下面命令可以重新抽出系统化所需的初始数据：

```bash
node scripts/extract_html_data.js
```

输出文件：

- `assets/map.svg`
- `data/members.json`
- `data/sponsor_members.json`
- `data/member_area_ranges.json`
- `data/sponsor_area_ranges.json`
- `data/districts.json`
- `data/map_areas.json`
- `data/map_layers.json`
- `data/svg_area_ids.json`
- `data/unmapped_svg_area_ids.json`

## Laravel setup

本项目已经是 Laravel + Vue 3 一体代码库。

安装依赖：

```bash
composer install
npm install
```

初始化数据库并导入初始数据：

```bash
php artisan migrate:fresh --seed
```

启动开发服务：

```bash
php artisan serve
npm run dev
```

构建前端：

```bash
npm run build
```

默认 seed 管理员：

- Email: `admin@example.com`
- Password: `password`

正式连接 MySQL 时，参考 `.env.example` 设置：

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taiseicho_map
DB_USERNAME=root
DB_PASSWORD=
```

## Initial API

地图和会员 API：

```http
GET /api/dashboard/summary
GET /api/members?keyword=佐藤&limit=50
GET /api/members/{member}
POST /api/members
PUT/PATCH /api/members/{member}
DELETE /api/members/{member}
GET /api/map
GET /api/map/areas/area10_B2
```

说明：

- `/api/dashboard/summary` 返回首页统计。
- `GET /api/members` 支持按姓名、フリガナ、电话、备注、区班、番地搜索。
- `POST /api/members` 新增会员，可同时写入 household 和 map area 绑定。
- `PUT/PATCH /api/members/{member}` 更新会员资料和区域绑定。
- `DELETE /api/members/{member}` soft delete 会员，并移除会员的地图区域绑定。
- `/api/map` 返回当前启用地图、图层、SVG 区域列表。
- `/api/map/areas/{svgElementId}` 返回某个 SVG 区域下的会員和賛助会員。
