/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/simple-type-components/record/product-search', ['pim:views/product/record/search', 'export:views/export-feed/simple-type-components/record/entity-search'], function (Dep, Filter) {
        return Dep.extend({

            disableSavePreset: true,

            setup() {
                Filter.prototype.setup.call(this);
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                Filter.prototype.afterRenderExportFilterPanel.call(this);
            },

            getTextFilterPlaceholder() {
                return Filter.prototype.getTextFilterPlaceholder.call(this);
            },

            isLeftDropdown() {
                return Filter.prototype.isLeftDropdown.call(this);
            },

            resetFilters() {
                return Filter.prototype.resetFilters.call(this);
            },

            refresh() {
                return Filter.prototype.refresh.call(this);
            },

            updateCollection() {
                return Filter.prototype.updateCollection.call(this);
            },

            setFilterDetailMode() {
                return Filter.prototype.setFilterDetailMode.call(this);
            },

            setFilterMode() {
                return Filter.prototype.setFilterMode.call(this);
            },

            getFilterData() {
                return Filter.prototype.getFilterData.call(this);
            },

        })
    }
);
