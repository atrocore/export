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

Espo.define('export:views/export-feed/record/panels/entity-filter-result', 'views/record/panels/relationship',
    Dep => Dep.extend({

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

            Dep.prototype.setup.call(this);

            this.actionList.push({
                label: 'showFullList',
                action: 'showFullList'
            });

            this.listenTo(Backbone.Events, 'search', searchView => {
                if (searchView.searchManager.type === 'exportSimpleType') {
                    this.model.set('data', _.extend({}, this.model.get('data'), {
                        where: this.getWhere(searchView),
                        whereData: this.getWhereData(searchView),
                        whereScope: this.model.get('entity'),
                    }));
                }
            });
        },

        getWhere(searchView) {
            return Espo.Utils.cloneDeep(searchView.searchManager.getWhere());
        },

        getWhereData(searchView) {
            return Espo.Utils.cloneDeep(searchView.searchManager.get());
        },

        actionShowFullList(data) {
            this.getStorage().set('listSearch', this.scope, this.model.get('data').whereData || {});
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
        },

        panelVisible() {
            return !(this.model.get('hasMultipleSheets'));
        }

    })
);