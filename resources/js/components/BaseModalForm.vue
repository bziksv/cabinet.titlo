<template>
    <div class="modal fade" :id="target" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ lang.save_project }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="lang.close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">{{ lang.project_name }}:</label>
                            <input type="text" class="form-control form-control-sm" v-model="name" required>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox"
                                       class="form-check-input"
                                       role="switch"
                                       :id="'customSwitchStatusForm' + target + status"
                                       v-model="status">
                                <label class="form-check-label" :for="'customSwitchStatusForm' + target + status">{{ lang.status }}</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ lang.period }}:</label>
                            <p class="form-control-plaintext small py-1 mb-0">{{ lang.period_24h }}</p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ lang.timeout }}:</label>
                            <input type="number" min="1" class="form-control form-control-sm" v-model="timeout">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ lang.link }}:</label>
                            <textarea class="form-control form-control-sm" rows="6" v-model="link" required></textarea>
                        </div>

                        <div class="row g-2" v-for="len in length" :key="len.id">
                            <div class="col-sm-6">
                                <div class="mb-3 mb-sm-0">
                                    <label class="form-label">{{ lang.length_word }} {{ len.name }}</label>
                                    <input type="number" class="form-control form-control-sm" placeholder="min" v-model.number="len.input.min">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="mb-3">
                                    <label class="form-label d-none d-sm-block">&nbsp;</label>
                                    <input type="number" class="form-control form-control-sm" placeholder="max" v-model.number="len.input.max">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ lang.close }}</button>
                    <button @click.prevent="OnSubmitMetaForm" type="button" class="btn btn-primary btn-sm">{{ lang.save }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        name: "BaseModalForm",
        props: {
            request: {
                required: true,
                type: String
            },
            method: {
                required: true,
                type: String
            },
            target: {
                required: true,
                type: String
            },
            data: {
                type: [Array, Object]
            },
            values: {
                type: [Array, Object]
            },
            links: {
                type: String
            },
            lang: {
                type: [Array, Object]
            },
        },
        created(){
            this.link = this.links
        },
        data(){
            return {
                status: 0,
                name: this.lang.default_project_name || 'Мой проект',
                period: 24,
                link: '',
                timeout: 500,
                length: [
                    {id: 'title', name: this.lang.title, input: {min: null, max: null}},
                    {id: 'description', name: this.lang.description, input: {min: null, max: null}},
                    {id: 'keywords', name: this.lang.keywords, input: {min: null, max: null}},
                ],
            }
        },
        watch: {
            values: function(val){
                this.status = val.status;
                this.name = val.name;
                this.period = 24;
                this.link = val.links;
                this.timeout = val.timeout;

                _.forEach(this.length, function(value) {
                    value.input.min = val[value.id + '_min'];
                    value.input.max = val[value.id + '_max'];
                });
            }
        },
        methods: {
            OnSubmitMetaForm() {
                var app = this;

                var data = {
                    status: app.status,
                    name: app.name,
                    period: 24,
                    links: app.link,
                    timeout: app.timeout,
                    histories: app.data,
                };

                _.forEach(this.length, function(value) {
                    data[value.id + '_min'] = value.input.min;
                    data[value.id + '_max'] = value.input.max;
                });

                axios.request({
                    url: app.request,
                    method: app.method,
                    data: data,
                }).then(function(response){
                    if(response.statusText === "OK");
                        toastr.success(app.lang.saved_success);

                    app.$emit('close-modal-form', response);
                }).catch(function (error) {
                    if(error.response){
                        toastr.error(error.response.data.message);
                    }
                });
            }
        }
    }
</script>
