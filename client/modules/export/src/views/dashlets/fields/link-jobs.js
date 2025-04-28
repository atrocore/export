/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/dashlets/fields/link-jobs', 'export:views/fields/int-with-link-to-list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            this.listScope = 'ExportJob';
        },

        getSearchFilter() {
            let bool = {};

            switch (parseInt(this.model.attributes.interval)) {
                case 1:
                    bool.onlyExportFailed24Hours = true;
                    break;
                case 7:
                    bool.onlyExportFailed7Days = true;
                    break;
                default:
                    bool.onlyExportFailed28Days = true;
                    break;
            }

            return {
                textFilter: '',
                primary: null,
                bool: bool,
                queryBuilderApplied: false
            };
        }

    })
);
