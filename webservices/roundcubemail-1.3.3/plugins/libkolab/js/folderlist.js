/**
 * Kolab groupware folders treelist widget
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2014, Kolab Systems AG <contact@kolabsys.com>
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

function kolab_folderlist(node, p)
{
    // extends treelist.js
    rcube_treelist_widget.call(this, node, p);

    // private vars
    var me = this;
    var search_results;
    var search_results_widget;
    var search_results_container;
    var listsearch_request;
    var search_messagebox;

    var Q = rcmail.quote_html;

    // render the results for folderlist search
    function render_search_results(results)
    {
        if (results.length) {
          // create treelist widget to present the search results
          if (!search_results_widget) {
              var list_id = (me.container.attr('id') || p.id_prefix || '0')
              search_results_container = $('<div class="searchresults"></div>')
                  .html(p.search_title ? '<h2 class="boxtitle" id="st:' + list_id + '">' + p.search_title + '</h2>' : '')
                  .insertAfter(me.container);

              search_results_widget = new rcube_treelist_widget('<ul>', {
                  id_prefix: p.id_prefix,
                  id_encode: p.id_encode,
                  id_decode: p.id_decode,
                  selectable: false
              });
              // copy classes from main list
              search_results_widget.container.addClass(me.container.attr('class')).attr('aria-labelledby', 'st:' + list_id);

              // register click handler on search result's checkboxes to select the given item for listing
              search_results_widget.container
                  .appendTo(search_results_container)
                  .on('click', 'input[type=checkbox], a.subscribed, span.subscribed', function(e) {
                      var node, has_children, li = $(this).closest('li'),
                          id = li.attr('id').replace(new RegExp('^'+p.id_prefix), '');
                      if (p.id_decode)
                          id = p.id_decode(id);
                      node = search_results_widget.get_node(id);
                      has_children = node.children && node.children.length;

                      e.stopPropagation();
                      e.bubbles = false;

                      // activate + subscribe
                      if ($(e.target).hasClass('subscribed')) {
                          search_results[id].subscribed = true;
                          $(e.target).attr('aria-checked', 'true');
                          li.children().first()
                              .toggleClass('subscribed')
                              .find('input[type=checkbox]').get(0).checked = true;

                          if (has_children && search_results[id].group == 'other user') {
                              li.find('ul li > div').addClass('subscribed')
                                  .find('a.subscribed').attr('aria-checked', 'true');;
                          }
                      }
                      else if (!this.checked) {
                          return;
                      }

                      // copy item to the main list
                      add_result2list(id, li, true);

                      if (has_children) {
                          li.find('input[type=checkbox]').first().prop('disabled', true).prop('checked', true);
                          li.find('a.subscribed, span.subscribed').first().hide();
                      }
                      else {
                          li.remove();
                      }

                      // set partial subscription status
                      if (search_results[id].subscribed && search_results[id].parent && search_results[id].group == 'other') {
                          parent_subscription_status($(me.get_item(id, true)));
                      }

                      // set focus to cloned checkbox
                      if (rcube_event.is_keyboard(e)) {
                        $(me.get_item(id, true)).find('input[type=checkbox]').first().focus();
                      }
                  })
                  .on('click', function(e) {
                      var prop, id = String($(e.target).closest('li').attr('id')).replace(new RegExp('^'+p.id_prefix), '');
                      if (p.id_decode)
                          id = p.id_decode(id);

                      if (!rcube_event.is_keyboard(e) && e.target.blur)
                        e.target.blur();

                      // forward event
                      if (prop = search_results[id]) {
                        e.data = prop;
                        if (me.triggerEvent('click-item', e) === false) {
                          e.stopPropagation();
                          return false;
                        }
                      }
                  });
          }

          // add results to list
          for (var prop, item, i=0; i < results.length; i++) {
              prop = results[i];
              item = $(prop.html);
              search_results[prop.id] = prop;
              search_results_widget.insert({
                  id: prop.id,
                  classes: [ prop.group || '' ],
                  html: item,
                  collapsed: true,
                  virtual: prop.virtual
              }, prop.parent);

              // disable checkbox if item already exists in main list
              if (me.get_node(prop.id) && !me.get_node(prop.id).virtual) {
                  item.find('input[type=checkbox]').first().prop('disabled', true).prop('checked', true);
                  item.find('a.subscribed, span.subscribed').hide();
              }
          }

          search_results_container.show();
        }
    }

    // helper method to (recursively) add a search result item to the main list widget
    function add_result2list(id, li, active)
    {
        var node = search_results_widget.get_node(id),
            prop = search_results[id],
            parent_id = prop.parent || null,
            has_children = node.children && node.children.length,
            dom_node = has_children ? li.children().first().clone(true, true) : li.children().first(),
            childs = [];

        // find parent node and insert at the right place
        if (parent_id && me.get_node(parent_id)) {
            dom_node.children('span,a').first().html(Q(prop.editname || prop.listname));
        }
        else if (parent_id && search_results[parent_id]) {
            // copy parent tree from search results
            add_result2list(parent_id, $(search_results_widget.get_item(parent_id)), false);
        }
        else if (parent_id) {
            // use full name for list display
            dom_node.children('span,a').first().html(Q(prop.name));
        }

        // replace virtual node with a real one
        if (me.get_node(id)) {
            $(me.get_item(id, true)).children().first()
                .replaceWith(dom_node)
                .removeClass('virtual');
        }
        else {
            // copy childs, too
            if (has_children && prop.group == 'other user') {
                for (var cid, j=0; j < node.children.length; j++) {
                    if ((cid = node.children[j].id) && search_results[cid]) {
                        childs.push(search_results_widget.get_node(cid));
                    }
                }
            }

            // move this result item to the main list widget
            me.insert({
                id: id,
                classes: [ prop.group || '' ],
                virtual: prop.virtual,
                html: dom_node,
                level: node.level,
                collapsed: true,
                children: childs
            }, parent_id, prop.group);
        }

        delete prop.html;
        prop.active = active;
        me.triggerEvent('insert-item', { id: id, data: prop, item: li });

        // register childs, too
        if (childs.length) {
            for (var cid, j=0; j < node.children.length; j++) {
                if ((cid = node.children[j].id) && search_results[cid]) {
                    prop = search_results[cid];
                    delete prop.html;
                    prop.active = false;
                    me.triggerEvent('insert-item', { id: cid, data: prop });
                }
            }
        }
    }

    // update the given item's parent's (partial) subscription state
    function parent_subscription_status(li)
    {
        var top_li = li.closest(me.container.children('li')),
            all_childs = $('li > div:not(.treetoggle)', top_li),
            subscribed = all_childs.filter('.subscribed').length;

        if (subscribed == 0) {
            top_li.children('div:first').removeClass('subscribed partial');
        }
        else {
            top_li.children('div:first')
                .addClass('subscribed')[subscribed < all_childs.length ? 'addClass' : 'removeClass']('partial');
        }
    }

    // do some magic when search is performed on the widget
    this.addEventListener('search', function(search) {
        // hide search results
        if (search_results_widget) {
            search_results_container.hide();
            search_results_widget.reset();
        }
        search_results = {};

        if (search_messagebox)
            rcmail.hide_message(search_messagebox);

        // send search request(s) to server
        if (search.query && search.execute) {
            // require a minimum length for the search string
            if (rcmail.env.autocomplete_min_length && search.query.length < rcmail.env.autocomplete_min_length && search.query != '*') {
                search_messagebox = rcmail.display_message(
                    rcmail.get_label('autocompletechars').replace('$min', rcmail.env.autocomplete_min_length));
                return;
            }

            if (listsearch_request) {
                // ignore, let the currently running request finish
                if (listsearch_request.query == search.query) {
                    return;
                }
                else { // cancel previous search request
                    rcmail.multi_thread_request_abort(listsearch_request.id);
                    listsearch_request = null;
                }
            }

            var sources = p.search_sources || [ 'folders' ];
            var reqid = rcmail.multi_thread_http_request({
                items: sources,
                threads: rcmail.env.autocomplete_threads || 1,
                action:  p.search_action || 'listsearch',
                postdata: { action:'search', q:search.query, source:'%s' },
                lock: rcmail.display_message(rcmail.get_label('searching'), 'loading'),
                onresponse: render_search_results,
                whendone: function(data){
                  listsearch_request = null;
                  me.triggerEvent('search-complete', data);
                }
            });

            listsearch_request = { id:reqid, query:search.query };
        }
        else if (!search.query && listsearch_request) {
            rcmail.multi_thread_request_abort(listsearch_request.id);
            listsearch_request = null;
        }
    });

    this.container.on('click', 'a.subscribed, span.subscribed', function(e) {
        var li = $(this).closest('li'),
            id = li.attr('id').replace(new RegExp('^'+p.id_prefix), ''),
            div = li.children().first(),
            is_subscribed;

        if (me.is_search()) {
            id = id.replace(/--xsR$/, '');
            li = $(me.get_item(id, true));
            div = $(div).add(li.children().first());
        }

        if (p.id_decode)
            id = p.id_decode(id);

        div.toggleClass('subscribed');
        is_subscribed = div.hasClass('subscribed');
        $(this).attr('aria-checked', is_subscribed ? 'true' : 'false');
        me.triggerEvent('subscribe', { id: id, subscribed: is_subscribed, item: li });

        // update subscribe state of all 'virtual user' child folders
        if (li.hasClass('other user')) {
            $('ul li > div', li).each(function() {
                $(this)[is_subscribed ? 'addClass' : 'removeClass']('subscribed');
                $('.subscribed', div).attr('aria-checked', is_subscribed ? 'true' : 'false');
            });
            div.removeClass('partial');
        }
        // propagate subscription state to parent  'virtual user' folder
        else if (li.closest('li.other.user').length) {
            parent_subscription_status(li);
        }

        e.stopPropagation();
        return false;
    });

    this.container.on('click', 'a.remove', function(e) {
      var li = $(this).closest('li'),
          id = li.attr('id').replace(new RegExp('^'+p.id_prefix), '');

      if (me.is_search()) {
          id = id.replace(/--xsR$/, '');
          li = $(me.get_item(id, true));
      }

      if (p.id_decode)
          id = p.id_decode(id);

      me.triggerEvent('remove', { id: id, item: li });

      e.stopPropagation();
      return false;
    });
}

// link prototype from base class
kolab_folderlist.prototype = rcube_treelist_widget.prototype;
