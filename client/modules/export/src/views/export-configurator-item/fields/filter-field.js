/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/filter-field', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        init: function () {
            Dep.prototype.init.call(this);

            this.listenTo(this.model, 'change:name change:type', () => {
                this.setupOptions();
                this.reRender();
                this.model.set('filterField', null);
            });
        },

        setupOptions() {
            this.params.options = [''];
            this.translatedOptions = {"": ""};

            if (this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']) !== 'linkMultiple') {
                return;
            }

            let scope = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'links', this.model.get('name'), 'entity']);

            const fields = this.getMetadata().get(['entityDefs', scope, 'fields']);
            if (fields) {
                $.each(fields, (field, fieldDefs) => {
                    if (fieldDefs.type && ['enum', 'multiEnum', 'bool'].includes(fieldDefs.type) && fieldDefs.exportDisabled !== true && this.checkNotStorableField(fieldDefs)) {
                        this.params.options.push(field);
                        this.translatedOptions[field] = this.translate(field, 'fields', scope);
                    }
                });
            }
            this.originalOptionList = this.params.options;
        },

        checkNotStorableField(fieldDefs) {
            if (fieldDefs.notStorable !== true) {
                return true;
            }

            return (fieldDefs.relatingEntityField || []).includes(this.model.get('entity'));
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                this.checkFieldVisibility();
            }
        },

        checkFieldVisibility() {
            if (this.model.get('type') === 'Field' && this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']) === 'linkMultiple') {
                this.$el.parent().show();
                this.$el.parent().parent().parent().parent().show();
            } else {
                this.$el.parent().hide();
                this.$el.parent().parent().parent().parent().hide();
            }
        },

    })
});