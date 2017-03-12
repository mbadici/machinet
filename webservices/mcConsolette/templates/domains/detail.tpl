<html>
<head>
<link rel="stylesheet" type="text/css" href="css/common.css" />
</head>
<body>
list user
{$smarty.get.user}

<br>
<form method=post action=change.php>
<table>
{foreach $result  as $ind}
<tr>
<td>{$ind@key} </td> <td><input type=text  name= {$ind@key}  value={$ind[0]}> </td>
</tr>
{/foreach}
</table>
<input type=submit value=change>
<form>
<a href= index.php?module=users&view=delete.tpl&user={$smarty.get.user|escape:'url'}> Delete</a>

</body>
</html>