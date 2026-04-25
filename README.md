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
