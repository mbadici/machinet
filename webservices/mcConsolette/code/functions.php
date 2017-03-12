<?php
require_once "config.php";

function ldap_init()
{
global $ldapuri;
//global $basedn;
//global $rootdn;
//global $rootpasswd;
$ldapcon=ldap_connect($ldapuri) or die("Error connecting");
ldap_set_option($ldapcon,LDAP_OPT_PROTOCOL_VERSION,3);
//if($bnd) { ldap_bind($ldapcon,$userdndn,$passwd);}
return $ldapcon;
}
function uid_bind($user,$pass)
{
global $rootdn;
$ldapcon=ldap_init();
$userdn=$rootdn;
if($user!="admin")
{
$res = ldap_search($ldapcon, $basedn,"uid=".$user) or die("ldap search failed");
if(!ldap_count_entries($ldapcon,$res)) return NULL;
$entry = ldap_first_entry($ldapcon, $res);
$userdn=ldap_get_dn($ldapcon,$entry);
}
if(ldap_bind($ldapcon,$userdn,$pass)==1) { return $ldapcon;}

 return NULL;

}
function checklogin($username,$pass)
{

{
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="mcConsole"');
        header('HTTP/1.0 401 Unauthorized');
            echo 'Text to send if user hits Cancel button';
	    return FALSE;
                exit;
                } else {
            return (login($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']));
                      }

}

}

function login($user,$pass)
{
if(uid_bind($user,$pass)!=NULL){return 1;} 
return 0;

}

function list_users($query,$ou,$seldomain)
{
$ldapcon=ldap_init() or die("Error connecting");
$qry="mail=*";
global $domain;
global $basedn;
#echo $domain;
switch($ou)
{
case "users":
{
$basedn="ou=".$seldomain.",".$basedn;

$qry="uid=*";
break;
}
case "groups":
{
$basedn="ou=".$seldomain.",".$basedn;

$qry="objectclass=groupofnames";
break;
}
case "domains":
{
$qry="objectclass=dnsdomain";
break;
}
}

$res = ldap_search($ldapcon, $basedn,$qry) or die("ldap search failed");
$number=ldap_count_entries($ldapcon,$res);
if($number<1) 
{$result[1][0]=$result[1][1]="no result";
}
else{

$entry = ldap_first_entry($ldapcon, $res);
$userdn=ldap_get_dn($ldapcon,$entry);
$info=explode(",", $userdn);
$moreinfo=explode("=",$info[0]);
$result[0][0]=$userdn;
$result[0][1]=$moreinfo[1];
for($i=1;$i<$number;$i++)
{
$entry=ldap_next_entry($ldapcon,$entry);
$userdn=ldap_get_dn($ldapcon,$entry);
$info=explode(",", $userdn);
$moreinfo=explode("=",$info[0]);
$result[$i][0]=$userdn;
$result[$i][1]=$moreinfo[1];
}
}
return $result;
}
function checkAuth()
{
if(!defined("AUTHENTICATED"))
{
echo "please authenticate first";
return 0;
}
else {
return 1;
    }
}
function details($userdn)
{
$ldapcon=ldap_init() or die("Error connecting");
$res = ldap_search($ldapcon, $userdn,"objectclass=*") or die("ldap search failed");
$number=ldap_count_entries($ldapcon,$res);
$entry = ldap_first_entry($ldapcon, $res);
$res= ldap_get_attributes($ldapcon, $entry);
for ($i=0; $i < $res["count"]; $i++) {
	//			    $attrs=$attrs.",".$res[$i] ;
				    $resu[$res[$i]]=ldap_get_values($ldapcon,$entry,$res[$i]);

				    }
//$resu=ldap_get_values($ldapcon,$entry,$attrs);
return $resu;

}
 
function newuseradd($givenname,$surname,$mail,$seldomain,$password,$module)
{

echo $module;
echo "\n";
$ldapcon=uid_bind($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

switch($module)
{
case "users":
{
$givenname=ucfirst($givenname);
$surname=ucfirst($surname);
$mailcustomer["sn"]= $surname;
$mailcustomer["cn"]= $givenname." ".$surname;
$mailcustomer["gn"]= $givenname;
$mailcustomer["objectclass"][0]="inetorgperson";
//$mailcustomer["objectclass"][1]="vacation";
$mailcustomer["objectclass"][1]="mailaccount";
$mailcustomer["userpassword"]=$password;
//$mailcustomer["uid"]=strtolower($givenname.".".$surname."@".$domain);
$mailcustomer["uid"]=$mail;
$mailcustomer["telephonenumber"]="0";
$mailcustomer["mobile"]="0";
$mailcustoner["vacationActive"]='FALSE';
$mailcustomer["mail"]= $mail;
//$mailcustomer[""]= $givenname;
//$fullcn= "cn=".$mailcustomer["cn"].",ou=Users,dc=machinet";
//$fullcn= "cn=".$mailcustomer["cn"].",ou=".$domain.",ou=Users,dc=machinet";
$fullcn= "cn=".$mailcustomer["cn"].",ou=Users,ou=".$seldomain.",dc=machinet";
break;
}
case "domains":
{
// create a new ou for users/groups

$org["ou"]= $givenname;
$org["objectclass"][0]="organizationalunit";
$fullcn= "ou=".$givenname.",dc=machinet";
$res=ldap_add($ldapcon,$fullcn,$org);

$org["ou"]= "Users";
$org["objectclass"][0]="organizationalunit";

//add users and group containers
$fullcn= "ou=Users,ou=".$givenname.",dc=machinet";
$res=ldap_add($ldapcon,$fullcn,$org);
$org["ou"]= "Groups";
$org["objectclass"][0]="organizationalunit";

$fullcn= "ou=Groups,ou=".$givenname.",dc=machinet";
$res=ldap_add($ldapcon,$fullcn,$org);

$org["cn"]= "Admins";
$org["objectclass"][0]="groupOfNames";
$org["member"][0]="";
$fullcn= "cn=Admins,ou=Groups,ou=".$givenname.",dc=machinet";
$res=ldap_add($ldapcon,$fullcn,$org);

$org["ou"]= $givenname;
$org["objectclass"][0]="organizationalunit";
$fullcn= "ou=".$givenname.",dc=machinet";
$res=ldap_add($ldapcon,$fullcn,$org);

$mailcustomer["dc"]= $givenname;
$mailcustomer["objectclass"][0]="dnsdomain";
$fullcn= "dc=".$mailcustomer["dc"].",ou=domains,dc=machinet";

break;
}
case "groups":
{
$mailcustomer["cn"]= $givenname."@".$seldomain;
$mailcustomer["objectclass"][0]="groupofnames";
$mailcustomer["member"][0]=$surname;
//echo $mailcustomer["member"][0];
print_r($mailcustomer);
$fullcn= "cn=".$mailcustomer["cn"].",ou=Groups,ou=".$seldomain.",dc=machinet";
print_r($fullcn);
break;
}
}
$res=ldap_add($ldapcon,$fullcn,$mailcustomer);
ldap_close($ldapcon);
return $res;
}
function userdel($dn)
{
$ldapcon=uid_bind($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

return ldap_delete($ldapcon,$dn);
}
function changepass($dn,$pass,$pass2)
{
global $rootdn;
if($pass!=$pass2) return 0;
$binduser=$_SERVER['PHP_AUTH_USER'];
if($binduser=="admin") $binduser=$rootdn;
$bindpw=$_SERVER['PHP_AUTH_PW'];
$command="ldappasswd -D $binduser -x -w $bindpw -s ".$pass." "."'".$dn."'";
echo $command;
return shell_exec($command);

}
function moduser($dn,$ldapobject,$op,$module)
{
echo $dn;
global $domain;
$ldapcon=uid_bind($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);
if($op=="change")
{
$res=ldap_modify($ldapcon,$dn,$ldapobject);
}
elseif($op=="disable")
{
$ldapobject="billPaid";
$ob[$ldapobject]="FALSE";
echo "<br>";

echo $ldapobject;
echo "<br>";

echo $dn;
echo "<br>";
$res=ldap_modify($ldapcon,$dn,$ob);

}
else
{
$ldapobject="member";
if($module=="users") $ldapobject="mail";
$ob[$ldapobject]=$op;
echo $ldapobject;
echo "<br>";
$res=ldap_mod_del($ldapcon,$dn,$ob);

}




ldap_close($ldapcon);
return $res;
}

function selectdomain($selecteddomain)
{
global $domain;
$domain=$selecteddomain;
//$session_start();
//$_SESSION['domain']=$domain;
echo $domain." selected";
global $basedn;
 $basedn="ou=".$domain.",dc=machinet";
}

?>
