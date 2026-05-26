<template>
    <div class="mb-3 cabinet-mt-filter">
        <label class="form-label" for="cabinet-mt-filter-select">{{ lang.filter }}</label>
        <select
            id="cabinet-mt-filter-select"
            class="form-select form-select-sm"
            v-model="selected"
            @change="onChange"
        >
            <option value="all">{{ lang.all }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.text }}</option>
        </select>
    </div>
</template>

<script>
    export default {
        name: "MetaFilter",
        props: {
            metaTags: {
                type: Array,
                default: () => [],
            },
            seen: {
                type: Array,
                default: () => [],
            },
            lang: {
                type: Object,
                default: () => ({}),
            },
        },
        data() {
            return {
                selected: 'all',
                options: [],
            }
        },
        watch: {
            metaTags: {
                handler() {
                    this.rebuildOptions();
                    if (this.selected !== 'all') {
                        this.applyFilter();
                    }
                },
                deep: true,
            },
        },
        created() {
            this.rebuildOptions();
        },
        methods: {
            onChange() {
                this.applyFilter();
            },
            applyFilter() {
                if (this.selected === 'all') {
                    this.$emit('update:seen', []);
                    return;
                }

                const seen = [];
                const items = Array.isArray(this.metaTags) ? this.metaTags : [];

                for (let index = 0; index < items.length; index++) {
                    seen[index] = 0;
                    const badges = items[index] && items[index].error && items[index].error.badge;
                    if (!badges) {
                        continue;
                    }
                    for (const tag in badges) {
                        if (!Object.prototype.hasOwnProperty.call(badges, tag)) {
                            continue;
                        }
                        if (tag === this.selected && badges[tag] && badges[tag].length) {
                            seen[index] = 1;
                            break;
                        }
                    }
                }

                this.$emit('update:seen', seen);
            },
            rebuildOptions() {
                const opts = [];
                const keys = {};
                const items = Array.isArray(this.metaTags) ? this.metaTags : [];

                items.forEach((value) => {
                    const badges = value && value.error && value.error.badge;
                    if (!badges) {
                        return;
                    }
                    Object.keys(badges).forEach((tag) => {
                        if (badges[tag] && badges[tag].length && !keys[tag]) {
                            keys[tag] = true;
                            opts.push({
                                value: tag,
                                text: _.upperFirst(tag),
                            });
                        }
                    });
                });

                this.options = opts;

                if (this.selected !== 'all' && !keys[this.selected]) {
                    this.selected = 'all';
                    this.$emit('update:seen', []);
                }
            },
        },
    }
</script>
