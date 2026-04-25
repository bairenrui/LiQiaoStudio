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
