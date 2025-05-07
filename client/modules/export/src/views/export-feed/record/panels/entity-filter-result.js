/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

Espo.define('export:views/export-feed/record/panels/entity-filter-result', ['views/record/panels/relationship', 'views/search/search-filter-opener'],
    (Dep, SearchFilterOpener) => Dep.extend({

        rowActionsView: 'views/record/row-actions/relationship-view-only',

        readOnly: true,

        setup() {
            if (!this.panelVisible()) {
                return;
            }

            this.scope = this.model.get('entity');
            this.url = this.model.get('entity');

            this.model.defs.links.entityFilterResult = {
                entity: this.scope,
                type: "hasMany"
            }

            this.defs.create = false;
            this.defs.select = false;
            this.defs.unlinkAll = false;

            Dep.prototype.setup.call(this);

            if(!this.defs.hideShowFullList && !this.getPreferences().get('hideShowFullList')) {
                this.actionList.push({
                    label: 'showFullList',
                    action: 'showFullList'
                });
            }

            let iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
            if (iconHtml) {
                if (this.defs.label) {
                    this.titleHtml = iconHtml + this.translate(this.defs.label, 'labels', 'ExportFeed');
                } else {
                    this.titleHtml = iconHtml + this.title;
                }
            }

            this.buttonList.unshift({
                title: this.translate('openSearchFilter'),
                action: 'openSearchFilter',
                html: this.getFilterButtonHtml()
            });
        },

        getLayoutRelatedScope() {
            return null;
        },

        actionShowFullList(data) {
            this.getStorage().set('listQueryBuilder', this.scope, this.model.get('data').whereData || {});
            window.open(`#${this.scope}`, '_blank');
        },

        setFilter(filter) {
            let data = this.model.get('data') || {};
            this.collection.where = data.where || [];
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.panelVisible()) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }

            $('.panel-entityFilterResult button[data-action="openSearchFilter"]').html(this.getFilterButtonHtml());
        },

        panelVisible() {
            return !(this.model.get('hasMultipleSheets'));
        },

        getFilterButtonHtml(){
            return SearchFilterOpener.prototype.getFilterButtonHtml.call(this, 'data');
        },

        actionOpenSearchFilter() {
            if(!this.model.get('entity') || !this.getMetadata().get(['scopes', this.model.get('entity')])) {
                this.notify(this.translate('The entity for the export is not valid'), 'error');
                return;
            }

            SearchFilterOpener.prototype.open.call(this, this.model.get('entity'), this.model.get('data')?.where,  ({where, whereData}) => {
                    this.model.set('data', _.extend({}, this.model.get('data'), {
                        where,
                        whereData,
                        whereScope: this.model.get('entity')
                    }));
                    this.notify(this.translate('saving', 'messages'));
                    this.model.save({_prev: null}).then(() =>  this.notify(this.translate('Done'), 'success'));
            });
        }
    })
);