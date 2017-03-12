<html>
<head>
<link rel="stylesheet" type="text/css" href="css/common.css" />
</head>

<body>
New user
<Form action=index.php?module=groups&view=added.tpl method=post>
<table>
<tr><td>
Grup name</td><td><input type=txt name= givenname> @{$domain}</td></tr>
<tr><td>

Member</td><td><input type=hidden name= surname value="">
</td></tr>
</table>
<input type=submit value=add >
</form>

</body>
</html>