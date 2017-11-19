/**
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2015, Kolab Systems AG                                |
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

function files_api()
{
  var ref = this;

  // default config
  this.sessions = {};
  this.translations = {};
  this.env = {
    url: 'api/',
    directory_separator: '/',
    resources_dir: 'resources'
  };


  /*********************************************************/
  /*********          Basic utilities              *********/
  /*********************************************************/

  // set environment variable(s)
  this.set_env = function(p, value)
  {
    if (p != null && typeof p === 'object' && !value)
      for (var n in p)
        this.env[n] = p[n];
    else
      this.env[p] = value;
  };

  // add a localized label(s) to the client environment
  this.tdef = function(p, value)
  {
    if (typeof p == 'string')
      this.translations[p] = value;
    else if (typeof p == 'object')
      $.extend(this.translations, p);
  };

  // return a localized string
  this.t = function(label)
  {
    if (this.translations[label])
      return this.translations[label];
    else
      return label;
  };

  /********************************************************/
  /*********        Remote request methods        *********/
  /********************************************************/

  // send a http POST request to the API service
  this.post = function(action, data, func)
  {
    var url = this.env.url + '?method=' + action;

    if (!func) func = 'response';

    this.set_request_time();

    return $.ajax({
      type: 'POST', url: url, data: JSON.stringify(data), dataType: 'json',
      contentType: 'application/json; charset=utf-8',
      success: function(response) { if (typeof func == 'function') func(response); else ref[func](response); },
      error: function(o, status, err) { ref.http_error(o, status, err, data); },
      cache: false,
      beforeSend: function(xmlhttp) { xmlhttp.setRequestHeader('X-Session-Token', ref.env.token); }
    });
  };

  // send a http GET request to the API service
  this.get = function(action, data, func)
  {
    var url = this.env.url;

    if (!func) func = 'response';

    this.set_request_time();
    data.method = action;

    return $.ajax({
      type: 'GET', url: url, data: data, dataType: 'json',
      success: function(response) { if (typeof func == 'function') func(response); else ref[func](response); },
      error: function(o, status, err) { ref.http_error(o, status, err, data); },
      cache: false,
      beforeSend: function(xmlhttp) { xmlhttp.setRequestHeader('X-Session-Token', ref.env.token); }
    });
  };

  // send request with auto-selection of POST/GET method
  this.request = function(action, data, func)
  {
    // Use POST for modification actions with probable big request size
    var method = /_(create|delete|move|copy|update|auth|subscribe|unsubscribe|invite|decline|request|accept|remove)$/.test(action) ? 'post' : 'get';
    return this[method](action, data, func);
  };

  // handle HTTP request errors
  this.http_error = function(request, status, err, data)
  {
    var errmsg = request.statusText;

    this.set_busy(false);
    request.abort();

    if (request.status && errmsg)
      this.display_message(this.t('servererror') + ' (' + errmsg + ')', 'error');
  };

  this.response = function(response)
  {
    this.update_request_time();
    this.set_busy(false);

    return this.response_parse(response);
  };

  this.response_parse = function(response)
  {
    if (!response || response.status != 'OK') {
      // Logout on invalid-session error
      if (response && response.code == 403)
        this.logout(response);
      else
        this.display_message(response && response.reason ? response.reason : this.t('servererror'), 'error');

      return false;
    }

    return true;
  };


  /*********************************************************/
  /*********             Utilities                 *********/
  /*********************************************************/

  // Called on "session expired" session
  this.logout = function(response) {};

  // set state
  this.set_busy = function(state, message) {};

  // displays error message
  this.display_message = function(label, type) {};

  // called when a request timed out
  this.request_timed_out = function() {};

  // called on start of the request
  this.set_request_time = function() {};

  // called on request response
  this.update_request_time = function() {};


  /*********************************************************/
  /*********             Helpers                   *********/
  /*********************************************************/

  // compose a valid url with the given parameters
  this.url = function(action, query)
  {
    var k, param = {},
      querystring = typeof query === 'string' ? '&' + query : '';

    if (typeof action !== 'string')
      query = action;
    else if (!query || typeof query !== 'object')
      query = {};

    // overwrite task name
    if (action)
      query.method = action;

    // remove undefined values
    for (k in query) {
      if (query[k] !== undefined && query[k] !== null)
        param[k] = query[k];
    }

    return '?' + $.param(param) + querystring;
  };

  // fill folder selector with options
  this.folder_select_element = function(select, params)
  {
    var options = [],
      selected = params && params.selected ? params.selected : this.env.folder;

    if (params && params.empty)
      options.push($('<option>').val('').text('---'));

    $.each(this.env.folders, function(i, f) {
      var n, name = escapeHTML(f.name);

      // skip read-only folders
      if (params && params.writable && (f.readonly || f.virtual)) {
        var folder, found = false, prefix = i + ref.env.directory_separator;

        // for virtual folders check if there's any writable subfolder
        for (n in ref.env.folders) {
          if (n.indexOf(prefix) === 0) {
            folder = ref.env.folders[n];
            if (!folder.virtual && !folder.readonly) {
              found = true;
              break;
            }
          }
        }

        if (!found)
          return;
      }

      for (n=0; n<f.depth; n++)
        name = '&nbsp;&nbsp;&nbsp;' + name;

      options.push($('<option>').val(i).html(name));
    });

    select.empty().append(options);

    if (selected)
      select.val(selected);
  };

  // Folder list parser, converts it into structure
  this.folder_list_parse = function(list, num, subscribed)
  {
    var i, n, j, items, items_len, f, tmp, folder, readonly,
      subs_support, subs_prefixes = {}, found,
      separator = this.env.directory_separator,
      len = list ? list.length : 0, folders = {};

    if (!num) num = 1;

    if (subscribed === undefined)
      subscribed = true;

    // prepare subscriptions support detection
    if (len && this.env.caps) {
      subs_support = !!this.env.caps.SUBSCRIPTIONS;
      $.each(this.env.caps.MOUNTPOINTS || [], function(i, v) {
        subs_prefixes[i] = !!v.SUBSCRIPTIONS;
      });
    }

    for (i=0; i<len; i++) {
      folder = list[i];
      readonly = false;

      // in extended format folder is an object
      if (typeof folder !== 'string') {
        readonly = folder.readonly;
        folder = folder.folder;
      }

      items = folder.split(separator);
      items_len = items.length;

      for (n=0; n<items_len-1; n++) {
        tmp = items.slice(0, n+1);
        f = tmp.join(separator);
        if (!folders[f])
          folders[f] = {name: tmp.pop(), depth: n, id: 'f'+num++, virtual: 1};
      }

      folders[folder] = {
        name: items.pop(),
        depth: items_len-1,
        id: 'f' + num++,
        readonly: readonly
      };

      // set subscription flag, leave undefined if the source does not support subscriptions
      found = false;
      for (j in subs_prefixes) {
        if (folder === j) {
          // this is a mount point
          found = true;
          break;
        }
        if (folder.indexOf(j + separator) === 0) {
          if (subs_prefixes[j])
            folders[folder].subscribed = subscribed;
          found = true;
          break;
        }
      }

      if (!found && subs_support)
        folders[folder].subscribed = subscribed;
    }

    return folders;
  };

  // folder structure presentation (structure icons)
  this.folder_list_tree = function(folders)
  {
    var i, n, diff, prefix, tree = [], folder;

    for (i in folders) {
      items = i.split(this.env.directory_separator);
      items_len = items.length;

      // skip root
      if (items_len < 2) {
        tree = [];
        continue;
      }

      folders[i].tree = [1];
      prefix = items.slice(0, items_len-1).join(this.env.directory_separator) + this.env.directory_separator;

      for (n=0; n<tree.length; n++) {
        folder = tree[n];
        diff = folders[folder].depth - (items_len - 1);
        if (diff >= 0 && folder.indexOf(prefix) === 0)
          folders[folder].tree[diff] |= 2;
      }

      tree.push(i);
    }

    for (i in folders) {
      if (tree = folders[i].tree) {
        var html = '', divs = [];
        for (n=0; n<folders[i].depth; n++) {
          if (tree[n] > 2)
            divs.push({'class': 'l3', width: 15});
          else if (tree[n] > 1)
            divs.push({'class': 'l2', width: 15});
          else if (tree[n] > 0)
            divs.push({'class': 'l1', width: 15});
          // separator
          else if (divs.length && !divs[divs.length-1]['class'])
            divs[divs.length-1].width += 15;
          else
            divs.push({'class': null, width: 15});
        }

        for (n=divs.length-1; n>=0; n--) {
          if (divs[n]['class'])
            html += '<span class="tree '+divs[n]['class']+'" />';
          else
            html += '<span style="width:'+divs[n].width+'px" />';
        }

        if (html)
          $('#' + folders[i].id + ' span.branch').html(html);
      }
    }
  };

  // Get editing sessions on the specified file
  this.file_sessions = function(file)
  {
    var sessions = [], folder = this.file_path(file);

    $.each(this.sessions[folder] || {}, function(session_id, session) {
      if (session.file == file) {
        session.id = session_id;
        sessions.push(session);
      }
    });

    return sessions;
  };

  // convert content-type string into class name
  this.file_type_class = function(type)
  {
    if (!type)
      return '';

    var classes = [];

    classes.push(type.replace(/\/.*/, ''));
    classes.push(type.replace(/[^a-z0-9]/g, '_'));

    return classes.join(' ');
  };

  // convert bytes into number with size unit
  this.file_size = function(size)
  {
    if (size >= 1073741824)
      return parseFloat(size/1073741824).toFixed(2) + ' GB';
    if (size >= 1048576)
      return parseFloat(size/1048576).toFixed(2) + ' MB';
    if (size >= 1024)
      return parseInt(size/1024) + ' kB';

    return parseInt(size || 0) + ' B';
  };

  // Extract file name from full path
  this.file_name = function(path)
  {
    var path = path.split(this.env.directory_separator);
    return path.pop();
  };

  // Extract file path from full path
  this.file_path = function(path)
  {
    var path = path.split(this.env.directory_separator);
    path.pop();
    return path.join(this.env.directory_separator);
  };

  // compare two sortable objects
  this.sort_compare = function(data1, data2)
  {
    var key = this.env.sort_col || 'name';

    if (key == 'mtime')
      key = 'modified';

    data1 = data1[key];
    data2 = data2[key];

    if (key == 'size' || key == 'modified')
      // numeric comparison
      return this.env.sort_reverse ? data2 - data1 : data1 - data2;
    else {
      // use Array.sort() for string comparison
      var arr = [data1, data2];
      arr.sort(function (a, b) {
        // @TODO: use localeCompare() arguments for better results
        return a.localeCompare(b);
      });

      if (this.env.sort_reverse)
        arr.reverse();

      return arr[0] === data2 ? 1 : -1;
    }
  };

  // Checks if specified mimetype is supported natively by the browser (return 1)
  // or can be displayed in the browser using File API viewer (return 2)
  // or is editable (using File API viewer, Manticore or WOPI) (return 4)
  this.file_type_supported = function(type, capabilities)
  {
    var i, t, res = 0, regexps = [], img = 'jpg|jpeg|gif|bmp|png',
      caps = this.env.browser_capabilities || {},
      doc = /^application\/vnd.oasis.opendocument.(text)$/i;

    type = String(type).toLowerCase();

    if (capabilities) {
      // Manticore?
      $.each(capabilities.MANTICORE_EDITABLE || [], function() {
        if (type == this) {
          res |= 4;
          return false;
        }
      });
      // old version of the check
      if (capabilities && capabilities.MANTICORE && doc.test(type))
        res |= 4;

      // WOPI (Collabora Online)?
      $.each(capabilities.WOPI_EDITABLE || [], function() {
        if (type == this) {
          res |= 4;
          return false;
        }
      });
    }

    if (caps.tiff)
      img += '|tiff';

    if ((new RegExp('^image/(' + img + ')$', 'i')).test(type))
      res |= 1;

    // prefer text viewer for any text type
    if (/^text\/(?!(pdf|x-pdf))/i.test(type))
      res |= 2 | 4;

    if (caps.pdf) {
      regexps.push(/^application\/(pdf|x-pdf|acrobat|vnd.pdf)/i);
      regexps.push(/^text\/(pdf|x-pdf)/i);
    }

    if (caps.flash)
      regexps.push(/^application\/x-shockwave-flash/i);

    for (i in regexps)
      if (regexps[i].test(type))
        res |= 1;

    for (i in navigator.mimeTypes) {
      t = navigator.mimeTypes[i].type;
      if (t == type && navigator.mimeTypes[i].enabledPlugin)
        res |= 1;
    }

    // types with viewer support
    if ($.inArray(type, this.env.supported_mimetypes) > -1)
      res |= 2;

    return res;
  };

  // Return browser capabilities
  this.browser_capabilities = function()
  {
    var i, caps = [], ctypes = ['pdf', 'flash', 'tiff'];

    for (i in ctypes)
      if (this.env.browser_capabilities[ctypes[i]])
        caps.push(ctypes[i]);

    return caps;
  };

  // Checks browser capabilities eg. PDF support, TIF support
  this.browser_capabilities_check = function()
  {
    if (!this.env.browser_capabilities)
      this.env.browser_capabilities = {};

    if (this.env.browser_capabilities.pdf === undefined)
      this.env.browser_capabilities.pdf = this.pdf_support_check();

    if (this.env.browser_capabilities.flash === undefined)
      this.env.browser_capabilities.flash = this.flash_support_check();

    if (this.env.browser_capabilities.tiff === undefined)
      this.tiff_support_check();
  };

  this.tiff_support_check = function()
  {
    var img = new Image(), ref = this;

    img.onload = function() { ref.env.browser_capabilities.tiff = 1; };
    img.onerror = function() { ref.env.browser_capabilities.tiff = 0; };
    img.src = this.env.resources_dir + '/blank.tiff';
  };

  this.pdf_support_check = function()
  {
    var plugin = navigator.mimeTypes ? navigator.mimeTypes["application/pdf"] : {},
      plugins = navigator.plugins,
      len = plugins.length,
      regex = /Adobe Reader|PDF|Acrobat/i,
      ref = this;

    if (plugin && plugin.enabledPlugin)
        return 1;

    if (window.ActiveXObject) {
      try {
        if (axObj = new ActiveXObject("AcroPDF.PDF"))
          return 1;
      }
      catch (e) {}
      try {
        if (axObj = new ActiveXObject("PDF.PdfCtrl"))
          return 1;
      }
      catch (e) {}
    }

    for (i=0; i<len; i++) {
      plugin = plugins[i];
      if (typeof plugin === 'String') {
        if (regex.test(plugin))
          return 1;
      }
      else if (plugin.name && regex.test(plugin.name))
        return 1;
    }

    return 0;
  };

  this.flash_support_check = function()
  {
    var plugin = navigator.mimeTypes ? navigator.mimeTypes["application/x-shockwave-flash"] : {};

    if (plugin && plugin.enabledPlugin)
        return 1;

    if (window.ActiveXObject) {
      try {
        if (axObj = new ActiveXObject("ShockwaveFlash.ShockwaveFlash"))
          return 1;
      }
      catch (e) {}
    }

    return 0;
  };

  // converts number of seconds into HH:MM:SS format
  this.time_format = function(s)
  {
    s = parseInt(s);

    if (s >= 60*60*24)
      return '-';

    return (new Date(1970, 1, 1, 0, 0, s, 0)).toTimeString().replace(/.*(\d{2}:\d{2}:\d{2}).*/, '$1');
  };

  // same as str.split(delimiter) but it ignores delimiters within quoted strings
  this.explode_quoted_string = function(str, delimiter)
  {
    var result = [],
      strlen = str.length,
      q, p, i, chr, last;

    for (q = p = i = 0; i < strlen; i++) {
      chr = str.charAt(i);
      if (chr == '"' && last != '\\') {
        q = !q;
      }
      else if (!q && chr == delimiter) {
        result.push(str.substring(p, i));
        p = i + 1;
      }
      last = chr;
    }

    result.push(str.substr(p));
    return result;
  };
};

/**
 * Class implementing Document Editor Host API
 * supporting Manticore and Collabora Online
 *
 * Configuration:
 *    iframe - editor iframe element
 *    title_input - document title element
 *    export_menu - export formats list
 *    members_list - collaborators list
 *    photo_url - <img> src for a collaborator
 *    photo_default_url - default image of a collaborator
 *    domain - iframe origin
 *
 *    set_busy, display_message, hide_message, gettext - common methods
 *
 *    api - Chwala files_api instance
 *    interval - how often to check for invitations in seconds (default: 60)
 *    owner - user identifier
 *    invitationMore - add "more" link into invitation notices
 *    invitationChange - method to handle invitation state updates
 *    invitationSave - method to handle invitation state update
 */
function document_editor_api(conf)
{
  var domain, editor,
    is_wopi = false,
    locks = {},
    callbacks = {},
    members = {},
    env = {},
    self = this;

  // Sets state
  this.set_busy = function(state, message)
  {
    if (conf.set_busy)
      return conf.set_busy(state, message);
  };

  // Displays error/notification message
  this.display_message = function(label, type, is_txt, timeout)
  {
    if (conf.display_message)
      return conf.display_message(label, type, is_txt, timeout);

    if (type == 'error')
      alert(is_txt ? label : this.gettext(label));
  };

  // Hides the error/notification message
  this.hide_message = function(id)
  {
    if (conf.hide_message)
      return conf.hide_message(id);
  };

  // Localization method
  this.gettext = function(label)
  {
    if (conf.gettext)
      return conf.gettext(label);

    return label;
  };

  // Handle messages from the editor
  this.message_handler = function(data)
  {
    var result;

    data = this.message_convert(data);

    if (data.id && callbacks[data.id])
      result = callbacks[data.id](data);
    else if (typeof data.callback == 'function')
      result = data.callback(data);

    if (result !== false && data.name && conf[data.name])
      result = conf[data.name](data);

    delete callbacks[data.id];

    if (locks[data.id]) {
      this.set_busy(false);
      this.hide_message(data.id);
      delete locks[data.id];
    }

    if (result === false)
      return;

    switch (data.name) {
      case 'ready':
        this.ready();
        break;

      case 'titleChanged':
        if (conf.title_input)
          $(conf.title_input).val(data.value);
        break;

      case 'memberAdded':
        // @TODO: display notification?
        if (conf.members_list)
          $(conf.members_list).append(this.member_item(data));
        break;

      case 'memberRemoved':
        // @TODO: display notification?
        if (conf.members_list && members[data.memberId]) {
          $('#' + members[data.memberId].id, conf.members_list).remove();
          delete members[data.memberId];
        }
        break;

      case 'sessionClosed':
        if (!env.session_terminated)
          this.display_message('sessionterminated', 'error');
        break;
    }
  };

  // Convert WOPI postMessage into internal (Manticore) format
  this.message_convert = function(data)
  {
    // In WOPI data is JSON-stringified
    if ($.type(data) == 'string')
      data = JSON.parse(data);

    // non-WOPI format
    if (!data.MessageId)
      return data;

    var value = data.Values,
      result = {name: data.MessageId, wopi: true, id: data.MessageId},
      member_fn = function(value) {
        var color = value.Color;

        // make sure color is in css hex format
        if (color) {
          if ($.type(color) == 'string' && color.charAt(0) != '#')
            color = '#' + color;
          else if ($.type(color) == 'number')
            color = ((color)>>>0).toString(16).slice(-6);
            color = '#' + ("000000").substring(0, 6 - color.length) + color;
        }

        return {
          memberId: value.ViewId,
          email: value.UserId,
          fullName: value.UserName,
          color: color
        };
      };

    switch (result.name) {
      // WOPI editor is ready
      case 'App_LoadingStatus':
        is_wopi = true;

        // wait for 'Document_Loaded' status, this is when
        // we can finally start using the CODE postMessage API
        if (value.Status != 'Document_Loaded')
          return result;

        result.name = 'ready';

        // Enable Save button, there's no documentChanged event in WOPI
        if (typeof conf.documentChanged == 'function')
          conf.documentChanged();
        break;

      // WOPI session member exited
      case 'View_Removed':
        result.name = 'memberRemoved';
        result.memberId = value.ViewId;
        break;

      // WOPI session member entered
      case 'View_Added':
        result.name = 'memberAdded';
        $.extend(result, member_fn(value));
        break;

      // Listing WOPI session members
      case 'Get_Views_Resp':
        result.list = $.map(value || [], member_fn);
        result.callback = function(data) { self.members_list(data.list); return false; };
        break;

      case 'Session_Closed':
        result.name = 'sessionClosed';
        break;

      case 'Get_Export_Formats_Resp':
        result.list = $.map(value || [], function(v) { return {label: v.Label, format: v.Format}; });
        result.callback = function(data) { self.export_menu_init(data.list); return false; };
        break;
    }

    return result;
  };

  // Sends Manticore postMessage
  this.post = function(action, data, callback, lock_label)
  {
    if (is_wopi) {
      // replace Manticore messages with WOPI messages
      // ignore unsupported functionality
      switch (action) {
        case 'getMembers':
          // ignore, Collabora Online sends View_Added for current user
          break;

        case 'actionSave':
          this.wopi_post('Action_Save', {DontTerminateEdit: true, DontSaveIfUnmodified: true});

          // Enable Save button, there's no documentChanged event in WOPI
          if (typeof conf.documentChanged == 'function')
            conf.documentChanged();
          break;

        case 'actionExport':
          this.wopi_post('Action_Export', {Format: data.value});
          break;

        case 'getExportFormats':
          this.wopi_post('Get_Export_Formats');
          break;
      }

      return;
    }

    if (!data) data = {};

    if (lock_label) {
      data.id = this.set_busy(true, this.gettext(lock_label));
      locks[data.id] = true;
    }

    if (!data.id)
      data.id = (new Date).getTime();

    // make sure the id is not in use
    while (callbacks[data.id])
      data.id++;

    data.name = action;

    callbacks[data.id] = callback;

    editor.postMessage(data, domain);
  };

  // Sends WOPI postMessage
  this.wopi_post = function(action, data)
  {
    var msg = {
        MessageId: action,
        SendTime: Date.now(),
        Values: data || {}
      };

    editor.postMessage(JSON.stringify(msg), domain);
  };

  // Callback for 'ready' message
  this.ready = function()
  {
    if (this.init_lock) {
      this.set_busy(false);
      this.hide_message(this.init_lock);
      delete this.init_lock;
    }

    if (conf.export_menu)
      this.export_menu_load();

    if (conf.members_list)
      this.get_members(function(data) { self.members_list(data.value); });

    if (conf.title_input)
      this.get_title(function(data) {
        $(conf.title_input).val(data.value);
      });
  };

  // Save current document
  this.save = function(callback)
  {
    this.post('actionSave', {}, callback, 'saving');
  };

  // Terminate session
  this.terminate = function()
  {
    // remember that user terminated the session
    // to skip the warning on Session_Closed
    env.session_terminated = true;
    conf.sessionClosed = null;

    // we send it only to WOPI editor, Manticore session will
    // be terminated by Chwala backend
    if (is_wopi)
      this.wopi_post('Close_Session');
  };

  // Print document
  this.print = function()
  {
    // this is implemented only by WOPI editor
    if (is_wopi)
      this.wopi_post('Action_Print');
  };

  // Export/download current document
  this.export = function(type, callback)
  {
    // If the specified type is not supported
    // TODO: https://bugs.documentfoundation.org/show_bug.cgi?id=104125
    if ($.inArray(type, env.supported_formats || []) == -1)
      type = (env.supported_formats || [])[0];

    if (type)
      this.post('actionExport', {value: type}, callback);
  };

  // Get supported export formats and create content of menu element
  this.export_menu_load = function()
  {
    this.post('getExportFormats', {}, function(data) { self.export_menu_init(data.value); });
  };

  this.export_menu_init = function(formats)
  {
    var items = [];

    env.supported_formats = [];

    $.each(formats || [], function(i, v) {
      env.supported_formats.push(v.format);
      items.push($('<li>').attr({role: 'menuitem'}).append(
        $('<a>').attr({href: '#', role: 'button', tabindex: 0, 'aria-disabled': false, 'class': 'active'})
          .text(v.label).click(function() { self.export(v.format); })
      ));
    });

    $(conf.export_menu).html('').append(items);
  };

  // Get document title
  this.get_title = function(callback)
  {
    this.post('getTitle', {}, callback);
  };

  // Set document title
  this.set_title = function(title, callback)
  {
    this.post('setTitle', {value: title}, callback);
  };

  // Get document session members
  this.get_members = function(callback)
  {
    this.post('getMembers', {}, callback);
  };

  // Creates session member image element
  this.member_item = function(member, id)
  {
    member.id = 'member' + (id || (new Date).getTime());
    member.name = member.fullName + ' (' + member.email + ')';

    members[member.memberId] = member;

    var img = $('<img>').attr({title: member.name, id: member.id, 'class': 'photo', src: conf.photo_default_url})
        .css({'border-color': member.color})
        .text(name);

    if (conf.photo_url) {
      img.attr('src', conf.photo_url.replace(/%email/, urlencode(member.email)));
      if (conf.photo_default_url)
        img.on('error', function() { this.src = conf.photo_default_url; });
    }

    return img;
  };

  this.members_list = function(list)
  {
    var images = [], id = (new Date).getTime();

    $.each(list || [], function() {
      images.push(self.member_item(this, id++));
    });

    $(conf.members_list).html('').append(images);
  };

  // track changes in invitations
  this.track_invitations = function()
  {
    conf.api.request('invitations', {timestamp: this.invitations_timestamp || -1}, this.parse_invitations);
    this.invitations_timeout = setTimeout(function() { self.track_invitations(); }, (conf.interval || 60) * 1000);
  };

  // parse 'invitations' response
  this.parse_invitations = function(response)
  {
    if (!conf.api.response(response) || !response.result)
      return;

    var invitation_change = function(invitation) {
      var msg = self.invitation_msg(invitation);

      if (conf.invitationMore)
        msg = $('<div>')
          .append($('<span>').text(msg + ' '))
          .append($('<a>').text(self.gettext('more')).attr('id', invitation.id)).html();

      self.display_message(msg, 'notice', true, 30);

      // update existing sessions info
      if (conf.api && conf.api.sessions && invitation.file) {
        var session, folder = conf.api.file_path(invitation.file),
          is_invited = function() {
            return !invitation.is_session_owner && /^(invited|accepted)/.test(invitation.status);
          };

        $.each(conf.api.sessions[folder] || {}, function(i, s) {
          if (i == invitation.session_id || s.file == invitation.file) {
            if (is_invited())
              conf.api.sessions[folder][i].is_invited = true;
            if (s.id == invitation.session_id)
              session = conf.api.sessions[folder][i];
          }
        });

        if (!session) {
          if (!conf.api.sessions[folder])
            conf.api.sessions[folder] = {};
          conf.api.sessions[folder][invitation.session_id] = {
            owner: invitation.owner,
            owner_name: invitation.owner_name,
            is_owner: invitation.is_session_owner,
            is_invited: is_invited(),
            file: invitation.file
          };
        }
      }

      if (conf.invitationChange)
        conf.invitationChange(invitation);
    }

    $.each(response.result.list || [], function(i, invitation) {
      invitation.id = 'i' + (response.result.timestamp + i);
      invitation.is_session_owner = invitation.user != conf.owner;

      // display notifications
      if (!invitation.is_session_owner) {
        if (invitation.status == 'invited' || invitation.status == 'declined-owner' || invitation.status == 'accepted-owner') {
          invitation_change(invitation);
        }
      }
      else {
        if (invitation.status == 'accepted' || invitation.status == 'declined' || invitation.status == 'requested') {
          invitation_change(invitation);
        }
      }
    });

    self.invitations_timestamp = response.result.timestamp;
  };

  this.invitation_msg = function(invitation)
  {
    return self.gettext(invitation.status.replace('-', '') + 'notice')
      .replace('$user', invitation.user_name ? invitation.user_name : invitation.user)
      .replace('$owner', invitation.owner_name ? invitation.owner_name : invitation.owner)
      .replace('$file', invitation.filename);
  };

  // Request access to the editing session
  this.invitation_request = function(invitation)
  {
    var params = {id: invitation.session_id, user: invitation.user || ''};

    conf.api.req = this.set_busy(true, 'invitationrequesting');
    conf.api.request('document_request', params, function(response) {
      self.invitation_response(response, invitation, 'requested');
    });
  };

  // Accept an invitations to the editing session
  this.invitation_accept = function(invitation)
  {
    var params = {id: invitation.session_id, user: invitation.user || ''};

    conf.api.req = this.set_busy(true, 'invitationaccepting');
    conf.api.request('document_accept', params, function(response) {
      self.invitation_response(response, invitation, 'accepted');
    });
  };

  // Decline an invitations to the editing session
  this.invitation_decline = function(invitation)
  {
    var params = {id: invitation.session_id, user: invitation.user || ''};

    conf.api.req = this.set_busy(true, 'invitationdeclining');
    conf.api.request('document_decline', params, function(response) {
      self.invitation_response(response, invitation, 'declined');
    });
  };

  // document_decline response handler
  this.invitation_response = function(response, invitation, status)
  {
    if (!conf.api.response(response))
      return;

    invitation.status = status;

    if (conf.invitationSaved)
      conf.invitationSaved(invitation);
  };


  if (!conf)
    conf = {};

  // Got editor iframe, use editor's API
  if (conf.iframe) {
    // Use iframe's onload event to not send the message to early,
    // if that happens WOPI will not accept any messages later
    $(conf.iframe).on('load', function() { self.wopi_post('Host_PostmessageReady'); });

    editor = conf.iframe.contentWindow;
    domain = conf.domain;

    if (!domain && /^(https?:\/\/[^/]+)/i.test(conf.iframe.src))
      domain = RegExp.$1;

    // Register 'message' event to receive messages from the editor iframe
    window.addEventListener('message', function(event) {
      if (event.source == editor && event.origin == domain) {
        self.message_handler(event.data);
      }
    });

    // Bind for document title changes
    if (conf.title_input)
      $(conf.title_input).change(function() { self.set_title($(this).val()); });

    // Display loading message
    this.init_lock = this.set_busy(true, 'loading');
  }

  if (conf.api)
    this.track_invitations();
};

// Add escape() method to RegExp object
// http://dev.rubyonrails.org/changeset/7271
RegExp.escape = function(str)
{
  return String(str).replace(/([.*+?^=!:${}()|[\]\/\\])/g, '\\$1');
};

// define String's startsWith() method for old browsers
if (!String.prototype.startsWith) {
  String.prototype.startsWith = function(search, position) {
    position = position || 0;
    return this.slice(position, search.length) === search;
  };
};

// make a string URL safe (and compatible with PHP's rawurlencode())
function urlencode(str)
{
  if (window.encodeURIComponent)
    return encodeURIComponent(str).replace('*', '%2A');

  return escape(str)
    .replace('+', '%2B')
    .replace('*', '%2A')
    .replace('/', '%2F')
    .replace('@', '%40');
};

function escapeHTML(str)
{
  return str === undefined ? '' : String(str)
    .replace(/&/g, '&amp;')
    .replace(/>/g, '&gt;')
    .replace(/</g, '&lt;');
};

function object_is_empty(obj)
{
  if (obj)
    for (var i in obj)
      if (i !== null)
        return true;

  return false;
}
