<template>

    <div class="row">
        <div class="col-md-12">

            <div v-if="loading && history.length === 0" class="text-muted py-3">{{ lang.loading || 'Загрузка…' }}</div>
            <div v-else-if="loadError" class="alert alert-danger">{{ loadError }}</div>

            <template v-else>
                <meta-filter :seen.sync="seenCard" :metaTags="history" :lang="lang"></meta-filter>

                <div id="accordion">

                    <div class="card border mb-2" v-for="(item, index) in history" :key="item.url || item.title || index" v-show="!seenCard.length || seenCard[index] === 1">
                        <div class="card-header card-header-accordion py-2 d-flex align-items-start gap-2">
                            <h4 class="card-title h6 mb-0 flex-grow-1">
                                <a class="d-block accordion-title collapsed"
                                   data-bs-toggle="collapse"
                                   :href="'#collapseHistory' + index"
                                   role="button"
                                   aria-expanded="false">
                                    <i class="bi bi-chevron-right cabinet-mt-caret me-1" aria-hidden="true"></i>{{ item.title }}
                                </a>
                            </h4>

                            <div class="dropdown">
                                <button type="button"
                                        class="btn btn-outline-secondary btn-sm dropdown-toggle"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a :href="item.url || item.title" target="_blank" rel="noopener" class="dropdown-item">
                                            <i class="bi bi-box-arrow-up-right me-2" aria-hidden="true"></i>
                                            {{ lang.go_to_site }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="dropdown-item" @click.prevent="openTextAnalyzer(item.url || item.title)">
                                            <i class="bi bi-pie-chart me-2" aria-hidden="true"></i>
                                            {{ lang.text_analysis }}
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <span v-for="(error_badge, tag) in (item.error && item.error.badge) || {}"
                                  :key="tag"
                                  v-if="error_badge && error_badge.length"
                                  class="me-1"
                                  v-html="namedMissingBadges(tag, error_badge)"></span>
                        </div>

                        <div :id="'collapseHistory' + index" class="collapse" data-bs-parent="#accordion">
                            <div class="card-body pt-0">
                                <table class="table table-sm table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 150px;">{{ lang.tag }}</th>
                                            <th>{{ lang.content }}</th>
                                            <th style="width: 4rem">{{ lang.count }}</th>
                                            <th style="width: 150px">{{ lang.main_problems }}</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr v-for="(value, tag) in item.data" :key="tag">
                                            <td><span class="badge text-bg-success">&lt; {{ tag }} &gt;</span></td>
                                            <td>
                                                <span v-if="isTagContentPresent(value)"><textarea class="form-control form-control-sm" readonly>{{ value.join( ', \r\n' ) }}</textarea></span>
                                                <span v-else class="badge text-bg-danger">{{ tagMissingLabelFor(tag) }}</span>
                                            </td>
                                            <td>
                                                <span class="badge text-bg-warning">{{ tagContentCount(value) }}</span>
                                            </td>
                                            <td v-html="problemHtml(item, tag)"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

                <div v-if="hasMore" class="text-center py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loadingMore" @click="loadMore">
                        {{ loadingMore ? (lang.loading || 'Загрузка…') : (lang.load_more || 'Показать ещё') }}
                    </button>
                    <span class="text-muted small ml-2" v-if="total">{{ history.length }} / {{ total }}</span>
                </div>
            </template>

        </div>

    </div>

</template>

<script>
    import MetaFilter from './Filter'

    export default {
        name: "MetaTagsHistory",
        components: {
            MetaFilter
        },
        props: {
            historyId: {
                type: [Number, String],
                required: true,
            },
            lang: [Object, Array],
        },
        data() {
            return {
                history: [],
                loading: true,
                loadingMore: false,
                loadError: null,
                seenCard: [],
                offset: 0,
                limit: 50,
                total: 0,
                hasMore: false,
            }
        },
        computed: {
            tagMissingLabel() {
                return (this.lang && this.lang.tag_missing) ? this.lang.tag_missing : 'Не найден';
            },
        },
        mounted() {
            this.fetchChunk(0, true);
        },
        methods: {
            isTagContentPresent(value) {
                return Array.isArray(value) && value.length > 0;
            },
            tagContentCount(value) {
                return Array.isArray(value) ? value.length : 0;
            },
            tagMissingLabelFor(tag) {
                var tpl = (this.lang && this.lang.tag_missing_named) ? this.lang.tag_missing_named : 'Нет :tag';
                return String(tpl).replace(':tag', tag || '');
            },
            namedMissingBadges(tag, error_badge) {
                var html = Array.isArray(error_badge) ? error_badge.join('') : String(error_badge || '');
                var plain = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                var generic = this.tagMissingLabel;
                if (plain === generic || plain === 'Не найден' || plain === 'Not found') {
                    var label = this.tagMissingLabelFor(tag)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                    return '<span class="badge text-bg-danger me-1">' + label + '</span>';
                }
                return html;
            },
            problemHtml(item, tag) {
                const main = item && item.error && item.error.main && item.error.main[tag];
                if (Array.isArray(main) && main.length) {
                    return main.join(' <br />');
                }
                return '';
            },
            fetchChunk(offset, initial) {
                if (initial) {
                    this.loading = true;
                } else {
                    this.loadingMore = true;
                }

                axios.get('/meta-tags/history/' + this.historyId + '/data', {
                    params: { offset: offset, limit: this.limit },
                })
                    .then((response) => {
                        const payload = response.data;
                        if (payload && Array.isArray(payload.items)) {
                            this.history = offset === 0 ? payload.items : this.history.concat(payload.items);
                            this.offset = offset + payload.items.length;
                            this.total = payload.total || 0;
                            this.hasMore = !!payload.has_more;
                        } else if (Array.isArray(payload)) {
                            this.history = payload;
                            this.hasMore = false;
                            this.total = payload.length;
                        }
                    })
                    .catch(() => {
                        this.loadError = this.lang.error_load || 'Не удалось загрузить историю';
                    })
                    .finally(() => {
                        this.loading = false;
                        this.loadingMore = false;
                    });
            },
            loadMore() {
                if (!this.hasMore || this.loadingMore) {
                    return;
                }
                this.fetchChunk(this.offset, false);
            },
            openTextAnalyzer(pageUrl) {
                if (!pageUrl) {
                    return;
                }
                var encoded = String(pageUrl).replace(/\//g, 'abc');
                window.location.href = '/redirect-to-text-analyzer/' + encoded;
            },
        }
    }
</script>

<style scoped>

</style>
