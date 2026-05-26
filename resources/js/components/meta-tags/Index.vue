<template>
    <div>
        <div id="cabinet-mt-step-1" class="card shadow-sm border-0 cabinet-mt-check-card">
            <div class="card-header py-2">
                <div class="cabinet-mt-step__head">
                    <span class="cabinet-mt-step-badge" aria-hidden="true">1</span>
                    <div class="cabinet-mt-step__titles">
                        <h3 class="cabinet-mt-step__title">{{ lang.step_1_title }}</h3>
                        <p class="cabinet-mt-step__desc mb-0">{{ lang.step_1_hint || '' }}</p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form @submit.prevent="onSubmitMetaTags">
                    <div class="mb-3">
                        <label class="form-label">{{ lang.check_url }}</label>
                        <textarea class="form-control form-control-sm"
                                  rows="8"
                                  v-model="url"
                                  :placeholder="lang.urls_placeholder"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">{{ lang.fetch_fields }}</label>
                        <div class="cabinet-mt-tags-picker border rounded p-2 bg-body-tertiary">
                            <div class="row g-2">
                                <div class="col-sm-6 col-lg-4"
                                     v-for="option in availableOptions"
                                     :key="option.value">
                                    <div class="form-check mb-0">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               :id="'cabinetMtTag' + option.value"
                                               :value="option.value"
                                               v-model="selectedOptions">
                                        <label class="form-check-label small"
                                               :for="'cabinetMtTag' + option.value">
                                            {{ option.text }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!availableOptions.length" class="text-secondary small mb-0">
                                {{ lang.tags_loading || 'Загрузка списка тегов…' }}
                            </p>
                        </div>
                        <div class="form-text">{{ lang.fetch_fields_hint }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ lang.timeout_request }}</label>
                        <input type="number" min="1" class="form-control form-control-sm" v-model="time">
                        <div class="form-text">{{ lang.timeout_hint }}</div>
                    </div>

                    <p class="text-secondary small mb-2">{{ lang.length_hint }}</p>
                    <div class="row g-2" v-for="len in length" :key="len.id">
                        <div class="col-sm-6">
                            <div class="mb-3 mb-sm-0">
                                <label class="form-label">{{ lang.length_word }}: {{ len.name }}</label>
                                <input type="number" class="form-control form-control-sm" :placeholder="lang.min" v-model.lazy="len.input.min">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label class="form-label d-none d-sm-block">&nbsp;</label>
                                <input type="number" class="form-control form-control-sm" :placeholder="lang.max" v-model.lazy="len.input.max">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1" aria-hidden="true"></i>{{ lang.send }}
                    </button>
                </form>
            </div>
        </div>

        <div id="cabinet-mt-step-2"
             class="card shadow-sm border-0 cabinet-mt-results-card"
             v-if="result.length">
            <div class="card-header py-2">
                <div class="cabinet-mt-step__head">
                    <span class="cabinet-mt-step-badge" aria-hidden="true">2</span>
                    <div class="cabinet-mt-step__titles">
                        <h3 class="cabinet-mt-step__title">{{ lang.results_title }}</h3>
                        <p class="cabinet-mt-step__desc mb-0">{{ lang.step_2_hint || '' }}</p>
                    </div>
                </div>
            </div>

            <div class="progress rounded-0" role="progressbar" :aria-valuenow="loading" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" :style="'width: '+ loading +'%;'">
                    <span v-if="loading === 100">{{ lang.done }}</span>
                    <span v-else>{{ loading }}%</span>
                </div>
            </div>

            <div class="card-body">
                <meta-filter :seen.sync="seenCard" :metaTags="result" :lang="lang"></meta-filter>

                <div id="cabinetMtAccordion" class="accordion">
                    <div class="card border mb-2" v-for="(url, index) in result" :key="index" v-show="!seenCard.length || seenCard[index] === 1">
                        <div class="card-header card-header-accordion py-2 d-flex align-items-start gap-2">
                            <h4 class="card-title h6 mb-0 flex-grow-1">
                                <a class="d-block accordion-title collapsed"
                                   data-bs-toggle="collapse"
                                   :href="'#collapse' + index"
                                   role="button"
                                   aria-expanded="false">
                                    <i class="bi bi-chevron-right cabinet-mt-caret me-1" aria-hidden="true"></i>{{ url.title }}
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
                                        <a :href="url.url" target="_blank" rel="noopener" class="dropdown-item">
                                            <i class="bi bi-box-arrow-up-right me-2" aria-hidden="true"></i>{{ lang.go_to_site }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="dropdown-item" @click.prevent="Analyzer(url.url)">
                                            <i class="bi bi-pie-chart me-2" aria-hidden="true"></i>{{ lang.text_analysis }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <span v-for="(error_badge, tag) in url.error.badge"
                                  :key="tag"
                                  v-if="error_badge.length"
                                  v-html="error_badge.join('')"></span>
                        </div>

                        <div :id="'collapse' + index" class="collapse" data-bs-parent="#cabinetMtAccordion">
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
                                    <tr v-for="(item, tag) in url.data" :key="tag">
                                        <td><span class="badge text-bg-success">&lt; {{ tag }} &gt;</span></td>
                                        <td>
                                            <span v-if="item.length"><textarea class="form-control form-control-sm" readonly>{{ item.join( ', \r\n' ) }}</textarea></span>
                                            <span v-else class="badge text-bg-danger">{{ item }}</span>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-warning">{{ item.length }}</span>
                                        </td>
                                        <td v-html="url.error.main[tag].join(' <br />')"></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap cabinet-mt-export-actions mt-3" v-if="FormShow">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#ProjectModalForm">
                        <i class="bi bi-folder-plus me-1" aria-hidden="true"></i>{{ lang.save_as_project }}
                    </button>
                    <base-modal-form v-on:close-modal-form="CloseModalFormMetaTags" target="ProjectModalForm" method="post" request="/meta-tags" :data="result" :links="url" :lang="lang"></base-modal-form>
                    <button type="button" class="btn btn-outline-primary btn-sm" @click.prevent="Export('csv')">
                        <i class="bi bi-download me-1" aria-hidden="true"></i>{{ lang.export_csv }}
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" @click.prevent="Export('xlsx')">
                        <i class="bi bi-download me-1" aria-hidden="true"></i>{{ lang.export_xlsx }}
                    </button>
                </div>
            </div>
        </div>

        <div id="cabinet-mt-step-3" class="card shadow-sm border-0 cabinet-mt-projects-card">
            <div class="card-header py-2">
                <div class="cabinet-mt-step__head">
                    <span class="cabinet-mt-step-badge" aria-hidden="true">3</span>
                    <div class="cabinet-mt-step__titles">
                        <h3 class="cabinet-mt-step__title">{{ lang.projects_title }}</h3>
                        <p class="cabinet-mt-step__desc mb-0">{{ lang.step_3_hint || '' }}</p>
                    </div>
                </div>
            </div>

            <div v-if="!metas.length" class="card-body">
                <p class="text-secondary small mb-0">{{ lang.projects_empty_hint }}</p>
            </div>

            <div v-else class="table-responsive">
                <table class="table table-sm table-hover table-striped align-middle cabinet-mt-projects-table mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 1%">{{ lang.id }}</th>
                        <th style="width: 14%">{{ lang.name }}</th>
                        <th style="width: 10%">{{ lang.period }}</th>
                        <th style="width: 10%">{{ lang.timeout }}</th>
                        <th style="width: 24%">{{ lang.link }}</th>
                        <th style="width: 8%">{{ lang.status }}</th>
                        <th class="cabinet-mt-col-actions">{{ lang.actions }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="meta in metas" :key="meta.id">
                        <td>{{ meta.id }}</td>
                        <td>{{ meta.name }}</td>
                        <td class="text-nowrap small">{{ lang.period_24h }}</td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" min="1" v-model.number="meta.timeout" @keyup.prevent="onSubmitMetaTagsEditField(meta)">
                                <span class="input-group-text">{{ lang.ms_unit }}</span>
                            </div>
                        </td>
                        <td>
                            <textarea class="form-control form-control-sm" readonly v-text="meta.links"></textarea>
                        </td>
                        <td>
                            <div class="form-check form-switch form-switch-sm cabinet-mt-status-switch mb-0">
                                <input type="checkbox"
                                       class="form-check-input"
                                       role="switch"
                                       :id="'customSwitchStatus' + meta.id"
                                       v-model="meta.status"
                                       @change.prevent="onSubmitMetaTagsEditField(meta)">
                                <label class="form-check-label small" :for="'customSwitchStatus' + meta.id">
                                    {{ meta.status ? lang.on : lang.off }}
                                </label>
                            </div>
                        </td>
                        <td class="cabinet-mt-col-actions">
                            <div class="btn-group btn-group-sm cabinet-mt-project-actions"
                                 role="group"
                                 :aria-label="lang.actions">
                                <a class="btn btn-outline-secondary cabinet-mt-action-tip"
                                   target="_blank"
                                   rel="noopener"
                                   :href="'/meta-tags/histories/' + meta.id"
                                   data-bs-toggle="tooltip"
                                   data-bs-placement="top"
                                   :data-bs-title="lang.action_tip_history">
                                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                                    <span class="visually-hidden">{{ lang.history }}</span>
                                </a>
                                <button type="button"
                                        class="btn btn-outline-primary cabinet-mt-action-tip"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        :data-bs-title="lang.action_tip_run"
                                        @click.prevent="StartMetaTags(meta)">
                                    <i class="bi bi-play-fill" aria-hidden="true"></i>
                                    <span class="visually-hidden">{{ lang.action_run }}</span>
                                </button>
                                <button type="button"
                                        class="btn btn-outline-secondary cabinet-mt-action-tip"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        :data-bs-title="lang.action_tip_edit"
                                        @click.prevent="onSubmitMetaTagsEdit(meta)">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                    <span class="visually-hidden">{{ lang.edit }}</span>
                                </button>
                                <button type="button"
                                        class="btn btn-outline-danger cabinet-mt-action-tip"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        :data-bs-title="lang.action_tip_delete"
                                        @click.prevent="DeleteMetaTags(meta.id)">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                    <span class="visually-hidden">{{ lang.delete }}</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <base-modal-form v-on:close-modal-form="CloseModalFormMetaTags"
                             target="ProjectModalFormEdit"
                             method="patch"
                             :values="value"
                             :request="'/meta-tags/' + request"
                             :lang="lang"
            ></base-modal-form>
        </div>
    </div>
</template>

<script>
    import MetaFilter from './Filter'

    export default {
        name: "MetaTags",
        components: {
            MetaFilter
        },
        props: {
            meta: {
                type: [Object, Array],
                default: () => [],
            },
            lang: {
                type: [Object, Array]
            },
            tagsOptions: {
                type: Array,
                default: () => [],
            }
        },
        computed: {
        },
        created() {
            var app = this;
            this.initTagOptions(this.tagsOptions);

            if (this.meta && this.meta.length) {
                this.metas = this.meta;
            } else {
                axios.get('/meta-tags/projects')
                    .then(function (response) {
                        app.metas = response.data || [];
                    });
            }

            axios.get('/meta-tags/getTariffMetaTagsPages')
                .then(function (response) {
                    app.TariffMetaTagsPages = response.data;
                });

            if (!this.availableOptions.length) {
                axios.get('/meta-tags/tags-options')
                    .then(function (response) {
                        app.initTagOptions(response.data || []);
                    })
                    .catch(function () {
                        toastr.error(app.lang.tags_load_error || 'Не удалось загрузить список тегов');
                    });
            }
        },
        data() {
            return {
                TariffMetaTagsPages: {},
                loading: 0,
                metas: [],
                value: {},
                request: null,
                FormShow: false,
                url: '',
                time: 500,
                length: [
                    {id: 'title', name: this.lang.title, input: {min: null, max: null}},
                    {id: 'description', name: this.lang.description, input: {min: null, max: null}},
                    {id: 'keywords', name: this.lang.keywords, input: {min: null, max: null}},
                ],
                result: [],
                seenCard: [],
                startBtnProjectId: null,
                selectedOptions: [],
                availableOptions: [],
            }
        },
        mounted() {
            this.initProjectActionTooltips();
        },
        updated() {
            this.initProjectActionTooltips();
        },
        beforeDestroy() {
            this.disposeProjectActionTooltips();
        },
        watch:{
            result: function(val){
                let url = this.StringAsObj(this.url);
                this.FormShow = (url.length === val.length);

                this.loading = Math.ceil(val.length / url.length * 100);
            },
            loading: function(val){

                if(this.startBtnProjectId && val === 100 && this.FormShow){
                    var self = this;

                    axios.request({
                        url: '/meta-tags/histories/' + this.startBtnProjectId,
                        method: 'patch',
                        data: { histories: this.result }
                    }).then(function(response){

                        if(response.statusText === "OK");
                            toastr.success(self.lang.history_saved);

                    }).catch(function (error) {

                        console.log(error);
                    });

                    this.startBtnProjectId = null;
                }
            }
        },
        methods: {
            initTagOptions(options) {
                if (!Array.isArray(options) || !options.length) {
                    return;
                }
                this.availableOptions = options;
                this.selectedOptions = options.map(function (option) {
                    return option.value;
                });
            },
            initProjectActionTooltips() {
                var self = this;
                this.$nextTick(function () {
                    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                        return;
                    }
                    var root = document.getElementById('cabinet-mt-step-3');
                    if (!root) {
                        return;
                    }
                    root.querySelectorAll('.cabinet-mt-action-tip[data-bs-toggle="tooltip"]').forEach(function (el) {
                        var inst = bootstrap.Tooltip.getInstance(el);
                        if (inst) {
                            inst.dispose();
                        }
                        new bootstrap.Tooltip(el, {
                            container: 'body',
                            trigger: 'hover focus',
                            boundary: 'viewport',
                            customClass: 'cabinet-mt-action-tooltip',
                        });
                    });
                });
            },
            disposeProjectActionTooltips() {
                if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                    return;
                }
                var root = document.getElementById('cabinet-mt-step-3');
                if (!root) {
                    return;
                }
                root.querySelectorAll('.cabinet-mt-action-tip[data-bs-toggle="tooltip"]').forEach(function (el) {
                    var inst = bootstrap.Tooltip.getInstance(el);
                    if (inst) {
                        inst.dispose();
                    }
                });
            },
            openTextAnalyzer(pageUrl) {
                if (!pageUrl) {
                    return;
                }
                var encoded = String(pageUrl).replace(/\//g, 'abc');
                window.location.href = '/redirect-to-text-analyzer/' + encoded;
            },
            Analyzer(link) {
                this.openTextAnalyzer(link);
            },
            StartMetaTags(meta)
            {
                $("html, body").stop().animate({scrollTop: $('#cabinet-mt-step-1').offset().top - 80}, 500, 'swing');

                this.url = meta.links;
                this.time = meta.timeout;
                this.seenCard = [];

                _.forEach(this.length, function(value) {
                    value.input.min = meta[value.id + '_min'];
                    value.input.max = meta[value.id + '_max'];
                });

                this.onSubmitMetaTags();

                this.startBtnProjectId = meta.id;
            },

            onSubmitMetaTagsEditField(meta)
            {
                var app = this;
                meta.period = 24;

                axios.request({
                    url: '/meta-tags/' + meta.id,
                    method: 'patch',
                    data: meta
                }).then(function(response){

                    if(response.statusText === "OK");
                        toastr.success(app.lang.saved_success);

                }).catch(function (error) {

                    console.log(error);
                });
            },

            onSubmitMetaTagsEdit(meta)
            {

                this.request = meta.id;
                this.value = meta;

                var modalEl = document.getElementById('ProjectModalFormEdit');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            },

            onSubmitMetaTags()
            {
                let url = '';

                if (!this.selectedOptions.length) {
                    toastr.warning(this.lang.tags_none_selected || 'Выберите хотя бы один тег для проверки');
                    return false;
                }

                if(this.url.length){
                    url = this.StringAsObj(this.url);

                    if(url.length > this.TariffMetaTagsPages.value){
                        toastr.error(this.TariffMetaTagsPages.message);
                        this.url = _.join(_.slice(url, 0, this.TariffMetaTagsPages.value), '\r\n');

                        return false;
                    }

                    this.result = [];
                    url.forEach((element, i) => {
                        setTimeout(() => {
                            this.HttpRequest(element, i);
                        }, i * this.time);
                    });
                } else{
                    toastr.warning(this.lang.urls_empty);
                }
            },

            HttpRequest(url, i)
            {
                var app = this;

                axios.post('/meta-tags/get', {
                    url: url,
                    length: app.length,
                    tags: app.selectedOptions,
                }).then(function (response) {
                    app.result.push(response.data);
                }).catch(function (error) {
                    if (error.response && error.response.data && error.response.data.message) {
                        toastr.error(error.response.data.message);
                    }
                });
            },

            DeleteMetaTags(id)
            {
                var app = this;

                if (!confirm(app.lang.delete_confirm)) {
                    return;
                }

                let idx = _.findIndex(this.metas, function(o) { return o.id === id; });

                axios.delete('/meta-tags/' + id);
                this.metas.splice(idx, 1);

                toastr.info(app.lang.deleted_success);
            },

            CloseModalFormMetaTags: function(response) {

                let idx = _.findIndex(this.metas, function(o) { return o.id === response.data.id; });

                if(idx < 0){

                    this.metas.push(response.data);
                }else{

                    _.merge(this.metas[idx], response.data);
                }

                $('.modal').modal('hide');
            },

            StringAsObj(str)
            {
                return _.compact(str.split(/[\r\n]+/));
            },
            Export(format)
            {
                axios.request({
                    url: '/meta-tags/export',
                    method: 'post',
                    responseType: 'blob',
                    data: {
                        result: this.result,
                        format: format
                    }
                }).then(function(response){
                    const url = window.URL.createObjectURL(new Blob([response.data]));
                    const link = document.createElement('a');

                    link.href = url;

                    const contentDisposition = response.headers['content-disposition'];
                    const fileNameMatch = contentDisposition.match(/filename="?(.+)"?/);

                    link.setAttribute('download', fileNameMatch[1]);
                    document.body.appendChild(link);
                    link.click();

                    link.remove();
                    window.URL.revokeObjectURL(url);
                });
            }
        }
    }
</script>

<style scoped>
    .list-item {
        display: inline-block;
        margin-right: 10px;
    }
    .list-enter-active, .list-leave-active {
        transition: all 1s;
    }
    .list-enter, .list-leave-to {
        opacity: 0;
        transform: translateY(30px);
    }
</style>
