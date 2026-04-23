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
            this.listenTo(this.model, 'change:name change:columnType change:exportIntoSeparateColumns', () => {
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

            if(this.mode === 'list') {
                this.setReadOnly();
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
            if (this.model.get('type') !== 'allAttributes') {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        },

        isCustomType() {
            return this.model.get('columnType') === 'custom';
        },

        prepareValue() {
            if (this.mode !== 'list') {
                if (this.model.get('type') === 'Field') {
                    this.prepareFieldValue();
                }
            }
        },

        prepareFieldValue() {
            if (this.model.get('columnType') === 'name' || this.model.isNew()) {
                if (this.model.get('entityAttributeId') && this.model.get('fieldDefs')) {
                    this.model.set('column', this.model.get('fieldDefs').label);
                } else {
                    let localeId = null;
                    let originField = this.model.get('name');
                    if (!this.model.get('exportFeedData')) {
                        const exportFeedId = this.model.get('_entityFrom').exportFeedId;
                        this.ajaxGetRequest(`ExportFeed/${exportFeedId}`, {}, {async: false}).success(res => {
                            localeId = res.localeId;
                        })
                    } else {
                        localeId = this.model.get('exportFeedData').localeId;
                    }

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
            }
        },

        getTranslates(locale, callback) {
            this.ajaxGetRequest(`i18n`, {locale: locale}).then(responseData => {
                callback(responseData);
            });
        },

    })
});