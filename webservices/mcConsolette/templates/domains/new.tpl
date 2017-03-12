<html>
<head>
<script language=javascript>
function setuid(tpl)
{

partone=document.getElementById('gn').value.toLowerCase();
if(tpl==2) partone=partone.charAt(0);
parttwo=document.getElementById('sn').value.toLowerCase();
document.getElementById('uid').value=partone+'.'+parttwo;
}
</script>
<link rel="stylesheet" type="text/css" href="css/common.css" />
</head>
<body>
New user
<Form action=index.php?module=users&view=added.tpl method=post>
<table>

<tr><td>Prenume</td><td><input type=txt name=givenname id=gn value="" onkeyup="setuid()"> </td></tr>
<tr><td>Nume</td><td><input type=txt name=surname id=sn onkeyup="setuid()"> </td></tr>
<tr><td>mail</td><td><input type=txt name=uid id=uid > @{$domain}</td></tr>

</td></tr>
<tr><td>parola</td><td><input type=txt name=password> </td></tr>
</table>
<input type=submit value=add >
</form>
<button  onclick="setuid(1)"> <i>prenume.nume</i></buton>
<button  onclick="setuid(2)"> <i>initiala.nume</i></buton>

</body>
</html>
