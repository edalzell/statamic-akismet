Vue.component('akismet-fieldtype', {
    template: `
        <div>
            <div v-if="loading" class="loading loading-basic">
                <span class="icon icon-circular-graph animation-spin"></span> {{ translate('cp.loading') }}
            </div>

            <div v-else>
                <p>Form: <suggest-fieldtype :data.sync="selectedData.form" :config="form_config" name="form" :suggestions-prop="forms"></suggest-fieldtype></p>
                <div v-if="hasSelectedForm">
                    <p>Author: <suggest-fieldtype v-ref:author :data.sync="selectedData.author" :config="field_config" name="author" :suggestions-prop="fields"></suggest-fieldtype></p>
                    <p>Email: <suggest-fieldtype :data.sync="selectedData.email" :config="field_config" name="email" :suggestions-prop="fields"></suggest-fieldtype></p>
                    <p>Content: <suggest-fieldtype :data.sync="selectedData.content" :config="field_config" name="content" :suggestions-prop="fields"></suggest-fieldtype></p>
                </div>
            </div>
        </div>
    `,

    props: ['data', 'config', 'name'],

    data: function() {
        return {
            loading: true,
            forms: [],
            fields: [],
            selectedData: {
                form: (this.data && this.data.form) ? this.data.form : '',
                author: (this.data && this.data.author) ? this.data.author : '',
                email: (this.data && this.data.email) ? this.data.email : '',
                content: (this.data && this.data.content) ? this.data.content : ''
            },
            form_config: {
                type: 'suggest',
                max_items: 1
            },
            field_config: {
                type: 'suggest',
                max_items: 1
            },
        }
    },

    computed: {
        hasSelectedForm() {
            return this.data && this.data.form;
        }
    },

    methods: {
        getForms: function(loadFields = false) {
            this.$http.get('/!/Akismet/forms', function(data) {
                this.forms = data;
                if (loadFields && this.selectedData.form) {
                    this.getFields(this.selectedData.form[0]);
                }
                this.loading = false;
            });
        },
        getFields: function(formName) {
            if (! formName) {
                this.fields = [];
                this.selectedData.author = '';

                return false;
            }

            let selectedForm = this.forms.filter(function(form) {
                return form.value == formName;
            })[0];

            this.fields = selectedForm.fields;
        },
    },

    watch: {
        'selectedData.form': function() {
            // Reset the fields array before loading new data
            this.getFields();

            // Wait until next tick before repopulating fields array
            this.$nextTick(() => {
                if (this.selectedData.form) {
                    this.getFields(this.selectedData.form[0]);
                    if (!this.data) {
                        this.data = {};
                    }
                    this.data.form = this.selectedData.form;
                }
            });
        },
        'selectedData.author': function() {
            this.data.author = this.selectedData.author;
        },
        'selectedData.email': function() {
            this.data.email = this.selectedData.email;
        },
        'selectedData.content': function() {
            this.data.content = this.selectedData.content;
        },
    },

    ready: function() {
        this.getForms(true);
    }
});