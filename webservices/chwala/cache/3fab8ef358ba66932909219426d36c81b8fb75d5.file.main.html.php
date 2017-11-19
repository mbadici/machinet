<?php /* Smarty version Smarty-3.1.15, created on 2017-11-19 11:19:22
         compiled from "skins/default/templates/main.html" */ ?>
<?php /*%%SmartyHeaderCode:5316085515a114c9a6cbff7-14223892%%*/if(!defined('SMARTY_DIR')) exit('no direct access allowed');
$_valid = $_smarty_tpl->decodeProperties(array (
  'file_dependency' => 
  array (
    '3fab8ef358ba66932909219426d36c81b8fb75d5' => 
    array (
      0 => 'skins/default/templates/main.html',
      1 => 1510922526,
      2 => 'file',
    ),
  ),
  'nocache_hash' => '5316085515a114c9a6cbff7-14223892',
  'function' => 
  array (
  ),
  'variables' => 
  array (
    'pagetitle' => 0,
    'skin_path' => 0,
    'user' => 0,
    'engine' => 0,
    'main_menu' => 0,
    'task_menu' => 0,
    'script' => 0,
  ),
  'has_nocache_code' => false,
  'version' => 'Smarty-3.1.15',
  'unifunc' => 'content_5a114c9a733104_70182725',
),false); /*/%%SmartyHeaderCode%%*/?>
<?php if ($_valid && !is_callable('content_5a114c9a733104_70182725')) {function content_5a114c9a733104_70182725($_smarty_tpl) {?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title><?php echo $_smarty_tpl->tpl_vars['pagetitle']->value;?>
</title>
    <link rel="stylesheet" href="<?php echo $_smarty_tpl->tpl_vars['skin_path']->value;?>
style.css" />
    <link rel="stylesheet" href="<?php echo $_smarty_tpl->tpl_vars['skin_path']->value;?>
images/mimetypes/style.css" />
    <link rel="shortcut icon" type="image/png" href="<?php echo $_smarty_tpl->tpl_vars['skin_path']->value;?>
images/favicon.png" />
    <script src="js/jquery.min.js"></script>
    <script src="js/files_api.js"></script>
    <script src="js/files_ui.js"></script>
    <script src="<?php echo $_smarty_tpl->tpl_vars['skin_path']->value;?>
ui.js"></script>
    <script src="js/wModal.js"></script>
</head>
<body>
    <div id="logo" onclick="document.location='.'"></div>
    <div id="topmenu">
        <span class="login"><?php echo $_smarty_tpl->tpl_vars['user']->value['username'];?>
</span>
        <span class="logout link" onclick="ui.main_logout()"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('logout');?>
</span>
    </div>
    <div id="navigation"><?php echo $_smarty_tpl->tpl_vars['main_menu']->value;?>
</div>
    <div id="task_navigation"><?php echo $_smarty_tpl->tpl_vars['task_menu']->value;?>
</div>
    <div id="content">
        <div id="actionbar">
            <a id="folder-create-button" onclick="ui.command('folder.create')" class="disabled"><span><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('folder.createtitle');?>
</span></a>
            <form id="uploadform" name="uploadform" method="post" enctype="multipart/form-data">
                <a id="file-upload-button" class="disabled"><span><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.upload');?>
</span></a>
            </form>
            <a id="file-search-button" onclick="ui.command('file.search')" class="disabled"><span><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('search');?>
</span></a>
        </div>
        <div id="folderlist">
            <div class="scroller">
                <table></table>
            </div>
            <div class="boxfooter">
                <a href="#" onclick="ui.command('folder.edit')" alt="" id="folder-edit-button" class="button edit disabled"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('folder.edit');?>
</a>
                <a href="#" onclick="ui.command('folder.delete')" alt="" id="folder-delete-button" class="button delete disabled"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('folder.delete');?>
</a>
            </div>
        </div>
        <div id="forms">
            <?php echo $_smarty_tpl->tpl_vars['engine']->value->folder_create_form();?>

            <?php echo $_smarty_tpl->tpl_vars['engine']->value->folder_edit_form();?>

            <?php echo $_smarty_tpl->tpl_vars['engine']->value->file_search_form();?>

        </div>
        <div id="taskcontent">
            <div class="scroller">
                <table id="filelist" class="list">
                    <thead>
                        <tr>
                            <td class="filename" onclick="file_list_sort('name', this)"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.name');?>
</td>
                            <td class="filemtime" onclick="file_list_sort('mtime', this)"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.mtime');?>
</td>
                            <td class="filesize" onclick="file_list_sort('size', this)"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.size');?>
</td>
                        </tr>
                    </thead>
                </table>
            </div>
            <div class="boxfooter">
                <a href="#" onclick="ui.command('file.delete')" alt="" id="file-delete-button" class="button delete disabled"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.delete');?>
</a>
            </div>
        </div>
    </div>
    <div id="footer">
        <?php echo $_smarty_tpl->getSubTemplate ("footer.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, null, array(), 0);?>

    </div>
    <div id="file-menu" class="popup">
        <ul>
            <li class="file-open"><a id="file-open-button" href="#"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.open');?>
</a></li>
            <li class="file-download"><a id="file-download-button" href="#"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.download');?>
</a></li>
            <li class="file-delete"><a id="file-delete-button" href="#"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.delete');?>
</a></li>
            <li class="file-rename"><a id="file-rename-button" href="#"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.rename');?>
</a></li>
        </ul>
    </div>
    <div id="file-drag-menu" class="popup">
        <ul>
            <li class="file-move"><a id="file-move-button" href="#"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.move');?>
</a></li>
            <li class="file-copy"><a id="file-copy-button" href="#"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('file.copy');?>
</a></li>
        </ul>
    </div>
<?php echo $_smarty_tpl->tpl_vars['script']->value;?>

</body>
</html>
<?php }} ?>
