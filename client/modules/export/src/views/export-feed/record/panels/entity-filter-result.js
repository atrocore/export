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

Espo.define('export:views/export-feed/record/panels/entity-filter-result', 'views/search/panels/entity-filter-result',
    Dep => Dep.extend({

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

            let iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
            if (iconHtml) {
                if (this.defs.label) {
                    this.titleHtml = iconHtml + this.translate(this.defs.label, 'labels', 'ExportFeed');
                } else {
                    this.titleHtml = iconHtml + this.title;
                }
            }

            this.additionalBoolFilterList = this.options.additionalBoolFilterList ?? this.additionalBoolFilterList ?? [];
            this.boolFilterData = this.options.boolFilterData ?? this.boolFilterData ?? {};

            if(!this.additionalBoolFilterList.includes('unexported')) {
                this.additionalBoolFilterList.push('unexported');
                this.boolFilterData['unexported'] = this.model.get('lastTime')
            }

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
        },

        panelVisible() {
            return !(this.model.get('hasMultipleSheets'));
        },

        actionOpenSearchFilter(data) {
            if(!this.model.get('entity') || !this.getMetadata().get(['scopes', this.model.get('entity')])) {
                this.notify(this.translate('The entity for the export is not valid'), 'error');
                return;
            }

            let self  = this;

            if(self.defs?.name !== 'entityFilterResult') {
                self = this.getView('bottom').getView('entityFilterResult');
            }

           self.openSearchFilter(this.model.get('entity'), this.model.get('data')?.where,
                ({where, whereData}) => {
                    this.model.set('data', _.extend({}, this.model.get('data'), {
                        where,
                        whereData,
                        whereScope: this.model.get('entity')
                    }));
                    this.notify(this.translate('saving', 'messages'));
                    this.model.save({_prev: null}).then(() =>  this.notify(this.translate('Done'), 'success'));
                }
            );
        }
    })
);