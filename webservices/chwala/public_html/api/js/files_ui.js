/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2013, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 +--------------------------------------------------------------------------+
*/

function files_ui()
{
  var ref = this;

  this.request_timeout = 300;
  this.message_time = 3000;
  this.events = {};
  this.commands = {};
  this.requests = {};
  this.uploads = {};
  this.ie = document.all && !window.opera;
  this.env = {
    url: 'api/',
    sort_col: 'name',
    sort_reverse: 0,
    search_threads: 1,
    directory_separator: '/',
    resources_dir: 'resources'
  };

  // set jQuery ajax options
  $.ajaxSetup({
    cache: false,
    error: function(request, status, err) { ref.http_error(request, status, err); },
    beforeSend: function(xmlhttp) { xmlhttp.setRequestHeader('X-Session-Token', ref.env.token); }
  });


  /*********************************************************/
  /*********          basic utilities              *********/
  /*********************************************************/

  // initialize interface
  this.init = function()
  {
    if (!this.env.token)
      return;

    if (this.env.task == 'main') {
      this.enable_command('folder.list', 'folder.create', 'file.search', true);
      this.command('folder.list');
    }
    else if (this.env.task == 'file') {
      this.load_file('#file-content', this.env.filedata);
      this.enable_command('file.delete', 'file.download', true);
    }

    if (!this.env.browser_capabilities)
      this.browser_capabilities_check();
  };

  // set environment variable(s)
  this.set_env = function(p, value)
  {
    if (p != null && typeof p === 'object' && !value)
      for (var n in p)
        this.env[n] = p[n];
    else
      this.env[p] = value;
  };

  // execute a specific command on the web client
  this.command = function(command, props, obj)
  {
    if (obj && obj.blur)
      obj.blur();

    if (this.busy)
      return false;

    if (!this.commands[command])
      return;

    var ret = undefined,
      func = command.replace(/[^a-z]/g, '_'),
      task = command.replace(/\.[a-z-_]+$/g, '');

    if (this[func] && typeof this[func] === 'function') {
      ret = this[func](props);
    }

    return ret === false ? false : obj ? false : true;
  };

  this.set_busy = function(a, message)
  {
    if (a && this.busy)
      return;

    if (a && message) {
      var msg = this.t(message);
      if (msg == message)
        msg = 'Loading...';

      this.display_message(msg, 'loading');
    }
    else if (!a) {
      this.hide_message('loading');
    }

    this.busy = a;

//    if (this.gui_objects.editform)
  //    this.lock_form(this.gui_objects.editform, a);

    // clear pending timer
    if (this.request_timer)
      clearTimeout(this.request_timer);

    // set timer for requests
    if (a && this.request_timeout)
      this.request_timer = window.setTimeout(function() { ref.request_timed_out(); }, this.request_timeout * 1000);
  };

  // called when a request timed out
  this.request_timed_out = function()
  {
    this.set_busy(false);
    this.display_message('Request timed out!', 'error');
  };

  // Add variable to GET string, replace old value if exists
  this.add_url = function(url, name, value)
  {
    value = urlencode(value);

    if (/(\?.*)$/.test(url)) {
      var urldata = RegExp.$1,
        datax = RegExp('((\\?|&)'+RegExp.escape(name)+'=[^&]*)');

      if (datax.test(urldata))
        urldata = urldata.replace(datax, RegExp.$2 + name + '=' + value);
      else
        urldata += '&' + name + '=' + value

      return url.replace(/(\?.*)$/, urldata);
    }

    return url + '?' + name + '=' + value;
  };

  this.trigger_event = function(event, data)
  {
    if (this.events[event])
      for (var i in this.events[event])
        this.events[event][i](data);
  };

  this.add_event_listener = function(event, func)
  {
    if (!this.events[event])
      this.events[event] = [];

    this.events[event].push(func);
  };

  this.buttons = function(p)
  {
    $.each(p, function(i, v) {
      if (!ui.buttons[i])
        ui.buttons[i] = [];

      if (typeof v == 'object')
        ui.buttons[i] = $.merge(ui.buttons[i], v);
      else
        ui.buttons[i].push(v);
    });
  };

  this.enable_command = function()
  {
    var i, n, args = Array.prototype.slice.call(arguments),
      enable = args.pop(), cmd;

    for (n=0; n<args.length; n++) {
      cmd = args[n];
      // argument of type array
      if (typeof cmd === 'string') {
        this.commands[cmd] = enable;
        if (this.buttons[cmd])
          $.each(this.buttons[cmd], function (i, button) {
            $('#'+button)[enable ? 'removeClass' : 'addClass']('disabled');
          });
        this.trigger_event('enable-command', {command: cmd, status: enable});
      }
      // push array elements into commands array
      else {
        for (i in cmd)
          args.push(cmd[i]);
      }
    }
  };


  /*********************************************************/
  /*********           GUI functionality           *********/
  /*********************************************************/

  // write to the document/window title
  this.set_pagetitle = function(title)
  {
    if (title && document.title)
      document.title = title;
  };

  // display a system message (types: loading, notice, error)
  this.display_message = function(msg, type, timeout)
  {
    var obj, ref = this;

    if (!type)
      type = 'notice';
    if (msg)
      msg = this.t(msg);

    if (type == 'loading') {
      timeout = this.request_timeout * 1000;
      if (!msg)
        msg = this.t('loading');
    }
    else if (!timeout)
      timeout = this.message_time * (type == 'error' || type == 'warning' ? 2 : 1);

    obj = $('<div>');

    if (type != 'loading') {
      msg = '<div><span>' + msg + '</span></div>';
      obj.addClass(type).click(function() { return ref.hide_message(); });
    }

    if (timeout > 0)
      window.setTimeout(function() { ref.hide_message(type, type != 'loading'); }, timeout);

    obj.attr('id', type == 'loading' ? 'loading' : 'message')
      .appendTo('body').html(msg).show();
  };

  // make a message to disapear
  this.hide_message = function(type, fade)
  {
    if (type == 'loading')
      $('#loading').remove();
    else
      $('#message').fadeOut('normal', function() { $(this).remove(); });
  };

  this.set_watermark = function(id)
  {
    if (this.env.watermark)
      $('#'+id).html(this.env.watermark);
  };


  /********************************************************/
  /*********        Remote request methods        *********/
  /********************************************************/
/*
  // send a http POST request to the server
  this.http_post = function(action, postdata)
  {
    var url = this.url(action);

    if (postdata && typeof postdata === 'object')
      postdata.remote = 1;
    else {
      if (!postdata)
        postdata = '';
      postdata += '&remote=1';
    }

    this.set_request_time();

    return $.ajax({
      type: 'POST', url: url, data: postdata, dataType: 'json',
      success: function(response) { ui.http_response(response); },
      error: function(o, status, err) { ui.http_error(o, status, err); }
    });
  };

  // handle HTTP response
  this.http_response = function(response)
  {
    var i;

    if (!response)
      return;

    // set env vars
    if (response.env)
      this.set_env(response.env);

    // we have translation labels to add
    if (typeof response.labels === 'object')
      this.tdef(response.labels);

    // HTML page elements
    if (response.objects)
      for (i in response.objects)
        $('#'+i).html(response.objects[i]);

    this.update_request_time();
    this.set_busy(false);

    // if we get javascript code from server -> execute it
    if (response.exec)
      eval(response.exec);

    this.trigger_event('http-response', response);
  };

  // handle HTTP request errors
  this.http_error = function(request, status, err)
  {
    var errmsg = request.statusText;

    this.set_busy(false);
    request.abort();

    if (request.status && errmsg)
      this.display_message(this.t('servererror') + ' (' + errmsg + ')', 'error');
  };
*/

  /********************************************************/
  /*********            Helper methods            *********/
  /********************************************************/

  // disable/enable all fields of a form
  this.lock_form = function(form, lock)
  {
    if (!form || !form.elements)
      return;

    var n, len, elm;

    if (lock)
      this.disabled_form_elements = [];

    for (n=0, len=form.elements.length; n<len; n++) {
      elm = form.elements[n];

      if (elm.type == 'hidden')
        continue;
      // remember which elem was disabled before lock
      if (lock && elm.disabled)
        this.disabled_form_elements.push(elm);
      // check this.disabled_form_elements before inArray() as a workaround for FF5 bug
      // http://bugs.jquery.com/ticket/9873
      else if (lock || (this.disabled_form_elements && $.inArray(elm, this.disabled_form_elements)<0))
        elm.disabled = lock;
    }
  };

  this.set_request_time = function()
  {
    this.env.request_time = (new Date()).getTime();
  };

  // Update request time element
  this.update_request_time = function()
  {
    if (this.env.request_time) {
      var t = ((new Date()).getTime() - this.env.request_time)/1000,
        el = $('#reqtime');
      el.text(el.text().replace(/[0-9.,]+/, t));
    }
  };

  // position and display popup
  this.popup_show = function(e, popup)
  {
    var popup = $(popup),
      pos = this.mouse_pos(e),
      win = $(window),
      w = popup.width(),
      h = popup.height(),
      left = pos.left - w + 20,
      top = pos.top - 10;

    if (top + h > win.height())
      top -= h;
    if (left + w > win.width())
      left -= w;

    popup.css({left: left + 'px', top: top + 'px'})
      .click(function(e) { e.stopPropagation(); $(this).hide(); }).show();
    e.stopPropagation();
  };

  // Return absolute mouse position of an event
  this.mouse_pos = function(e)
  {
    if (!e) e = window.event;

    var mX = e.pageX ? e.pageX : e.clientX,
      mY = e.pageY ? e.pageY : e.clientY;

    if (document.body && document.all) {
      mX += document.body.scrollLeft;
      mY += document.body.scrollTop;
    }

    if (e._offset) {
      mX += e._offset.left;
      mY += e._offset.top;
    }

    return {left:mX, top:mY};
  };

  this.serialize_form = function(id)
  {
    var i, v, json = {},
      form = $(id),
      query = form.serializeArray();

    for (i in query)
      json[query[i].name] = query[i].value;

    // serializeArray() doesn't work properly for multi-select
    $('select[multiple="multiple"]', form).each(function() {
      var name = this.name;
      json[name] = [];
      $(':selected', this).each(function() {
        json[name].push(this.value);
      });
    });

    return json;
  };


  /*********************************************************/
  /*********   Commands and response handlers      *********/
  /*********************************************************/

  this.logout = function()
  {
    this.main_logout();
  };

  this.main_logout = function(params)
  {
    location.href = '?task=main&action=logout' + (params ? '&' + $.param(params) : '');
    return false;
  };

  // folder list request
  this.folder_list = function()
  {
    this.set_busy(true, 'loading');
    this.request('folder_list', {}, 'folder_list_response');
  };

  // folder list response handler
  this.folder_list_response = function(response)
  {
    if (!this.response(response))
      return;

    var elem = $('#folderlist'), table = $('table', elem);

    this.env.folders = this.folder_list_parse(response.result);

    table.empty();

    $.each(this.env.folders, function(i, f) {
      var row = ui.folder_list_row(i, f);
      table.append(row);
    });

    // add virtual collections
    $.each(['audio', 'video', 'image', 'document'], function(i, n) {
      var row = $('<tr><td><span class="name"></span></td></tr>'),
        span = $('span.name', row);

      row.attr('id', 'folder-collection-' + n);
      span.text(ui.t('collection.' + n))
        .click(function() { ui.folder_select(n, true); });

      if (n == ui.env.collection)
        row.addClass('selected');

      table.append(row);
    });

    // add tree icons
    this.folder_list_tree(this.env.folders);
  };

  this.folder_select = function(folder, is_collection)
  {
    this.env.search = null;
    this.file_search_stop();

    var list = $('#folderlist');
    $('tr.selected', list).removeClass('selected');

    if (is_collection) {
      var found = $('#folder-collection-' + folder, list).addClass('selected');

      this.env.folder = null;
      this.enable_command('file.list', true);
      this.enable_command('folder.delete', 'folder.edit', 'file.upload', false);
      this.file_list({collection: folder});
    }
    else {
      var found = $('#' + this.env.folders[folder].id, list).addClass('selected');

      this.env.collection = null;
      this.enable_command('file.list', 'folder.delete', 'folder.edit', 'file.upload', found.length);
      this.file_list({folder: folder});
    }
  };

  this.folder_unselect = function()
  {
    this.env.search = null;
    this.env.folder = null;
    this.env.collection = null;
    this.file_search_stop();

    var list = $('#folderlist');
    $('tr.selected', list).removeClass('selected');

    this.enable_command('file.list', 'folder.delete', 'folder.edit', 'file.upload', false);
  };

  // folder create request
  this.folder_create = function(folder)
  {
    if (!folder) {
      this.folder_create_start();
      return;
    }

    this.set_busy(true, 'saving');
    this.request('folder_create', {folder: folder}, 'folder_create_response');
  };

  // folder create response handler
  this.folder_create_response = function(response)
  {
    if (!this.response(response))
      return;

    this.folder_list();
  };

  // folder rename request
  this.folder_edit = function(folder)
  {
    if (!folder) {
      this.folder_edit_start();
      return;
    }

    this.set_busy(true, 'saving');
    this.request('folder_move', {folder: folder.folder, 'new': folder['new']}, 'folder_rename_response');
  };

  // folder rename response handler
  this.folder_rename_response = function(response)
  {
    if (!this.response(response))
      return;

    this.env.folder = this.env.folder_rename;
    this.folder_list();
  };

  // folder delete request
  this.folder_delete = function(folder)
  {
    if (folder === undefined)
      folder = this.env.folder;

    if (!folder)
      return;

    // @todo: confirm

    this.set_busy(true, 'saving');
    this.request('folder_delete', {folder: folder}, 'folder_delete_response');
  };

  // folder delete response handler
  this.folder_delete_response = function(response)
  {
    if (!this.response(response))
      return;

    this.env.folder = null;
    $('#filelist tbody').empty();
    this.enable_command('folder.delete', 'folder.edit', 'file.list', 'file.search', 'file.upload', false);
    this.folder_list();
  };

  // file list request
  this.file_list = function(params)
  {
    if (!params)
      params = {};

    var i, req = (new Date).getTime();

    // reset all pending list requests
    for (i in this.requests) {
      this.requests[i].abort();
      delete this.requests[i];
    }

    if (params.all_folders) {
      params.collection = null;
      params.folder = null;
      this.folder_unselect();
    }

    if (params.collection == undefined)
      params.collection = this.env.collection;
    if (params.folder == undefined)
      params.folder = this.env.folder;
    if (params.sort == undefined)
      params.sort = this.env.sort_col;
    if (params.reverse == undefined)
      params.reverse = this.env.sort_reverse;
    if (params.search == undefined)
      params.search = this.env.search;

    this.env.collection = params.collection;
    this.env.folder = params.folder;
    this.env.sort_col = params.sort;
    this.env.sort_reverse = params.reverse;

    // empty the list
    $('#filelist tbody').empty();
    this.env.file_list = [];
    this.env.list_shift_start = null;
    this.enable_command('file.open', 'file.get', 'file.rename', 'file.delete', 'file.copy', 'file.move', false);

    // request
    if (params.collection || params.all_folders)
      this.file_list_loop(params);
    else {
      this.set_busy(true, 'loading');
      this.requests[req] = this.request('file_list', params, 'file_list_response');
    }
  };

  // call file.list request for every folder (used for search and virt. collections)
  this.file_list_loop = function(params)
  {
    var i, folders = [], req = (new Date).getTime(),
      limit = Math.max(this.env.search_threads || 1, 1);

    if (params.collection) {
      if (!params.search)
        params.search = {};
      params.search['class'] = params.collection;
      delete params['collection'];
    }

    delete params['all_folders'];

    $.each(this.env.folders, function(i, f) {
      if (!f.virtual)
        folders.push(i);
    });

    this.env.folders_loop = folders;
    this.env.folders_loop_params = params;
    this.env.folders_loop_lock = false;

    for (i=0; i<folders.length && i<limit; i++) {
      this.set_busy(true, 'loading');
      params.folder = folders.shift();
      this.requests[req+'-'+i] = this.request('file_list', params, 'file_list_loop_response');
    }
  };

  // file list response handler
  this.file_list_response = function(response)
  {
    if (!this.response(response))
      return;

    var table = $('#filelist'), list = [];

    $.each(response.result, function(key, data) {
      var row = ui.file_list_row(key, data);
      table.append(row);
      data.row = row;
      list.push(data);
    });

    this.env.file_list = list;
  };

  // file list response handler for loop'ed request
  this.file_list_loop_response = function(response)
  {
    var i, folders = this.env.folders_loop,
      params = this.env.folders_loop_params,
      limit = Math.max(this.env.search_threads || 1, 1),
      valid = this.response(response),
      req = (new Date).getTime();

    for (i=0; i<folders.length && i<limit; i++) {
      this.set_busy(true, 'loading');
      params.folder = folders.shift();
      this.requests[req+'-'+i] = this.request('file_list', params, 'file_list_loop_response');
    }

    if (!valid)
      return;

    this.file_list_loop_result_add(response.result);
  };

  // add files from list request to the table (with sorting)
  this.file_list_loop_result_add = function(result)
  {
    // chack if result (hash-array) is empty
    if (!object_is_empty(result))
      return;

    if (this.env.folders_loop_lock) {
      setTimeout(function() { ui.file_list_loop_result_add(result); }, 100);
      return;
    }

    // lock table, other list responses will wait
    this.env.folders_loop_lock = true;

    var n, i, len, elem, list = [], table = $('#filelist');

    for (n=0, len=this.env.file_list.length; n<len; n++) {
      elem = this.env.file_list[n];
      for (i in result) {
        if (this.sort_compare(elem, result[i]) < 0)
          break;

        var row = this.file_list_row(i, result[i]);
        elem.row.before(row);
        result[i].row = row;
        list.push(result[i]);
        delete result[i];
      }

      list.push(elem);
    }

    // add the rest of rows
    $.each(result, function(key, data) {
      var row = ui.file_list_row(key, data);
      table.append(row);
      result[key].row = row;
      list.push(result[key]);
    });

    this.env.file_list = list;
    this.env.folders_loop_lock = false;
  };

  // sort files list (without API request)
  this.file_list_sort = function(col, reverse)
  {
    var n, len, list = this.env.file_list,
      table = $('#filelist'), tbody = $('<tbody>');

    this.env.sort_col = col;
    this.env.sort_reverse = reverse;

    if (!list || !list.length)
      return;

    // sort the list
    list.sort(function (a, b) {
      return ui.sort_compare(a, b);
    });

    // add rows to the new body
    for (n=0, len=list.length; n<len; n++) {
      tbody.append(list[n].row);
    }

    // replace table bodies
    $('tbody', table).replaceWith(tbody);
  };

  // file delete request
  this.file_delete = function(file)
  {
    if (!file) {
      file = [];

      if (this.env.file)
        file.push(this.env.file);
      else
        file = this.file_list_selected();
    }

    this.set_busy(true, 'deleting');
    this.request('file_delete', {file: file}, 'file_delete_response');
  };

  // file delete response handler
  this.file_delete_response = function(response)
  {
    if (!this.response(response))
      return;

    if (this.env.file) {
      var path = this.file_path(this.env.file);
      if (window.opener && opener.ui && (!opener.ui.env.folder || opener.ui.env.folder == path))
        opener.ui.file_list();
      window.close();
    }
    else
      this.file_list();
  };

  // file rename request
  this.file_rename = function(file, newname)
  {
    if (file === newname)
      return;

    this.set_busy(true, 'saving');
    this.request('file_move', {file: file, 'new': newname}, 'file_rename_response');
  };

  // file rename response handler
  this.file_rename_response = function(response)
  {
    if (!this.response(response))
      return;

    // @TODO: we could update list/file metadata and just sort
    this.file_list();
  };

  // file copy request
  this.file_copy = function(folder)
  {
    var count = 0, list = {}, files = this.file_list_selected();

    if (!files || !files.length || !folder)
      return;

    $.each(files, function(i, v) {
      var name = folder + ui.env.directory_separator + ui.file_name(v);

      if (name != v) {
        list[v] = name;
        count++;
      }
    });

    if (!count)
      return;

    this.set_busy(true, 'copying');
    this.request('file_copy', {file: list}, 'file_copy_response');
  };

  // file copy response handler
  this.file_copy_response = function(response)
  {
    if (!this.response(response))
      return;

    if (response.result && response.result.already_exist && response.result.already_exist.length)
      this.file_move_ask_user(response.result.already_exist);
  };

  // file move request
  this.file_move = function(folder)
  {
    var count = 0, list = {}, files = this.file_list_selected();

    if (!files || !files.length || !folder)
      return;

    $.each(files, function(i, v) {
      var name = folder + ui.env.directory_separator + ui.file_name(v);

      if (name != v) {
        list[v] = name;
        count++;
      }
    });

    if (!count)
      return;

    this.set_busy(true, 'moving');
    this.request('file_move', {file: list}, 'file_move_response');
  };

  // file move response handler
  this.file_move_response = function(response)
  {
    if (!this.response(response))
      return;

    if (response.result && response.result.already_exist && response.result.already_exist.length)
      this.file_move_ask_user(response.result.already_exist, true);
    else
      this.file_list();
  };

  this.file_download = function(file)
  {
    if (!file)
      file = this.env.file;

    location.href = this.env.url + this.url('file_get', {token: this.env.token, file: file, 'force-download': 1});
  };

  // file upload request
  this.file_upload = function()
  {
    var form = $('#uploadform'),
      field = $('input[type=file]', form).get(0),
      files = field.files ? field.files.length : field.value ? 1 : 0;

    if (files) {
      // submit form and read server response
      this.file_upload_form(form, 'file_upload', function(e, frame, folder) {
        var doc, response, res;

        try {
          doc = frame.contentDocument ? frame.contentDocument : frame.contentWindow.document;
          response = doc.body.innerHTML;

          // in Opera onload is called twice, once with empty body
          if (!response)
            return;
          // response may be wrapped in <pre> tag
          if (response.match(/^<pre[^>]*>(.*)<\/pre>$/i))
            response = RegExp.$1;

          response = eval('(' + response + ')');
        }
        catch (err) {
          response = {status: 'ERROR'};
        }

        if ((res = ui.response_parse(response)) && folder == ui.env.folder)
          ui.file_list();

        return res;
      });
    }
  };


  /*********************************************************/
  /*********          Command helpers              *********/
  /*********************************************************/

  // create folders table row
  this.folder_list_row = function(folder, data)
  {
    var row = $('<tr><td><span class="branch"></span><span class="name"></span></td></tr>'),
      span = $('span.name', row);

    span.text(data.name);
    row.attr('id', data.id).data('folder', folder);

    if (data.depth)
      $('span.branch', row).width(15 * data.depth);

    if (data.virtual)
      row.addClass('virtual');
    else {
      span.click(function() { ui.folder_select(folder); })
      row.mouseenter(function() {
          if (ui.drag_active && (!ui.env.folder || ui.env.folder != $(this).data('folder')))
            $(this).addClass('droptarget');
        })
        .mouseleave(function() {
          if (ui.drag_active)
            $(this).removeClass('droptarget');
        });

      if (folder == this.env.folder)
        row.addClass('selected');
    }

    return row;
  };

  // create files table row
  this.file_list_row = function(filename, data)
  {
    var row = $('<tr><td class="filename"></td>'
        +' <td class="filemtime"></td><td class="filesize"></td></tr>'),
      link = $('<span></span>').text(data.name).click(function(e) { ui.file_menu(e, filename, data.type); });

    $('td.filename', row).addClass(ui.file_type_class(data.type)).append(link);
    $('td.filemtime', row).text(data.mtime);
    $('td.filesize', row).text(ui.file_size(data.size));

    row.attr('data-file', filename)
      .click(function(e) { ui.file_list_click(e, this); })
      .mousedown(function(e) { return ui.file_list_drag(e, this); });

    // disables selection in IE
    if (document.all)
      row.on('selectstart', function() { return false; });

    return row;
  };

  // file row click event handler
  this.file_list_click = function(e, row)
  {
    var list = $('#filelist'), org = row, row = $(row),
      found, selected, shift = this.env.list_shift_start;

    if (e.shiftKey && shift && org != shift) {
      $('tr', list).each(function(i, r) {
        if (r == org) {
          found = 1;
          $(r).addClass('selected');
          return;
        }
        else if (!selected && r == shift) {
          selected = 1;
          return;
        }

        if ((!found && selected) || (found && !selected))
          $(r).addClass('selected');
        else
          $(r).removeClass('selected');
      });
    }
    else if (e.ctrlKey)
      row.toggleClass('selected');
    else {
      $('tr.selected', list).removeClass('selected');
      $(row).addClass('selected');
      this.env.list_shift_start = org;
    }

    selected = $('tr.selected', list).length;

    if (!selected)
      this.env.list_shift_start = null;

    this.enable_command('file.delete', 'file.move', 'file.copy', selected);
    this.enable_command('file.open', 'file.get', 'file.rename', selected == 1);
  };

  // file row drag start event handler
  this.file_list_drag = function(e, row)
  {
    if (e.shiftKey || e.ctrlKey)
      return true;

    // selects currently unselected row
    if (!$(row).hasClass('selected'))
      this.file_list_click(e, row);

    this.drag_start = true;
    this.drag_mouse_start = this.mouse_pos(e);

    $(document)
      .on('mousemove.draghandler', function(e) { ui.file_list_drag_mouse_move(e); })
      .on('mouseup.draghandler', function(e) { ui.file_list_drag_mouse_up(e); });
/*
    if (bw.mobile) {
      $(document)
        .on('touchmove.draghandler', function(e) { ui.file_list_drag_mouse_move(e); })
        .on('touchend.draghandler', function(e) { ui.file_list_drag_mouse_up(e); });
    }
*/
    return false;
  };

  // file row mouse move event handler
  this.file_list_drag_mouse_move = function(e)
  {
/*
    // convert touch event
    if (e.type == 'touchmove') {
      if (e.changedTouches.length == 1)
        e = rcube_event.touchevent(e.changedTouches[0]);
      else
        return rcube_event.cancel(e);
    }
*/
    var max_rows = 10, pos = this.mouse_pos(e);

    if (this.drag_start) {
      // check mouse movement, of less than 3 pixels, don't start dragging
      if (!this.drag_mouse_start || (Math.abs(pos.left - this.drag_mouse_start.left) < 3 && Math.abs(pos.top - this.drag_mouse_start.top) < 3))
        return false;

      if (!this.draglayer)
        this.draglayer = $('<div>').attr('id', 'draglayer')
          .css({position:'absolute', display:'none', 'z-index':2000})
          .appendTo(document.body);

      // reset content
      this.draglayer.html('');

      // get subjects of selected messages
      $('#filelist tr.selected').slice(0, max_rows+1).each(function(i) {
        if (i == 0)
          ui.drag_start_pos = $(this).offset();
        else if (i == max_rows) {
          ui.draglayer.append('...');
          return;
        }

        var subject = $('td.filename', this).text();

        // truncate filename to 50 characters
        if (subject.length > 50)
          subject = subject.substring(0, 50) + '...';

        ui.draglayer.append($('<div>').text(subject));
      });

      this.draglayer.show();
      this.drag_active = true;
    }

    if (this.drag_active && this.draglayer)
      this.draglayer.css({left:(pos.left+20)+'px', top:(pos.top-5 + (this.ie ? document.documentElement.scrollTop : 0))+'px'});

    this.drag_start = false;

    return false;
  };

  // file row mouse up event handler
  this.file_list_drag_mouse_up = function(e)
  {
    document.onmousemove = null;
/*
    if (e.type == 'touchend') {
      if (e.changedTouches.length != 1)
        return rcube_event.cancel(e);
    }
*/

    $(document).off('.draghandler');
    this.drag_active = false;

    var got_folder = this.file_list_drag_end(e);

    if (this.draglayer && this.draglayer.is(':visible')) {
      if (this.drag_start_pos && !got_folder)
        this.draglayer.animate(this.drag_start_pos, 300, 'swing').hide(20);
      else
        this.draglayer.hide();
    }
  };

  // files drag end handler
  this.file_list_drag_end = function(e)
  {
    var folder = $('#folderlist tr.droptarget').removeClass('droptarget');

    if (folder.length) {
      folder = folder.data('folder');

      if (e.shiftKey && this.commands['file.copy']) {
        this.file_drag_menu(e, folder);
        return true;
      }

      this.command('file.move', folder);

      return true;
    }
  };

  // display file drag menu
  this.file_drag_menu = function(e, folder)
  {
    var menu = $('#file-drag-menu');

    $('li.file-copy > a', menu).off('click').click(function() { ui.command('file.copy', folder); });
    $('li.file-move > a', menu).off('click').click(function() { ui.command('file.move', folder); });

    this.popup_show(e, menu);
  };

  // display file menu
  this.file_menu = function(e, file, type)
  {
    var href, caps, viewer,
      menu = $('#file-menu'),
      open_action = $('li.file-open > a', menu);

    if (viewer = this.file_type_supported(type)) {
      caps = this.browser_capabilities().join();
      href = '?' + $.param({task: 'file', action: 'open', token: this.env.token, file: file, caps: caps, viewer: viewer == 2 ? 1 : 0});
      open_action.attr({target: '_blank', href: href}).removeClass('disabled').off('click');
    }
    else
      open_action.click(function() { return false; }).addClass('disabled');

    $('li.file-download > a', menu)
      .attr({href: this.env.url + this.url('file_get', {token: this.env.token, file: file, 'force-download': 1})});
    $('li.file-delete > a', menu).off('click').click(function() { ui.file_delete(file); });
    $('li.file-rename > a', menu).off('click').click(function() { ui.file_rename_start(e); });

    this.popup_show(e, menu);
  };

  // returns selected files (with paths)
  this.file_list_selected = function()
  {
    var files = [];

    $('#filelist tr.selected').each(function() {
      files.push($(this).data('file'));
    });

    return files;
  };

  this.file_rename_start = function(e)
  {
    var list = $('#filelist'),
      tr = $(e.target).parents('tr'),
      td = $('td.filename', tr),
      file = tr.data('file'),
      name = this.file_name(file),
      input = $('<input>').attr({type: 'text', name: 'filename', 'class': 'filerename'})
        .val(name).data('file', file)
        .click(function(e) { e.stopPropagation(); })
        .keydown(function(e) {
          switch (e.which) {
          case 27: // ESC
            ui.file_rename_stop();
            break;
          case 13: // Enter
            var elem = $(this),
              newname = elem.val(),
              oldname = elem.data('file'),
              path = ui.file_path(file);

            ui.file_rename(oldname, path + ui.env.directory_separator + newname);
            elem.parent().text(newname);
            break;
          }
        });

    $('span', td).text('').append(input);
    input.focus();
  };

  this.file_rename_stop = function()
  {
    $('input.filerename').each(function() {
      var elem = $(this), name = ui.file_name(elem.data('file'));
      elem.parent().text(name);
    });
  };

  // post the given form to a hidden iframe
  this.file_upload_form = function(form, action, onload)
  {
    var ts = new Date().getTime(),
      frame_name = 'fileupload' + ts;

    // upload progress supported (and handler exists)
    if (this.env.capabilities.PROGRESS_NAME && window.progress_update) {
      var fname = this.env.capabilities.PROGRESS_NAME,
        field = $('input[name='+fname+']', form);

      if (!field.length) {
        field = $('<input>').attr({type: 'hidden', name: fname});
        field.prependTo(form);
      }

      field.val(ts);
      this.uploads[ts] = this.env.folder;
      this.file_upload_progress(ts);
    }

    // have to do it this way for IE
    // otherwise the form will be posted to a new window
    if (document.all) {
      document.body.insertAdjacentHTML('BeforeEnd',
        '<iframe id="'+frame_name+'" name="'+frame_name+'"'
        + ' src="' + this.env.resources_dir + '/blank.gif" '
        + ' style="width:0;height:0;visibility:hidden;"></iframe>');
    }
    // for standards-compliant browsers
    else
      $('<iframe>')
        .attr({name: frame_name, id: frame_name})
        .css({border: 'none', width: 0, height: 0, visibility: 'hidden'})
        .appendTo(document.body);

    // handle upload errors, parsing iframe content in onload
    $('#'+frame_name).load(function(e) {
      // hide progressbar on upload error
      if (!onload(e, this, ui.uploads[ts]) && window.progress_update)
        window.progress_update({id: ts, done: true});
      delete ui.uploads[ts];
    });

    $(form).attr({
      target: frame_name,
      action: this.env.url + this.url(action, {folder: this.env.folder, token: this.env.token, uploadid:ts}),
      method: 'POST'
    }).attr(form.encoding ? 'encoding' : 'enctype', 'multipart/form-data')
      .submit();
  };

  // upload progress requests
  this.file_upload_progress = function(id)
  {
    setTimeout(function() {
      if (id && ui.uploads[id])
        ui.request('upload_progress', {id: id}, 'file_upload_progress_response');
    }, this.env.capabilities.PROGRESS_TIME * 1000);
  };

  // upload progress response
  this.file_upload_progress_response = function(response)
  {
    if (!this.response(response))
      return;

    var param = response.result;

    if (param.id && window.progress_update)
      window.progress_update(param);

    if (!param.done)
      this.file_upload_progress(param.id);
  };

  // Display file search form
  this.file_search = function()
  {
    var form = this.form_show('file-search'),
      has_folder = this.env.folder || this.env.collection,
      radio1 = $('input[name="all_folders"][value="0"]', form);

    $('input[name="name"]', form).val('').focus();

    if (has_folder)
      radio1.prop('disabled', false).click();
    else {
      radio1.prop('disabled', true);
      $('input[name="all_folders"][value="1"]', form).click();
    }
  };

  // Hide file search form
  this.file_search_stop = function()
  {
    if (this.env.search)
      this.file_list(null, {search: null});

    this.form_hide('file-search');
    this.env.search = null;
  };

  // Execute file search
  this.file_search_submit = function()
  {
    var form = this.form_show('file-search'),
      value = $('input[name="name"]', form).val(),
      all = $('input[name="all_folders"]:checked', form).val();

    if (value) {
      this.env.search = {name: value};
      this.file_list({search: this.env.search, all_folders: all == 1});
    }
    else
      this.file_search_stop();
  };

  // Display folder creation form
  this.folder_create_start = function()
  {
    var form = this.form_show('folder-create');
    $('input[name="name"]', form).val('').focus();
    $('input[name="parent"]', form).prop('checked', this.env.folder)
      .prop('disabled', !this.env.folder);
  };

  // Hide folder creation form
  this.folder_create_stop = function()
  {
    this.form_hide('folder-create');
  };

  // Submit folder creation form
  this.folder_create_submit = function()
  {
    var folder = '', data = this.serialize_form('#folder-create-form');

    if (!data.name)
      return;

    if (data.parent && this.env.folder)
      folder = this.env.folder + this.env.directory_separator;

    folder += data.name;

    this.folder_create_stop();
    this.command('folder.create', folder);
  };

  // Display folder edit form
  this.folder_edit_start = function()
  {
    var form = this.form_show('folder-edit'),
      arr = this.env.folder.split(this.env.directory_separator),
      name = arr.pop();

    this.env.folder_edit_path = arr.join(this.env.directory_separator);

    $('input[name="name"]', form).val(name).focus();
  };

  // Hide folder edit form
  this.folder_edit_stop = function()
  {
    this.form_hide('folder-edit');
  };

  // Submit folder edit form
  this.folder_edit_submit = function()
  {
    var folder = '', data = this.serialize_form('#folder-edit-form');

    if (!data.name)
      return;

    if (this.env.folder_edit_path)
      folder = this.env.folder_edit_path + this.env.directory_separator;

    folder += data.name;
    this.env.folder_rename = folder;

    this.folder_edit_stop();
    this.command('folder.edit', {folder: this.env.folder, 'new': folder});
  };

  // when file move/copy operation returns file-exists error
  // this displays a dialog where user can decide to skip
  // or overwrite destination file(s)
  this.file_move_ask_user = function(list, move)
  {
    var file = list[0], buttons = {},
      label = this.t('file.moveconfirm').replace('$file', file.dst);

    buttons['file.overwrite'] = function() {
      var file = list.shift(), f = {},
        action = move ? 'file_move' : 'file_copy';

      f[file.src] = file.dst;
      ui.file_move_ask_list = list;
      ui.file_move_ask_mode = move;
      this.hide();
      ui.set_busy(true, move ? 'moving' : 'copying');
      ui.request(action, {file: f, overwrite: 1}, 'file_move_ask_user_response');
    };

    if (list.length > 1)
      buttons['file.overwriteall'] = function() {
        var f = {}, action = move ? 'file_move' : 'file_copy';

        $.each(list, function() { f[this.src] = this.dst; });
        this.hide();
        ui.set_busy(true, move ? 'moving' : 'copying');
        ui.request(action, {file: f, overwrite: 1}, action + '_response');
      };

    buttons['file.skip'] = function() {
      list.shift();
      this.hide();
      if (list.length)
        ui.file_move_ask_user(list, move);
      else if (move)
        ui.file_list();
    };

    if (list.length > 1)
      buttons['file.skipall'] = function() {
        this.hide();
        if (move)
          ui.file_list();
      };

    this.modal_dialog(label, buttons);
  };

  // file move (with overwrite) response handler
  this.file_move_ask_user_response = function(response)
  {
    var mode = this.file_move_ask_mode, list = this.file_move_ask_list;

    this.response(response);

    if (list && list.length)
      this.file_move_ask_user(list, mode);
    else if (mode)
      this.file_list();
  };

  this.file_edit = function()
  {
    if (this.file_editor) {
      this.file_editor.enable();
      this.enable_command('file.save', true);
    }
  };

  this.file_save = function()
  {
    if (!this.file_editor)
      return;

    var content = this.file_editor.getContent();
    this.file_editor.disable();

    // because we currently can edit only text file
    // and we do not expect them to be very big, we save
    // file in a very simple way, no upload progress, etc.
    this.set_busy(true, 'saving');
    this.request('file_update', {file: this.env.file, content: content, info: 1}, 'file_save_response');
  };

  this.file_save_response = function(response)
  {
    this.file_editor.enable();

    if (!this.response(response))
      return;

    // update file properties table
    var table = $('#filedata table'), file = response.result;

    if (response.result) {
      $('td.filetype', table).text(file.type);
      $('td.filesize', table).text(this.file_size(file.size));
      $('td.filemtime', table).text(file.mtime);
    }
  };


  /*********************************************************/
  /*********             Utilities                 *********/
  /*********************************************************/

  // modal dialog popup
  this.modal_dialog = function(content, buttons, opts)
  {
    var settings = {position: 'cm', btns: {}, fxShow: 'fade'},
      dialog = $('<div class="_wModal"></div>'),
      body = $('<div class="_wModal"></div>'),
      head, foot, footer = [];

    // title bar
    if (opts && opts.title)
      $('<div class="_wModal_header"></div>')
        .append($('<span>').text(opts.title))
        .appendTo(body);

    // dialog content
    if (typeof content != 'object')
      content = $('<div></div>').html(content);

    content.addClass('_wModal_msg').appendTo(body);

    // buttons
    $.each(buttons, function(i, v) {
      var n = i.replace(/[^a-z0-9_]/ig, '');
      settings.btns[n] = v;
      footer.push({name: n, label: ui.t(i)});
    });

//    if (!settings.btns.cancel && (!opts || !opts.no_cancel))
//      settings.btns.cancel = function() { this.hide(); };

    if (footer.length) {
      foot = $('<div class="_wModal_btns"></div>');
      $.each(footer, function() {
        $('<div></div>').addClass('_wModal_btn_' + this.name).text(this.label).appendTo(foot);
      });

      body.append(foot);
    }

    // configure and display dialog
    dialog.append(body).wModal(settings).wModal('show');
  };

  // Display folder creation form
  this.form_show = function(name)
  {
    var form = $('#' + name + '-form');
    $('#forms > form').hide();
    form.show();
    $('#taskcontent').css('top', form.height() + 20);

    return form;
  };

  // Display folder creation form
  this.form_hide = function(name)
  {
    var form = $('#' + name + '-form');
    form.hide();
    $('#taskcontent').css('top', 10);
  };

  // loads a file content into an iframe (with loading image)
  this.load_file = function(content, filedata)
  {
    var iframe = $(content);

    if (!iframe.length)
      return;

    var href = filedata.href,
      div = iframe.parent(),
      loader = $('#loader'),
      offset = div.offset(),
      w = loader.width(), h = loader.height(),
      width = div.width(), height = div.height();

    loader.css({
      top: offset.top + height/2 - h/2 - 20,
      left: offset.left + width/2 - w/2
      }).show();

    iframe.css('opacity', 0.1)
      .load(function() { ui.loader_hide(this); })
      .attr('src', href);

    // some content, e.g. movies or flash doesn't execute onload on iframe
    // let's wait some time and check document ready state
    if (!/^text/i.test(filedata.mimetype))
      setTimeout(function() {
        // there sometimes "Permission denied to access propert document", use try/catch
        try {
          $(iframe.get(0).contentWindow.document).ready(function() {
            parent.ui.loader_hide(content);
          });
        } catch (e) {};
      }, 1000);
  };

  // hide content loader element, show content element
  this.loader_hide = function(content)
  {
    $('#loader').hide();
    $(content).css('opacity', 1);

    var win = content.contentWindow;

    this.file_editor = null;

    try {
      // it throws "permission denied" sometimes
      if (win.file_editor && win.file_editor.editable)
        this.file_editor = win.file_editor;
    }
    catch (e) {}

    if (this.file_editor)
      ui.enable_command('file.edit', true);
  };
};

// Initialize application object (don't change var name!)
var ui = $.extend(new files_api(), new files_ui());

// general click handler
$(document).click(function() {
  $('.popup').hide();
  ui.file_rename_stop();
}).ready(function() {
  ui.init();
});
