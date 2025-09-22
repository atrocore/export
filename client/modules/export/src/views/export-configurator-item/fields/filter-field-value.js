/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/filter-field-value', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        init: function () {
            Dep.prototype.init.call(this);

            this.listenTo(this.model, 'change:name change:type change:filterField', () => {
                this.setupOptions();
                this.reRender();
                this.model.set('filterFieldValue', null);
            });
        },

        setupOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            if (this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']) !== 'linkMultiple') {
                return;
            }

            let scope = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'links', this.model.get('name'), 'entity']);

            let fieldType = this.getMetadata().get(['entityDefs', scope, 'fields', this.model.get('filterField'), 'type']);

            if (fieldType === 'bool') {
                this.params.options = ['+', '-'];
                this.translatedOptions = {
                    "+": this.getLanguage().translateOption('+', 'boolFilterFieldValue', 'ExportConfiguratorItem'),
                    "-": this.getLanguage().translateOption('-', 'boolFilterFieldValue', 'ExportConfiguratorItem'),
                }
            } else if (['enum', 'multiEnum'].includes(fieldType)) {
                (this.getMetadata().get(['entityDefs', scope, 'fields', this.model.get('filterField'), 'options']) || []).forEach(option => {
                    this.params.options.push(option);
                    this.translatedOptions[option] = this.translate(option, 'labels', scope);
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

        isRequired() {
            return this.model.get('type') === 'Field' && this.model.get('filterField');
        },

        checkFieldVisibility() {
            if (this.isRequired()) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        },

    })
});