Vue.component('akismet-fieldtype', {
    template: `
        <div>
            <div v-if="loading" class="loading loading-basic">
                <span class="icon icon-circular-graph animation-spin"></span> {{ translate('cp.loading') }}
            </div>

            <div v-else class="row">
                <div class="col-xs-3">Form: <suggest-fieldtype :data.sync="data.form" :config="field_config" name="form" :suggestions-prop="forms"></suggest-fieldtype></div> 
                
                <div v-if="data.form && !repopulatingFields">
                    <div class="col-xs-3"><p>Author: <suggest-fieldtype :data.sync="data.author_field" :config="field_config" :suggestions-prop="fields"></suggest-fieldtype></p></div>
                    <div class="col-xs-3"><p>Email: <suggest-fieldtype :data.sync="data.email_field" :config="field_config" :suggestions-prop="fields"></suggest-fieldtype></p></div>
                    <div class="col-xs-3"><p>Content: <suggest-fieldtype :data.sync="data.content_field" :config="field_config" :suggestions-prop="fields"></suggest-fieldtype></p></div>
                </div>
        </div>
    `,

    props: ['data'],

    data: function() {
        return {
            loading: true,
            repopulatingFields: false,
            forms: [],
            fields: [],
            field_config: {
                type: 'suggest',
                max_items: 1
            },
        }
    },

    methods: {
        getForms: function() {
            this.$http.get('/!/Akismet/forms', function(data) {
                this.forms = data;
                this.loading = false;
                this.getFields();
            });
        },

        getFields: function() {
            let formName = this.data.form ? this.data.form[0] : null;

            if (formName) {
                this.repopulatingFields = true;

                this.$nextTick(function() {
                    let selectedForm = this.forms.filter(function(form) {
                        return form.value == formName;
                    })[0];

                    this.fields = selectedForm.fields;
                    this.repopulatingFields = false;
                });
            }
        },
        resetFields: function() {
            this.fields = [];
            this.data.author_field = null;
            this.data.email_field = null;
            this.data.content_field = null;
        },
    },

    watch: {
        'data.form': function() {
            this.resetFields();
            this.getFields();
        },
   },

    ready: function() {
        this.getForms();
    }
});