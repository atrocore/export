/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('export:views/export-configurator-item/modals/edit', ['views/modals/edit', 'views/search/search-filter-opener'],
    (Dep,SearchFilterOpener) => {
        return Dep.extend({

            fullFormDisabled: true,

            hasRightSideView: false,

            setup() {
                Dep.prototype.setup.call(this);

                this.buttonList.push({
                    title: this.translate('openSearchFilter'),
                    name: 'filterButton',
                    hidden: true,
                    html: this.getFilterButtonHtml(),
                    onClick: () => {
                        SearchFilterOpener.prototype.open.call(this, this.getFilterScope(), this.model.get('searchFilter')?.where,  ({where, whereData}) => {
                            this.model.set('searchFilter',  _.extend({}, this.model.get('searchFilter'), {
                                where,
                                whereData,
                                whereScope: this.getFilterScope()
                            }));
                            this.$el.find('button[data-name="filterButton"]').html(this.getFilterButtonHtml());
                            this.checkFieldVisibility();
                        });
                    }
                });

                this.listenTo(this.model, 'change:name change:type', () => {
                    this.model.set('searchFilter', null);
                    this.$el.find('button[data-name="filterButton"]').html(this.getFilterButtonHtml());
                    this.checkFieldVisibility();
                });
            },

            getFilterButtonHtml(){
                return SearchFilterOpener.prototype.getFilterButtonHtml.call(this, 'searchFilter');
            },

            checkFieldVisibility() {
                if(this.model.get('type') === 'Field' && this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']) === 'linkMultiple') {
                    this.$el.find('button[data-name="filterButton"]').removeClass('hidden')
                }else{
                    this.$el.find('button[data-name="filterButton"]').addClass('hidden')
                }
            },

            getFilterScope() {
                return this.getMetadata().get(['entityDefs', this.model.get('entity'), 'links', this.model.get('name'), 'entity']);
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);
                this.$el.find('button[data-name="filterButton"]').html(this.getFilterButtonHtml());
                this.checkFieldVisibility();
            }
        });

    });
