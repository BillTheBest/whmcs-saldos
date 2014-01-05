<?php

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

/*
**********************************************

**********************************************
*/

function saldos_config() {
$configarray = array(
'name' => 'Saldos',
'version' => '0.2',
'author' => 'Angel Costa',
'description' => 'Mostra saldos da sua conta F2B, MoIP e Paypal (mais meios serão adicionados)',
'fields' => array(
	'onde' => array("FriendlyName" => "Onde mostra o saldo?", "Type" => "dropdown", "Options" => "Sempre na Home,Apenas na página do módulo", "Description" => "Mostrar o saldo no topo da p&aacute;gina inicial do Admin ou na página deste <a href=addonmodules.php?module=saldos>m&oacute;dulo</a>",),
	'f2b' => array("FriendlyName" => "Mostra F2b?", "Type" => "yesno",),
	'fconta' => array("FriendlyName" => "N. Conta F2b", "Type" => "text", "Size" => "30","Description" => "N&uacutemero da sua conta no F2B sem espa&ccedil;os (dispon&iacute;vel na primeira tela ap&oacute;s login)", ),
	'fsenha' => array("FriendlyName" => "Senha webservices F2b", "Type" => "password", "Size" => "30","Description" => "Senha de webservices (Conta > Seguran&ccedil;a > WebServices > Cadastrar senha)", ),
	'fpin' => array("FriendlyName" => "Pin da conta F2b", "Type" => "password", "Size" => "30", "Description" => "Informe o PIN cadastrado",),
	'moip' => array("FriendlyName" => "Mostra MoIP?", "Type" => "yesno", ),
	'mlogin' => array("FriendlyName" => "Login MoIP", "Type" => "text", "Size" => "30","Description" => "Login da sua conta no MoIP", ),
	'msenha' => array("FriendlyName" => "Senha MoIP", "Type" => "password", "Size" => "30", "Description" => "Senha da sua conta no MOIP",),
	'pagseguro' => array("FriendlyName" => "Mostra PagSeguro?", "Type" => "yesno", ),
	'pagemail' => array("FriendlyName" => "Email PagSeguro", "Type" => "text", "Size" => "30","Description" => "Login da sua conta no PagSeguro", ),
	'pagsenha' => array("FriendlyName" => "Senha PagSeguro", "Type" => "password", "Size" => "30", "Description" => "Senha da sua conta no PagSeguro",),
	'paypal' => array("FriendlyName" => "Mostra Paypal?", "Type" => "yesno", "Description" => "Apenas contas especiais (upgrade gratuito). <a href=https://www.paypal.com/br/cgi-bin/webscr?cmd=_profile-api-signature>J&aacute; tem dados da API?</a> Se não, acesse a <a href=https://www.paypal.com/cgi-bin/customerprofileweb?cmd=_profile-api-access&upgrade.x=1>API</a>, escolha a op&ccedil&atilde;o 2 e pegue os dados.",),
	'pplogin' => array("FriendlyName" => "Login Paypal (API)", "Type" => "text", "Size" => "30", "Description" => "",),
	'ppsenha' => array("FriendlyName" => "Senha Paypal (API)", "Type" => "password", "Size" => "30","Description" => "", ),
	'ppassina' => array("FriendlyName" => "Assinatura (API)", "Type" => "password", "Size" => "30","Description" => "", ),
	),
);
return $configarray;
}

function saldos_output($vars) {

if ($vars[f2b]=='on'){

	require_once("f2b/WSBalance.php");
	$WSBalance = new WSBalance();
	$xmlObj = $WSBalance->add_node("","soap-env:Envelope");
	$WSBalance->add_attributes($xmlObj, array("xmlns:soap-env" => "http://schemas.xmlsoap.org/soap/envelope/") );
	$xmlObj = $WSBalance->add_node($xmlObj,"soap-env:Body");
	$xmlObjF2bSaldo = $WSBalance->add_node($xmlObj,"m:F2bSaldo");
	$WSBalance->add_attributes($xmlObjF2bSaldo, array("xmlns:m" => "http://www.f2b.com.br/soap/wsbalance.xsd") );
	$xmlObj = $WSBalance->add_node($xmlObjF2bSaldo,"mensagem");
	$WSBalance->add_attributes($xmlObj, array("data" => date("Y-m-d"),
	                                          "numero" => date("His")));
	$xmlObj = $WSBalance->add_node($xmlObjF2bSaldo, "cliente");
	$WSBalance->add_attributes($xmlObj, array("conta" => $vars[fconta],
	                                          "senha" => $vars[fsenha],
	                                          "pin" => $vars[fpin]));
	$WSBalance->send($WSBalance->getXML());
	$resposta = $WSBalance->resposta;
	if(strlen($resposta) > 0){
		$WSBalance = new WSBalance($resposta);
		$log = $WSBalance->pegaLog();
		if($log["texto"] == "OK"){
			$saldo = $WSBalance->pegaSaldo();
			$fdisponivel = $saldo[0][disponivel];
			$fbloqueado = $saldo[0][bloqueado];
			$ffuturo = $saldo[0][futuro];
			echo '<div style="background:#FFCC00; border:1px solid #1052AD; padding:5px; margin:10px">';
			echo "<strong>F2b</strong> - Total: R$ $fdisponivel | Bloqueado: R$ $fbloqueado | A receber: R$ $ffuturo";
			echo '</div>';
		} 
	} else {
		echo '<font color="red">Sem resposta</font>';
	}
}//fim f2b

if ($vars[moip]=='on'){
	require 'moip/MoIPStatus.php';
	$status = new MoIPStatus();
	$status->setCredenciais($vars[mlogin],$vars[msenha]);
	$status->getStatus();
	$saldos = $status->saldos_geral;
	$valores = explode('R$ ',$saldos);
	if ($valores[4]){$areceber = "| A receber: R$ $valores[4]";}
	echo '<div style="background:#E5ECF8; border:1px solid #5B6DAD; padding:5px; margin:10px">';
	echo "<strong>MoIP</strong> - Total: R$ $valores[1]| Bloqueado: R$ $valores[2]| Disponivel: R$ $valores[3] $areceber";
	echo '</div>';
}//fim moip

if ($vars[pagseguro]=='on'){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://acesso.uol.com.br/login.html');
	curl_setopt ($ch, CURLOPT_POST, 1);
	curl_setopt ($ch, CURLOPT_POSTFIELDS, "user=$vars[pagemail]&pass=$vars[pagsenha]&skin=ps&dest=REDIR|https://pagseguro.uol.com.br/transaction/search.jhtml");
	curl_setopt ($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	$store = curl_exec ($ch);
	curl_setopt($ch, CURLOPT_URL, 'https://pagseguro.uol.com.br/transaction/search.jhtml');
	$content = curl_exec ($ch);
	curl_close ($ch);
	preg_match_all("#<dl>(.*?)<\/dl>#s",$content,$saldos);
	$saldo = utf8_encode(substr(trim(strip_tags($saldos[1][2])),75));
	//$saldo= preg_replace("/[^[:space:]a-z0-9]/e", "", $saldo);
$saldo= trim($saldo);
$saldo= preg_replace('/\s\s+/', '_', $saldo);
$saldos = explode("_",$saldo);
	echo '<div style="background:#f2f2f2; border:1px solid #77BB34; padding:5px; margin:10px">';
	echo "<strong>PagSeguro</strong> - Total: R$ $saldos[1] | Bloqueado: R$ $saldos[3] | Disponivel: R$ $saldos[5]";
	echo '</div>';
}

if($vars[paypal]=='on'){

	$nvpStr="";
  	$API_UserName = urlencode($vars[pplogin]);
	$API_Password = urlencode($vars[ppsenha]);
	$API_Signature = urlencode($vars[ppassina]);
	$API_Endpoint = "https://api-3t.paypal.com/nvp";
	$version = urlencode('51.0');
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	$nvpreq = "METHOD=GetBalance&VERSION=$version&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature$nvpStr_";
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	$httpResponse = curl_exec($ch);
	if(!$httpResponse) {
		exit("$methodName_ failed: ".curl_error($ch).'('.curl_errno($ch).')');
	}
	$httpResponseAr = explode("&", $httpResponse);
	$httpParsedResponseAr = array();
	foreach ($httpResponseAr as $i => $value) {
		$tmpAr = explode("=", $value);
		if(sizeof($tmpAr) > 1) {
			$httpParsedResponseAr[$tmpAr[0]] = $tmpAr[1];
		}
	}
	if((0 == sizeof($httpParsedResponseAr)) || !array_key_exists('ACK', $httpParsedResponseAr)) {
		exit("Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.");
	}

	if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {	
		echo '<div style="background:#EFEFEF; border:1px solid #1A3665; padding:5px; margin:10px">';
		echo '<strong>Paypal</strong> - ';
		if ("BRL"== $httpParsedResponseAr['L_CURRENCYCODE0']) {echo "R$ ";}
		echo str_replace(".",",",urldecode($httpParsedResponseAr['L_AMT0']));
		echo '</div>';
	} else  {
		echo "Dados errados";
	}
}//fim pp

} //fim output

?>
