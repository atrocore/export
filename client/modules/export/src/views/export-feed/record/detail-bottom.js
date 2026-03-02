/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/record/detail-bottom', 'views/record/detail-bottom',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            let filterResultPanel = null;
            (this.getMetadata().get(['clientDefs', 'ExportFeed', 'bottomPanels', 'detail']) || []).forEach(row => {
                if (row.name === 'entityFilterResult') {
                    filterResultPanel = row;
                }
            });

            this.onModelReady(() => {
                this.listenTo(this.model, 'change:data change:entity change:hasMultipleSheets after:save', () => {
                    this.createPanelView(filterResultPanel, view => {
                        view.render();
                    });
                });
            });
        },
    })
);