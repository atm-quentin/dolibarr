<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2010	   Juanjo Menent	    <jmenent@2byte.es>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/compta/bank/rappro.php
 *       \ingroup    banque
 *       \brief      Page to reconciliate bank transactions
 */

require('../../main.inc.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/sociales/class/chargesociales.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/tva/class/tva.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';

$langs->load("banks");
$langs->load("categories");
$langs->load("bills");

if (! $user->rights->banque->consolidate) accessforbidden();

$action=GETPOST('action', 'alpha');
$id=GETPOST('account', 'int');

$dateyear='';
$datemonth='';
$viewtype='-1';
$search_description='';
$search_debit='';
$search_credit='';

$formother = new FormOther($db);
/*
 * Actions
 */

// Conciliation
if ($action == 'rappro' && $user->rights->banque->consolidate)
{
	$error=0;

	// Definition, nettoyage parametres
    $num_releve=trim($_POST["num_releve"]);

    if ($num_releve)
    {
        $bankline=new AccountLine($db);

		if (isset($_POST['rowid']) && is_array($_POST['rowid']))
		{
			foreach($_POST['rowid'] as $row)
			{
				if($row > 0)
				{
					$result=$bankline->fetch($row);
					$bankline->num_releve=$num_releve; //$_POST["num_releve"];
					$result=$bankline->update_conciliation($user,$_POST["cat"]);
					if ($result < 0)
					{
						setEventMessages($bankline->error, $bankline->errors, 'errors');
						$error++;
						break;
					}
				}
			}
        }
    }
	else if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter.x") || GETPOST("button_removefilter")) // Both test are required to be compatible with all browsers
	{//To avoid error msg
		$error++;
		$dateyear='';
		$datemonth='';
		$viewtype='-1';
		$search_description='';
		$search_debit='';
		$search_credit='';
	}
	else if(GETPOST("button_search_x") || GETPOST("button_search.x") || GETPOST("button_search")){//To avoid error msg
		$error++;
		$dateyear=GETPOST('dateyear','int');
		$datemonth=GETPOST('datemonth','int');
		$viewtype=GETPOST('viewtype');
		$search_description=GETPOST('search_description','alpha');
		$search_debit=GETPOST('search_debit');
		$search_credit=GETPOST('search_credit');
		$paymentType = '';
		
	}

    else
    {
    	$error++;
    	$langs->load("errors");
	    setEventMessages($langs->trans("ErrorPleaseTypeBankTransactionReportName"), null, 'errors');
		
    }

    if (! $error)
    {
		header('Location: '.DOL_URL_ROOT.'/compta/bank/rappro.php?account='.$id);	// To avoid to submit twice and allow back
    	exit;
    }
}

/*
 * Action suppression ecriture
 */
if ($action == 'del')
{
	$bankline=new AccountLine($db);

    if ($bankline->fetch($_GET["rowid"]) > 0) {
        $result = $bankline->delete($user);
        if ($result < 0) {
            dol_print_error($db, $bankline->error);
        }
    } else {
        setEventMessage($langs->trans('ErrorRecordNotFound'), 'errors');
    }
}


// Load bank groups
$sql = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."bank_categ ORDER BY label";
$resql = $db->query($sql);
$options="";
if ($resql)
{
    $var=True;
    $num = $db->num_rows($resql);
    if ($num > 0) $options .= '<option value="0"'.(GETPOST('cat')?'':' selected').'>&nbsp;</option>';
    $i = 0;
    while ($i < $num)
    {
        $obj = $db->fetch_object($resql);
        $options .= '<option value="'.$obj->rowid.'"'.(GETPOST('cat')==$obj->rowid?' selected':'').'>'.$obj->label.'</option>'."\n";
        $i++;
    }
    $db->free($resql);
    //print $options;
}
else dol_print_error($db);


/*
 * View
 */

$form=new Form($db);

llxHeader();

$societestatic=new Societe($db);
$chargestatic=new ChargeSociales($db);
$memberstatic=new Adherent($db);
$paymentstatic=new Paiement($db);
$paymentsupplierstatic=new PaiementFourn($db);
$paymentvatstatic=new TVA($db);

$acct = new Account($db);
$acct->fetch($id);

$now=dol_now();

switch($viewtype){
	case 1:
		$paymentType = 'PRE';
		break;
	case 2:
		$paymentType = 'LIQ';
		break;
	case 3:
		$paymentType = 'CB';
		break;
	case 4:
		$paymentType = 'CHQ';
		break;
	case 5:
		$paymentType = 'TIP';
		break;
	case 6:
		$paymentType = 'VAD';
		break;
	case 7:
		$paymentType = 'TRA';
		break;
	case 8:
		$paymentType = 'FAC';
		break;
	case 9 :
		$paymentType = 'VIR';
		break;
	default:
		$paymentType='';
	
}

$sql = "SELECT b.rowid, b.dateo as do, b.datev as dv, b.amount, b.label, b.rappro, b.num_releve, b.num_chq, b.fk_type as type";
$sql.= " FROM ".MAIN_DB_PREFIX."bank as b";
$sql.= " WHERE rappro=0 AND fk_account=".$acct->id;
if(!empty($dateyear)){//Search
	$sql.= " AND YEAR(b.dateo)=".$dateyear;
}
if(!empty($datemonth)){//Search
	$sql.=" AND MONTH(b.dateo)=".$datemonth;
}
if(!empty($paymentType)){//Search
	$sql.=" AND b.fk_type='".$paymentType."'";
}

if(!empty($search_debit)){//Search
	if(preg_match('#<#',$search_debit)){//Debit is negative but displayed positive.
		$search_debit = preg_replace('#<#', '>', $search_debit);
		$sql.= natural_search('b.amount', '-'.$search_debit,1);
		$search_debit = preg_replace('#>#', '<', $search_debit);
	} else if(preg_match('#>#',$search_debit)){
		$search_debit = preg_replace('#>#', '<', $search_debit);
		$sql.= natural_search('b.amount', '-'.$search_debit,1);
		$search_debit = preg_replace('#<#', '>', $search_debit);
	} else {
		$sql.= natural_search('b.amount', '-'.$search_debit,1);
	}
}
if(!empty($search_credit)){//Search
	$sql.= natural_search('b.amount', $search_credit,1);
}

$sql.= " ORDER BY dateo ASC";
$sql.= " LIMIT 1000";	// Limit to avoid page overload

/// ajax adjust value date
print '
<script type="text/javascript">
$(function() {
	$("a.ajax").each(function(){
		var current = $(this);
		current.click(function()
		{
			$.get("'.DOL_URL_ROOT.'/core/ajax/bankconciliate.php?"+current.attr("href").split("?")[1], function(data)
			{
				current.parent().prev().replaceWith(data);
			});
			return false;
		});
	});
});
</script>

';

$resql = $db->query($sql);
if ($resql)
{
    $var=True;
    $num = $db->num_rows($resql);

    print load_fiche_titre($langs->trans("Reconciliation").': <a href="account.php?account='.$acct->id.'">'.$acct->label.'</a>', '', 'title_bank.png');
    print '<br>';

    // Show last bank receipts
    $nbmax=15;      // We accept to show last 15 receipts (so we can have more than one year)
    $liste="";
    $sql = "SELECT DISTINCT num_releve FROM ".MAIN_DB_PREFIX."bank";
    $sql.= " WHERE fk_account=".$acct->id." AND num_releve IS NOT NULL";
    $sql.= $db->order("num_releve","DESC");
    $sql.= $db->plimit($nbmax+1);
    print $langs->trans("LastAccountStatements").' : ';
    $resqlr=$db->query($sql);
    if ($resqlr)
    {
        $numr=$db->num_rows($resqlr);
        $i=0;
        $last_ok=0;
        while (($i < $numr) && ($i < $nbmax))
        {
            $objr = $db->fetch_object($resqlr);
            if (! $last_ok) {
            $last_releve = $objr->num_releve;
                $last_ok=1;
            }
            $i++;
            $liste='<a href="'.DOL_URL_ROOT.'/compta/bank/releve.php?account='.$acct->id.'&amp;num='.$objr->num_releve.'">'.$objr->num_releve.'</a> &nbsp; '.$liste;
        }
        if ($numr >= $nbmax) $liste="... &nbsp; ".$liste;
        print $liste;
        if ($numr > 0) print '<br><br>';
        else print '<b>'.$langs->trans("None").'</b><br><br>';
    }
    else
    {
        dol_print_error($db);
    }


	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?account='.$acct->id.'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="rappro">';
	print '<input type="hidden" name="account" value="'.$acct->id.'">';

    print '<strong>'.$langs->trans("InputReceiptNumber").'</strong>: ';
    print '<input class="flat" name="num_releve" type="text" value="'.(GETPOST('num_releve')?GETPOST('num_releve'):'').'" size="10">';  // The only default value is value we just entered
    print '<br>';
    if ($options)
    {
        print $langs->trans("EventualyAddCategory").': <select class="flat" name="cat">'.$options.'</select><br>';
    }
    print '<br>'.$langs->trans("ThenCheckLinesAndConciliate").' "'.$langs->trans("Conciliate").'"<br>';

    print '<br>';

    print '<table class="liste" width="100%">';
    print '<tr class="liste_titre">'."\n";
    print '<td align="center">'.$langs->trans("DateOperationShort").'</td>';
    print '<td align="center">'.$langs->trans("DateValueShort").'</td>';
    print '<td>'.$langs->trans("Type").'</td>';
    print '<td>'.$langs->trans("Description").'</td>';
    print '<td align="right" width="60" class="nowrap">'.$langs->trans("Debit").'</td>';
    print '<td align="right" width="60" class="nowrap">'.$langs->trans("Credit").'</td>';
    print '<td align="center" width="80">'.$langs->trans("Action").'</td>';
    print '<td align="center" width="60" class="nowrap">'.$langs->trans("ToConciliate").'</td>';
    print "</tr>\n";
	//------------------------------------------------------------SEARCH
	print '<tr class="liste_titre">';
	
	print '<td class="liste_titre" align="center">';
    print '<input class="flat" type="text" size="1" maxlength="2" name="datemonth" value="'.$datemonth.'">';
    $formother->select_year($dateyear?$dateyear:-1,'dateyear',1, 20, 5);
	print '</td>';
	print '<td></td>';
	print '<td align="left">';
	$listtypes=array(
	   
	    '1'=>$langs->trans("PaymentTypePRE"), 
	    '2'=>$langs->trans("PaymentTypeLIQ"),
	    '3'=>$langs->trans("PaymentTypeCB"),
	    '4'=>$langs->trans("PaymentTypeCHQ"),
	    '5'=>$langs->trans("PaymentTypeTIP"),
	    '6'=>$langs->trans("PaymentTypeVAD"),
	    '7'=>$langs->trans("PaymentTypeTRA"),
	    '8'=>$langs->trans("PaymentTypeFAC"),
	    '9'=>$langs->trans("PaymentTypeVIR")         
	);
	print $form->selectarray('viewtype', $listtypes, $viewtype,-1);
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text"  name="search_description" value="'.$search_description.'">';
	print '</td>';
	print '<td class="liste_titre" align="right">';
	print '<input class="flat" type="text" size="6" name="search_debit" value="'.$search_debit.'">';
	print '</td>';
	
	print '<td class="liste_titre" align="right">';
	print '<input class="flat" type="text" size="6" name="search_credit" value="'.$search_credit.'">';
	print '</td>';
	print '<td></td>';
	
	print '<td class="liste_titre" align="right"><input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
	print "</tr>\n";
//----------------------------------------------------------------------------END SEARCH

    $i = 0;
    while ($i < $num)
    {
        $objp = $db->fetch_object($resql);
		$isDescribe = false;
		$theLine ='';
			
	        $var=!$var;
	        $theLine.= "<tr ".$bc[$var].">\n";
	//         print '<form method="post" action="rappro.php?account='.$_GET["account"].'">';
	//         print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	
	//         print "<input type=\"hidden\" name=\"rowid\" value=\"".$objp->rowid."\">";
	
	        // Date op
	        $theLine.= '<td align="center" class="nowrap">'.dol_print_date($db->jdate($objp->do),"day").'</td>';
	
	        // Date value
			if (! $objp->rappro && ($user->rights->banque->modifier || $user->rights->banque->consolidate))
			{
				$theLine.= '<td align="center" class="nowrap">'."\n";
				$theLine.= '<span id="datevalue_'.$objp->rowid.'">'.dol_print_date($db->jdate($objp->dv),"day")."</span>";
				$theLine.= '&nbsp;';
				$theLine.= '<span>';
				$theLine.= '<a class="ajax" href="'.$_SERVER['PHP_SELF'].'?action=dvprev&amp;account='.$acct->id.'&amp;rowid='.$objp->rowid.'">';
				$theLine.= img_edit_remove() . "</a> ";
				$theLine.= '<a class="ajax" href="'.$_SERVER['PHP_SELF'].'?action=dvnext&amp;account='.$acct->id.'&amp;rowid='.$objp->rowid.'">';
				$theLine.= img_edit_add() ."</a>";
				$theLine.= '</span>';
				$theLine.= '</td>';
			}
			else
			{
				$theLine.= '<td align="center">';
				$theLine.= dol_print_date($db->jdate($objp->dv),"day");
				$theLine.= '</td>';
			}
	
			// Type + Number
			$label=($langs->trans("PaymentType".$objp->type)!="PaymentType".$objp->type)?$langs->trans("PaymentType".$objp->type):$objp->type;  // $objp->type is a code
			if ($label=='SOLD') $label='';
			$theLine.= '<td class="nowrap">'.$label.($objp->num_chq?' '.$objp->num_chq:'').'</td>';
	
			// Description
	        $theLine.= '<td valign="center"><a href="'.DOL_URL_ROOT.'/compta/bank/ligne.php?rowid='.$objp->rowid.'&amp;account='.$acct->id.'">';
			$reg=array();
			preg_match('/\((.+)\)/i',$objp->label,$reg);	// Si texte entoure de parentheses on tente recherche de traduction
			if(empty($search_description) || stristr($langs->transnoentities($reg[1]),$search_description )){
			$isDescribe = true;
		}
			if ($reg[1] && $langs->trans($reg[1])!=$reg[1]) $theLine.= $langs->trans($reg[1]); 
			else $theLine.= $objp->label;
	        $theLine.= '</a>';
	
	        /*
	         * Ajout les liens (societe, company...)
	         */
	        $newline=1;
	        $links = $acct->get_url($objp->rowid);
	        foreach($links as $key=>$val)
	        {
	            if ($newline == 0) $theLine.= ' - ';
	            else if ($newline == 1) $theLine.= '<br>';
	            if ($links[$key]['type']=='payment') {
		            $paymentstatic->id=$links[$key]['url_id'];
		            $theLine.= ' '.$paymentstatic->getNomUrl(2);
					if(empty($search_description))$isDescribe = true;
	                $newline=0;
	            }
	            elseif ($links[$key]['type']=='payment_supplier') {
					$paymentsupplierstatic->id=$links[$key]['url_id'];
					$paymentsupplierstatic->ref=$links[$key]['label'];
					$theLine.= ' '.$paymentsupplierstatic->getNomUrl(1);
					if(empty($search_description) ||stristr($links[$key]['label'],$search_description)) $isDescribe = true;
	                $newline=0;
				}
	            elseif ($links[$key]['type']=='company') {
	                $societestatic->id=$links[$key]['url_id'];
	                $societestatic->name=$links[$key]['label'];
	                $theLine.= $societestatic->getNomUrl(1,'',24);
					if(empty($search_description) ||stristr($links[$key]['label'],$search_description)) $isDescribe = true;
					
	                $newline=0;
	            }
				else if ($links[$key]['type']=='sc') {
					$chargestatic->id=$links[$key]['url_id'];
					$chargestatic->ref=$links[$key]['url_id'];
					$chargestatic->lib=$langs->trans("SocialContribution");
					$theLine.= ' '.$chargestatic->getNomUrl(1);
					
					if(empty($search_description) ||stristr($chargestatic->ref,$search_description)) $isDescribe = true;
					
				}
				else if ($links[$key]['type']=='payment_sc')
				{
				    // We don't show anything because there is 1 payment for 1 social contribution and we already show link to social contribution
					/*print '<a href="'.DOL_URL_ROOT.'/compta/payment_sc/card.php?id='.$links[$key]['url_id'].'">';
					print img_object($langs->trans('ShowPayment'),'payment').' ';
					print $langs->trans("SocialContributionPayment");
					print '</a>';*/
				    $newline=2;
				}
				else if ($links[$key]['type']=='payment_vat')
				{
					$paymentvatstatic->id=$links[$key]['url_id'];
					$paymentvatstatic->ref=$links[$key]['url_id'];
					$paymentvatstatic->ref=$langs->trans("VATPayment");
					$theLine.= ' '.$paymentvatstatic->getNomUrl(1);
					if(empty($search_description) ||stristr($paymentvatstatic->ref,$search_description)) $isDescribe = true;
				}
				else if ($links[$key]['type']=='banktransfert') {
					$theLine.= '<a href="'.DOL_URL_ROOT.'/compta/bank/ligne.php?rowid='.$links[$key]['url_id'].'">';
					$theLine.= img_object($langs->trans('ShowTransaction'),'payment').' ';
					$theLine.= $langs->trans("TransactionOnTheOtherAccount");
					if(empty($search_description) ||stristr($langs->transnoentities("TransactionOnTheOtherAccount"),$search_description)) $isDescribe = true;
					$theLine.= '</a>';
				}
				else if ($links[$key]['type']=='member') {
					$theLine.= '<a href="'.DOL_URL_ROOT.'/adherents/card.php?rowid='.$links[$key]['url_id'].'">';
					$theLine.= img_object($langs->trans('ShowMember'),'user').' ';
					$theLine.= $links[$key]['label'];
					if(empty($search_description) ||stristr($links[$key]['label'],$search_description)) $isDescribe = true;
					$theLine.= '</a>';
				}
				else {
					//print ' - ';
					$theLine.= '<a href="'.$links[$key]['url'].$links[$key]['url_id'].'">';
					if (preg_match('/^\((.*)\)$/i',$links[$key]['label'],$reg))
					{
						// Label generique car entre parentheses. On l'affiche en le traduisant
						if ($reg[1]=='paiement') $reg[1]='Payment';
						$theLine.= $langs->trans($reg[1]);
						if(empty($search_description) ||stristr($langs->transnoentities($reg[1]),$search_description)) $isDescribe = true;
					}
					else
					{
						$theLine.= $links[$key]['label'];
						if(empty($search_description) ||stristr($links[$key]['label'],$search_description)) $isDescribe = true;
					}
					$theLine.= '</a>';
	                $newline=0;
	            }
	        }
	        $theLine.= '</td>';
	
	        if ($objp->amount < 0)
	        {
	            $theLine.= "<td align=\"right\" nowrap>".price($objp->amount * -1)."</td><td>&nbsp;</td>\n";
	        }
	        else
	        {
	            $theLine.= "<td>&nbsp;</td><td align=\"right\" nowrap>".price($objp->amount)."</td>\n";
	        }
	
	        if ($objp->rappro)
	        {
	            // If line already reconciliated, we show receipt
	            $theLine.= "<td align=\"center\" nowrap=\"nowrap\"><a href=\"releve.php?num=$objp->num_releve&amp;account=$acct->id\">$objp->num_releve</a></td>";
	        }
	        else
	        {
	            // If not already reconciliated
	            if ($user->rights->banque->modifier)
	            {
	                $theLine.= '<td align="center" width="30" class="nowrap">';
	
	                $theLine.= '<a href="'.DOL_URL_ROOT.'/compta/bank/ligne.php?rowid='.$objp->rowid.'&amp;account='.$acct->id.'&amp;orig_account='.$acct->id.'">';
	                $theLine.= img_edit();
	                $theLine.= '</a>&nbsp; ';
	
	                $now=dol_now();
	                if ($db->jdate($objp->do) <= $now) {
	                    $theLine.= '<a href="'.DOL_URL_ROOT.'/compta/bank/rappro.php?action=del&amp;rowid='.$objp->rowid.'&amp;account='.$acct->id.'">';
	                    $theLine.= img_delete();
	                    $theLine.= '</a>';
	                }
	                else {
	                    $theLine.= "&nbsp;";	// On n'empeche la suppression car le raprochement ne pourra se faire qu'apr�s la date pass�e et que l'�criture apparaisse bien sur le compte.
	                }
	                $theLine.= "</td>";
	            }
	            else
	            {
	                $theLine.= "<td align=\"center\">&nbsp;</td>";
	            }
	        }
        

        // Show checkbox for conciliation
        if ($db->jdate($objp->do) <= $now)
        {

            $theLine.= '<td align="center" class="nowrap">';
            $theLine.= '<input class="flat" name="rowid['.$objp->rowid.']" type="checkbox" value="'.$objp->rowid.'" size="1"'.(! empty($_POST['rowid'][$objp->rowid])?' checked':'').'>';
//             print '<input class="flat" name="num_releve" type="text" value="'.$objp->num_releve.'" size="8">';
//             print ' &nbsp; ';
//             print "<input class=\"button\" type=\"submit\" value=\"".$langs->trans("Conciliate")."\">";
//             if ($options)
//             {
//                 print "<br><select class=\"flat\" name=\"cat\">$options";
//                 print "</select>";
//             }
            $theLine.= "</td>";
        }
        else
        {
            $theLine.= '<td align="left">';
            $theLine.= $langs->trans("FutureTransaction");
            $theLine.= '</td>';
        }

        $theLine.= "</tr>\n";
		if($isDescribe){
			print $theLine;
		}
        $i++;
   
    }
    $db->free($resql);

    print "</table><br>\n";
   

    print '<div align="right"><input class="button" type="submit" value="'.$langs->trans("Conciliate").'"></div><br>';

    print "</form>\n";

}
else
{
  dol_print_error($db);
}


llxFooter();

$db->close();
