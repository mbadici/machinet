<?php /* Smarty version Smarty-3.1.15, created on 2017-11-17 14:50:26
         compiled from "skins/default/templates/login.html" */ ?>
<?php /*%%SmartyHeaderCode:17891951695a0edb1243a007-90185417%%*/if(!defined('SMARTY_DIR')) exit('no direct access allowed');
$_valid = $_smarty_tpl->decodeProperties(array (
  'file_dependency' => 
  array (
    'fe25a38b49e64865454baec24325a71e1897c7de' => 
    array (
      0 => 'skins/default/templates/login.html',
      1 => 1510922526,
      2 => 'file',
    ),
  ),
  'nocache_hash' => '17891951695a0edb1243a007-90185417',
  'function' => 
  array (
  ),
  'variables' => 
  array (
    'pagetitle' => 0,
    'skin_path' => 0,
    'engine' => 0,
    'script' => 0,
  ),
  'has_nocache_code' => false,
  'version' => 'Smarty-3.1.15',
  'unifunc' => 'content_5a0edb12495a19_19706026',
),false); /*/%%SmartyHeaderCode%%*/?>
<?php if ($_valid && !is_callable('content_5a0edb12495a19_19706026')) {function content_5a0edb12495a19_19706026($_smarty_tpl) {?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title><?php echo $_smarty_tpl->tpl_vars['pagetitle']->value;?>
</title>
    <link rel="stylesheet" href="<?php echo $_smarty_tpl->tpl_vars['skin_path']->value;?>
style.css" />
    <link rel="shortcut icon" type="image/png" href="<?php echo $_smarty_tpl->tpl_vars['skin_path']->value;?>
images/favicon.png" />
    <script src="js/jquery.min.js"></script>
    <script src="js/files_api.js"></script>
    <script src="js/files_ui.js"></script>
</head>
<body>
    <div id="logo"></div>
    <div id="topmenu"></div>
    <div id="navigation"></div>
    <div id="task_navigation"></div>
    <div id="content"><?php echo $_smarty_tpl->tpl_vars['engine']->value->login_form();?>
</div>
    <div id="footer">
        <?php echo $_smarty_tpl->getSubTemplate ("footer.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, null, array(), 0);?>

    </div>
<?php echo $_smarty_tpl->tpl_vars['script']->value;?>

</body>
</html>
<?php }} ?>
