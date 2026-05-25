/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/sort-field-relation', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name change:type', () => {
                this.setupOptions();
                this.reRender();
                this.model.set('sortFieldRelation', null);
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                this.checkFieldVisibility();
            }
        },

        checkFieldVisibility() {
            let type = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']);

            if (this.model.get('type') === 'Field' && type === 'linkMultiple') {
                this.show();
                this.setRequired();
            } else {
                this.setNotRequired();
                this.hide();
            }
        },

        setupOptions() {
            this.translatedOptions = {'': '', 'id': this.translate('id', 'fields', 'Global')};

            let entity = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'links', this.model.get('name'), 'entity']);
            if (entity) {
                let fields = this.getMetadata().get(['entityDefs', entity, 'fields']) || {};
                let notAllowedType = ['jsonObject', 'linkMultiple'];
                $.each(fields, function (field, fieldData) {
                    if (fieldData.notStorable !== true && !notAllowedType.includes(fieldData.type) && fieldData.exportDisabled !== true) {
                        if (fieldData.type === 'link' || fieldData.type === 'file') {
                            this.translatedOptions[field + 'Id'] = this.translate(field, 'fields', entity) + ' ID';

                            // add relation fields
                            let foreignEntity = this.getMetadata().get(['entityDefs', entity, 'links', field, 'entity']);
                            if (foreignEntity) {
                                let foreignFields = this.getMetadata().get(['entityDefs', foreignEntity, 'fields']) || {};
                                $.each(foreignFields, function (foreignField, foreignFieldData) {
                                    if (foreignFieldData.notStorable !== true && !notAllowedType.includes(foreignFieldData.type) && foreignFieldData.exportDisabled !== true) {
                                        if (foreignFieldData.type === 'link' || foreignFieldData.type === 'file') {
                                            this.translatedOptions[field + '.' + foreignField + 'Id'] = this.translate(field, 'fields', entity) + ': ' + this.translate(foreignField, 'fields', foreignEntity) + ' ID';
                                        } else {
                                            this.translatedOptions[field + '.' + foreignField] = this.translate(field, 'fields', entity) + ': ' + this.translate(foreignField, 'fields', foreignEntity);
                                        }
                                    }
                                }.bind(this));
                            }
                        } else {
                            this.translatedOptions[field] = this.translate(field, 'fields', entity);
                        }
                    }
                }.bind(this));
            }

            this.params.options = Object.keys(this.translatedOptions);
        },

    })
);