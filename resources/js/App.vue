<script setup>
const modules = [
    { label: '会員', count: 631 },
    { label: '賛助会員', count: 20 },
    { label: '地図区域', count: 65 },
    { label: 'レイヤー', count: 5 },
];
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
                    <strong>{{ item.count }}</strong>
                </article>
            </div>

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
