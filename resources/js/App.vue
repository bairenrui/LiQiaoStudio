<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';

const summary = ref({
    members: null,
    sponsor_members: null,
    districts: null,
    map_areas: null,
});
const mapData = ref(null);
const mapCanvas = ref(null);
const svgMarkup = ref('');
const selectedArea = ref(null);
const areaMembers = ref([]);
const sponsorMembers = ref([]);
const searchKeyword = ref('');
const searchResults = ref([]);
const layerVisibility = reactive({});
const isLoading = ref(true);
const isAreaLoading = ref(false);
const isSearching = ref(false);
const errorMessage = ref('');
const focusedViewBox = '1365.18 2597 1600 841.94';
const zoomScale = ref(1);
const minZoomScale = 0.75;
const maxZoomScale = 3;
const zoomStep = 0.25;

const modules = computed(() => [
    { label: '会員', count: summary.value.members },
    { label: '賛助会員', count: summary.value.sponsor_members },
    { label: '区班', count: summary.value.districts },
    { label: '地図区域', count: summary.value.map_areas },
]);

const zoomPercent = computed(() => `${Math.round(zoomScale.value * 100)}%`);

const memberRows = computed(() => [
    ...areaMembers.value.map((member) => ({
        id: `member-${member.id}`,
        type: '会員',
        name: member.name,
        phone: member.phone,
        district: member.district_code,
        address: member.address_lot,
        note: member.note,
    })),
    ...sponsorMembers.value.map((sponsor) => ({
        id: `sponsor-${sponsor.id}`,
        type: '賛助',
        name: sponsor.company_name,
        phone: sponsor.phone,
        district: sponsor.district_code,
        address: sponsor.address_lot,
        note: sponsor.business_description,
    })),
]);

onMounted(async () => {
    try {
        const [summaryResponse, mapResponse] = await Promise.all([
            fetch('/api/dashboard/summary'),
            fetch('/api/map'),
        ]);

        if (!summaryResponse.ok || !mapResponse.ok) {
            throw new Error('API request failed');
        }

        summary.value = await summaryResponse.json();
        mapData.value = await mapResponse.json();

        mapData.value.layers.forEach((layer) => {
            layerVisibility[layer.svg_group_id] = layer.is_default_visible;
        });

        const svgPath = normalizeSvgPath(mapData.value.map.svg_path);
        const svgResponse = await fetch(svgPath);
        if (!svgResponse.ok) {
            throw new Error(`SVG request failed: ${svgResponse.status}`);
        }

        svgMarkup.value = await svgResponse.text();

        await nextTick();
        initializeSvgMap();
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Load failed';
    } finally {
        isLoading.value = false;
    }
});

function normalizeSvgPath(path) {
    if (!path) {
        return '/assets/map.svg';
    }

    return path.startsWith('/') ? path : `/${path}`;
}

function initializeSvgMap() {
    focusSvgViewport();
    applyZoom();
    applyLayerVisibility();
    resetAreaStyles();

    const svg = document.querySelector('.map-canvas svg');
    svg?.addEventListener('click', handleSvgClick);

    mapData.value?.areas.forEach((area) => {
        const element = document.getElementById(area.svg_element_id);
        if (!element) {
            return;
        }

        element.dataset.originalStyle = element.getAttribute('style') ?? '';
        element.classList.add('map-area');
        element.setAttribute('tabindex', '0');
        element.setAttribute('role', 'button');
        element.setAttribute('aria-label', area.display_name);
        element.addEventListener('click', (event) => {
            event.stopPropagation();
            selectArea(area.svg_element_id);
        });
        element.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectArea(area.svg_element_id);
            }
        });
    });
}

function handleSvgClick(event) {
    const areaIds = new Set((mapData.value?.areas ?? []).map((area) => area.svg_element_id));
    const clickedArea = event
        .composedPath()
        .find((element) => element instanceof Element && areaIds.has(element.id));

    if (clickedArea) {
        selectArea(clickedArea.id);
    }
}

function focusSvgViewport() {
    const svg = getSvgElement();
    if (!svg) {
        return;
    }

    svg.setAttribute('viewBox', focusedViewBox);
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    svg.removeAttribute('width');
    svg.removeAttribute('height');
}

function getSvgElement() {
    return mapCanvas.value?.querySelector('svg') ?? null;
}

function applyZoom() {
    const svg = getSvgElement();
    if (!svg) {
        return;
    }

    svg.style.width = `${zoomScale.value * 100}%`;
    svg.style.minWidth = `${Math.round(980 * zoomScale.value)}px`;
    svg.style.height = 'auto';
}

async function setZoom(nextScale) {
    const container = mapCanvas.value;
    const currentScrollWidth = container?.scrollWidth || 0;
    const currentScrollHeight = container?.scrollHeight || 0;
    const centerXRatio = container && currentScrollWidth > 0
        ? (container.scrollLeft + container.clientWidth / 2) / currentScrollWidth
        : 0.5;
    const centerYRatio = container && currentScrollHeight > 0
        ? (container.scrollTop + container.clientHeight / 2) / currentScrollHeight
        : 0.5;

    zoomScale.value = Math.min(maxZoomScale, Math.max(minZoomScale, nextScale));

    await nextTick();
    applyZoom();

    if (!container) {
        return;
    }

    container.scrollLeft = centerXRatio * container.scrollWidth - container.clientWidth / 2;
    container.scrollTop = centerYRatio * container.scrollHeight - container.clientHeight / 2;
}

function zoomIn() {
    setZoom(zoomScale.value + zoomStep);
}

function zoomOut() {
    setZoom(zoomScale.value - zoomStep);
}

function resetZoom() {
    setZoom(1);
}

function handleMapWheel(event) {
    if (!event.ctrlKey && !event.metaKey) {
        return;
    }

    event.preventDefault();
    setZoom(zoomScale.value + (event.deltaY < 0 ? zoomStep : -zoomStep));
}

function applyLayerVisibility() {
    mapData.value?.layers.forEach((layer) => {
        const element = document.getElementById(layer.svg_group_id);
        if (element) {
            element.style.display = layerVisibility[layer.svg_group_id] ? '' : 'none';
        }
    });
}

function toggleLayer(layer) {
    layerVisibility[layer.svg_group_id] = !layerVisibility[layer.svg_group_id];
    applyLayerVisibility();
}

async function selectArea(svgElementId) {
    isAreaLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(`/api/map/areas/${encodeURIComponent(svgElementId)}`);
        if (!response.ok) {
            throw new Error(`Area request failed: ${response.status}`);
        }

        const payload = await response.json();
        selectedArea.value = payload.area;
        areaMembers.value = payload.members;
        sponsorMembers.value = payload.sponsor_members;
        highlightArea(svgElementId);
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Area load failed';
    } finally {
        isAreaLoading.value = false;
    }
}

function resetAreaStyles() {
    document.querySelectorAll('.map-area').forEach((element) => {
        const originalStyle = element.dataset.originalStyle ?? '';
        if (originalStyle) {
            element.setAttribute('style', originalStyle);
        } else {
            element.removeAttribute('style');
        }
        element.classList.remove('map-area-selected');
    });
}

function highlightArea(svgElementId) {
    resetAreaStyles();

    const element = document.getElementById(svgElementId);
    if (!element) {
        return;
    }

    element.classList.add('map-area-selected');
    element.style.fill = selectedArea.value?.highlight_fill_color || '#a8d0ff';
    element.style.stroke = '#134f8f';
    element.style.strokeWidth = '8px';
}

async function runSearch() {
    const keyword = searchKeyword.value.trim();
    if (!keyword) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    errorMessage.value = '';

    try {
        const params = new URLSearchParams({ keyword, limit: '20' });
        const response = await fetch(`/api/members?${params.toString()}`);
        if (!response.ok) {
            throw new Error(`Search failed: ${response.status}`);
        }

        const payload = await response.json();
        searchResults.value = payload.data;
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Search failed';
    } finally {
        isSearching.value = false;
    }
}

function selectSearchResult(member) {
    const area = member.map_areas?.[0];
    if (area) {
        selectArea(area.svg_element_id);
    }
}
</script>

<template>
    <main class="app-shell">
        <section class="workspace">
            <header class="workspace-header">
                <div>
                    <p class="eyebrow">Taiseicho Map System</p>
                    <h1>大成町二丁目 地図管理</h1>
                </div>
                <form class="search-form" @submit.prevent="runSearch">
                    <input
                        v-model="searchKeyword"
                        type="search"
                        placeholder="氏名・電話・住所で検索"
                    >
                    <button type="submit" :disabled="isSearching">
                        {{ isSearching ? '検索中' : '検索' }}
                    </button>
                </form>
            </header>

            <div class="summary-grid">
                <article v-for="item in modules" :key="item.label" class="summary-card">
                    <span>{{ item.label }}</span>
                    <strong>{{ item.count ?? '-' }}</strong>
                </article>
            </div>

            <p v-if="isLoading" class="status-line">Loading map...</p>
            <p v-else-if="errorMessage" class="status-line error">{{ errorMessage }}</p>

            <section class="map-workspace">
                <div class="map-pane">
                    <div class="layer-toolbar" aria-label="Layer controls">
                        <button
                            v-for="layer in mapData?.layers ?? []"
                            :key="layer.id"
                            type="button"
                            :class="{ active: layerVisibility[layer.svg_group_id] }"
                            @click="toggleLayer(layer)"
                        >
                            {{ layer.display_name }}
                        </button>
                    </div>
                    <div class="map-stage">
                        <div class="zoom-controls" aria-label="Map zoom controls">
                            <button
                                type="button"
                                :disabled="zoomScale <= minZoomScale"
                                aria-label="Zoom out"
                                @click="zoomOut"
                            >
                                -
                            </button>
                            <span>{{ zoomPercent }}</span>
                            <button
                                type="button"
                                :disabled="zoomScale >= maxZoomScale"
                                aria-label="Zoom in"
                                @click="zoomIn"
                            >
                                +
                            </button>
                            <button type="button" aria-label="Reset zoom" @click="resetZoom">
                                Reset
                            </button>
                        </div>
                        <div
                            ref="mapCanvas"
                            class="map-canvas"
                            @wheel="handleMapWheel"
                            v-html="svgMarkup"
                        />
                    </div>
                </div>

                <aside class="detail-pane">
                    <section class="detail-section">
                        <p class="section-label">選択区域</p>
                        <h2>{{ selectedArea?.display_name ?? '区域を選択してください' }}</h2>
                        <p v-if="selectedArea" class="muted">
                            SVG ID: {{ selectedArea.svg_element_id }}
                        </p>
                        <p v-if="isAreaLoading" class="muted">Loading area...</p>
                    </section>

                    <section v-if="memberRows.length" class="detail-section">
                        <p class="section-label">登録情報</p>
                        <article v-for="row in memberRows" :key="row.id" class="member-row">
                            <div>
                                <strong>{{ row.name }}</strong>
                                <span>{{ row.type }} / {{ row.district ?? '-' }}</span>
                            </div>
                            <p>{{ row.address ?? '-' }}</p>
                            <p>{{ row.phone ?? '-' }}</p>
                            <p v-if="row.note" class="note">{{ row.note }}</p>
                        </article>
                    </section>

                    <section v-else class="detail-section empty-state">
                        <p>クリックした区域の会員情報がここに表示されます。</p>
                    </section>

                    <section v-if="searchResults.length" class="detail-section">
                        <p class="section-label">検索結果</p>
                        <button
                            v-for="member in searchResults"
                            :key="member.id"
                            type="button"
                            class="search-result"
                            @click="selectSearchResult(member)"
                        >
                            <strong>{{ member.name }}</strong>
                            <span>
                                {{ member.district?.code ?? '-' }}
                                / {{ member.household?.address_lot ?? '-' }}
                            </span>
                        </button>
                    </section>
                </aside>
            </section>
        </section>
    </main>
</template>
