<?php
/*
 * Page des groupes
 * 
 */ 
require_once('require/function_groups.php');
require_once('require/function_computers.php');
//ADD new static group
if($protectedPost['Valid_modif_x']){
	$result=creat_group ($protectedPost['NAME'],$protectedPost['DESCR'],'','','STATIC');
	if ($result['RESULT'] == "OK"){
		$color="green";
		unset($protectedPost['add_static_group']);
	}else
	$color="red";
	$msg=$result['RESULT'];	
	$tab_options['CACHE']='RESET';
}
//annule la cr�ation d'un groupe statique
if ($protectedPost['Reset_modif_x']) 
 unset($protectedPost['add_static_group']);
 
//view only your computers
if ($_SESSION['OCS']['RESTRICTION']['GUI'] == 'YES')
	$mycomputers=computer_list_by_tag();

//View for all profils?
if (isset($protectedPost['CONFIRM_CHECK']) and  $protectedPost['CONFIRM_CHECK'] != "")
	$result=group_4_all($protectedPost['CONFIRM_CHECK']);

//if delete group
if ($protectedPost['SUP_PROF'] != ""){
	$result=delete_group($protectedPost['SUP_PROF']);	
	if ($result['RESULT'] == "ERROR")
	$color="red";
	else
	$color="green";
	$msg=$result['LBL'];
	$tab_options['CACHE']='RESET';
}
//si un message
if ($msg != "")
echo "<font color = ".$color." ><b>".$result['LBL']."</b></font>";

//ouverture du formulaire de la page
$form_name='groups';
echo "<form name='".$form_name."' id='".$form_name."' method='POST' action=''>";
//view all groups
if ($_SESSION['OCS']['CONFIGURATION']['GROUPS']=="YES"){
	$def_onglets['DYNA']=$l->g(810); //Dynamic group
	$def_onglets['STAT']=$l->g(809); //Static group centraux
	$def_onglets['SERV']=strtoupper($l->g(651));
	if ($protectedPost['onglet'] == "")
	$protectedPost['onglet']="STAT";	
	//show onglet
	onglet($def_onglets,$form_name,"onglet",0);
	echo '<div class="mlt_bordure" >';

	
//	echo "<table ALIGN = 'Center' class='onglet'><tr><td align =center>";
}else{	
	$protectedPost['onglet']="STAT";
}

$list_fields= array('GROUP_NAME'=>'h.NAME',
					'GROUP_ID' =>'h.ID',
						'DESCRIPTION'=>'h.DESCRIPTION',
						'CREATE'=>'h.LASTDATE',
						'NBRE'=>'NBRE');
//only for admins
if ($_SESSION['OCS']['CONFIGURATION']['GROUPS']=="YES"){
	if ($protectedPost['onglet'] == "STAT")
		$list_fields['CHECK']= 'ID';
	$list_fields['SUP']= 'ID';	
}
//changement de nom � l'affichage des champs	
$tab_options['LBL']['CHECK']="Visible";
$tab_options['LBL']['GROUP_NAME']="Nom";

$table_name="LIST_GROUPS";
$default_fields= array('GROUP_NAME'=>'GROUP_NAME','DESCRIPTION'=>'DESCRIPTION','CREATE'=>'CREATE','NBRE'=>'NBRE','SUP'=>'SUP','CHECK'=>'CHECK');
$list_col_cant_del=array('GROUP_NAME'=>'GROUP_NAME','SUP'=>'SUP','CHECK'=>'CHECK');
$querygroup = 'SELECT ';
foreach ($list_fields as $key=>$value){
	if($key != 'SUP' and $key != 'CHECK' and $key != 'NBRE')
	$querygroup .= $value.',';		
} 
$querygroup=substr($querygroup,0,-1);
//requete pour les groupes de serveurs
if ($protectedPost['onglet'] == "SERV"){
	$querygroup .= " from hardware h,download_servers ds where ds.group_id=h.id and h.deviceid = '_DOWNLOADGROUP_'";	
	//calcul du nombre de machines par groupe de serveur
	$sql_nb_mach="SELECT count(*) nb, group_id
					from download_servers group by group_id";
}else{ //requete pour les groupes 'normaux'
	$querygroup .= " from hardware h,groups g";
	$querygroup .="	where g.hardware_id=h.id and h.deviceid = '_SYSTEMGROUP_' ";
	if ($protectedPost['onglet'] == "DYNA")
		$querygroup.=" and ((g.request is not null and trim(g.request) != '') 
							or (g.xmldef is not null and trim(g.xmldef) != ''))";
	elseif ($protectedPost['onglet'] == "STAT")
		$querygroup.=" and (g.request is null or trim(g.request) = '')
					    and (g.xmldef  is null or trim(g.xmldef) = '') ";
	if($_SESSION['OCS']['CONFIGURATION']['GROUPS']!="YES")
		$querygroup.=" and h.workgroup='GROUP_4_ALL' ";

	//calcul du nombre de machines par groupe
	$sql_nb_mach="SELECT count(*) nb, group_id
					from groups_cache gc,hardware h where h.id=gc.hardware_id ";
	if($_SESSION['OCS']['RESTRICTION']['GUI'] == "YES")
			$sql_nb_mach.=" and gc.hardware_id in ".$mycomputers;		
	$sql_nb_mach .=" group by group_id";

}
$querygroup.=" group by h.ID";
$result = mysql_query($sql_nb_mach, $_SESSION['OCS']["readServer"]) or mysql_error($_SESSION['OCS']["readServer"]);
while($item = mysql_fetch_object($result)){
	//on force les valeurs du champ "nombre" � l'affichage
	$tab_options['VALUE']['NBRE'][$item -> group_id]=$item -> nb;
}
	
//Modif ajout�e pour la prise en compte 
//du chiffre � rajouter dans la colonne de calcul
//quand on a un seul groupe et qu'aucune machine n'est dedant.
if (!isset($tab_options['VALUE']['NBRE']))
$tab_options['VALUE']['NBRE'][]=0;
//on recherche les groupes visible pour cocher la checkbox � l'affichage
if ($protectedPost['onglet'] == "STAT"){
	$sql="select id from hardware where workgroup='GROUP_4_ALL'";
	$result = mysql_query($sql, $_SESSION['OCS']["readServer"]) or mysql_error($_SESSION['OCS']["readServer"]);
	while($item = mysql_fetch_object($result)){
		$protectedPost['check'.$item ->id]="check";
	}
}
//on ajoute un javascript lorsque l'on clic sur la visibilit� du groupe pour tous
$tab_options['JAVA']['CHECK']['NAME']="NAME";
$tab_options['JAVA']['CHECK']['QUESTION']=$l->g(811);
$tab_options['FILTRE']=array('NAME'=>$l->g(679),'DESCRIPTION'=>$l->g(636));
//affichage du tableau
$result_exist=tab_req($table_name,$list_fields,$default_fields,$list_col_cant_del,$querygroup,$form_name,100,$tab_options); 

//si super admin, on donne la possibilit� d'ajouter un nouveau groupe statique	
if ($_SESSION['OCS']['CONFIGURATION']['GROUPS']=="YES"){
	echo "</td></tr></table>";	
	if ($protectedPost['onglet'] == "STAT")
		echo "<BR><input type='submit' name='add_static_group' value='".$l->g(587)."'>";
}

//if user want add a new group
if (isset($protectedPost['add_static_group']) and $_SESSION['OCS']['CONFIGURATION']['GROUPS']=="YES"){
	$tdhdpb = "<td  align='left' width='20%'>";
	$tdhfpb = "</td>";
	$tdhd = "<td  align='left' width='20%'><b>";
	$tdhf = ":</b></td>";
	$img_modif="";
		//list of input we can modify
		$name=show_modif($protectedPost['NAME'],'NAME',0);
		$description=show_modif($protectedPost['DESCR'],'DESCR',1);
		//show new bottons
		$button_valid="<input title='".$l->g(625)."' type='image'  src='image/modif_valid_v2.png' name='Valid_modif'>";
		$button_reset="<input title='".$l->g(626)."' type='image'  src='image/modif_anul_v2.png' name='Reset_modif'>";
	
	echo "<br><br><table align='center' width='65%' border='0' cellspacing=20 bgcolor='#C7D9F5' style='border: solid thin; border-color:#A1B1F9'>";
	echo "<tr>".$tdhd.$l->g(577).$tdhf.$tdhdpb.$name.$tdhfpb;
	echo "</tr>";
	echo $tdhd."</b></td><td  align='left' width='20%' colspan='3'>";
	echo "</tr><tr>".$tdhd.$l->g(53).$tdhf.$tdhdpb.$description.$tdhfpb;
	echo "<tr><td align='left' colspan=4>".$button_valid."&nbsp&nbsp".$button_reset."&nbsp&nbsp".$img_modif."</td></tr>";
	echo "$tdhfpb</table>";
	echo "<input type='hidden' id='add_static_group' name='add_static_group' value='BYHIDDEN'>";
}

			

	echo '	</div>
		';
//fermeture du formulaire
echo "</form>";
?>