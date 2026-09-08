/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/header-property', 'views/fields/enum',
    Dep => {

        // Field items (no entityAttributeId): fixed set of EntityField properties - Name, System
        // Name (code), Tooltip text - plus 'custom', added generically below for every non-
        // allAttributes item.
        const FIELD_PROPERTIES = ['name', 'code', 'tooltipText'];

        // Attribute-entity properties allowed as a header source for Attribute / All attributes
        // items - text-producing types only. link/linkMultiple resolve to the foreign record's
        // name at export time (see ExportConfiguratorItem::resolveAttributePropertyColumnName()).
        const ATTRIBUTE_PROPERTY_TYPES = ['varchar', 'text', 'enum', 'link', 'linkMultiple'];

        // structural/internal Attribute fields that aren't meaningful as header text
        const ATTRIBUTE_PROPERTY_EXCLUDE = [
            'data', 'conditionalRequired', 'conditionalReadOnly', 'conditionalProtected',
            'conditionalVisible', 'conditionalDisableOptions', 'conditionalProperties',
            'entityType', 'entityField', 'htmlSanitizer', 'nestedAttributes',
            'classificationAttributes', 'compositeAttribute', 'sortOrder', 'attributeGroupSortOrder',
            'modifiedExtendedDisabled', 'duplicateIgnore',
        ];

        return Dep.extend({

            init: function () {
                Dep.prototype.init.call(this);

                this.listenTo(this.model, 'change:name', () => {
                    this.reRender();
                });

                this.listenTo(this.model, 'change:type', () => {
                    if (this.model.get(this.name) !== 'custom' && (this.model.get('type') === 'Fixed value' || this.model.get('type') === 'script')) {
                        this.model.set(this.name, 'custom');
                    }
                    this.reRender();
                })
            },

            setup: function () {
                this.setupHeaderOptions();

                Dep.prototype.setup.call(this);
            },

            // Per-item-type option set - the allowed values depend on the item's type/
            // entityAttributeId, which metadata alone can't express, so it's computed here
            // (mirrors entity-field.js's prepareEnumOptions()/getEntityFields() pattern) instead
            // of relying on the static options declared in
            // Export\Listeners\Metadata::prepareHeaderFields().
            setupHeaderOptions: function () {
                const isAllAttributes = this.model.get('type') === 'allAttributes';
                const isAttribute = this.model.get('type') === 'Field' && !!this.model.get('entityAttributeId');
                const isField = this.model.get('type') === 'Field' && !this.model.get('entityAttributeId');

                let options;
                const translatedOptions = {};

                if (isAllAttributes || isAttribute) {
                    options = this.getAttributePropertyOptions();
                    options.forEach(option => {
                        translatedOptions[option] = this.translate(option, 'fields', 'Attribute');
                    });
                } else if (isField) {
                    options = FIELD_PROPERTIES;
                    options.forEach(option => {
                        translatedOptions[option] = this.translate(option, 'fields', 'EntityField');
                    });
                } else {
                    // Fixed value / script - dropdown is disabled, always 'custom'
                    options = [];
                }

                // No custom name for All attributes (same label would apply to every expanded
                // attribute column, which makes no sense).
                if (!isAllAttributes) {
                    options = options.concat(['custom']);
                    translatedOptions['custom'] = this.translateHeaderProperty('custom');
                }

                this.params.options = options;
                this.params.translatedOptions = translatedOptions;
            },

            // bare 'headerProperty' (not this.name, which is 'headerProperty1'/'headerProperty2'/... -
            // the i18n file only has one shared options.headerProperty map for every header index).
            translateHeaderProperty: function (option) {
                return this.getLanguage().translateOption(option, 'headerProperty', 'ExportConfiguratorItem');
            },

            getAttributePropertyOptions: function () {
                const fields = this.getMetadata().get('entityDefs.Attribute.fields') || {};

                return Object.keys(fields).filter(field => {
                    const defs = fields[field] || {};

                    return !defs.notStorable
                        && ATTRIBUTE_PROPERTY_TYPES.includes(defs.type)
                        && !ATTRIBUTE_PROPERTY_EXCLUDE.includes(field);
                });
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if (this.mode !== 'list') {
                    this.checkFieldDisability();
                }
            },

            checkFieldDisability() {
                if (this.model.get('type') === 'Fixed value' || this.model.get('type') === 'script') {
                    this.$el.find('select').attr('disabled', 'disabled');
                } else {
                    this.$el.find('select').removeAttr('disabled');
                }
            },

        })
    });
