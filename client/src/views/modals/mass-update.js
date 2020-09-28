

Espo.define('views/modals/mass-update', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'mass-update',

        header: false,

        template: 'modals/mass-update',

        data: function () {
            return {
                scope: this.scope,
                fields: this.fields
            };
        },

        events: {
            'click button[data-action="update"]': function () {
                this.update();
            },
            'click a[data-action="add-field"]': function (e) {
                var field = $(e.currentTarget).data('name');
                this.addField(field);
            },
            'click button[data-action="reset"]': function (e) {
                this.reset();
            }
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'update',
                    label: 'Update',
                    style: 'danger',
                    disabled: true
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.scope = this.options.scope;
            this.ids = this.options.ids;
            this.where = this.options.where;
            this.selectData = this.options.selectData;
            this.byWhere = this.options.byWhere;

            this.header = this.translate(this.scope, 'scopeNamesPlural') + ' &raquo ' + this.translate('Mass Update');

            this.wait(true);
            this.getModelFactory().create(this.scope, function (model) {
                this.model = model;
                this.getHelper().layoutManager.get(this.scope, 'massUpdate', function (layout) {
                    layout = layout || [];
                    this.fields = [];
                    layout.forEach(function (field) {
                        if (model.hasField(field)) {
                            this.fields.push(field);
                        }
                    }, this);

                    this.wait(false);
                }.bind(this));
            }.bind(this));

            this.fieldList = [];
        },

        addField: function (name) {
            this.enableButton('update');

            this.$el.find('[data-action="reset"]').removeClass('hidden');

            this.$el.find('ul.filter-list li[data-name="'+name+'"]').addClass('hidden');

            if (this.$el.find('ul.filter-list li:not(.hidden)').size() == 0) {
                this.$el.find('button.select-field').addClass('disabled').attr('disabled', 'disabled');
            }

            this.notify('Loading...');
            var label = this.translate(name, 'fields', this.scope);
            var html = '<div class="cell form-group col-sm-6" data-name="'+name+'"><label class="control-label">'+label+'</label><div class="field" data-name="'+name+'" /></div>';
            this.$el.find('.fields-container').append(html);

            var type = this.model.getFieldType(name);

            var viewName = this.model.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);

            this.createView(name, viewName, {
                model: this.model,
                el: this.getSelector() + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                },
                mode: 'edit'
            }, function (view) {
                this.fieldList.push(name);
                view.render();
                view.notify(false);
            }.bind(this));
        },

        actionUpdate: function () {
            this.disableButton('update');

            var self = this;

            var attributes = {};
            this.fieldList.forEach(function (field) {
                var view = self.getView(field);
                _.extend(attributes, view.fetch());
            });

            this.model.set(attributes);

            var notValid = false;
            this.fieldList.forEach(function (field) {
                var view = self.getView(field);
                notValid = view.validate() || notValid;
            });

            if (!notValid) {
                self.notify('Saving...');
                $.ajax({
                    url: this.scope + '/action/massUpdate',
                    type: 'PUT',
                    data: JSON.stringify({
                        attributes: attributes,
                        ids: self.ids || null,
                        where: (!self.ids || self.ids.length == 0) ? self.options.where : null,
                        selectData: (!self.ids || self.ids.length == 0) ? self.options.selectData : null,
                        byWhere: this.byWhere
                    }),
                    success: function (result) {
                        var result = result || {};
                        var count = result.count;

                        self.trigger('after:update', count);
                    },
                    error: function () {
                        self.notify('Error occurred', 'error');
                        self.enableButton('update');
                    }
                });
            } else {
                this.notify('Not valid', 'error');
                this.enableButton('update');
            }
        },

        reset: function () {
            this.fieldList.forEach(function (field) {
                this.clearView(field);
                this.$el.find('.cell[data-name="'+field+'"]').remove();
            }, this);

            this.fieldList = [];

            this.model.clear();

            this.$el.find('[data-action="reset"]').addClass('hidden');

            this.$el.find('button.select-field').removeClass('disabled').removeAttr('disabled');
            this.$el.find('ul.filter-list').find('li').removeClass('hidden');

            this.disableButton('update');
        }
    });
});