/**
 * Contao Open Source CMS
 *
 * @copyright   Andreas Schempp 2011
 * @copyright   certo web & design GmbH 2011
 * @copyright   MEN AT WORK 2013
 * @author      Ingolf Steinhardt <info@e-spin.de> 2020
 * @package     MultiColumnWizard
 * @license     GNU/LGPL
 * @info        tab is set to 4 whitespaces
 * @info        Compressed with https://prepros.io/
 */

var MultiColumnWizard = new Class(
    {
        Implements: [Options],
        options:
            {
                table: null,
                maxCount: 0,
                minCount: 0,
                uniqueFields: []
            },
        asyncBlock: false,

        // instance callbacks (use e.g. myMCWVar.addOperationCallback() to register a callback that is for ONE specific MCW only)
        operationLoadCallbacks: [],
        operationClickCallbacks: [],
        operationUpdateCallbacks: [],

        /**
         * Initialize the wizard
         * @param Object options
         */
        initialize: function(options)
        {
            this.setOptions(options);

            // make sure we really have the table as element
            this.options.table = document.id(this.options.table);

            // Do not run this in the frontend, Backend class would not be available
            if (window.Backend)
            {
                Backend.getScrollOffset();
            }

            var self = this;

            this.options.table.getElement('tbody').getChildren('tr').each(function(el, index){

                el.getChildren('td.operations a').each(function(operation) {
                    var key = operation.get('data-operations');

                    // call static load callbacks
                    if (MultiColumnWizard.operationLoadCallbacks[key])
                    {
                        MultiColumnWizard.operationLoadCallbacks[key].each(function(callback)
                        {
                            callback.pass([operation, el], self)();
                        });
                    }

                    // call instance load callbacks
                    if (self.operationLoadCallbacks[key])
                    {
                        self.operationLoadCallbacks[key].each(function(callback)
                        {
                            callback.pass([operation, el], self)();
                        });
                    }
                });
            });
            this.updateOperations();
        },

        /**
         * Update operations
         */
        updateOperations: function()
        {
            var self = this;

            // execute load callback and register click event callback
            this.options.table.getElement('tbody').getChildren('tr').each(function(el, index)
            {
                el.getChildren('td.operations a').each(function(operation)
                {
                    var key = operation.get('data-operations');

                    // remove all click events
                    operation.removeEvents('click');
                    if(key ==='move') {
                        self.dragAndDrop(el, operation);
                    }
                    // register static click callbacks
                    if (MultiColumnWizard.operationClickCallbacks[key])
                    {
                        MultiColumnWizard.operationClickCallbacks[key].each(function(callback)
                        {
                            operation.addEvent('click', function(e)
                            {
                                e.preventDefault();
                                callback.pass([operation, el], self)();
                            });
                        });
                    }

                    // register instance click callbacks
                    if (self.operationClickCallbacks[key])
                    {
                        self.operationClickCallbacks[key].each(function(callback)
                        {
                            operation.addEvent('click', function(e)
                            {
                                e.preventDefault();
                                callback.pass([operation, el], self)();
                                self.updateFields(index);
                            });
                        });
                    }

                    //register updateOperations as last click event (see issue #40)
                    operation.addEvent('click', function(e)
                    {
                        e.preventDefault();
                        self.updateOperations.pass([operation, el], self)();
                    });


                    // call static update callbacks
                    if (MultiColumnWizard.operationUpdateCallbacks[key])
                    {
                        MultiColumnWizard.operationUpdateCallbacks[key].each(function(callback)
                        {
                            callback.pass([operation, el], self)();
                        });
                    }

                    // call instance update callbacks
                    if (self.operationUpdateCallbacks[key])
                    {
                        self.operationUpdateCallbacks[key].each(function(callback)
                        {
                            callback.pass([operation, el], self)();
                        });
                    }


                });
            });
        },

        /**
         * Update row attributes
         * @param int level
         * @param element row
         * @return element the updated element
         */
        updateRowAttributes: function(level, row)
        {
            var firstLevel = true;
            var intInnerMCW = 0;
            var intSubLevels = 0;
            var innerMCWCols = 0;
            var innerMCWColCount = 0;

            // The row index that belongs to THIS wizard is the one right after the wizard's own id
            // ("<wizardId>_row<n>_..."). Anchoring the rewrite on that id keeps the indices of parent and
            // child wizards intact. A blind "last _row<n>_" replace corrupts nested ids: when an outer row
            // is moved, all of its inner rows collapse onto the same id, so only the first inner editor
            // survives and one gets duplicated.
            var mcwStrId   = (this.options.table && this.options.table.get('id') || '').replace(/^ctrl_/, '');
            var mcwAnchor  = mcwStrId.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '_row';
            var reRowFirst = new RegExp('(' + mcwAnchor + ')[0-9]+_');
            var reRowAll   = new RegExp('(' + mcwAnchor + ')[0-9]+_', 'g');

            row.getElements('.mcwUpdateFields *').each(function(el)
            {

                /*
                 *  We have to process the following steps:
                 *  - delete elements created by choosen or other scripts and create new ones if necessary
                 *  - rewrite the attributes name, id, onlick, for
                 *  - rewrite inline SCRIPT-tags
                 */

                // Check if we have a mcw in mcw
                if (el.hasClass('tl_modulewizard') && el.hasClass('multicolumnwizard')) {
                    firstLevel = false;
                    intInnerMCW++;
                    el.addClass('mcw_inner_' + intInnerMCW);
                    innerMCWCols = el.getElement('tbody').getElement('tr').getElements('td.mcwUpdateFields').length;
                    innerMCWColCount = 1;
                }

                // Check if we have left one mcw
                if (intInnerMCW !== 0 && (!el.hasClass('tl_modulewizard') || !el.hasClass('multicolumnwizard')) && el.getParent('.mcw_inner_' + intInnerMCW) === null) {
                    intInnerMCW--;
                    if (intInnerMCW === 0) {
                        firstLevel = true;
                    }
                }

                //remove choosen elements
                if (el.hasClass('chzn-container')){
                    el.destroy();
                    return;
                }

                // rewrite elements name
                if (typeOf(el.getProperty('name')) == 'string')
                {
                    var oldName   = el.getProperty('name');
                    var matches   = oldName.match(/([^[\]]+)/g);
                    var lastIndex = null;
                    var newName   = '';

                    matches.each(function(element, index) {
                        if (!isNaN(parseFloat(element)) && isFinite(element))
                        {
                            lastIndex = index;
                        }
                    });

                    matches.each(function(element, index) {
                        if (index === 0)
                        {
                            newName += element;
                        }
                        // First element
                        else if (index === lastIndex && firstLevel)
                        {
                            newName += '[' + level + ']';
                        }
                        // All other elements
                        else if (index === (lastIndex - 2) && !firstLevel)
                        {
                            newName += '[' + level + ']';
                        }
                        else if (index === lastIndex && !firstLevel)
                        {
                            newName += '[' + intSubLevels + ']';
                            intSubLevels = ((innerMCWColCount >0) && (innerMCWColCount % innerMCWCols) ==0) ? ++intSubLevels : intSubLevels;
                            innerMCWColCount++
                        }
                        else
                        {
                            newName += '[' + element + ']';
                        }
                    });

                    if(oldName.substr(oldName.length - 2) == '[]') {
                        newName += '[]';
                    }

                    el.setProperty('name', newName);
                }

                // rewrite elements id or delete input fields without an id
                if (typeOf(el.getProperty('id')) == 'string')
                {
                    // Rewrite only the row index of THIS wizard (see reRowFirst above), leaving the
                    // indices of nested parent/child wizards untouched.
                    el.setProperty('id', el.getProperty('id').replace(reRowFirst, '$1' + level + '_'));
                }

                // rewrite elements onclick (e.g. checkbox group toggle, pagePicker). Anchored on the
                // wizard id and global (reRowAll), since an onclick can reference more than one id.
                if (typeOf(el.getProperty('onclick')) == 'string')
                {
                    el.setProperty('onclick', el.getProperty('onclick').replace(reRowAll, '$1' + level + '_'));
                }

                // rewrite elements for attribute (option labels, "select all"). Anchored like the id
                // above so nested parent/child indices stay intact.
                if (typeOf(el.getProperty('for')) == 'string')
                {
                    el.setProperty('for', el.getProperty('for').replace(reRowAll, '$1' + level + '_'));
                }
                // set attributes depending of the tag type
                switch (el.nodeName.toUpperCase())
                {

                    case 'SELECT':
                        //create new chosen (2.11 only)
                        if (el.hasClass('tl_chosen')) new Chosen(el);
                        break;
                    case 'INPUT':
                        //set input field to visible
                        if (el.getStyle('display').toLowerCase() == 'none') el.setStyle('display','inline');
                        // delete input field without ids (these input fields are created by JS)
                        if (typeOf(el.getProperty('id')) != 'string') el.destroy();
                        break;
                    case 'SCRIPT':
                        // Rewrite this wizard's row index inside inline scripts (e.g. the tinyMCE init
                        // "selector" and "source"). Anchored on the wizard id (reRowAll) so it stays in
                        // sync with the element ids above and never touches the indices of nested wizards.
                        el.set('html', el.get('html').toString().replace(reRowAll, '$1' + level + '_'));
                        break;
                }

            });

            return row;
        },

        /**
         * Adding Sortable Mode for Drag and drop
         * @param element table row
         * @param element move button
         */
        dragAndDrop: function(tr, link) {
            var self = this;
            new Sortables(tr.getParent('table').getElement('tbody'), {
                constrain: true,
                opacity: 0.6,
                // Only use the row's OWN move handle. Without the "> td.operations" scope Sortables'
                // element.getElement(handle) returns the FIRST descendant move handle, which for a row
                // containing a nested wizard is an inner row's handle - so the outer wizard could not be
                // dragged at all (the outer move handle was never bound).
                handle: '> td.operations a[data-operations=move]',
                // Flush and remove the RTE editors of the affected wizard before the row is moved in the
                // DOM. Re-parenting an editor iframe reloads it and drops its content, so we sync the
                // content back into the textarea first and reinitialise the editors once the new order is
                // applied.
                onStart: function(el) {
                    self.killAllTinyMCE(link, el);
                },
                onComplete: function(el) {
                    tr.getParent('table').getElement('tbody').getChildren('tr').each(function(row, i) {
                        //Must be substract down 1 because the loop iterator begins with 1
                        var level = i--;
                        this.updateRowAttributes(level, row);
                    }, this);
                    self.reinitTinyMCE(link, el, false);
                }.bind(this)
            });
        },

        /**
         * Add a load callback for the instance
         * @param string the key e.g. 'copy' - your button has to have the matching data-operations="" attribute (<a href="jsfallbackurl" data-operations="copy">...</a>)
         * @param function callback
         */
        addOperationLoadCallback: function(key, func)
        {
            if (!this.operationLoadCallbacks[key])
            {
                this.operationLoadCallbacks[key] = [];
            }

            this.operationLoadCallbacks[key].include(func);
        },

        /**
         * Add a load callback for the instance
         * @param string the key e.g. 'copy' - your button has to have the matching data-operations="" attribute (<a href="jsfallbackurl" data-operations="copy">...</a>)
         * @param function callback
         */
        addOperationUpdateCallback: function(key, func)
        {
            if (!this.operationUpdateCallbacks[key])
            {
                this.operationUpdateCallbacks[key] = [];
            }

            this.operationUpdateCallbacks[key].include(func);
        },

        /**
         * Add a click callback for the instance
         * @param string the key e.g. 'copy' - your button has to have the matching data-operations="" attribute (<a href="jsfallbackurl" data-operations="copy">...</a>)
         * @param function callback
         */
        addOperationClickCallback: function(key, func)
        {
            if (!this.operationClickCallbacks[key])
            {
                this.operationClickCallbacks[key] = [];
            }

            this.operationClickCallbacks[key].include(func);
        },

        killAllTinyMCE: function(el, row)
        {
            var parent = row.getParent('.multicolumnwizard');

            // skip if tinymce is not available or the wizard has no rte field
            if(typeof tinymce === 'undefined' || !parent || parent.getElements('.tinymce').length == 0)
            {
                return;
            }

            // TinyMCE 6 no longer exposes tinymce.editors; tinymce.get() (without argument) returns the
            // list of all editors. Iterate a copy, because editor.remove() mutates that list. save()
            // writes the current content back into the underlying textarea so it survives the DOM move;
            // remove() then destroys the editor but keeps the textarea (with its value) in place. The
            // init <script> tags are intentionally left in the DOM - reinitTinyMCE() re-runs them.
            var myRegex = new RegExp(parent.get('id'));
            (tinymce.get() || []).slice().each(function(editor){
                if (editor && editor.id && editor.id.match(myRegex) != null)
                {
                    try {
                        editor.save();
                        editor.remove();
                    } catch (e) {
                        console.log(e);
                    }
                }
            });
        },

        reinitTinyMCE: function(el, row, isParent)
        {
            var parent = (isParent === true) ? row : row.getParent('.multicolumnwizard');

            // skip if tinymce is not available or the wizard has no rte field
            if(typeof tinymce === 'undefined' || !parent || parent.getElements('.tinymce').length == 0)
            {
                return;
            }

            // TinyMCE 6 has no usable "add editor by id" command (mceAddEditor needs the full options).
            // Re-run each RTE init script instead: updateRowAttributes() has already rewritten the element
            // id inside it, so executing it recreates the editor with its full configuration and the
            // content that killAllTinyMCE() saved into the textarea. The wizard's own init script (which
            // creates a MultiColumnWizard instance) is skipped via the tinymce.init marker, and the
            // tinymce loader line (window.tinymce || document.write(...)) short-circuits because tinymce
            // is already loaded.
            parent.getElements('script').each(function(script){
                var code = script.get('html');
                if (code && code.indexOf('tinymce.init') !== -1)
                {
                    Browser.exec(code);
                }
            });
        },

        reinitStylect: function()
        {

            if(window.Stylect)
            {
                if (versionCompare('3.2.3') >= 0) {
                    $$('.styled_select').each(function(item, index){
                        item.dispose();
                    });
                    Stylect.convertSelects();
                }
            }
        }
    });

/**
 * Extend the MultiColumnWizard with some static functions
 */
Object.append(MultiColumnWizard,
    {
        // static callbacks (use e.g. MultiColumnWizard.addOperationCallback() to register a callback that is for EVERY MCW on the page)
        operationLoadCallbacks: {},
        operationClickCallbacks: {},
        operationUpdateCallbacks: {},

        /**
         * Add a load callback for all the MCW's
         * @param string the key e.g. 'copy' - your button has to have the matching data-operations="" attribute (<a href="jsfallbackurl" data-operations="copy">...</a>)
         * @param function callback
         */
        addOperationLoadCallback: function(key, func)
        {
            if (!MultiColumnWizard.operationLoadCallbacks[key])
            {
                MultiColumnWizard.operationLoadCallbacks[key] = [];
            }

            MultiColumnWizard.operationLoadCallbacks[key].include(func);
        },

        /**
         * Add a dupate callback for all the MCW's
         * @param string the key e.g. 'copy' - your button has to have the matching data-operations="" attribute (<a href="jsfallbackurl" data-operations="copy">...</a>)
         * @param function callback
         */
        addOperationUpdateCallback: function(key, func)
        {
            if (!MultiColumnWizard.operationUpdateCallbacks[key])
            {
                MultiColumnWizard.operationUpdateCallbacks[key] = [];
            }

            MultiColumnWizard.operationUpdateCallbacks[key].include(func);
        },


        /**
         * Add a click callback for all the MCW's
         * @param string the key e.g. 'copy' - your button has to have the matching data-operations="" attribute (<a href="jsfallbackurl" data-operations="copy">...</a>)
         * @param function callback
         */
        addOperationClickCallback: function(key, func)
        {
            if (!MultiColumnWizard.operationClickCallbacks[key])
            {
                MultiColumnWizard.operationClickCallbacks[key] = [];
            }

            MultiColumnWizard.operationClickCallbacks[key].include(func);
        },

        /**
         * Call PHP/Contao/Widget to get a new row.
         *
         * @param el The button.
         *
         * @param row The tr of the table.
         *
         * @return void
         */
        insertNewElement: function (el, row) {
            if(this.asyncBlock === true){
                return;
            }
            this.asyncBlock = true;
            el.addClass('rotate');

            var parentMcw = $(row).getParent('.tl_modulewizard.multicolumnwizard');
            var fieldName = $(parentMcw).getAttribute('data-name');
            var rows = $(parentMcw).getElements('tr');
            var maxRowId = 0;
            for (var i = 0; i < rows.length; i++)
            {
                maxRowId = Math.max(maxRowId, ($(rows[i]).getAttribute('data-rowid')));
            }

            var self = this;
            new Request.Contao({
                evalScripts: false,
                onSuccess:   function (txt, json) {
                   el.removeClass('rotate');
                   // Text to html.
                   var newEl = new Element('div', {
                       html: json.content
                   });
                   // Inject it on the right place.
                   var newRow = $(newEl).getElement('tr')
                   newRow.inject(row, 'after');
                   // Execute the JS from widgets.
                   json.javascript && Browser.exec(json.javascript);
                   // Execute the inline scripts contained in the new row itself (e.g. tinyMCE/RTE
                   // init, pickers, nested wizard init). The request runs with evalScripts:false,
                   // so the scripts embedded in json.content are not executed automatically. Without
                   // this the RTE of a freshly added row (also inside nested wizards) stays
                   // uninitialised until the whole mask is saved and rebuilt. Scoped to newRow, so
                   // editors of existing rows are not touched (no double init).
                   newRow.getElements('script').each(function (script) {
                       Browser.exec(script.get('html'));
                   });
                   // Rebind the events.
                   newRow.getElements('td.operations a').each(function (operation) {
                       var key = operation.get('data-operations');

                       // call static load callbacks
                       if (MultiColumnWizard.operationLoadCallbacks[key])
                       {
                           MultiColumnWizard.operationLoadCallbacks[key].each(function (callback) {
                               callback.pass([operation, el], self)();
                           });
                       }

                       // call instance load callbacks
                       if (self.operationLoadCallbacks[key])
                       {
                           self.operationLoadCallbacks[key].each(function (callback) {
                               callback.pass([operation, el], self)();
                           });
                       }
                   });
                   // Add init chosen if tl_chosen defined in select for the new row.
                   if (Elements.chosen !== undefined) {
                       newRow.getElements('select.tl_chosen').chosen();
                   }
                   self.updateOperations();
                   self.asyncBlock = false;
                },
                onFailure: function(xhr){
                    el.removeClass('rotate');
                    self.asyncBlock = false;
                }
            }).post({
                "action":        "mcwCreateNewRow",
                "name":          fieldName,
                "maxRowId":      maxRowId,
                "REQUEST_TOKEN": Contao.request_token
            });
        },

        /**
         * Operation "new" - update
         * @param Element the icon element
         * @param Element the row
         */
        newUpdate: function(el, row)
        {
            var rowCount = row.getSiblings().length + 1;

            // remove the copy possibility if we have already reached maxCount
            if (this.options.maxCount > 0 && rowCount >= this.options.maxCount)
            {
                el.setStyle('display', 'none');
            }else{
                el.setStyle('display', 'inline');
            }
        },


        /**
         * Operation "new" - click
         * @param Element the icon element
         * @param Element the row
         */
        newClick: function(el, row)
        {
            this.killAllTinyMCE(el, row);

            var rowCount = row.getSiblings().length + 1;

            // check maxCount for an inject
            if (this.options.maxCount == 0 || (this.options.maxCount > 0 && rowCount < this.options.maxCount))
            {
                var copy = row.clone(true,true);

                // clear all elements
                copy.getElements('input,select,textarea').each(function(el){
                    MultiColumnWizard.clearElementValue(el);
                });

                // get the current level of the row
                level = row.getAllPrevious().length;

                // update the row attributes
                copy = this.updateRowAttributes(++level, copy);
                copy.inject(row, 'after');

                // update tooltips
                copy.getElements('a[data-operations]').each(function(el) {
                    $$(el).set('title', $$(el).getElement('img').get('alt'));
                    new Tips.Contao($$(el).filter(function(i) {
                        return i.title != '';
                    }), {
                        offset: {x:0, y:26}
                    });
                });

                // exec script
                if (copy.getElements('script').length > 0)
                {
                    copy.getElements('script').each(function(script){
                        Browser.exec(script.get('html'));
                    });
                }

                // update the row attribute of the following rows
                var that = this;
                copy.getAllNext().each(function(row){
                    that.updateRowAttributes(++level, row);
                });
            }

            this.reinitTinyMCE(el, row, false);
            this.reinitStylect();
        },

        /**
         * Operation "copy" - update
         * @param Element the icon element
         * @param Element the row
         */
        copyUpdate: function(el, row)
        {
            var rowCount = row.getSiblings().length + 1;

            // remove the copy possibility if we have already reached maxCount
            if (this.options.maxCount > 0 && rowCount >= this.options.maxCount)
            {
                el.setStyle('display', 'none');
            }else{
                el.setStyle('display', 'inline');
            }
        },

        /**
         * Operation "copy" - click
         * @param Element the icon element
         * @param Element the row
         */
        copyClick: function(el, row)
        {
            this.killAllTinyMCE(el, row);

            var rowCount = row.getSiblings().length + 1;

            // check maxCount for an inject
            if (this.options.maxCount == 0 || (this.options.maxCount > 0 && rowCount < this.options.maxCount))
            {
                var copy = row.clone(true,true);

                // get the current level of the row
                level = row.getAllPrevious().length;

                // update the row attributes
                copy = this.updateRowAttributes(++level, copy);
                copy.inject(row, 'after');

                // update tooltips
                copy.getElements('a[data-operations]').each(function(el) {
                    $$(el).set('title', $$(el).getElement('img').get('alt'));
                    new Tips.Contao($$(el).filter(function(i) {
                        return i.title != '';
                    }), {
                        offset: {x:0, y:26}
                    });
                });

                // exec script
                if (copy.getElements('script').length > 0)
                {
                    copy.getElements('script').each(function(script){
                        Browser.exec(script.get('html'));
                    });
                }

                // update the row attribute of the following rows
                var that = this;
                copy.getAllNext().each(function(row){
                    that.updateRowAttributes(++level, row);
                });
            }

            this.reinitTinyMCE(el, row, false);
            this.reinitStylect();
        },

        /**
         * Operation "delete" - load
         * @param Element the icon element
         * @param Element the row
         */
        deleteUpdate: function(el, row)
        {
            var rowCount = row.getSiblings().length + 1;

            // remove the delete possibility if necessary
            if (this.options.minCount > 0 && rowCount <= this.options.minCount)
            {
                el.setStyle('display', 'none');
            }
            else
            {
                el.setStyle('display', 'inline');
            }
        },

        /**
         * Operation "delete" - click
         * @param Element the icon element
         * @param Element the row
         */
        deleteClick: function(el, row)
        {
            var parent = row.getParent('.multicolumnwizard');

            if (row.getSiblings().length > 0) {
                //get all following rows
                var rows = row.getAllNext();
                //extract the current level
                level = row.getAllPrevious().length;

                //destroy current row
                row.dispose();
                row.destroy.delay(10, row); // destroy delayed, to ensure all remaining event handlers are called
            }
        },

        /**
         * Operation "up" - click
         * @param Element the icon element
         * @param Element the row
         */
        upClick: function(el, row)
        {
            this.killAllTinyMCE(el, row);

            var previous = row.getPrevious();
            if (previous)
            {
                // update the attributes so the order remains as desired
                // we have to set it to a value that is not in the DOM first, otherwise the values will get lost!!
                var previousPosition = previous.getAllPrevious().length;

                // this is the dummy setting (guess no one will have more than 99999 entries ;-))
                previous = this.updateRowAttributes(99999, previous);

                // now set the correct values again
                row = this.updateRowAttributes(previousPosition, row);
                previous = this.updateRowAttributes(previousPosition+1, previous);

                row.inject(previous, 'before');
            }

            this.reinitTinyMCE(el, row, false);
        },

        /**
         * Operation "down" - click
         * @param Element the icon element
         * @param Element the row
         */
        downClick: function(el, row)
        {
            this.killAllTinyMCE(el, row);

            var next = row.getNext();
            if (next)
            {
                // update the attributes so the order remains as desired
                // we have to set it to a value that is not in the DOM first, otherwise the values will get lost!!
                var rowPosition = row.getAllPrevious().length;

                // this is the dummy setting (guess no one will have more than 99999 entries ;-))
                row = this.updateRowAttributes(99999, row);

                // now set the correct values again
                next = this.updateRowAttributes(rowPosition, next);
                row = this.updateRowAttributes(rowPosition+1, row);

                row.inject(next, 'after');
            }

            this.reinitTinyMCE(el, row, false);
        },

        /**
         * @param Element the element which should be cleared
         */
        clearElementValue: function(el)
        {
            if (el.get('type') == 'checkbox' || el.get('type') == 'radio')
            {
                el.checked = false;
            }
            else
            {
                el.set('value', '');
            }
        }
    });


/**
 * Register default callbacks
 */
// MultiColumnWizard.addOperationClickCallback('new', MultiColumnWizard.newClick);
// MultiColumnWizard.addOperationUpdateCallback('copy', MultiColumnWizard.copyUpdate);
// MultiColumnWizard.addOperationClickCallback('copy', MultiColumnWizard.copyClick);

MultiColumnWizard.addOperationUpdateCallback('new', MultiColumnWizard.newUpdate);
MultiColumnWizard.addOperationClickCallback('new', MultiColumnWizard.insertNewElement);
MultiColumnWizard.addOperationUpdateCallback('delete', MultiColumnWizard.deleteUpdate);
MultiColumnWizard.addOperationClickCallback('delete', MultiColumnWizard.deleteClick);
MultiColumnWizard.addOperationClickCallback('up', MultiColumnWizard.upClick);
MultiColumnWizard.addOperationClickCallback('down', MultiColumnWizard.downClick);

/**
 * Patch Contao Core to support file & page tree
 */
(function(Backend) {
    if(!Backend) return;
    Backend.openModalSelectorOriginal = Backend.openModalSelector;
    Backend.openModalSelector = function(options) {
        Backend.openModalSelectorOriginal(options);

        var frm = null;
        var tProtect = 60;
        var id = new URI(options.url).getData('field')+'_parent';
        var timer = setInterval(function() {
            tProtect -= 1;
            var frms = window.frames;
            for (var i=0; i<frms.length; i++) {
                if (frms[i].name == 'simple-modal-iframe') {
                    frm = frms[i];
                    break;
                }
            }

            if (frm && frm.document.getElementById(id)) {
                frm.document.getElementById(id).set('id', options.id+'_parent');
                clearInterval(timer);
                return;
            }

            // Try for 30 seconds
            if (tProtect <= 0) {
                clearInterval(timer);
            }
        }, 500);
    };
})(window.Backend);


/**
 * Compare Versions
 *
 * Example:
 * versionCompare('3.1', '3.2') => -1
 * versionCompare('3.1', '3.1') =>  0
 * versionCompare('3.2', '3.1') =>  1
 *
 * @function
 *
 * @return {Number}
 */
versionCompare = function(toCompare) {

    // Get Version-Class and convert it to a valid Version-String
    var version = $('top').get('class').match(/version_[^\s]*/);
    version = version[0];
    version = version.replace('version_', '');
    version = version.split('-').join('.');

    if (typeof version !== 'string') {
        return false;
    }

    if (typeof toCompare + typeof version != 'stringstring')
        return false;

    var a = toCompare.split('.')
        ,   b = version.split('.')
        ,   i = 0, len = Math.max(a.length, b.length);

    for (; i < len; i++) {
        if ((a[i] && !b[i] && parseInt(a[i]) > 0) || (parseInt(a[i]) > parseInt(b[i]))) {
            return 1;
        } else if ((b[i] && !a[i] && parseInt(b[i]) > 0) || (parseInt(a[i]) < parseInt(b[i]))) {
            return -1;
        }
    }

    return 0;
};
