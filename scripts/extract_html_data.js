const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const HTML_PATH = path.join(ROOT, '大成町二丁目地図.html');
const DATA_DIR = path.join(ROOT, 'data');
const ASSETS_DIR = path.join(ROOT, 'assets');

const html = fs.readFileSync(HTML_PATH, 'utf8');

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function writeJson(relativePath, value) {
  const filePath = path.join(ROOT, relativePath);
  fs.writeFileSync(filePath, `${JSON.stringify(value, null, 2)}\n`, 'utf8');
}

function writeText(relativePath, value) {
  const filePath = path.join(ROOT, relativePath);
  fs.writeFileSync(filePath, value.endsWith('\n') ? value : `${value}\n`, 'utf8');
}

function extractConstLiteral(source, constName) {
  const marker = `const ${constName} =`;
  const markerIndex = source.indexOf(marker);
  if (markerIndex === -1) {
    throw new Error(`Cannot find ${marker}`);
  }

  let index = markerIndex + marker.length;
  while (/\s/.test(source[index])) index += 1;

  const open = source[index];
  const close = open === '{' ? '}' : open === '[' ? ']' : null;
  if (!close) {
    throw new Error(`Unsupported literal for ${constName}`);
  }

  let depth = 0;
  let quote = null;
  let escaped = false;

  for (let i = index; i < source.length; i += 1) {
    const char = source[i];

    if (quote) {
      if (escaped) {
        escaped = false;
      } else if (char === '\\') {
        escaped = true;
      } else if (char === quote) {
        quote = null;
      }
      continue;
    }

    if (char === '"' || char === "'") {
      quote = char;
      continue;
    }

    if (char === open) depth += 1;
    if (char === close) depth -= 1;

    if (depth === 0) {
      return source.slice(index, i + 1);
    }
  }

  throw new Error(`Cannot parse literal for ${constName}`);
}

function extractTemplateSvg(source) {
  const match = source.match(/<template id="svg_template">\s*([\s\S]*?)\s*<\/template>/);
  if (!match) {
    throw new Error('Cannot find svg_template');
  }

  return match[1].trim();
}

function parseJsonLiteral(literal, name) {
  try {
    return JSON.parse(literal);
  } catch (error) {
    throw new Error(`Cannot JSON.parse ${name}: ${error.message}`);
  }
}

function parseArrayLiteral(literal, name) {
  const json = literal.replace(/'/g, '"');
  return parseJsonLiteral(json, name);
}

function rowValue(row, index) {
  return String(row[index] ?? '').trim();
}

function normalizeMemberRows(sheet) {
  return sheet.rows.slice(2).map((row, index) => ({
    source_row_no: index + 3,
    member_no: Number(rowValue(row, 0)) || null,
    district_code: rowValue(row, 1),
    address_lot: rowValue(row, 2),
    name: rowValue(row, 3),
    name_kana: rowValue(row, 4),
    phone: rowValue(row, 5),
    note: rowValue(row, 6),
  }));
}

function normalizeSponsorRows(sheet) {
  return sheet.rows.slice(2).map((row, index) => ({
    source_row_no: index + 3,
    sponsor_no: Number(rowValue(row, 0)) || null,
    district_code: rowValue(row, 1),
    address_lot: rowValue(row, 2),
    company_name: rowValue(row, 3),
    contact_name: rowValue(row, 4),
    phone: rowValue(row, 5),
    business_description: rowValue(row, 6),
  }));
}

function normalizeRanges(ranges) {
  return ranges.map(([start_row, end_row, svg_element_id]) => ({
    start_row,
    end_row,
    svg_element_id,
  }));
}

function extractDistrictCodeFromAreaId(svgElementId) {
  const match = svgElementId.match(/^area(\d{2})_([A-Z]\d?|[A-Z])$/);
  if (!match) return null;
  return `${Number(match[1])}区${match[2]}`;
}

function buildDistricts(members, sponsors, ranges, svgAreaIds) {
  const codes = new Set();

  members.forEach(row => {
    if (row.district_code) codes.add(row.district_code);
  });
  sponsors.forEach(row => {
    if (row.district_code) codes.add(row.district_code);
  });
  ranges.forEach(range => {
    const code = extractDistrictCodeFromAreaId(range.svg_element_id);
    if (code) codes.add(code);
  });
  svgAreaIds.forEach(svgElementId => {
    const code = extractDistrictCodeFromAreaId(svgElementId);
    if (code) codes.add(code);
  });

  return Array.from(codes)
    .sort((a, b) => a.localeCompare(b, 'ja', { numeric: true }))
    .map((code, index) => {
      const match = code.match(/^(\d+)区(.+)?$/);
      return {
        code,
        district_no: match ? Number(match[1]) : null,
        block_code: match ? (match[2] || '') : '',
        display_name: code,
        sort_order: index + 1,
      };
    });
}

function buildMapAreas(svgAreaIds, ranges) {
  const mappedAreaIds = new Set(ranges.map(range => range.svg_element_id));

  return svgAreaIds
    .sort((a, b) => a.localeCompare(b, 'en', { numeric: true }))
    .map((svgElementId, index) => ({
      svg_element_id: svgElementId,
      district_code: extractDistrictCodeFromAreaId(svgElementId),
      area_type: 'district',
      display_name: extractDistrictCodeFromAreaId(svgElementId) || svgElementId,
      has_source_range: mappedAreaIds.has(svgElementId),
      sort_order: index + 1,
    }));
}

function extractSvgAreaIds(svg) {
  return Array.from(svg.matchAll(/\sid="(area\d{2}_[^"]+)"/g))
    .map(match => match[1])
    .filter((value, index, list) => list.indexOf(value) === index)
    .sort((a, b) => a.localeCompare(b, 'en', { numeric: true }));
}

ensureDir(DATA_DIR);
ensureDir(ASSETS_DIR);

const sheets = parseJsonLiteral(extractConstLiteral(html, 'SHEETS'), 'SHEETS');
const memberRanges = normalizeRanges(parseArrayLiteral(extractConstLiteral(html, 'memberRanges'), 'memberRanges'));
const sponsorRanges = normalizeRanges(parseArrayLiteral(extractConstLiteral(html, 'supportRanges'), 'supportRanges'));
const mapSvg = extractTemplateSvg(html);

const members = normalizeMemberRows(sheets['会員名簿']);
const sponsorMembers = normalizeSponsorRows(sheets['賛助会員']);
const allRanges = [...memberRanges, ...sponsorRanges];
const svgAreaIds = extractSvgAreaIds(mapSvg);
const districts = buildDistricts(members, sponsorMembers, allRanges, svgAreaIds);
const mapAreas = buildMapAreas(svgAreaIds, allRanges);
const unmappedSvgAreaIds = mapAreas
  .filter(area => !area.has_source_range)
  .map(area => area.svg_element_id);

writeJson('data/members.json', members);
writeJson('data/sponsor_members.json', sponsorMembers);
writeJson('data/member_area_ranges.json', memberRanges);
writeJson('data/sponsor_area_ranges.json', sponsorRanges);
writeJson('data/districts.json', districts);
writeJson('data/map_areas.json', mapAreas);
writeJson('data/svg_area_ids.json', svgAreaIds);
writeJson('data/unmapped_svg_area_ids.json', unmappedSvgAreaIds);
writeJson('data/map_layers.json', [
  { key_name: 'home', svg_group_id: 'home', display_name: '住宅', is_default_visible: false, sort_order: 1 },
  { key_name: 'apartment', svg_group_id: 'apartment', display_name: '集合住宅', is_default_visible: false, sort_order: 2 },
  { key_name: 'boundary', svg_group_id: 'boundary', display_name: '境界', is_default_visible: true, sort_order: 3 },
  { key_name: 'commercial', svg_group_id: 'comercial', display_name: '商業', is_default_visible: false, sort_order: 4 },
  { key_name: 'map1914', svg_group_id: 'map_1914', display_name: '1914年地図', is_default_visible: false, sort_order: 5 },
]);
writeText('assets/map.svg', mapSvg);

console.log(`members: ${members.length}`);
console.log(`sponsor_members: ${sponsorMembers.length}`);
console.log(`member_area_ranges: ${memberRanges.length}`);
console.log(`sponsor_area_ranges: ${sponsorRanges.length}`);
console.log(`districts: ${districts.length}`);
console.log(`map_areas: ${mapAreas.length}`);
console.log(`svg_area_ids: ${svgAreaIds.length}`);
console.log(`unmapped_svg_area_ids: ${unmappedSvgAreaIds.length}`);
