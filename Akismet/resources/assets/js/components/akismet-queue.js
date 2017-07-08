import Dossier from "./dossier/Dossier.vue";
let VueTruncate = require('vue-truncate-filter');
Vue.use(VueTruncate);

Vue.component('akismet-queue', {
    mixins: [Dossier],

    props: ['formset'],

    data: function () {
        return {
            ajax: {
                get: cp_url('addons/akismet/spam?form=' + this.formset),
                api: cp_url('addons/akismet/spam')
            },
            tableOptions: {
                checkboxes: true,
                partials: {
                    cell: `{{ item[column.label] | truncate 100 }}`,
                    actions: `
                        <li><a href="#" @click.prevent="call('approveItem', item.id)">Approve</a></li>
                        <li class="warning"><a href="#" @click.prevent="call('discardItem',item.id)">Discard</a></li>`
                }
            }
        }
    },
    methods: {
        discardItem: function (id) {
            let self = this;

            swal({
                type: 'warning',
                title: translate('cp.are_you_sure'),
                text: 'Are you sure you want to discard this spam?',
                confirmButtonText: 'Discard',
                cancelButtonText: translate('cp.cancel'),
                showCancelButton: true
            }, function () {
                self.$http.delete(self.ajax.api, {formset: self.formset, ids: [id]}, function (data) {
                    self.removeItemFromList(id);
                });
            });
        },

        approveItem: function (id) {
            let self = this;

            swal({
                type: 'warning',
                title: translate('cp.are_you_sure'),
                text: 'Are you sure you want to approve this spam?',
                confirmButtonText: 'Approve',
                cancelButtonText: translate('cp.cancel'),
                showCancelButton: true
            }, function () {
                self.$http.put(self.ajax.api, {formset: self.formset, ids: [id]}, function (data) {
                    self.removeItemFromList(id);
                });
            });
        },

        discardMultiple: function (ids) {
            let self = this;

            swal({
                type: 'warning',
                title: translate('cp.are_you_sure'),
                text: 'Are you sure you want to discard this spam?',
                confirmButtonText: 'Discard',
                cancelButtonText: translate('cp.cancel'),
                showCancelButton: true,
            }, function () {
                self.$http.delete(self.ajax.api, {formset: self.formset, ids: ids}, function (data) {
                    _.each(ids, function (id) {
                        self.removeItemFromList(id);
                    });
                });
            });
        },

        approveItems: function (ids) {
            let self = this;

            swal({
                type: 'warning',
                title: translate('cp.are_you_sure'),
                text: 'Are you sure you want to approve this spam?',
                confirmButtonText: 'Approve',
                cancelButtonText: translate('cp.cancel'),
                showCancelButton: true
            }, function () {
                self.$http.put(self.ajax.api, {formset: self.formset, ids: ids}, function (data) {
                    _.each(ids, function (id) {
                        self.removeItemFromList(id);
                    });
                });
            });
        },
    },
});