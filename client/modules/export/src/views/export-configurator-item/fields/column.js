/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/column', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        getCellElement: function () {
            return this.$el;
        },

        init: function () {
            Dep.prototype.init.call(this);

            this.prepareValue();
            this.listenTo(this.model, 'change:name change:attributeId change:columnType change:exportIntoSeparateColumns', () => {
                this.prepareValue();
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                this.checkFieldVisibility();
                this.checkFieldDisability();
            }
        },

        checkFieldDisability() {
            if (!this.isCustomType()) {
                this.$el.find('input').attr('disabled', 'disabled');
            } else {
                this.$el.find('input').removeAttr('disabled');
            }
        },

        checkFieldVisibility() {
            this.$el.parent().show();
        },

        isCustomType() {
            return this.model.get('columnType') === 'custom';
        },

        prepareValue() {
            if (this.mode !== 'list') {
                if (this.model.get('type') === 'Field') {
                    this.prepareFieldValue();
                }

                if (this.model.get('type') === 'Attribute') {
                    this.prepareAttributeValue();
                }
            }
        },

        prepareFieldValue() {
            if (this.model.get('columnType') === 'name' || this.model.isNew()) {
                let originField = this.model.get('name');
                let localeId = this.model.get('exportFeedData').localeId;
                this.getTranslates(localeId, translates => {
                    let columnName = originField;
                    if (translates[this.model.get('entity')] && translates[this.model.get('entity')]['fields'][originField]) {
                        columnName = translates[this.model.get('entity')]['fields'][originField];
                    } else if (translates['Global'] && translates['Global']['fields'][originField]) {
                        columnName = translates['Global']['fields'][originField];
                    }
                    this.model.set('column', columnName);
                });
            }
        },

        prepareAttributeValue() {
            if (this.model.get('columnType') === 'name') {
                this.model.set('column', this.model.get('attributeData').name);

                let localeId = this.model.get('exportFeedData').localeId;
                if (this.getConfig().get('locales')[localeId]) {
                    let language = this.getConfig().get('locales')[localeId].language;
                    let fieldName = 'name' + language.charAt(0).toUpperCase() + language.charAt(1) + language.charAt(3) + language.charAt(4).toLowerCase();
                    if (this.getMetadata().get(`entityDefs.Attribute.fields.${fieldName}`)) {
                        let val = this.model.get('attributeData')[fieldName];
                        if (val) {
                            this.model.set('column', val);
                        }
                    }
                }
            }
        },

        getTranslates(locale, callback) {
            this.ajaxGetRequest(`I18n`, {locale: locale}).then(responseData => {
                callback(responseData);
            });
        },

    })
});