<template>

    <div class="row">
        <div class="col-md-12">

            <div v-if="loading && history.length === 0" class="text-muted py-3">{{ lang.loading || 'Загрузка…' }}</div>
            <div v-else-if="loadError" class="alert alert-danger">{{ loadError }}</div>

            <template v-else>
                <meta-filter :seen.sync="seenCard" :metaTags="history" :lang="lang"></meta-filter>

                <div id="accordion">

                    <div class="card" v-for="(item, index) in history" :key="item.url || item.title || index" v-show="!seenCard.length || seenCard[index] === 1">
                        <div class="card-header card-header-accordion">
                            <h4 class="card-title">
                                <a class="d-block w-100 collapsed accordion-title" data-toggle="collapse" :href="'#collapse' + index" aria-expanded="false">
                                    <i class="expandable-accordion-caret fas fa-caret-right fa-fw"></i> {{ item.title }}
                                </a>
                            </h4>

                            <div class="card-tools">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-tool dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-right" role="menu" style="">
                                        <a :href="item.url || item.title" target="_blank" rel="noopener" class="dropdown-item">
                                            <i class="bi bi-box-arrow-up-right me-2" aria-hidden="true"></i>
                                            {{ lang.go_to_site }}
                                        </a>
                                        <a href="#" class="dropdown-item" @click.prevent="openTextAnalyzer(item.url || item.title)">
                                            <i class="bi bi-pie-chart me-2" aria-hidden="true"></i>
                                            {{ lang.text_analysis }}
                                        </a>
                                    </div>
                                </div>

                                <span v-for="error_badge in item.error.badge" v-if="error_badge.length" v-html="error_badge.join('')"></span>
                            </div>
                        </div>

                        <div :id="'collapse' + index" class="collapse" data-parent="#accordion" style="">
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width: 150px;">{{ lang.tag }}</th>
                                            <th>{{ lang.content }}</th>
                                            <th style="width: 40px">{{ lang.count }}</th>
                                            <th style="width: 150px">{{ lang.main_problems }}</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr v-for="(value, tag) in item.data">
                                            <td><span class="badge badge-success">< {{ tag }} ></span></td>
                                            <td>
                                                <span v-if="value.length"><textarea class="form-control">{{ value.join( ', \r\n' ) }}</textarea></span>
                                                <span v-else class="badge badge-danger">{{ value }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">{{ value.length }}</span>
                                            </td>
                                            <td v-html="item.error.main[tag].join(' <br />')"></td>
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
        mounted() {
            this.fetchChunk(0, true);
        },
        methods: {
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
