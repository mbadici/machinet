<html>
<head>
<link rel="stylesheet" type="text/css" href="css/common.css" />
</head>
<body>

change password for user <br>{$smarty.get.user}
{$userdn=$smarty.get.user|escape: 'url'}
<Form action=index.php?module=users&view=changed.tpl&user={$userdn} method=post>
<table>
<tr><td>
parola</td><td><input type=password name=password> </td></tr>
<tr><td>confirma</td><td><input type=password name=password2> </td></tr>
</table>
<input type=submit value=change >
</form>

</body>
</html>