<script setup>
import { computed, onMounted, ref } from 'vue';

const summary = ref({
    members: null,
    sponsor_members: null,
    districts: null,
    map_areas: null,
});
const isLoading = ref(true);
const errorMessage = ref('');

const modules = computed(() => [
    { label: '会員', count: summary.value.members },
    { label: '賛助会員', count: summary.value.sponsor_members },
    { label: '区班', count: summary.value.districts },
    { label: '地図区域', count: summary.value.map_areas },
]);

onMounted(async () => {
    try {
        const response = await fetch('/api/dashboard/summary');
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        summary.value = await response.json();
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'API request failed';
    } finally {
        isLoading.value = false;
    }
});
</script>

<template>
    <main class="app-shell">
        <section class="workspace">
            <header class="workspace-header">
                <div>
                    <p class="eyebrow">Taiseicho Map System</p>
                    <h1>大成町二丁目 地図管理</h1>
                </div>
                <button type="button">地図を開く</button>
            </header>

            <div class="summary-grid">
                <article v-for="item in modules" :key="item.label" class="summary-card">
                    <span>{{ item.label }}</span>
                    <strong>{{ item.count ?? '-' }}</strong>
                </article>
            </div>

            <p v-if="isLoading" class="status-line">Loading summary...</p>
            <p v-else-if="errorMessage" class="status-line error">{{ errorMessage }}</p>

            <section class="panel">
                <h2>初期構築状況</h2>
                <ul>
                    <li>Laravel migrations are ready.</li>
                    <li>Seed data is generated from the original HTML.</li>
                    <li>Vue 3 is mounted as the frontend entry point.</li>
                </ul>
            </section>
        </section>
    </main>
</template>
