<html>
<head>
<link rel="stylesheet" type="text/css" href="css/common.css" />
</head>

<body>

change password for user <br>{$smarty.get.user}
{$userdn=$smarty.get.user|escape: 'url'}
<Form action=index.php?module=users&view=changed.tpl&user={$userdn} method=post>
parola<input type=password name=password> <br>
parola 2<input type=password name=password2> <br>
<input type=submit value=change >
</form>

</body>
</html>