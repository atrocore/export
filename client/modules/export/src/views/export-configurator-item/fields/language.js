/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/language', 'views/fields/enum',
    Dep => {

        return Dep.extend({

            prohibitedEmptyValue: true,

            isMultiLang: false,

            init: function () {
                this.prepareMultiLangParam();
                Dep.prototype.init.call(this);
                this.listenTo(this.model, 'change:type change:name change:attributeId', () => {
                    this.prepareMultiLangParam();
                });
            },

            setupOptions() {
                this.params.options = ['main'];
                this.translatedOptions = {"main": this.getLanguage().translateOption('main', 'languageFilter', 'Global')};

                (this.getConfig().get('inputLanguageList') || []).forEach(locale => {
                    this.params.options.push(locale);
                    this.translatedOptions[locale] = locale;
                });
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if (this.mode !== 'list') {
                    this.checkFieldVisibility();
                }
            },

            prepareMultiLangParam() {
                if (this.model.get('exportFeedLanguage')) {
                    this.isMultiLang = false;
                    this.model.set('_isMultilang', false)
                    this.reRender();
                    return;
                }

                if (this.model.get('type') === 'Field') {
                    this.isMultiLang = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.isMultilang`);
                    this.reRender();
                } else if (this.model.get('type') === 'Attribute') {
                    if (this.model.get('attributeId')) {
                        this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).then(attribute => {
                            this.isMultiLang = attribute.isMultilang;
                            this.model.set('_isMultilang', this.isMultiLang)
                            this.reRender();
                        });
                    } else {
                        this.isMultiLang = false;
                        this.model.set('_isMultilang', false)
                        this.reRender();
                    }
                }
            },

            checkFieldVisibility() {
                if (this.isMultiLang && (this.getConfig().get('inputLanguageList') || []).length > 0) {
                    if(!this.model.get('language')){
                        this.model.set('language', this.params.options[0])
                    }
                    this.show();
                } else {
                    this.hide();
                }
            },

        })
    });