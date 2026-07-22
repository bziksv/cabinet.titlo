<template>
    <div class="cabinet-ic-bulk">
        <section class="cabinet-ic-panel card border shadow-sm">
            <div class="card-body">
                <h2 class="cabinet-ic-step-title h6 mb-3">
                    <span class="cabinet-ic-step-badge">1</span>
                    <span>{{ stepTitle }}</span>
                </h2>

                <form @submit.prevent="requestConfirm">
                    <div class="mb-3">
                        <label class="form-label fw-medium" for="cabinet-ic-urls">{{ urlsLabel }}</label>
                        <div class="cabinet-ic-url-editor">
                            <pre
                                ref="urlHighlight"
                                class="cabinet-ic-url-highlight font-monospace"
                                aria-hidden="true"
                                v-html="urlsHighlightHtml"
                            ></pre>
                            <textarea
                                id="cabinet-ic-urls"
                                ref="urlTextarea"
                                class="cabinet-ic-url-textarea form-control font-monospace"
                                rows="8"
                                v-model="urls"
                                :placeholder="urlsPlaceholder"
                                :disabled="loading"
                                spellcheck="false"
                                @scroll="syncUrlHighlightScroll"
                            ></textarea>
                        </div>
                        <div class="form-text">{{ urlsHint }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-label fw-medium">{{ enginesLabel }}</div>
                        <div class="d-flex flex-wrap gap-3">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="yandex" :disabled="loading">
                                <span class="form-check-label">{{ yandexLabel }}</span>
                            </label>
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="google" :disabled="loading">
                                <span class="form-check-label">{{ googleLabel }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium" for="cabinet-ic-google-domain">{{ googleDomainLabel }}</label>
                            <select id="cabinet-ic-google-domain" class="form-select" v-model="googleDomain" :disabled="loading || !google">
                                <option v-for="(lr, domain) in googleDomains" :key="domain" :value="domain">{{ domain }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <label class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" v-model="unifyWww" :disabled="loading">
                                <span class="form-check-label">{{ unifyWwwLabel }}</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="urlStats.total > 0" class="cabinet-ic-url-stats small text-secondary mb-2" aria-live="polite">
                        <span>{{ statsLinesLabel }}: <strong class="text-body">{{ urlStats.total }}</strong></span>
                        <span class="ms-3">{{ statsUniqueLabel }}: <strong class="text-body">{{ urlStats.unique }}</strong></span>
                        <span v-if="urlStats.duplicates > 0" class="ms-3">
                            {{ statsDuplicatesLabel }}:
                            <strong class="text-warning">{{ urlStats.duplicates }}</strong>
                        </span>
                        <span class="ms-3">{{ statsToCheckLabel }}: <strong class="text-body">{{ urlStats.toCheck }}</strong></span>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="submit" class="btn btn-primary" :disabled="loading || !canSubmit">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            <i v-else class="bi bi-play-fill me-1" aria-hidden="true"></i>
                            {{ submitLabel }}
                        </button>
                        <button v-if="items.length" type="button" class="btn btn-outline-secondary" :disabled="loading" @click="clearResults">
                            {{ clearLabel }}
                        </button>
                    </div>

                    <div v-if="errorMessage" class="alert alert-warning mt-3 mb-0" role="alert">{{ errorMessage }}</div>
                </form>

                <div v-if="loading" class="mt-3" aria-live="polite">
                    <div class="d-flex justify-content-between small text-secondary mb-1">
                        <span>{{ progressLabel }}</span>
                        <span>{{ doneCount }} / {{ totalCount }}</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" :style="{ width: progressPercent + '%' }"></div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="items.length" class="cabinet-ic-panel card border shadow-sm mt-3">
            <div class="card-header py-3">
                <div class="cabinet-ic-results-toolbar d-flex flex-wrap align-items-center gap-2 w-100">
                    <div class="d-flex flex-wrap align-items-center gap-2 min-w-0">
                        <h2 class="cabinet-ic-step-title h6 mb-0">
                            <span class="cabinet-ic-step-badge">2</span>
                            <span>{{ resultsTitle }}</span>
                        </h2>
                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="exportCsv">
                            <i class="bi bi-download me-1" aria-hidden="true"></i>{{ exportLabel }}
                        </button>
                        <span v-if="urlSearchTrimmed" class="small text-secondary">
                            {{ filteredItems.length }} / {{ items.length }}
                        </span>
                    </div>
                    <div class="cabinet-ic-results-search ms-md-auto">
                        <label class="visually-hidden" for="cabinet-ic-url-search">{{ searchPlaceholder }}</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                            <input
                                id="cabinet-ic-url-search"
                                type="search"
                                class="form-control"
                                v-model="urlSearch"
                                :placeholder="searchPlaceholder"
                                autocomplete="off"
                                spellcheck="false"
                            >
                            <button
                                v-if="urlSearchTrimmed"
                                type="button"
                                class="btn btn-outline-secondary"
                                :aria-label="clearLabel"
                                @click="urlSearch = ''"
                            >
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 cabinet-ic-results-table">
                    <thead>
                        <tr>
                            <th class="cabinet-ic-col-url">{{ colUrl }}</th>
                            <th class="cabinet-ic-col-engine">{{ colYandex }}</th>
                            <th class="cabinet-ic-col-engine">{{ colGoogle }}</th>
                        </tr>
                    </thead>
                    <tbody v-if="filteredItems.length">
                        <tr v-for="row in filteredItems" :key="row.id">
                            <td class="cabinet-ic-url-cell align-top" v-html="renderUrlCell(row.url)"></td>
                            <td class="cabinet-ic-engine-cell align-top" v-html="renderEngineCell(row.yandex)"></td>
                            <td class="cabinet-ic-engine-cell align-top" v-html="renderEngineCell(row.google)"></td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="3" class="text-center text-secondary py-4">{{ searchEmpty }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="savedHistories.length" class="cabinet-ic-panel card border shadow-sm mt-3">
            <div class="card-header py-3">
                <h2 class="cabinet-ic-step-title h6 mb-0">
                    <span class="cabinet-ic-step-badge">3</span>
                    <span>{{ historyTitle }}</span>
                </h2>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 cabinet-ic-results-table cabinet-ic-history-table">
                    <thead>
                        <tr>
                            <th class="cabinet-ic-col-url">{{ colUrl }}</th>
                            <th class="cabinet-ic-col-engine">{{ colYandex }}</th>
                            <th class="cabinet-ic-col-engine">{{ colGoogle }}</th>
                            <th class="cabinet-ic-col-date text-nowrap">Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in savedHistories" :key="'h-' + row.id">
                            <td class="cabinet-ic-url-cell align-top" v-html="renderUrlCell(row.url)"></td>
                            <td class="cabinet-ic-engine-cell align-top" v-html="renderEngineCell(row.yandex)"></td>
                            <td class="cabinet-ic-engine-cell align-top" v-html="renderEngineCell(row.google)"></td>
                            <td class="small text-secondary text-nowrap align-top">{{ row.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div
            class="modal fade cabinet-ic-confirm-modal"
            id="cabinetIcConfirmModal"
            tabindex="-1"
            aria-labelledby="cabinetIcConfirmModalLabel"
            aria-hidden="true"
            ref="confirmModal"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title" id="cabinetIcConfirmModalLabel">{{ confirmTitle }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="confirmCancel"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <ul class="list-unstyled mb-0 cabinet-ic-confirm-modal__list">
                            <li>{{ confirmUrlsText }}</li>
                            <li>{{ confirmEnginesText }}</li>
                            <li class="fw-semibold mt-2">{{ confirmCostText }}</li>
                            <li v-if="showLimitInModal" class="mt-1">{{ confirmRemainingText }}</li>
                            <li class="text-secondary small mt-2 mb-0">{{ costHint }}</li>
                        </ul>
                        <div v-if="insufficientLimit" class="alert alert-warning py-2 px-3 small mt-3 mb-0" role="alert">
                            {{ confirmInsufficientText }}
                        </div>
                    </div>
                    <div class="modal-footer border-top d-flex flex-wrap justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ confirmCancel }}</button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="insufficientLimit"
                            @click="confirmRun"
                        >
                            {{ submitLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "IndexCheckBulk",
    props: {
        stepTitle: { type: String, required: true },
        urlsLabel: { type: String, required: true },
        urlsPlaceholder: { type: String, required: true },
        urlsHint: { type: String, required: true },
        enginesLabel: { type: String, required: true },
        yandexLabel: { type: String, required: true },
        googleLabel: { type: String, required: true },
        googleDomainLabel: { type: String, required: true },
        unifyWwwLabel: { type: String, required: true },
        submitLabel: { type: String, required: true },
        clearLabel: { type: String, required: true },
        progressLabel: { type: String, required: true },
        resultsTitle: { type: String, required: true },
        exportLabel: { type: String, required: true },
        colUrl: { type: String, required: true },
        colYandex: { type: String, required: true },
        colGoogle: { type: String, required: true },
        titleLabel: { type: String, default: "Title" },
        snippetLabel: { type: String, default: "Сниппет" },
        historyTitle: { type: String, default: "Сохранённые проверки" },
        historyEmpty: { type: String, default: "Пока нет сохранённых проверок" },
        yesLabel: { type: String, required: true },
        noLabel: { type: String, required: true },
        errLabel: { type: String, required: true },
        costHint: { type: String, required: true },
        confirmTitle: { type: String, required: true },
        confirmUrls: { type: String, required: true },
        confirmEngines: { type: String, required: true },
        confirmCost: { type: String, required: true },
        confirmRemaining: { type: String, required: true },
        confirmInsufficient: { type: String, required: true },
        confirmCancel: { type: String, required: true },
        searchPlaceholder: { type: String, required: true },
        searchEmpty: { type: String, required: true },
        statsLinesLabel: { type: String, required: true },
        statsUniqueLabel: { type: String, required: true },
        statsDuplicatesLabel: { type: String, required: true },
        statsToCheckLabel: { type: String, required: true },
        limit: { type: Number, default: null },
        remaining: { type: Number, default: null },
        costPerEngine: { type: Number, default: 1 },
        googleDomainsJson: { type: String, default: "{}" },
        delayMs: { type: Number, default: 1200 },
        demoItemsJson: { type: String, default: "[]" },
        demoUrls: { type: String, default: "" },
        historiesJson: { type: String, default: "[]" },
    },
    data() {
        return {
            urls: "",
            yandex: true,
            google: true,
            unifyWww: false,
            googleDomain: "google.ru",
            loading: false,
            items: [],
            doneCount: 0,
            totalCount: 0,
            errorMessage: "",
            localRemaining: this.remaining,
            pendingUrlList: [],
            urlSearch: "",
            savedHistories: [],
        };
    },
    mounted() {
        this.applyDemoShowcase();
        this.loadSavedHistories();
    },
    computed: {
        googleDomains() {
            try {
                return JSON.parse(this.googleDomainsJson);
            } catch (e) {
                return { "google.ru": "213" };
            }
        },
        urlsTrimmed() {
            return this.urls.trim().length > 0;
        },
        canSubmit() {
            return this.urlsTrimmed && (this.yandex || this.google);
        },
        hasDemoShowcase() {
            try {
                const items = JSON.parse(this.demoItemsJson || "[]");
                return Array.isArray(items) && items.length > 0;
            } catch (e) {
                return false;
            }
        },
        progressPercent() {
            if (!this.totalCount) return 0;
            return Math.round((this.doneCount / this.totalCount) * 100);
        },
        checkCost() {
            let cost = 0;
            if (this.yandex) cost += this.costPerEngine;
            if (this.google) cost += this.costPerEngine;
            return cost;
        },
        pendingUrlCount() {
            return this.pendingUrlList.length;
        },
        neededCost() {
            return this.pendingUrlCount * this.checkCost;
        },
        selectedEnginesText() {
            const parts = [];
            if (this.yandex) parts.push(this.yandexLabel);
            if (this.google) parts.push(this.googleLabel);
            return parts.join(", ");
        },
        showLimitInModal() {
            return this.limit !== null && this.localRemaining !== null;
        },
        insufficientLimit() {
            if (!this.showLimitInModal) return false;
            return this.neededCost > this.localRemaining;
        },
        confirmUrlsText() {
            return this.formatTemplate(this.confirmUrls, { count: this.pendingUrlCount });
        },
        confirmEnginesText() {
            return this.formatTemplate(this.confirmEngines, { engines: this.selectedEnginesText });
        },
        confirmCostText() {
            return this.formatTemplate(this.confirmCost, { cost: this.neededCost });
        },
        confirmRemainingText() {
            return this.formatTemplate(this.confirmRemaining, {
                remaining: this.localRemaining,
                limit: this.limit,
            });
        },
        confirmInsufficientText() {
            return this.formatTemplate(this.confirmInsufficient, {
                needed: this.neededCost,
                remaining: this.localRemaining,
            });
        },
        urlSearchTrimmed() {
            return (this.urlSearch || "").trim();
        },
        filteredItems() {
            if (!this.urlSearchTrimmed) {
                return this.items;
            }
            return this.items.filter((row) => this.matchesUrlSearch(row.url));
        },
        urlStats() {
            const lines = (this.urls || "").split(/\r?\n/);
            const seen = new Set();
            let total = 0;
            let duplicates = 0;

            lines.forEach((line) => {
                const trimmed = line.trim();
                if (!trimmed) {
                    return;
                }
                total += 1;
                const key = this.normalizeUrlForDedup(trimmed);
                if (seen.has(key)) {
                    duplicates += 1;
                } else {
                    seen.add(key);
                }
            });

            const unique = seen.size;

            return {
                total,
                unique,
                duplicates,
                toCheck: Math.min(unique, 500),
            };
        },
        urlsHighlightHtml() {
            const parts = (this.urls || "").split(/\r?\n/);
            const seen = new Set();

            return parts
                .map((line) => {
                    const trimmed = line.trim();
                    if (!trimmed) {
                        return _.escape(line);
                    }
                    const key = this.normalizeUrlForDedup(trimmed);
                    const isDuplicate = seen.has(key);
                    seen.add(key);
                    const escaped = _.escape(line);
                    return isDuplicate ? `<mark class="cabinet-ic-url-dup">${escaped}</mark>` : escaped;
                })
                .join("\n");
        },
    },
    updated() {
        this.$nextTick(() => this.syncUrlHighlightScroll());
    },
    methods: {
        formatTemplate(template, vars) {
            let out = template;
            Object.keys(vars).forEach((key) => {
                out = out.split(`:${key}`).join(String(vars[key]));
            });
            return out;
        },

        parseUrlList() {
            const lines = (this.urls || "").split(/\r?\n/);
            const seen = new Set();
            const out = [];

            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed) {
                    continue;
                }
                const key = this.normalizeUrlForDedup(trimmed);
                if (seen.has(key)) {
                    continue;
                }
                seen.add(key);
                out.push(trimmed);
                if (out.length >= 500) {
                    break;
                }
            }

            return out;
        },

        syncUrlHighlightScroll() {
            const textarea = this.$refs.urlTextarea;
            const highlight = this.$refs.urlHighlight;
            if (!textarea || !highlight) {
                return;
            }
            highlight.scrollTop = textarea.scrollTop;
            highlight.scrollLeft = textarea.scrollLeft;
        },

        normalizeUrlForDedup(value) {
            let s = String(value || "").toLowerCase().trim();
            s = s.replace(/^https?:\/\//, "");
            s = s.replace(/^www\./, "");
            return s.replace(/\/+$/, "");
        },

        requestConfirm() {
            this.errorMessage = "";
            const list = this.parseUrlList();
            if (!list.length) return;
            if (!this.yandex && !this.google) return;

            this.pendingUrlList = list;
            this.$nextTick(() => {
                const modalEl = this.$refs.confirmModal;
                if (modalEl && typeof bootstrap !== "undefined" && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    this.runCheck(list);
                }
            });
        },

        confirmRun() {
            const modalEl = this.$refs.confirmModal;
            if (modalEl && typeof bootstrap !== "undefined" && bootstrap.Modal) {
                const instance = bootstrap.Modal.getInstance(modalEl);
                if (instance) instance.hide();
            }
            this.runCheck(this.pendingUrlList);
        },

        runCheck(list) {
            if (!list || !list.length) {
                list = this.parseUrlList();
            }
            if (!list.length) return;

            // Демо-кабинет: готовый снимок, без живых запросов к XmlRiver.
            if (this.hasDemoShowcase) {
                this.loading = true;
                this.errorMessage = "";
                this.totalCount = list.length;
                this.doneCount = 0;
                this.items = [];
                window.setTimeout(() => {
                    this.applyDemoShowcase();
                    this.loading = false;
                }, 280);
                return;
            }

            const needed = list.length * this.checkCost;
            if (this.limit !== null && this.localRemaining !== null && needed > this.localRemaining) {
                this.errorMessage = this.formatTemplate(this.confirmInsufficient, {
                    needed,
                    remaining: this.localRemaining,
                });
                return;
            }

            this.items = [];
            this.loading = true;
            this.totalCount = list.length;
            this.doneCount = 0;

            list.forEach((url, index) => {
                setTimeout(() => this.requestUrl(url, index), index * this.delayMs);
            });
        },

        requestUrl(url, index) {
            axios
                .get("/index-check", {
                    params: {
                        ajax: 1,
                        url,
                        yandex: this.yandex ? 1 : 0,
                        google: this.google ? 1 : 0,
                        unify_www: this.unifyWww ? 1 : 0,
                        google_domain: this.googleDomain,
                    },
                })
                .then((response) => {
                    const data = response.data || {};
                    if (data.remaining !== undefined && data.remaining !== null) {
                        this.localRemaining = data.remaining;
                    }
                    const result = data.result || {};
                    this.items.push({
                        id: index,
                        url: result.url || url,
                        yandex: result.yandex,
                        google: result.google,
                    });
                    if (data.history && data.history.id) {
                        this.savedHistories = [
                            {
                                id: data.history.id,
                                url: data.history.url || result.url || url,
                                created_at: data.history.created_at || "",
                                yandex: result.yandex,
                                google: result.google,
                            },
                            ...this.savedHistories,
                        ].slice(0, 30);
                    }
                })
                .catch((error) => {
                    const msg = error.response?.data?.message;
                    if (error.response?.status === 403 && msg) {
                        this.errorMessage = msg;
                    }
                    this.items.push({
                        id: index,
                        url,
                        yandex: this.yandex ? { indexed: false, error: msg || this.errLabel } : null,
                        google: this.google ? { indexed: false, error: msg || this.errLabel } : null,
                    });
                })
                .finally(() => {
                    this.doneCount += 1;
                    if (this.doneCount >= this.totalCount) {
                        this.loading = false;
                    }
                });
        },

        renderUrlCell(url) {
            const raw = String(url || "").trim();
            if (!raw) {
                return '<span class="text-muted">—</span>';
            }
            const href = /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
            const soft = _.escape(raw).replace(/([:/?&=#_.-])/g, "$1<wbr>");
            return (
                `<a class="cabinet-ic-url-link" href="${_.escape(href)}" ` +
                `target="_blank" rel="noopener noreferrer" title="${_.escape(raw)}">${soft}</a>`
            );
        },

        renderEngineCell(engine) {
            if (!engine) return '<span class="text-muted">—</span>';
            if (engine.error) {
                return `<span class="badge text-bg-warning">${_.escape(engine.error)}</span>`;
            }
            let badge = engine.indexed
                ? `<span class="badge text-bg-success">${_.escape(this.yesLabel)}</span>`
                : `<span class="badge text-bg-secondary">${_.escape(this.noLabel)}</span>`;
            if (!engine.indexed) {
                return badge;
            }
            const parts = [badge];
            if (engine.title) {
                parts.push(
                    `<div class="small fw-semibold mt-1">${_.escape(engine.title)}</div>`
                );
            }
            if (engine.snippet) {
                parts.push(
                    `<div class="small text-secondary mt-1">${_.escape(engine.snippet)}</div>`
                );
            }
            return parts.join("");
        },

        loadSavedHistories() {
            try {
                const rows = JSON.parse(this.historiesJson || "[]");
                this.savedHistories = Array.isArray(rows) ? rows : [];
            } catch (e) {
                this.savedHistories = [];
            }
        },

        clearResults() {
            this.items = [];
            this.doneCount = 0;
            this.totalCount = 0;
            this.errorMessage = "";
            this.urlSearch = "";
        },

        applyDemoShowcase() {
            let items = [];
            try {
                items = JSON.parse(this.demoItemsJson || "[]");
            } catch (e) {
                items = [];
            }
            if (!Array.isArray(items) || !items.length) {
                return;
            }
            if (this.demoUrls) {
                this.urls = this.demoUrls;
            } else {
                this.urls = items.map((row) => row.url || "").filter(Boolean).join("\n");
            }
            this.items = items.map((row, index) => ({
                id: index,
                url: row.url || "",
                yandex: row.yandex != null ? row.yandex : null,
                google: row.google != null ? row.google : null,
            }));
            this.doneCount = this.items.length;
            this.totalCount = this.items.length;
            this.loading = false;
            this.errorMessage = "";
        },

        normalizeUrlForSearch(value) {
            return this.normalizeUrlForDedup(value);
        },

        matchesUrlSearch(url) {
            const raw = this.urlSearchTrimmed.toLowerCase();
            if (!raw) {
                return true;
            }

            const haystack = this.normalizeUrlForDedup(url);
            const tokens = raw.split(/\s+/).filter(Boolean);

            return tokens.every((token) => {
                let needle = this.normalizeUrlForDedup(token);
                if (!needle) {
                    return true;
                }
                if (haystack.includes(needle)) {
                    return true;
                }
                // domain/path без слэша: almamed.su/category
                const hayNoSlash = haystack.replace(/\//g, " ");
                const needleNoSlash = needle.replace(/\//g, " ");
                return hayNoSlash.includes(needleNoSlash);
            });
        },

        exportCsv() {
            const rows = this.urlSearchTrimmed ? this.filteredItems : this.items;
            const lines = [[
                "URL",
                this.colYandex,
                this.titleLabel + " Yandex",
                this.snippetLabel + " Yandex",
                this.colGoogle,
                this.titleLabel + " Google",
                this.snippetLabel + " Google",
            ].join(";")];
            rows.forEach((row) => {
                lines.push([
                    row.url,
                    this.engineCsv(row.yandex),
                    this.engineField(row.yandex, "title"),
                    this.engineField(row.yandex, "snippet"),
                    this.engineCsv(row.google),
                    this.engineField(row.google, "title"),
                    this.engineField(row.google, "snippet"),
                ].join(";"));
            });
            const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "index-check.csv";
            link.click();
        },

        engineCsv(engine) {
            if (!engine) return "—";
            if (engine.error) return engine.error;
            return engine.indexed ? this.yesLabel : this.noLabel;
        },

        engineField(engine, key) {
            if (!engine || !engine[key]) return "";
            return String(engine[key]).replace(/;/g, ",");
        },
    },
};
</script>
