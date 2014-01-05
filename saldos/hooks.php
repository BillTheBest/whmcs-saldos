<?php
/*
**********************************************

**********************************************
*/

function saldos_mostra_saldo($vars) {

function saldo_f2b(){
$query = mysql_query("SELECT setting, value FROM tbladdonmodules WHERE module='saldos' AND setting='fconta' OR setting='fsenha' OR setting='fpin'");
while($row = mysql_fetch_array($query)) {
  $f2blogin[$row['setting']] = $row['value'];
  }
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
$WSBalance->add_attributes($xmlObj, array("conta" => $f2blogin[fconta],
                                          "senha" => $f2blogin[fsenha],
                                          "pin" => $f2blogin[fpin]));
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
		echo "Total: R$ $fdisponivel | Bloqueado: R$ $fbloqueado | A receber: R$ $ffuturo";
	} 
} else {
	echo '<font color="red">Sem resposta</font>';
}
  
}
//Fim da funcao f2b

function saldo_moip() {
$query = mysql_query("SELECT setting,value FROM tbladdonmodules WHERE module='saldos' AND setting='mlogin' OR setting='msenha'");
while($row = mysql_fetch_array($query)) {
  $moiplogin[$row['setting']] = $row['value'];
  }
require 'moip/MoIPStatus.php';
$status = new MoIPStatus();
$status->setCredenciais($moiplogin[mlogin],$moiplogin[msenha]);
$status->getStatus();
$saldos = $status->saldos_geral;
$valores = explode('R$ ',$saldos);
if ($valores[4]){$areceber = "| A receber: R$ $valores[4]";}
echo "Total: R$ $valores[1]| Bloqueado: R$ $valores[2]| Disponivel: R$ $valores[3] $areceber";
}
//Fim da funcao Moip

function saldo_pagseguro() {
$query = mysql_query("SELECT setting,value FROM tbladdonmodules WHERE module='saldos' AND setting='pagemail' OR setting='pagsenha'");
while($row = mysql_fetch_array($query)) {
  $paglogin[$row['setting']] = $row['value'];
  }
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://acesso.uol.com.br/login.html');
	curl_setopt ($ch, CURLOPT_POST, 1);
	curl_setopt ($ch, CURLOPT_POSTFIELDS, "user=$paglogin[pagemail]&pass=$paglogin[pagsenha]&skin=ps&dest=REDIR|https://pagseguro.uol.com.br/transaction/search.jhtml");
	curl_setopt ($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	$store = curl_exec ($ch);
	curl_setopt($ch, CURLOPT_URL, 'https://pagseguro.uol.com.br/transaction/search.jhtml');
	$content = curl_exec ($ch);
	curl_close ($ch);
	preg_match_all("#<dl>(.*?)<\/dl>#s",$content,$saldos);
	$saldo = utf8_encode(substr(trim(strip_tags($saldos[1][2])),75));
	$saldo= trim($saldo);
	$saldo= preg_replace('/\s\s+/', '_', $saldo);
	$saldos = explode("_",$saldo);
	echo "Total: R$ $saldos[1] | Bloqueado: R$ $saldos[3] | Disponivel: R$ $saldos[5]";

}
//Fim da funcao pagseguro

function saldo_paypal(){

function PPHttpPost($methodName_, $nvpStr_) {

$query = mysql_query("SELECT * FROM tbladdonmodules WHERE module='saldos' AND setting='pplogin' OR setting='ppsenha' OR setting='ppassina'");
while($row = mysql_fetch_array($query)) {
  $pplogin[$row['setting']] = $row['value']; 
  }
  	$API_UserName = urlencode($pplogin[pplogin]);
	$API_Password = urlencode($pplogin[ppsenha]);
	$API_Signature = urlencode($pplogin[ppassina]);
	$API_Endpoint = "https://api-3t.paypal.com/nvp";
	$version = urlencode('51.0');
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	$nvpreq = "METHOD=$methodName_&VERSION=$version&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature$nvpStr_";
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
	return $httpParsedResponseAr;
}

$nvpStr="";

$httpParsedResponseAr = PPHttpPost('GetBalance', $nvpStr);

if("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
	if ("BRL"== $httpParsedResponseAr['L_CURRENCYCODE0']) {echo "R$ ";}
	echo str_replace(".",",",urldecode($httpParsedResponseAr['L_AMT0']));
} else  {
	echo "Dados errados";
}

}
//fim funcao paypal

$query = mysql_query("SELECT setting, value FROM tbladdonmodules WHERE module='saldos' AND value='on'");
while($row = mysql_fetch_array($query)) {
  $gates[] = $row['setting'];
  }
  if($gates){
echo '<div style="background:#FCFCDE;border:1px solid #F9E459; padding:5px; position:absolute; top:35px; left:50%; margin-left:-275px; width:625px; font-size:11px; font-family:verdana">';
if (in_array("f2b",$gates)){
	echo '<strong>F2b:</strong> ';
	saldo_f2b();
	echo '<br />';
}
if (in_array("moip",$gates)){
	echo '<strong>MoIP:</strong> ';
	saldo_moip();
	echo '<br />';
}
if (in_array("pagdigital",$gates)){
	echo '<strong>Pag. Digital:</strong> ';
	echo '<br />';
}
if (in_array("pagseguro",$gates)){
	echo '<strong>PagSeguro:</strong> ';
	saldo_pagseguro();
	echo '<br />';
}
if (in_array("paypal",$gates)){
	echo '<strong>Paypal:</strong> ';
	saldo_paypal();
}


echo '</div>';
}
}
$query = mysql_query("SELECT setting, value FROM tbladdonmodules WHERE module='saldos' AND setting='onde'");
while($row = mysql_fetch_array($query)) {
  $local = $row['value'];
  }
if ($local == "Sempre na Home"){
add_hook("AdminHomepage",1,"saldos_mostra_saldo");
}

?>
