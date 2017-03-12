<?php
define('SMARTY_DIR', '/usr/share/php/smarty3/');
require_once(SMARTY_DIR . 'Smarty.class.php');
require_once("../code/functions.php");
$view= isset($_GET['view']) ? $_GET['view'] :"index.tpl";
$module= isset($_GET['module']) ? $_GET['module'] :"";
$username= isset($_POST['username']) ? $_POST['username'] :"";
$pass= isset($_POST['pass']) ? $_POST['pass'] :"";
$smarty = new Smarty();
$smarty->setTemplateDir('../templates/');
$smarty->setCompileDir('../templates_c/');
  $smarty->assign('username',$username);
  $smarty->assign('pass',$pass);
  
//$tpl=str_replace(".php",".tpl",$_SERVER['PHP_SELF']);

//if(login($username,$pass))
//{
//define("AUTHENTICATED","authenticated");
//}
//if ($view=="index.tpl")
//{
//$smarty->display($view);
//} 
//else
//{
$smarty->display("header.tpl");

if(checklogin($username,$pass))
{
    session_start();
if($_SERVER['PHP_AUTH_USER'] == "admin")
$_SESSION['isadmin']=1;
else
$_SESSION['seldomain']=substr(strstr($_SERVER['PHP_AUTH_USER'],'@',0),1);
if ($view=="index.tpl")
{
$smarty->display("login/welcome.tpl");
} 
else {
switch($view)
{
   case "list.tpl":
   {

   $seldomain=$_SESSION['seldomain'];

   echo "domain ".$_SESSION['seldomain']." active";
   $alist= list_users("NULL",$module,$seldomain);
   $smarty->assign('alist',$alist);
//    $smarty->assign('domain',$domain);

    break;
   }
   case "new.tpl":
   {
    $alist= list_users("NULL","domains");
   $smarty->assign('alist',$alist);
    
  $smarty->assign('domain',$_SESSION['seldomain']);
  $smarty->assign('pass', bin2hex(openssl_random_pseudo_bytes(4)));
    break;
   }
   case "added.tpl":
   {
   $givenname= isset($_POST['givenname']) ? $_POST['givenname'] :"nobody";
   $surname= isset($_POST['surname']) ? $_POST['surname'] :"nobody";
   $domain= $_SESSION['seldomain'];
   $uid= isset($_POST['uid']) ? $_POST['uid'] :"";
    $mail=$uid."@".$domain;

   $password= isset($_POST['password']) ? $_POST['password'] :"nobody";
     $result=newuseradd($givenname,$surname,$mail,$_SESSION['seldomain'],$password,$module);
   $smarty->assign('result',$result);
   break;
   }
   case "delete.tpl":
   {
   $userdn= isset($_GET['user']) ? $_GET['user'] :"";
   $result=userdel($userdn);
   $smarty->assign('result',$result);
   break;
   }
   case "changed.tpl":
   {
   $userdn= isset($_GET['user']) ? $_GET['user'] :"";
   $password= isset($_POST['password']) ? $_POST['password'] :"";
   $password2= isset($_POST['password2']) ? $_POST['password2'] :"";
    $result=changepass($userdn,$password,$password2);
   $smarty->assign('result',$result);
   break;
   }
   case "detail.tpl":
   {
   $userdn= isset($_GET['user']) ? $_GET['user'] :"";
   $result=details($userdn,$module);
   $smarty->assign('result',$result);
   break;
   }
   case "change.tpl":
   {
//      $givenname = isset($_POST['givenname']) ? $_POST['givenname'] :"";
//      $surname= isset($_POST['surname']) ? $_POST['surname'] :"";
//   $telephonenumber= isset($_POST['telephoneNumber']) ? $_POST['telephoneNumber'] :"";
//   $mobile= isset($_POST['mobile']) ? $_POST['mobile'] :"";
//   $member= isset($_POST['member']) ? $_POST['member'] :"";
//   $ldapobject = array("telephonenumber"=> $telephonenumber,
//			"mobile"=>$mobile);
//			echo $mobile; echo "mobile"; echo $telephonenumber;

foreach($_POST as $field_name => $field_value)
{
   if(($field_name!= "userdn")&($field_name!= "cn")&($field_name!= "op")&($field_name!= "objectClass"))
//$ldapobject[$field_name]=!is_null($field_value) ? $field_value:"";

if($field_value!=NULL) $ldapobject[$field_name] = $field_value;
}
   $op =$_POST['op'];
   $userdn= isset($_POST['userdn']) ? $_POST['userdn'] :"";

   $result=moduser($userdn,$ldapobject,$op,$module);
   $smarty->assign('result',$result);
   break;
   }
   case "select.tpl":
   {
if($_SESSION['isadmin'])
{
   $alist= list_users("NULL",$module);
   $smarty->assign('alist',$alist);
} 
  break;
   }
   case "selected.tpl":
   {
   $selecteddomain=isset($_POST['selecteddomain']) ? $_POST['selecteddomain'] : "";
    $domain=$selecteddomain;
    echo $domain." selected";
    $_SESSION['seldomain']=$domain;
    $result=0;
   break;
   }
}

$smarty->display($module."/".$view);
}
}
else
{
 header("HTTP/1.0 401 Unauthorized");
         header("WWW-authenticate: Basic realm=\"mcConsole\"");
                 header("Content-type: text/html");
$smarty->display("index.tpl");
}
$smarty->display("footer.tpl");

?>
