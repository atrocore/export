/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/record/panels/export-jobs', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            let timeout = null;
            this.listenTo(this.collection, 'sync', () => {
                if (timeout !== null) {
                    clearTimeout(timeout);
                }
                timeout = setTimeout(() => {
                    if (this.hasPanel()) {
                        const hash = this.collectionToString()
                        this.collection.fetch({
                            noRebuild: () => {
                                return hash === this.collectionToString()
                            }
                        });
                    }
                }, 5000);
            });
        },

        collectionToString() {
            return JSON.stringify(this.collection.toArray().map(model => model.attributes))
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().hide();
            if (this.hasPanel()) {
                this.$el.parent().show();
            }
        },

        hasPanel() {
            return true;
        },

    })
);