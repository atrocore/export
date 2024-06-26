/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/fields/folder', 'views/fields/link', Dep => {

    return Dep.extend({

        selectBoolFilterList: ['notEntity'],

        boolFilterData: {
            notEntity() {
                return this.model.get('folderId');
            }
        },

        setup: function () {
            this.name = 'folder'
            this.foreignScope = 'Folder'

            Dep.prototype.setup.call(this);
        },

    });
});