<?php /* Smarty version Smarty-3.1.15, created on 2017-11-17 14:50:26
         compiled from "skins/default/templates/footer.html" */ ?>
<?php /*%%SmartyHeaderCode:4547604725a0edb12499853-58288059%%*/if(!defined('SMARTY_DIR')) exit('no direct access allowed');
$_valid = $_smarty_tpl->decodeProperties(array (
  'file_dependency' => 
  array (
    '694bb9991c958fd569174cae543e81f44f9dccbe' => 
    array (
      0 => 'skins/default/templates/footer.html',
      1 => 1510922526,
      2 => 'file',
    ),
  ),
  'nocache_hash' => '4547604725a0edb12499853-58288059',
  'function' => 
  array (
  ),
  'variables' => 
  array (
    'engine' => 0,
    'max_upload' => 0,
  ),
  'has_nocache_code' => false,
  'version' => 'Smarty-3.1.15',
  'unifunc' => 'content_5a0edb124a5d62_01401362',
),false); /*/%%SmartyHeaderCode%%*/?>
<?php if ($_valid && !is_callable('content_5a0edb124a5d62_01401362')) {function content_5a0edb124a5d62_01401362($_smarty_tpl) {?><table width="100%" cellpadding="0">
    <tr>
        <td width="99%">
            <?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('about.community');?>
<br />
            <?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('about.warranty');?>
<br />
            <?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('about.support');?>

        </td>
        <td width="1%" class="foot">
            <?php if ($_smarty_tpl->tpl_vars['max_upload']->value) {?><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('maxupload',$_smarty_tpl->tpl_vars['max_upload']->value);?>
<br /><?php }?>
            <span id="reqtime"><?php echo $_smarty_tpl->tpl_vars['engine']->value->translate('reqtime',$_smarty_tpl->tpl_vars['engine']->value->gentime());?>
</span>
        </td>
    </tr>
</table>
<?php }} ?>
