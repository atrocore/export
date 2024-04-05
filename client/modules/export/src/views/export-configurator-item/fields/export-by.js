/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/export-by', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name change:type change:attributeId change:attributeValue', () => {
                this.model.set('exportBy', null);
                this.setupOptions();
                this.reRender();
            });
        },

        getMaxDepth() {
            return parseInt($('.field[data-name="exportByMaxDepth"]>span').text()) || 1
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                this.checkFieldVisibility();
            }

            if (this.model.get('entity') === 'ProductAttributeValue' && this.model.get('name') === 'value') {
                this.$el.append(`<span style="color: #999; font-size: 12px">${this.translate('exportByForAttributeValue', 'labels', 'ExportConfiguratorItem')}</span>`)
                this.$el.closest('.cell').find('.label-text').text(this.translate('exportByForListAttribute', 'fields', 'ExportConfiguratorItem'))
            } else {
                this.$el.closest('.cell').find('.label-text').text(this.translate('exportBy', 'fields', 'ExportConfiguratorItem'))
            }
        },

        checkFieldVisibility() {
            if (this.model.get('entity') === 'ProductAttributeValue' && this.model.get('name') === 'value') {
                this.show();
                return
            }

            if (this.isRequired()) {
                this.show();
            } else {
                this.hide();
            }
        },

        setupOptions() {
            let translatedOptions = this.getTranslatesForExportByField();
            this.params.options = Object.keys(translatedOptions);
            this.translatedOptions = this.params.translatedOptions = translatedOptions;
        },

        getTranslatesForExportByField() {
            let result = {'id': this.translate('id', 'fields', 'Global')};

            let entity;
            if (this.model.get('type') === 'Field') {
                entity = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'links', this.model.get('name'), 'entity']);
                if (this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'extensibleEnumId'])) {
                    entity = 'ExtensibleEnumOption';
                }
                if (this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']) === 'measure') {
                    entity = 'Unit';
                }
                if (this.model.get('entity') === 'ProductAttributeValue' && this.model.get('name') === 'value') {
                    entity = 'ExtensibleEnumOption'
                }
            } else {
                if (this.model.get('attributeId')) {
                    let attribute = this.getAttribute(this.model.get('attributeId'));
                    if (['extensibleEnum', 'extensibleMultiEnum'].includes(attribute.type)) {
                        entity = 'ExtensibleEnumOption';
                    } else if (this.model.get('attributeValue') === 'valueUnit') {
                        entity = 'Unit'
                    } else if (attribute.type === 'link' || attribute.type === 'linkMultiple') {
                        entity = attribute.entityType;
                    }
                }
            }

            if (entity) {
                /**
                 * For main image
                 */
                if (this.model.get('name') === 'mainImage' || ['Category', 'Product'].includes(this.model.get('entity')) && this.model.get('name') === 'image') {
                    entity = 'Asset';
                }

                let fields = this.getMetadata().get(['entityDefs', entity, 'fields']) || {};
                let sortedFields = Object.keys(fields).sort((v1, v2) => this.translate(v1, 'fields', entity).localeCompare(this.translate(v2, 'fields', entity)));

                sortedFields.forEach(field => {
                    let fieldData = fields[field];
                    if (!fieldData.disabled && !fieldData.exportDisabled && !['jsonObject', 'linkMultiple'].includes(fieldData.type)) {
                        if (fieldData.type === 'link') {
                            result = this.pushLinkFields(result, entity, field);
                        } else if (fieldData.type === 'file') {
                            result = this.pushLinkFields(result, entity, field);
                            result[field + 'Url'] = this.translate(field, 'fields', entity) + ' ' + this.translate('url', 'fields', 'File');
                        } else {
                            result[field] = this.translate(field, 'fields', entity);
                        }
                    }
                });
            }

            if ((entity === 'ExtensibleEnumOption' || entity === 'Unit') && this.model.get('exportBy') == null) {
                this.model.set('exportBy', ['name'])
            }

            return result;
        },

        pushLinkFields(result, entity, field, depth = 1, fieldPrefix = "", translationPrefix = "") {
            if (depth > this.getMaxDepth()) {
                return result
            }

            let linkEntity = this.getMetadata().get(['entityDefs', entity, 'links', field, 'entity']);
            if (linkEntity) {
                result[fieldPrefix + field + 'Id'] = translationPrefix + this.translate(field, 'fields', entity) + ': ID';
                $.each(this.getMetadata().get(['entityDefs', linkEntity, 'fields']), (linkField, linkFieldDefs) => {
                    if (!linkFieldDefs.disabled && !linkFieldDefs.exportDisabled && !['jsonObject', 'linkMultiple'].includes(linkFieldDefs.type)) {
                        if (linkFieldDefs.type === 'link') {
                            this.pushLinkFields(result, linkEntity, linkField, depth + 1, fieldPrefix + field + '.', translationPrefix + this.translate(field, 'fields', entity) + ': ')
                        }
                        if (linkField === 'name') {
                            result[fieldPrefix + field + 'Name'] = translationPrefix + this.translate(field, 'fields', entity) + ': ' + this.translate(linkField, 'fields', linkEntity);
                        } else {
                            result[fieldPrefix + field + '.' + linkField] = translationPrefix + this.translate(field, 'fields', entity) + ': ' + this.translate(linkField, 'fields', linkEntity);
                        }
                    }
                });
            }

            return result;
        },

        isRequired() {
            let type = 'varchar';
            if (this.model.get('type') === 'Field') {
                type = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']);
            } else {
                if (this.model.get('attributeId')) {
                    switch (this.model.get('attributeValue')) {
                        case 'value':
                            type = this.getAttribute(this.model.get('attributeId')).type;
                            break
                        case 'valueUnit':
                            type = 'link'
                            break
                        default:
                    }
                }
            }

            return ['link', 'extensibleEnum', 'linkMultiple', 'extensibleMultiEnum', 'file', 'measure'].includes(type) && (this.params.options || []).length;
        },

        getAttribute(attributeId) {
            let key = `attribute_${attributeId}`;
            if (!Espo[key]) {
                Espo[key] = null;
                this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`, null, {async: false}).success(attr => {
                    Espo[key] = attr;
                });
            }

            return Espo[key];
        },

    })
);