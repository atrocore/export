/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-job/record/row-actions/export-again-and-remove', 'views/record/row-actions/remove-only', Dep => {

    return Dep.extend({

        getActionList() {
            let list = Dep.prototype.getActionList.call(this) || [];

            if (['Failed', 'Canceled'].includes(this.model.get('state')) && this.options.acl.edit) {
                list.unshift({
                    action: 'tryAgainExportJob',
                    label: 'tryAgain',
                    data: {
                        id: this.model.id
                    }
                });
            }

            return list;
        }
    });

});