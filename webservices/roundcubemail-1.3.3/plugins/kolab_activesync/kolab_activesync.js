/**
 * Client scripts for the Kolab ActiveSync configuration utitlity
 *
 * @version @package_version@
 * @author Aleksander Machniak <machniak@kolabsys.com>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2011-2012, Kolab Systems AG <contact@kolabsys.com>
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
 */

function kolab_activesync_config()
{
  /* private members */
  var me = this,
    http_lock = null,
    active_device = null;

  rcmail.register_command('plugin.save-config', save_config);
  rcmail.register_command('plugin.delete-device', delete_device_config);
  rcmail.addEventListener('plugin.activesync_save_complete', save_complete);

  if (rcmail.gui_objects.devicelist) {
    var devicelist = new rcube_list_widget(rcmail.gui_objects.devicelist,
      { multiselect:true, draggable:false, keyboard:true });
    devicelist.addEventListener('select', select_device).init().focus();

    // load frame if there are no devices
    if (!rcmail.env.devicecount)
      device_select();
  }
  else if (rcmail.env.action != 'edit-folder') {
    if (rcmail.env.active_device)
      rcmail.enable_command('plugin.save-config', true);

    $('input.alarm').change(function(e) {
      if (this.checked)
        $('#'+this.id.replace(/_alarm/, '')).prop('checked', this.checked);
    });

    $('input.subscription').change(function(e) {
      if (!this.checked)
        $('#'+this.id+'_alarm').prop('checked', false);
    });

    var fn = function(elem) {
      var classname = elem.className,
        list = $(elem).closest('table').find('input.' + classname),
        check = list.not(':checked').length > 0;

      list.prop('checked', check).change();
    };

    $('th.subscription,th.alarm').click(function() { fn(this); })
      .keydown(function(e) { if (e.which == 13 || e.which == 32) fn(this); });
  }

  /* private methods */
  function select_device(list)
  {
    active_device = list.get_single_selection();

    if (active_device)
      device_select(active_device);
    else if (rcmail.env.contentframe)
      rcmail.show_contentframe(false);
  };

  function device_select(id)
  {
    var win, target = window, url = '&_action=plugin.activesync-config';

    if (id)
      url += '&_id='+urlencode(id);
    else if (!rcmail.env.devicecount)
      url += '&_init=1';
    else {
      rcmail.show_contentframe(false);
      return;
    }

    if (win = rcmail.get_frame_window(rcmail.env.contentframe)) {
      target = win;
      url += '&_framed=1';
    }

    if (String(target.location.href).indexOf(url) >= 0)
      rcmail.show_contentframe(true);
    else
      rcmail.location_href(rcmail.env.comm_path+url, target, true);
  };

  // submit current configuration form to server
  function save_config()
  {
    // TODO: validate device info
    var data = {
      cmd: 'save',
      id: rcmail.env.active_device,
      devicealias: $('#config-device-alias').val()
    };

    if (data.devicealias == data.id)
      data.devicealias = '';

    data.subscribed = {};
    $('input.subscription:checked').each(function(i, elem) {
      data.subscribed[elem.value] = 1;
    });
    $('input.alarm:checked').each(function(i, elem) {
      if (data.subscribed[elem.value])
        data.subscribed[elem.value] = 2;
    });

    http_lock = rcmail.set_busy(true, 'kolab_activesync.savingdata');
    rcmail.http_post('plugin.activesync-json', data, http_lock);
  };

  // callback function when saving has completed
  function save_complete(p)
  {
    // device updated
    if (p.success && p.alias)
      parent.window.activesync_object.update_list(p.id, p.alias);

    // device deleted
    if (p.success && p.id && p['delete']) {
      active_device = null;
      device_select();
      devicelist.remove_row(p.id);
      rcmail.enable_command('plugin.delete-device', false);
    }
  };
  // handler for delete commands
  function delete_device_config()
  {
    if (active_device && confirm(rcmail.gettext('devicedeleteconfirm', 'kolab_activesync'))) {
      http_lock = rcmail.set_busy(true, 'kolab_activesync.savingdata');
      rcmail.http_post('plugin.activesync-json', { cmd:'delete', id:active_device }, http_lock);
    }
  };

  this.update_list = function(id, name)
  {
    $('#devices-table tr.selected span.devicealias').html(name);
  };

  this.update_sync_data = function(elem)
  {
    elem.name.match(/^_(subscriptions|alarms)\[(.+)\]$/);

    var flag, type = RegExp.$1, device = RegExp.$2,
        http_lock = rcmail.set_busy(true, 'kolab_activesync.savingdata');

    // set subscription flag
    if (elem.checked) {
      flag = type == 'alarms' ? 2 : 1;
    }
    else {
      flag = type == 'alarms' ? 1 : 0;
    }

    // make sure subscription checkbox is checked if alarms is checked
    if (flag == 2) {
      $('input[name="_subscriptions[' + device + ']"]').prop('checked', true);
    }
    // make sure alarms checkbox is unchecked if subscription is unchecked
    else if (flag == 0) {
      $('input[name="_alarms[' + device + ']"]').prop('checked', false);
    }

    // send the request
    rcmail.http_post('plugin.activesync-json', {cmd: 'update', id: device, flag: flag, folder: rcmail.env.folder}, http_lock);
  };
};

window.rcmail && rcmail.addEventListener('init', function(evt) {
  activesync_object = new kolab_activesync_config();
});
