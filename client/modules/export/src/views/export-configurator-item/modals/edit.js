/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore GmbH.
 *
 * This Software is the property of AtroCore UG (haftungsbeschränkt) and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
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
