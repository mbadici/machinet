/**
 * Client scripts for the Kolab Notes plugin
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2014-2015, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

function rcube_kolab_notes_ui(settings)
{
    /*  private vars  */
    var ui_loading = false;
    var saving_lock;
    var search_query;
    var folder_drop_target;
    var notebookslist;
    var noteslist;
    var notesdata = {};
    var tagsfilter = [];
    var tags = [];
    var search_request;
    var search_query;
    var tag_draghelper;
    var render_no_focus;
    var me = this;

    /*  public members  */
    this.selected_list = null;
    this.selected_note = null;
    this.notebooks = rcmail.env.kolab_notebooks || {};

    /**
     * initialize the notes UI
     */
    function init()
    {
        // register button commands
        rcmail.register_command('createnote', function(){
            warn_unsaved_changes(function(){ edit_note(null, 'new'); })
        }, false);
        rcmail.register_command('list-create', function(){ list_edit_dialog(null); }, true);
        rcmail.register_command('list-edit', function(){ list_edit_dialog(me.selected_list); }, false);
        rcmail.register_command('list-delete', function(){ list_delete(me.selected_list); }, false);
        rcmail.register_command('list-remove', function(){ list_remove(me.selected_list); }, false);
        rcmail.register_command('list-sort', list_set_sort, true);
        rcmail.register_command('save', save_note, true);
        rcmail.register_command('delete', delete_notes, false);
        rcmail.register_command('search', quicksearch, true);
        rcmail.register_command('reset-search', reset_search, true);
        rcmail.register_command('sendnote', send_note, false);
        rcmail.register_command('print', print_note, false);
        rcmail.register_command('history', show_history_dialog, false);

        // register server callbacks
        rcmail.addEventListener('plugin.data_ready', data_ready);
        rcmail.addEventListener('plugin.render_note', render_note);
        rcmail.addEventListener('plugin.update_note', update_note);
        rcmail.addEventListener('plugin.update_list', list_update);
        rcmail.addEventListener('plugin.destroy_list', list_destroy);
        rcmail.addEventListener('plugin.unlock_saving', function(){
            if (saving_lock) {
                rcmail.set_busy(false, null, saving_lock);
            }
            if (rcmail.gui_objects.noteseditform) {
                rcmail.lock_form(rcmail.gui_objects.noteseditform, false);
            }
        });

        rcmail.addEventListener('plugin.close_history_dialog', close_history_dialog);
        rcmail.addEventListener('plugin.note_render_changelog', render_changelog);
        rcmail.addEventListener('plugin.note_show_revision', render_revision);
        rcmail.addEventListener('plugin.note_show_diff', show_diff);

        // initialize folder selectors
        if (settings.selected_list && !me.notebooks[settings.selected_list]) {
            settings.selected_list = null;
        }
        for (var id in me.notebooks) {
            if (me.notebooks[id].editable && !settings.selected_list) {
                settings.selected_list = id;
            }
        }

        var widget_class = window.kolab_folderlist || rcube_treelist_widget;
        notebookslist = new widget_class(rcmail.gui_objects.notebooks, {
          id_prefix: 'rcmliknb',
          save_state: true,
          selectable: true,
          keyboard: false,
          searchbox: '#notebooksearch',
          search_action: 'notes/list',
          search_sources: [ 'folders', 'users' ],
          search_title: rcmail.gettext('listsearchresults','kolab_notes'),
          check_droptarget: function(node) {
              var list = me.notebooks[node.id];
              return !node.virtual && list.editable && node.id != me.selected_list;
          }
        });
        notebookslist.addEventListener('select', function(node) {
            var id = node.id;
            if (me.notebooks[id] && id != me.selected_list) {
                warn_unsaved_changes(function(){
                    rcmail.enable_command('createnote', has_permission(me.notebooks[id], 'i'));
                    rcmail.enable_command('list-edit', has_permission(me.notebooks[id], 'a'));
                    rcmail.enable_command('list-delete', has_permission(me.notebooks[id], 'xa'));
                    rcmail.enable_command('list-remove', !me.notebooks[id]['default']);
                    fetch_notes(id);  // sets me.selected_list
                },
                function(){
                    // restore previous selection
                    notebookslist.select(me.selected_list);
                });
            }

            // unfocus clicked list item
            $(notebookslist.get_item(id)).find('a.listname').first().blur();
        });
        notebookslist.addEventListener('subscribe', function(p) {
            var list;
            if ((list = me.notebooks[p.id])) {
                list.subscribed = p.subscribed || false;
                rcmail.http_post('list', { _do:'subscribe', _list:{ id:p.id, permanent:list.subscribed?1:0 } });
            }
        });
        notebookslist.addEventListener('remove', function(p) {
            if (me.notebooks[p.id] && !me.notebooks[p.id]['default']) {
                list_remove(p.id);
            }
        });
        notebookslist.addEventListener('insert-item', function(p) {
            var list = p.data;
            if (list && list.id && !list.virtual) {
                me.notebooks[list.id] = list;
                if (list.subscribed)
                    rcmail.http_post('list', { _do:'subscribe', _list:{ id:p.id, permanent:1 } });
            }
        });
        notebookslist.addEventListener('click-item', function(p) {
            // avoid link execution
            return false;
        });
        notebookslist.addEventListener('search-complete', function(data) {
            if (data.length)
                rcmail.display_message(rcmail.gettext('nrnotebooksfound','kolab_notes').replace('$nr', data.length), 'voice');
            else
                rcmail.display_message(rcmail.gettext('nonotebooksfound','kolab_notes'), 'info');
        });

        $(rcmail.gui_objects.notebooks).on('click', 'div.folder > a.listname', function(e) {
            var id = String($(this).closest('li').attr('id')).replace(/^rcmliknb/, '');
            notebookslist.select(id);
            e.preventDefault();
            return false;
        });

        // register dbl-click handler to open list edit dialog
        $(rcmail.gui_objects.notebooks).on('dblclick', 'li:not(.virtual) a', function(e) {
            var id = String($(this).closest('li').attr('id')).replace(/^rcmliknb/, '');
            if (me.notebooks[id] && has_permission(me.notebooks[id], 'a')) {
                list_edit_dialog(id);
            }

            // clear text selection (from dbl-clicking)
            var sel = window.getSelection ? window.getSelection() : document.selection;
            if (sel && sel.removeAllRanges) {
                sel.removeAllRanges();
            }
            else if (sel && sel.empty) {
                sel.empty();
            }

            e.preventDefault();
            return false;
        });

        // initialize notes list widget
        if (rcmail.gui_objects.noteslist) {
            noteslist = new rcube_list_widget(rcmail.gui_objects.noteslist,
                { multiselect:true, draggable:true, keyboard:true });
            noteslist.addEventListener('select', function(list) {
                render_no_focus = rcube_event._last_keyboard_event && $(list.list).has(rcube_event._last_keyboard_event.target);
                var selection_changed = list.selection.length != 1 || !me.selected_note || list.selection[0] != me.selected_note.id;
                selection_changed && warn_unsaved_changes(function(){
                    var note;
                    if (noteslist.selection.length == 1 && (note = notesdata[noteslist.selection[0]])) {
                        edit_note(note.uid, 'edit');
                    }
                    else {
                        reset_view();
                    }
                },
                function(){
                    // TODO: previous restore selection
                    list.select(me.selected_note.id);
                });

                rcmail.enable_command('delete', me.notebooks[me.selected_list] && has_permission(me.notebooks[me.selected_list], 'td') && list.selection.length > 0);
                rcmail.enable_command('sendnote', list.selection.length > 0);
                rcmail.enable_command('print', 'history', list.selection.length == 1);
            })
            .addEventListener('dragstart', function(e) {
                folder_drop_target = null;
                notebookslist.drag_start();
            })
            .addEventListener('dragmove', function(e) {
                folder_drop_target = notebookslist.intersects(rcube_event.get_mouse_pos(e), true);
            })
            .addEventListener('dragend', function(e) {
                notebookslist.drag_end();

                // move dragged notes to this folder
                if (folder_drop_target) {
                    noteslist.draglayer.hide();

                    // check unsaved changes first
                    var new_folder_id = folder_drop_target;
                    warn_unsaved_changes(
                        // ok
                        function() {
                            move_notes(new_folder_id);
                            reset_view();
                            noteslist.clear_selection();
                        },
                        // nok
                        undefined,
                        // beforesave
                        function(savedata) {
                            savedata.list = new_folder_id;

                            // remove from list and thus avoid being moved (again)
                            var id = me.selected_note.id;
                            noteslist.remove_row(id);
                            delete notesdata[id];
                        }
                    );
                }
                folder_drop_target = null;
            })
            .init().focus();
        }

        if (settings.sort_col) {
            $('#notessortmenu a.by-' + settings.sort_col).addClass('selected');
        }

        // click-handler on tags list
        $(rcmail.gui_objects.notestagslist).on('click', 'li', function(e){
            var item = e.target.nodeName == 'LI' ? $(e.target) : $(e.target).closest('li'),
                tag = item.data('value');

            if (!tag)
                return false;

            // reset selection on regular clicks
            var index = $.inArray(tag, tagsfilter);
            var shift = e.shiftKey || e.ctrlKey || e.metaKey;

            if (!shift) {
                if (tagsfilter.length > 1)
                    index = -1;

                $('li', rcmail.gui_objects.notestagslist).removeClass('selected').attr('aria-checked', 'false');
                tagsfilter = [];
            }

            // add tag to filter
            if (index < 0) {
                item.addClass('selected').attr('aria-checked', 'true');
                tagsfilter.push(tag);
            }
            else if (shift) {
                item.removeClass('selected').attr('aria-checked', 'false');
                var a = tagsfilter.slice(0,index);
                tagsfilter = a.concat(tagsfilter.slice(index+1));
            }

            filter_notes();

            // clear text selection in IE after shift+click
            if (shift && document.selection)
              document.selection.empty();

            e.preventDefault();
            return false;
        })
        .on('keypress', 'li', function(e) {
            if (e.keyCode == 13) {
                $(this).trigger('click', { pointerType:'keyboard' });
            }
        })
        .mousedown(function(e){
            // disable content selection with the mouse
            e.preventDefault();
            return false;
        });

        init_editor();

        if (settings.selected_list) {
            notebookslist.select(settings.selected_list)
        }
    }
    this.init = init;

    /**
     *
     */
    function init_dialog()
    {
        rcmail.register_command('save', save_note, true);
        rcmail.addEventListener('plugin.render_note', render_note);
        rcmail.addEventListener('plugin.update_note', function(data){
            data.id = rcmail.html_identifier_encode(data.uid);
            notesdata[data.id] = data;
            render_note(data);
        });
        rcmail.addEventListener('plugin.unlock_saving', function(){
            if (saving_lock) {
                rcmail.set_busy(false, null, saving_lock);
            }
            if (rcmail.gui_objects.noteseditform) {
                rcmail.lock_form(rcmail.gui_objects.noteseditform, false);
            }
        });

        var id;
        for (id in me.notebooks) {
            if (me.notebooks[id].editable) {
                me.selected_list = id;
                break;
            }
        }

        init_editor();
        if (settings.selected_uid) {
            me.selected_list = settings.selected_list;
            edit_note(settings.selected_uid);
        }
        else {
            setTimeout(function(){
                me.selected_note = $.extend({
                    list: me.selected_list,
                    uid: null,
                    title: rcmail.gettext('newnote','kolab_notes'),
                    description: '',
                    tags: [],
                    created: rcmail.gettext('now', 'kolab_notes'),
                    changed: rcmail.gettext('now', 'kolab_notes')
                }, rcmail.env.kolab_notes_template || {});
                render_note(me.selected_note);
            }, 100);
        }
    }
    this.init_dialog = init_dialog;

    /**
     * initialize tinyMCE editor
     */
    function init_editor()
    {
        var editor_conf = {
            selector: '#notecontent',
            theme: 'modern',
            language: settings.editor.lang,
            content_css: rcmail.assets_path('program/js/tinymce/roundcube/content.css?v2'),
            plugins: 'autolink charmap code link paste tabfocus searchreplace table textcolor',
            toolbar: 'bold italic underline | alignleft aligncenter alignright alignjustify '
                + '| bullist numlist outdent indent blockquote | forecolor backcolor fontselect fontsizeselect '
                + '| link unlink table charmap | code searchreplace undo redo',
            menubar: false,
            statusbar: false,
            toolbar_items_size: 'small',
            extended_valid_elements: 'font[face|size|color|style],span[id|class|align|style]',
            relative_urls: false,
            remove_script_host: false,
            convert_urls: false,
            image_description: false,
            paste_webkit_style: "color font-size font-family",
            paste_data_images: true,
            //spellchecker_rpc_url: '../../../../../?_task=utils&_action=spell_html&_remote=1',
            //spellchecker_language: rcmail.env.spell_lang,
            accessibility_focus: false,
            tabfocus_elements: [':prev','btn-save-note'],
            setup: function(ed) {
                // make links open on shift-click
                ed.on('click', function(e) {
                    var link = $(e.target).closest('a');
                    if (link.length && e.shiftKey) {
                        if (!bw.mz) window.open(link.get(0).href, '_blank');
                        return false;
                    }
                });
            }
        };

        // support external configuration settings e.g. from skin
        if (window.rcmail_editor_settings)
            $.extend(editor_conf, window.rcmail_editor_settings);

        tinyMCE.init(editor_conf);

        // register click handler for message links
        $(rcmail.gui_objects.notesattachmentslist).on('click', 'li a.messagelink', function(){
            rcmail.open_window(this.href);
            return false;
        });
    }

    /**
     * Quote HTML entities
     */
    function Q(str)
    {
        return String(str).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /**
     * Trim whitespace off the given string
     */
    function trim(str)
    {
        return String(str).replace(/\s+$/, '').replace(/^\s+/, '');
    }

    /**
     * Check permissions on the given list object
     */
    function has_permission(list, perm)
    {
        // multiple chars means "either of"
        if (String(perm).length > 1) {
            for (var i=0; i < perm.length; i++) {
                if (has_permission(list, perm[i])) {
                    return true;
                }
            }
        }

        if (list.rights && String(list.rights).indexOf(perm) >= 0) {
            return true;
        }

        return (perm == 'i' && list.editable);
    }

    /**
     * 
     */
    function edit_note(uid, action)
    {
        if (!uid) {
            me.selected_note = null;
            if (noteslist)
                noteslist.clear_selection();

            me.selected_note = {
                list: me.selected_list,
                uid: null,
                title: rcmail.gettext('newnote','kolab_notes'),
                description: '',
                tags: [],
                created: rcmail.gettext('now', 'kolab_notes'),
                changed: rcmail.gettext('now', 'kolab_notes')
            }
            render_note(me.selected_note);
            rcmail.enable_command('print', true);
        }
        else {
            ui_loading = rcmail.set_busy(true, 'loading');
            rcmail.http_request('get', { _list:me.selected_list, _id:uid }, true);
        }
    }

    /**
     * 
     */
    function list_edit_dialog(id)
    {
        if (!rcmail.gui_containers.notebookeditform) {
            return false;
        }

        // close show dialog first
        var $dialog = rcmail.gui_containers.notebookeditform;
        if ($dialog.is(':ui-dialog')) {
            $dialog.dialog('close');
        }

        var list = me.notebooks[id] || { name:'', editable:true, rights: 'riwta' };
        var form, name;

        $dialog.html(rcmail.get_label('loading'));
        $.ajax({
            type: 'GET',
            dataType: 'html',
            url: rcmail.url('list'),
            data: { _do: (list.id ? 'form-edit' : 'form-new'), _list: { id: list.id } },
            success: function(data) {
                $dialog.html(data);
                rcmail.triggerEvent('kolab_notes_editform_load', list);

                // resize and reposition dialog window
                form = $('#noteslistpropform');
                var win = $(window), w = win.width(), h = win.height();
                $dialog.dialog('option', { height: Math.min(h-20, form.height()+130), width: Math.min(w-20, form.width()+50) });

                name = $('#noteslist-name').prop('disabled', !has_permission(list, 'a')).val(list.editname || list.name);
                name.select();
            }
        });

        // dialog buttons
        var buttons = {};
        buttons[rcmail.gettext('save')] = function() {
            // form is not loaded
            if (!form || !form.length)
              return;

            // do some input validation
            if (!name.val() || name.val().length < 2) {
              alert(rcmail.gettext('invalidlistproperties', 'kolab_notes'));
              name.select();
              return;
            }

            // post data to server
            var data = form.serializeJSON();
            if (list.id)
              data.id = list.id;

            saving_lock = rcmail.set_busy(true, 'kolab_notes.savingdata');
            rcmail.http_post('list', { _do: (list.id ? 'edit' : 'new'), _list: data });
            $dialog.dialog('close');
        };

        buttons[rcmail.gettext('cancel')] = function() {
            $dialog.dialog('close');
        };

        // open jquery UI dialog
        $dialog.dialog({
            modal: true,
            resizable: true,
            closeOnEscape: false,
            title: rcmail.gettext((list.id ? 'editlist' : 'newnotebook'), 'kolab_notes'),
            open: function() {
                $dialog.parent().find('.ui-dialog-buttonset .ui-button').first().addClass('mainaction');
            },
            close: function() {
                $dialog.html('').dialog('destroy').hide();
            },
            buttons: buttons,
            minWidth: 480,
            width: 640
        }).show();

    }

    /**
     * Callback from server after changing list properties
     */
    function list_update(prop)
    {
        if (prop._reload) {
            rcmail.redirect(rcmail.url('', { _list: (prop.newid || prop.id) }));
        }
        else if (prop.newid && prop.newid != prop.id) {
            var book = $.extend({}, me.notebooks[prop.id]);
            book.id = prop.newid;
            book.name = prop.name;
            book.listname = prop.listname;
            book.editname = prop.editname || prop.name;

            me.notebooks[prop.newid] = book;
            delete me.notebooks[prop.id];

            // update treelist item
            var li = $(notebookslist.get_item(prop.id));
            $('.listname', li).html(prop.listname);
            notebookslist.update(prop.id, { id:book.id, html:li.html() });

            // link all loaded note records to the new list id
            if (me.selected_list == prop.id) {
                me.selected_list = prop.newid;
                for (var k in notesdata) {
                    if (notesdata[k].list == prop.id) {
                        notesdata[k].list = book.id;
                    }
                }
                notebookslist.select(prop.newid);
            }
        }
    }

    /**
     * 
     */
    function list_delete(id)
    {
        var list = me.notebooks[id];
        if (list && confirm(rcmail.gettext('deletenotebookconfirm', 'kolab_notes'))) {
            saving_lock = rcmail.set_busy(true, 'kolab_notes.savingdata');
            rcmail.http_post('list', { _do: 'delete', _list: { id: list.id } });
        }
    }

    /**
     * 
     */
    function list_remove(id)
    {
        if (me.notebooks[id]) {
            list_destroy(me.notebooks[id]);
            rcmail.http_post('list', { _do:'subscribe', _list:{ id:id, permanent:0, recursive:1 } });
        }
    }

    /**
     * Callback from server on list delete command
     */
    function list_destroy(prop)
    {
        if (!me.notebooks[prop.id]) {
            return;
        }

        notebookslist.remove(prop.id);
        delete me.notebooks[prop.id];

        if (me.selected_list == prop.id) {
            for (id in me.notebooks) {
                if (me.notebooks[id]) {
                    notebookslist.select(id);
                    break;
                }
            }
        }
    }

    /**
     * Change notes list sort order
     */
    function list_set_sort(col)
    {
        if (settings.sort_col != col) {
            settings.sort_col = col;
            $('#notessortmenu a').removeClass('selected').filter('.by-' + col).addClass('selected');
            rcmail.save_pref({ name: 'kolab_notes_sort_col', value: col });

            // re-sort table in DOM
            $(noteslist.tbody).children().sortElements(function(la, lb){
                var a_id = String(la.id).replace(/^rcmrow/, ''),
                    b_id = String(lb.id).replace(/^rcmrow/, ''),
                    a = notesdata[a_id],
                    b = notesdata[b_id];

                if (!a || !b) {
                    return 0;
                }
                else if (settings.sort_col == 'title') {
                    return String(a.title).toLowerCase() > String(b.title).toLowerCase() ? 1 : -1;
                }
                else {
                    return b.changed_ - a.changed_;
                }
            });
        }
    }

    /**
     * Execute search
     */
    function quicksearch()
    {
        var q;
        if (rcmail.gui_objects.qsearchbox && (q = rcmail.gui_objects.qsearchbox.value)) {
            var id = 'search-'+q;

            // ignore if query didn't change
            if (search_request == id)
                return;

            warn_unsaved_changes(function(){
                search_request = id;
                search_query = q;

                fetch_notes();
            },
            function(){
                reset_search();
            });
        }
        else {  // empty search input equals reset
            reset_search();
        }
    }

    /**
     * Reset search and get back to normal listing
     */
    function reset_search()
    {
        $(rcmail.gui_objects.qsearchbox).val('');

        if (search_request) {
            search_request = search_query = null;
            fetch_notes();
        }
    }

    /**
     * 
     */
    function fetch_notes(id)
    {
        if (rcmail.busy)
            return;

        if (id && id != me.selected_list) {
            me.selected_list = id;
        }

        ui_loading = rcmail.set_busy(true, 'loading');
        rcmail.http_request('fetch', { _list:me.selected_list, _q:search_query }, true);

        reset_view();
        noteslist.clear(true);
        notesdata = {};
        tagsfilter = [];
        update_state();
    }

    function filter_notes()
    {
        // tagsfilter
        var note, tr, match;
        for (var id in noteslist.rows) {
            tr = noteslist.rows[id].obj;
            note = notesdata[id];
            match = note.tags && note.tags.length;
            for (var i=0; match && note && i < tagsfilter.length; i++) {
                if ($.inArray(tagsfilter[i], note.tags) < 0)
                    match = false;
            }

            if (match || !tagsfilter.length) {
                $(tr).show();
            }
            else {
                $(tr).hide();
            }

            if (me.selected_note && me.selected_note.uid == note.uid && !match) {
                warn_unsaved_changes(function(){
                    me.selected_note = null;
                    noteslist.clear_selection();
                }, function(){
                    tagsfilter = [];
                    filter_notes();
                    update_tagcloud();
                });
            }
        }
    }

    /**
     * 
     */
    function data_ready(data)
    {
        data.data.sort(function(a,b){
            if (settings.sort_col == 'title') {
                return String(a.title).toLowerCase() > String(b.title).toLowerCase() ? 1 : -1;
            }
            else {
                return b.changed_ - a.changed_;
            }
        });

        var i, id, rec;
        for (i=0; data.data && i < data.data.length; i++) {
            rec = data.data[i];
            rec.id = rcmail.html_identifier_encode(rec.uid);
            noteslist.insert_row({
                id: 'rcmrow' + rec.id,
                cols: [
                    { className:'title', innerHTML:Q(rec.title) },
                    { className:'date',  innerHTML:Q(rec.changed || '') }
                ]
            });

            notesdata[rec.id] = rec;
        }

        render_tagslist(data.tags || [], !data.search)
        rcmail.set_busy(false, 'loading', ui_loading);

        // select the single result
        if (data.data.length == 1) {
            noteslist.select(data.data[0].id);
        }
        else if (settings.selected_uid) {
            noteslist.select(rcmail.html_identifier_encode(settings.selected_uid));
            delete settings.selected_uid;
        }
        else if (me.selected_note && notesdata[me.selected_note.id]) {
            noteslist.select(me.selected_note.id);
        }
        else if (!data.data.length) {
            rcmail.display_message(rcmail.gettext('norecordsfound','kolab_notes'), 'info');
        }
    }

    /**
     *
     */
    function render_note(data, container, temp, retry)
    {
        rcmail.set_busy(false, 'loading', ui_loading);

        if (!data) {
            rcmail.display_message(rcmail.get_label('recordnotfound', 'kolab_notes'), 'error');
            return;
        }

        if (!container) {
            container = rcmail.gui_containers['notedetailview'];
        }

        var list = me.notebooks[data.list] || me.notebooks[me.selected_list] || { rights: 'lrs', editable: false };
            content = $('#notecontent').val(data.description),
            readonly = data.readonly || !(list.editable || !data.uid && has_permission(list,'i')),
            attachmentslist = gui_object('notesattachmentslist', container).html(''),
            titlecontainer = container || rcmail.gui_objects.noteviewtitle;

        $('.notetitle', titlecontainer).val(data.title).prop('disabled', readonly).show();
        $('.dates .notecreated', titlecontainer).html(Q(data.created || ''));
        $('.dates .notechanged', titlecontainer).html(Q(data.changed || ''));
        if (data.created || data.changed) {
            $('.dates', titlecontainer).show();
        }

        // tag-edit line
        var tagline = $('.tagline', titlecontainer).empty()[readonly?'addClass':'removeClass']('disabled').show();
        $.each(typeof data.tags == 'object' && data.tags.length ? data.tags : [''], function(i,val) {
            $('<input>')
                .attr('name', 'tags[]')
                .attr('tabindex', '0')
                .addClass('tag')
                .val(val)
                .appendTo(tagline);
        });

        if (!data.tags || !data.tags.length) {
            $('<span>').addClass('placeholder')
              .html(rcmail.gettext('notags', 'kolab_notes'))
              .appendTo(tagline)
              .click(function(e) { $(this).parent().find('.tagedit-list').trigger('click'); });
        }

        $('.tagline input.tag', titlecontainer).tagedit({
            animSpeed: 100,
            allowEdit: false,
            allowAdd: !readonly,
            allowDelete: !readonly,
            checkNewEntriesCaseSensitive: false,
            autocompleteOptions: { source: tags, minLength: 0, noCheck: true },
            texts: { removeLinkTitle: rcmail.gettext('removetag', 'kolab_notes') }
        });

        if (data.links) {
            $.each(data.links, function(i, link) {
                var li = $('<li>').addClass('link')
                    .addClass('message eml')
                    .append($('<a>')
                        .attr('href', link.mailurl)
                        .addClass('messagelink')
                        .text(link.subject || link.uri)
                    )
                    .appendTo(attachmentslist);

                if (!readonly && !data._from_mail) {
                    $('<a>')
                        .attr('href', '#delete')
                        .attr('title', rcmail.gettext('removelink', 'kolab_notes'))
                        .addClass('delete')
                        .html(rcmail.gettext('delete'))
                        .click({ uri:link.uri }, function(e) {
                            remove_link(this, e.data.uri);
                            return false;
                        })
                        .appendTo(li);
                }
            });
        }

        if (!readonly) {
            $('.tagedit-list', titlecontainer)
                .on('click', function(){ $('.tagline .placeholder').hide(); });
        }

        if (!data.list)
            data.list = list.id;

        data.readonly = readonly;

        if (!temp) {
            $(rcmail.gui_objects.notebooks).filter('select').val(list.id);
            me.selected_note = data;
            me.selected_note.id = rcmail.html_identifier_encode(data.uid);
            rcmail.enable_command('save', !readonly);
        }

        var html = data.html || data.description;

        // convert plain text to HTML and make URLs clickable
        if (html != '' && (!data.html || !html.match(/<(html|body)/))) {
            html = text2html(html);
        }

        var node, editor = tinyMCE.get('notecontent'), is_html = false;
        retry = retry || 0;

        // sometimes the editor instance is not ready yet (FF only)...
        if (!readonly && !editor && $('#notecontent').length && retry < 5) {
          // ... give it some more time
          setTimeout(function() {
              gui_object('noteseditform', container).show();
              render_note(data, container, temp, retry+1);
          }, 200);
          return;
        }

        if (!readonly && editor) {
            gui_object('notesdetailview', container).hide();
            gui_object('noteseditform', container).show();
            editor.setContent(''); // #4781
            editor.setContent(html);
            node = editor.getContentAreaContainer().childNodes[0];
            if (node) node.tabIndex = content.get(0).tabIndex;

            if (me.selected_note.uid) {
                if (!render_no_focus)
                    editor.getBody().focus();
            }
            else
                $('.notetitle', titlecontainer).focus().select();

            // read possibly re-formatted content back from editor for later comparison
            me.selected_note.description = editor.getContent({ format:'html' }).replace(/^\s*(<p><\/p>\n*)?/, '');
            is_html = true;
        }
        else {
            gui_object('noteseditform', container).hide();
            gui_object('notesdetailview', container).html(html).show();
        }

        render_no_focus = false;

        // notify subscribers
        rcmail.triggerEvent('kolab_notes_render', { data:data, readonly:readonly, html:is_html });
        if (rcmail.is_framed())
            parent.rcmail.triggerEvent('kolab_notes_render', { data:data, readonly:readonly, html:is_html });

        // Trigger resize (needed for proper editor resizing)
        $(window).resize();
    }

    /**
     *
     */
    function gui_object(name, container)
    {
        var elem = rcmail.gui_objects[name], selector = elem;
        if (elem && elem.className && container) {
            selector = '.' + String(elem.className).split(' ').join('.');
        }
        else if (elem && elem.id) {
            selector = '#' + elem.id;
        }

        return $(selector, container);
    }

    /**
     * Convert the given plain text to HTML contents to be displayed in editor
     */
    function text2html(str)
    {
        // simple link parser (similar to rcube_string_replacer class in PHP)
        var utf_domain = '[^?&@"\'/\\(\\)\\s\\r\\t\\n]+\\.([^\x00-\x2f\x3b-\x40\x5b-\x60\x7b-\x7f]{2,}|xn--[a-z0-9]{2,})',
            url1 = '.:;,', url2 = 'a-z0-9%=#@+?&/_~\\[\\]-',
            link_pattern = new RegExp('([hf]t+ps?://|www.)('+utf_domain+'(['+url1+']?['+url2+']+)*)?', 'ig'),
            link_replace = function(matches, p1, p2) {
                var url = (p1 == 'www.' ? 'http://' : '') + p1 + p2;
                return '<a href="' + url + '" class="x-templink">' + p1 + p2 + '</a>';
            };

        return '<pre>' + Q(str).replace(link_pattern, link_replace) + '</pre>';
    }

    /**
     *
     */
    function show_history_dialog()
    {
        var dialog, rec = me.selected_note;
        if (!rec || !rec.uid || !window.libkolab_audittrail) {
            return false;
        }

        // render dialog
        $dialog = libkolab_audittrail.object_history_dialog({
            module: 'kolab_notes',
            container: '#notehistory',
            title: rcmail.gettext('objectchangelog','kolab_notes') + ' - ' + rec.title,

            // callback function for list actions
            listfunc: function(action, rev) {
                var rec = $dialog.data('rec');
                saving_lock = rcmail.set_busy(true, 'loading', saving_lock);
                rcmail.http_post('action', { _do: action, _data: { uid: rec.uid, list:rec.list, rev: rev } }, saving_lock);
            },

            // callback function for comparing two object revisions
            comparefunc: function(rev1, rev2) {
                var rec = $dialog.data('rec');
                saving_lock = rcmail.set_busy(true, 'loading', saving_lock);
                rcmail.http_post('action', { _do: 'diff', _data: { uid: rec.uid, list: rec.list, rev1: rev1, rev2: rev2 } }, saving_lock);
            }
        });

        $dialog.data('rec', rec);

        // fetch changelog data
        saving_lock = rcmail.set_busy(true, 'loading', saving_lock);
        rcmail.http_post('action', { _do: 'changelog', _data: { uid: rec.uid, list: rec.list } }, saving_lock);
    }

    /**
     *
     */
    function render_changelog(data)
    {
        var $dialog = $('#notehistory'),
            rec = $dialog.data('rec');

        if (data === false || !data.length || !rec) {
          // display 'unavailable' message
          $('<div class="notfound-message note-dialog-message warning">' + rcmail.gettext('objectchangelognotavailable','kolab_notes') + '</div>')
              .insertBefore($dialog.find('.changelog-table').hide());
          return;
        }

        data.module = 'kolab_notes';
        libkolab_audittrail.render_changelog(data, rec, me.notebooks[rec.list]);
    }

    /**
     *
     */
    function render_revision(data)
    {
        data.readonly = true;

        // clone view and render data into a dialog
        var model = rcmail.gui_containers['notedetailview'],
            container = model.clone();

        container
            .removeAttr('id style class role')
            .find('.mce-container').remove();

        // reset custom styles
        container.children('div, form').removeAttr('id style');

        // open jquery UI dialog
        container.dialog({
            modal: false,
            resizable: true,
            closeOnEscape: true,
            title: data.title + ' @ ' + data.rev,
            close: function() {
                container.dialog('destroy').remove();
            },
            buttons: [
                {
                    text: rcmail.gettext('close'),
                    click: function() { container.dialog('close'); },
                    autofocus: true
                }
            ],
            width: model.width(),
            height: model.height(),
            minWidth: 450,
            minHeight: 400
        })
        .show();

        render_note(data, container, true);
    }

    /**
     *
     */
    function show_diff(data)
    {
        var rec = me.selected_note,
            $dialog = $('#notediff');

        $dialog.find('div.form-section, h2.note-title-new').hide().data('set', false);

        // always show title
        $('.note-title', $dialog).text(rec.title).removeClass('diff-text-old').show();

        // show each property change
        $.each(data.changes, function(i, change) {
            var prop = change.property, r2, html = false,
                row = $('div.note-' + prop, $dialog).first();

            // special case: title
            if (prop == 'title') {
                $('.note-title', $dialog).addClass('diff-text-old').text(change['old'] || '--');
                $('.note-title-new', $dialog).text(change['new'] || '--').show();
            }

            // no display container for this property
            if (!row.length) {
                return true;
            }

            if (change.diff_) {
                row.children('.diff-text-diff').html(change.diff_);
                row.children('.diff-text-old, .diff-text-new').hide();
            }
            else {
                if (!html) {
                    // escape HTML characters
                    change.old_ = Q(change.old_ || change['old'] || '--')
                    change.new_ = Q(change.new_ || change['new'] || '--')
                }
                row.children('.diff-text-old').html(change.old_ || change['old'] || '--').show();
                row.children('.diff-text-new').html(change.new_ || change['new'] || '--').show();
            }

            row.show().data('set', true);
        });

        // open jquery UI dialog
        $dialog.dialog({
            modal: false,
            resizable: true,
            closeOnEscape: true,
            title: rcmail.gettext('objectdiff','kolab_notes').replace('$rev1', data.rev1).replace('$rev2', data.rev2) + ' - ' + rec.title,
            open: function() {
                $dialog.attr('aria-hidden', 'false');
            },
            close: function() {
                $dialog.dialog('destroy').attr('aria-hidden', 'true').hide();
            },
            buttons: [
                {
                    text: rcmail.gettext('close'),
                    click: function() { $dialog.dialog('close'); },
                    autofocus: true
                }
            ],
            minWidth: 400,
            width: 480
        }).show();

        // set dialog size according to content
        libkolab_audittrail.dialog_resize($dialog.get(0), $dialog.height(), rcmail.gui_containers.notedetailview.width() - 40);
    }

    // close the event history dialog
    function close_history_dialog()
    {
        $('#notehistory, #notediff').each(function(i, elem) {
        var $dialog = $(elem);
        if ($dialog.is(':ui-dialog'))
            $dialog.dialog('close');
        });
    };

    /**
     *
     */
    function remove_link(elem, uri)
    {
        // remove the link item matching the given uri
        me.selected_note.links = $.grep(me.selected_note.links, function(link) { return link.uri != uri; });
        me.selected_note._links_removed = true

        // remove UI list item
        $(elem).hide().closest('li').addClass('deleted');
    }

    /**
     * Open a new window to print the currently selected note
     */
    function print_note()
    {
        var printwin, data, list;
        if (me.selected_note && (printwin = rcmail.open_window(settings.print_template))) {
            list = me.notebooks[me.selected_note.list] || me.notebooks[me.selected_list] || {};
            data = get_save_data();

            // for read-only notes, get_save_data() won't return the content
            if (me.selected_note.readonly || !list.editable) {
                data.description = me.selected_note.html || me.selected_note.description;
                if (!me.selected_note.html || !data.description.match(/<(html|body)/)) {
                    data.description = text2html(data.description);
                }
            }

            $(printwin).on('load', function() {
                printwin.document.title = data.title;
                $('#notetitle', printwin.document).html(Q(data.title));
                $('#notebody',  printwin.document).html(data.description);
                $('#notetags',  printwin.document).html('<span class="tag">' + data.tags.join('</span><span class="tag">') + '</span>');
                $('#notecreated', printwin.document).html(Q(me.selected_note.created));
                $('#notechanged', printwin.document).html(Q(me.selected_note.changed));
                printwin.print();
            });
        }
    }

    /**
     * Redirect to message compose screen with UIDs of notes to be appended
     */
    function send_note()
    {
        var uids = [];
        for (var rec, i=0; i < noteslist.selection.length; i++) {
            if (rec = notesdata[noteslist.selection[i]]) {
                uids.push(rec.uid);
                // TODO: check if rec.uid == me.selected_note.uid and unsaved changes
            }
        }

        if (uids.length) {
            rcmail.goto_url('mail/compose', { _with_notes: uids.join(','), _notes_list: me.selected_list }, true);
        }
    }

    /**
     *
     */
    function render_tagslist(newtags, replace)
    {
        if (replace) {
            tags = newtags;
        }
        else {
            var append = [];
            for (var i=0; i < newtags.length; i++) {
                if ($.inArray(newtags[i], tags) < 0)
                    append.push(newtags[i]);
            }
            if (!append.length) {
                update_tagcloud();
                return;  // nothing to be added
            }
            tags = tags.concat(append);
        }

        // sort tags first
        tags.sort(function(a,b){
            return a.toLowerCase() > b.toLowerCase() ? 1 : -1;
        })

        var widget = $(rcmail.gui_objects.notestagslist).html('');

        // append tags to tag cloud
        $.each(tags, function(i, tag){
            li = $('<li role="checkbox" aria-checked="false" tabindex="0"></li>')
                .attr('rel', tag)
                .data('value', tag)
                .html(Q(tag) + '<span class="count"></span>')
                .appendTo(widget)
                .draggable({
                    addClasses: false,
                    revert: 'invalid',
                    revertDuration: 300,
                    helper: tag_draggable_helper,
                    start: tag_draggable_start,
                    appendTo: 'body',
                    cursor: 'pointer'
                });
        });

        update_tagcloud();
    }

    /**
     * Display the given counts to each tag and set those inactive which don't
     * have any matching records in the current view.
     */
    function update_tagcloud(counts)
    {
        // compute counts first by iterating over all visible task items
        if (typeof counts == 'undefined') {
            counts = {};
            $.each(notesdata, function(id, rec){
                for (var t, j=0; rec && rec.tags && j < rec.tags.length; j++) {
                    t = rec.tags[j];
                    if (typeof counts[t] == 'undefined')
                        counts[t] = 0;
                    counts[t]++;
                }
            });
        }

        $(rcmail.gui_objects.notestagslist).children('li').each(function(i,li){
            var elem = $(li), tag = elem.attr('rel'),
                count = counts[tag] || 0;

            elem.children('.count').html(count+'');
            if (count == 0) elem.addClass('inactive');
            else            elem.removeClass('inactive');

            if (tagsfilter && tagsfilter.length && $.inArray(tag, tagsfilter)) {
                elem.addClass('selected').attr('aria-checked', 'true');
            }
            else {
                elem.removeClass('selected').attr('aria-checked', 'false');
            }
        });
    }

    /**
     * Callback from server after saving a note record
     */
    function update_note(data)
    {
        data.id = rcmail.html_identifier_encode(data.uid);

        var row, is_new = (notesdata[data.id] == undefined && data.list == me.selected_list);
        notesdata[data.id] = data;

        if (is_new || me.selected_note && data.id == me.selected_note.id) {
            render_note(data);
            render_tagslist(data.tags || []);
        }
        else if (data.tags) {
            render_tagslist(data.tags);
        }

        // add list item on top
        if (is_new) {
            noteslist.insert_row({
                id: 'rcmrow' + data.id,
                cols: [
                    { className:'title', innerHTML:Q(data.title) },
                    { className:'date',  innerHTML:Q(data.changed || '') }
                ]
            }, true);

            noteslist.select(data.id);
        }
        // update list item
        else if (row = noteslist.rows[data.id]) {
            $('.title', row.obj).html(Q(data.title));
            $('.date', row.obj).html(Q(data.changed || ''));
            // TODO: move to top
        }
    }

    /**
     * 
     */
    function reset_view()
    {
        close_history_dialog();
        me.selected_note = null;
        $('.notetitle', rcmail.gui_objects.noteviewtitle).val('').hide();
        $('.tagline, .dates', rcmail.gui_objects.noteviewtitle).hide();
        $(rcmail.gui_objects.noteseditform).hide();
        $(rcmail.gui_objects.notesdetailview).hide();
        $(rcmail.gui_objects.notesattachmentslist).html('');
        rcmail.enable_command('save', false);
    }

    /**
     * Collect data from the edit form and submit it to the server
     */
    function save_note(beforesave)
    {
        if (!me.selected_note) {
            return false;
        }

        var savedata = get_save_data();

        // run savedata through the given callback function
        if (typeof beforesave == 'function') {
            beforesave(savedata);
        }

        // add reference to old list if changed
        if (me.selected_note.list && savedata.list != me.selected_note.list) {
            savedata._fromlist = me.selected_note.list;
        }

        // do some input validation
        if (savedata.title == '') {
            alert(rcmail.gettext('entertitle', 'kolab_notes'));
            $('.notetitle', rcmail.gui_objects.noteviewtitle).focus();
            return false;
        }

        if (check_change_state(savedata)) {
            rcmail.lock_form(rcmail.gui_objects.noteseditform, true);
            saving_lock = rcmail.set_busy(true, 'kolab_notes.savingdata');
            rcmail.http_post('action', { _data: savedata, _do: savedata.uid?'edit':'new' }, true);
        }
        else {
            rcmail.display_message(rcmail.get_label('nochanges', 'kolab_notes'), 'info');
        }
    }

    /**
     * Collect updated note properties from edit form for saving
     */
    function get_save_data()
    {
        var editor = tinyMCE.get('notecontent'),
            listselect = $('option:selected', rcmail.gui_objects.notebooks);
        var savedata = {
            title: trim($('.notetitle', rcmail.gui_objects.noteviewtitle).val()),
            description: editor ? editor.getContent({ format:'html' }).replace(/^\s*(<p><\/p>\n*)?/, '') : $('#notecontent').val(),
            list: listselect.length ? listselect.val() : me.selected_note.list || me.selected_list,
            uid: me.selected_note.uid,
            tags: []
        };

        // copy links
        if ($.isArray(me.selected_note.links)) {
            savedata.links = me.selected_note.links;
        }

        // collect tags
        $('.tagedit-list input[type="hidden"]', rcmail.gui_objects.noteviewtitle).each(function(i, elem){
            if (elem.value)
                savedata.tags.push(elem.value);
        });
        // including the "pending" one in the text box
        var newtag = $('#tagedit-input').val();
        if (newtag != '') {
            savedata.tags.push(newtag);
        }

        return savedata;
    }

    /**
     * Check if the currently edited note record was changed
     */
    function check_change_state(data)
    {
        if (!me.selected_note || me.selected_note.readonly || !has_permission(me.notebooks[me.selected_note.list || me.selected_list], 'i')) {
            return false;
        }

        var savedata = data || get_save_data();

        return savedata.title != me.selected_note.title
            || savedata.description != me.selected_note.description
            || savedata.tags.join(',') != (me.selected_note.tags || []).join(',')
            || savedata.list != me.selected_note.list
            || me.selected_note._links_removed;
    }

    /**
     * Check for unsaved changes and warn the user
     */
    function warn_unsaved_changes(ok, nok, beforesave)
    {
        if (typeof ok != 'function')
            ok = function(){ };
        if (typeof nok != 'function')
            nok = function(){ };

        if (check_change_state()) {
            var dialog, buttons = [];

            buttons.push({
                text: rcmail.gettext('discard', 'kolab_notes'),
                click: function() {
                    dialog.dialog('close');
                    ok();
                }
            });

            buttons.push({
                text: rcmail.gettext('save'),
                'class': 'mainaction',
                click: function() {
                    save_note(beforesave);
                    dialog.dialog('close');
                    rcmail.busy = false;  // don't block next action
                    ok();
                }
            });

            buttons.push({
                text: rcmail.gettext('abort', 'kolab_notes'),
                click: function() {
                    dialog.dialog('close');
                    nok();
                }
            });

            var options = {
                width: 460,
                resizable: false,
                closeOnEscape: false,
                dialogClass: 'warning',
                open: function(event, ui) {
                    $(this).parent().find('.ui-dialog-titlebar-close').hide();
                    setTimeout(function(){
                        dialog.parent().find('.ui-button:visible').first().focus();
                    }, 10);
                },
                close: function(event, ui) {
                    $(this).dialog('destroy').remove();
                }
            };

            // open jquery UI dialog
            dialog = rcmail.show_popup_dialog(
                rcmail.gettext('discardunsavedchanges', 'kolab_notes'),
                rcmail.gettext('unsavedchanges', 'kolab_notes'),
                buttons,
                options
            );

            return false;
        }

        if (typeof ok == 'function') {
            ok();
        }

        return true;
    }

    /**
     *
     */
    function delete_notes()
    {
        if (!noteslist.selection.length) {
            return false;
        }

        if (confirm(rcmail.gettext('deletenotesconfirm','kolab_notes'))) {
            var rec, id, uids = [];
            for (var i=0; i < noteslist.selection.length; i++) {
                id = noteslist.selection[i];
                rec = notesdata[id];
                if (rec) {
                    noteslist.remove_row(id);
                    uids.push(rec.uid);
                    delete notesdata[id];
                }
            }

            saving_lock = rcmail.set_busy(true, 'kolab_notes.savingdata');
            rcmail.http_post('action', { _data: { uid: uids.join(','), list: me.selected_list }, _do: 'delete' }, true);

            reset_view();
            update_tagcloud();
            noteslist.clear_selection();
        }
    }

    /**
     *
     */
    function move_notes(list_id)
    {
        var rec, id, uids = [];
        for (var i=0; i < noteslist.selection.length; i++) {
            id = noteslist.selection[i];
            rec = notesdata[id];
            if (rec) {
                noteslist.remove_row(id);
                uids.push(rec.uid);
                delete notesdata[id];
            }
        }

        if (uids.length) {
            saving_lock = rcmail.set_busy(true, 'kolab_notes.savingdata');
            rcmail.http_post('action', { _data: { uid: uids.join(','), list: me.selected_list, to: list_id }, _do: 'move' }, true);
        }
    }

    /**
     * update browser location to remember current view
     */
    function update_state()
    {
        var query = { _list: me.selected_list }

        if (settings.selected_uid) {
            query._id = settings.selected_uid;
        }

        if (window.history.replaceState) {
            window.history.replaceState({}, document.title, rcmail.url('', query));
        }
    }


    /*  Helper functions for drag & drop functionality of tags  */
    
    function tag_draggable_helper()
    {
        if (!tag_draghelper)
            tag_draghelper = $('<div class="tag-draghelper"></div>');
        else
            tag_draghelper.html('');

        $(this).clone().addClass('tag').appendTo(tag_draghelper);
        return tag_draghelper;
    }

    function tag_draggable_start(event, ui)
    {
        // register notes list to receive drop events
        $('li', rcmail.gui_objects.noteslist).droppable({
            hoverClass: 'droptarget',
            accept: tag_droppable_accept,
            drop: tag_draggable_dropped,
            addClasses: false
        });

        // allow to drop tags onto edit form title
        $(rcmail.gui_objects.noteviewtitle).droppable({
            drop: function(event, ui){
                $('#tagedit-input').val(ui.draggable.data('value')).trigger('transformToTag');
                $('.tagline .placeholder', rcmail.gui_objects.noteviewtitle).hide();
            },
            addClasses: false
        })
    }

    function tag_droppable_accept(draggable)
    {
        if (rcmail.busy)
            return false;

        var tag = draggable.data('value'),
            drop_id = $(this).attr('id').replace(/^rcmrow/, ''),
            drop_rec = notesdata[drop_id];

        // target already has this tag assigned
        if (!drop_rec || (drop_rec.tags && $.inArray(tag, drop_rec.tags) >= 0)) {
            return false;
        }

        return true;
    }

    function tag_draggable_dropped(event, ui)
    {
        var drop_id = $(this).attr('id').replace(/^rcmrow/, ''),
            tag = ui.draggable.data('value'),
            rec = notesdata[drop_id],
            savedata;

        if (rec && rec.id) {
            savedata = me.selected_note && rec.uid == me.selected_note.uid ? get_save_data() : $.extend({}, rec);

            if (savedata.id)   delete savedata.id;
            if (savedata.html) delete savedata.html;

            if (!savedata.tags)
                savedata.tags = [];
            savedata.tags.push(tag);

            rcmail.lock_form(rcmail.gui_objects.noteseditform, true);
            saving_lock = rcmail.set_busy(true, 'kolab_notes.savingdata');
            rcmail.http_post('action', { _data: savedata, _do: 'edit' }, true);
        }
    }

}


// extend jQuery
// from http://james.padolsey.com/javascript/sorting-elements-with-jquery/
jQuery.fn.sortElements = (function(){
    var sort = [].sort;

    return function(comparator, getSortable) {
        getSortable = getSortable || function(){ return this };

        var last = null;
        return sort.call(this, comparator).each(function(i){
            // at this point the array is sorted, so we can just detach each one from wherever it is, and add it after the last
            var node = $(getSortable.call(this));
            var parent = node.parent();
            if (last) last.after(node);
            else      parent.prepend(node);
            last = node;
        });
    };
})();


/* notes plugin UI initialization */
var kolabnotes;
window.rcmail && rcmail.addEventListener('init', function(evt) {
    kolabnotes = new rcube_kolab_notes_ui(rcmail.env.kolab_notes_settings);
    if (rcmail.env.action == 'dialog-ui')
        kolabnotes.init_dialog();
    else
        kolabnotes.init();
});

