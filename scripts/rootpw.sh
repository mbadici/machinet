#ldapsearch -H ldapi:// -LLL -Q -Y EXTERNAL -b "cn=config" "(olcRootDN=*)" dn olcRootDN olcRootPW | tee ~/newpasswd.ldif

/usr/sbin/slappasswd -h {SSHA} >> ~/newpasswd.ldif
ldapmodify -Y EXTERNAL -H ldapi:/// -D "cn=admin,cn=config" -W -f /root/newpasswd.ldif 


