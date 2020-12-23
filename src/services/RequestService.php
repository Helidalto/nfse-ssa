<?php

namespace Helidalto\NfseSsa\Services;


use Helidalto\NfseSsa\MySoapClient;
use Helidalto\NfseSsa\Request\Error;
use Helidalto\NfseSsa\Request\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RequestService
{

    /**
     * @var string
     */
    public $certificatePrivate;

    /**
     * @var string
     */
    private $urlBase;

    /**
     * @var array
     */
    private $soapOptions;


    public function __construct()
    {
        $configuracao =  DB::table('configuracoes')->select("id","nfse_link")->where('id','=',Auth::user()->unidade)->first();
        if($configuracao) {            
            $this->urlBase = $configuracao->nfse_link;            
        }else{
            $this->urlBase = 'https://homologacao.ginfes.com.br';
            //$this->urlBase = 'https://producao.ginfes.com.br';            
        }

        $this->certificatePrivate = config('nfse-ssa.certificado_privado_path');
        $context = stream_context_create([
            'ssl' => [
                // set some SSL/TLS specific options
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $this->soapOptions = [
            'keep_alive' => true,
            'trace' => true,
            'local_cert' => $this->certificatePrivate,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'stream_context' => $context
        ];

    }

    /**
     * @param $wsdlSuffix
     * @param $xml
     * @param $method
     * @param $return
     * @return Response
     */
    private function consult($wsdlSuffix, $xml, $method, $return){
        
        $wsdl = $this->urlBase . $wsdlSuffix;
        try{
            $client = new \SoapClient($wsdl, $this->soapOptions);                        
        }catch (SoapFault $e ){
            echo "erro de conexão soap. Tente novamente mais tarde !<br>\n";
            echo $e->getMessage();
            return false;
        }
        //obtendo funcoes disponiveis do servidor
        //dd($client->__getFunctions()); 
        $cabecalho = '<ns2:cabecalho versao="3" xmlns:ns2="http://www.ginfes.com.br/cabecalho_v03.xsd"><versaoDados>3</versaoDados></ns2:cabecalho>';                
        //Salvando XML de envio
        Storage::disk('local')->put('nfse/XML_NFSE_Envio.xml', $xml);
        //dd($xml);
        try{
            //enviando a requisição para o webservice
            $result = $client->RecepcionarLoteRpsV3($cabecalho, $xml);            
            //dd($result);
        }catch (Exception $e){ 
            $result = false;
            dd("<h2>Erro ao enviar o lote RPS!</h2>"); 
            echo $e->getMessage(); 
        }        
        $xmlObj = simplexml_load_string($result);                                                         
        $response = new Response();

        if(isset($xmlObj->ListaMensagemRetorno)){
            //mensagem de erro                        
            $msgRetorno = new \DOMDocument();
            $msgRetorno->loadXML($result); 
            $error = new Error();                
            $error->codigo = $msgRetorno->getElementsByTagName('Codigo')->item(0)->nodeValue;
            $error->mensagem = $msgRetorno->getElementsByTagName('Mensagem')->item(0)->nodeValue;
            $error->correcao = $msgRetorno->getElementsByTagName('Correcao')->item(0)->nodeValue;
            $response->addError($error);            
            $response->setStatus(false);
            //dd($response);            
        }else{
            //sucesso        
            $xmlObj = new \DOMDocument();
            $xmlObj->loadXML($result);                                                                            
            $response->setStatus(true);                
            $data = array('Protocolo' => $xmlObj->getElementsByTagName('Protocolo')->item(0)->nodeValue);
            $response->setData($data);                             
        }        
        return $response;
    }
    /**
     * @param $xml
     * @return Response
     */
    public function enviarLoteRps($xml)
    {
        //endereco WSDL
        $wsdlSuffix = '/ServiceGinfesImpl?wsdl';        
        $wsdl = $this->urlBase . $wsdlSuffix;
        
        try{
            $client = new \SoapClient($wsdl, $this->soapOptions);                        
        }catch (SoapFault $e ){
            echo "Erro de conexão soap. Tente novamente mais tarde !<br>\n";
            echo $e->getMessage();
            return false;
        }
        //obtendo funcoes disponiveis do servidor
        //dd($client->__getFunctions()); 
        $cabecalho = '<ns2:cabecalho versao="3" xmlns:ns2="http://www.ginfes.com.br/cabecalho_v03.xsd"><versaoDados>3</versaoDados></ns2:cabecalho>';                
        //Salvando XML de envio
        Storage::disk('local')->put('nfse/XML_NFSE_Envio.xml', $xml);

        //dd($xml);
        try{
            //enviando a requisição para o webservice
            $result = $client->RecepcionarLoteRpsV3($cabecalho, $xml);            
            //dd($result);
        }catch (Exception $e){ 
            $result = false;
            dd("<h2>Erro ao enviar o lote RPS!</h2>"); 
            echo $e->getMessage(); 
        }        
        $xmlObj = simplexml_load_string($result);                                                         
        $response = new Response();

        if(isset($xmlObj->ListaMensagemRetorno)){
            //mensagem de erro                        
            $msgRetorno = new \DOMDocument();
            $msgRetorno->loadXML($result); 
            $error = new Error();                
            $error->codigo = $msgRetorno->getElementsByTagName('Codigo')->item(0)->nodeValue;
            $error->mensagem = $msgRetorno->getElementsByTagName('Mensagem')->item(0)->nodeValue;
            $error->correcao = $msgRetorno->getElementsByTagName('Correcao')->item(0)->nodeValue;
            $response->addError($error);            
            $response->setStatus(false);
            //dd($response);            
        }else{
            Storage::disk('local')->put('nfse/XML_NFSE_RETORNO.xml', $result);

            //se retornar o protocolo na tag de retorno
            if(isset($xmlObj->getElementsByTagName('Protocolo')->item(0)->nodeValue)){
                //sucesso        
                $response->setStatus(true);                
                $data = array('Protocolo' => $xmlObj->getElementsByTagName('Protocolo')->item(0)->nodeValue);
                $response->setData($data);                             
            }else{
                $error = new Error();                
                $error->codigo = $xmlObj->getElementsByTagName('Codigo')->item(0)->nodeValue;
                $error->mensagem = $xmlObj->getElementsByTagName('Mensagem')->item(0)->nodeValue;
                $error->correcao = $xmlObj->getElementsByTagName('Correcao')->item(0)->nodeValue;
                $response->addError($error);            
                $response->setStatus(false);
            }                        
        }        
        return $response;
    }

    /**
     * @param $xml
     * @return Response
     */
    public function consultarSituacaoLoteRps($xml)
    {

        $wsdlSuffix = '/ServiceGinfesImpl?wsdl';

        $wsdl = $this->urlBase . $wsdlSuffix;
        try{
            $client = new \SoapClient($wsdl, $this->soapOptions);                        
        }catch (SoapFault $e ){
            echo "erro de conexão soap. Tente novamente mais tarde !<br>\n";
            echo $e->getMessage();
            return false;
        }
        //obtendo funcoes disponiveis do servidor
        //dd($client->__getFunctions()); 
        $cabecalho = '<ns2:cabecalho versao="3" xmlns:ns2="http://www.ginfes.com.br/cabecalho_v03.xsd"><versaoDados>3</versaoDados></ns2:cabecalho>';
        /*
        $xml2 = '<?xml version="1.0" encoding="UTF-8"?><ns3:ConsultarSituacaoLoteRpsEnvio xmlns:ns3="http://www.ginfes.com.br/servico_consultar_situacao_lote_rps_envio_v03.xsd" xmlns:ns4="http://www.ginfes.com.br/tipos_v03.xsd"><ns3:Prestador><ns4:Cnpj>28110564000104</ns4:Cnpj><ns4:InscricaoMunicipal>1605500</ns4:InscricaoMunicipal></ns3:Prestador><ns3:Protocolo>9628183</ns3:Protocolo><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/><SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/><Reference URI=""><Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/><Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><DigestValue>I81Th9k6NZ+AWEjVNge69woIa2I=</DigestValue></Reference></SignedInfo><SignatureValue>qrVt0eTjnDzba7tNLOSY/kSXNV3y7JAqcHfSxUlubAQ447xYEjZvieLGF1LVajmTWXrXCyg+r0WmCthEoiRp+TM46HxZAMhFlkj7h4QNsVEwrn0DCFnC/sVc/7WfFcQYUxuvKZJX2f9xgWujWj+sDYHp4YvABOQvtt9IbCU83vwjL6fHCMTmUTskFhh4B5A/HCN0KVkD2jRnXXyPqz6NfPA739aYKy92ECakJLx4LP5zFTaCK23vYqayR+zUVWijqqg5KAA9qPEfSRXtVTRUmxOaDz7mCGRujZ2Dg7gJ2+QwZ4BVjIWV6TdXA7haSlnliQecqipOJy5TWT1Zw08Zyw==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></ns3:ConsultarSituacaoLoteRpsEnvio>';               
        */
                
        //dd($xml);   
        //Salvando XML de envio
        Storage::disk('local')->put('nfse/XML__NFSE_Consulta_situacao.xml', $xml);
        try{
            //executando a funcao ConsultarLoteRpsV3 do webservice
            $result = $client->ConsultarSituacaoLoteRpsV3($cabecalho, $xml);                        
            //dd($result);
        }catch (Exception $e){ 
            $result = false;
            dd("<h2>Erro ao consultar o lote RPS!</h2>"); 
            echo $e->getMessage(); 
        }          
        $xmlObj = simplexml_load_string($result);                                                              
        $response = new Response();

        if(isset($xmlObj->ListaMensagemRetorno)){                        
            $msgRetorno = new \DOMDocument();
            $msgRetorno->loadXML($result); 
            $error = new Error();                
            $error->codigo = $msgRetorno->getElementsByTagName('Codigo')->item(0)->nodeValue;
            $error->mensagem = $msgRetorno->getElementsByTagName('Mensagem')->item(0)->nodeValue;
            $error->correcao = $msgRetorno->getElementsByTagName('Correcao')->item(0)->nodeValue;
            $response->addError($error);            
            $response->setStatus(false);
            //dd($response);            
        }else {        
            $xmlObj = new \DOMDocument();
            $xmlObj->loadXML($result);                                       
            $getSituacao = $xmlObj->getElementsByTagName('Situacao');                    
            foreach ($getSituacao as $situacao){                
                $response->setStatus(true);                
                $data = array('Situacao' => $situacao->nodeValue );
                $response->setData($data);                
            } 
        }
        return $response;
    }

    /**
     * @param $xml
     * @return Response
     */
    public function consultarLoteRps($xml)
    {

        $wsdlSuffix = '/ServiceGinfesImpl?wsdl';

        $wsdl = $this->urlBase . $wsdlSuffix;
        try{
            $client = new \SoapClient($wsdl, $this->soapOptions);                        
        }catch (SoapFault $e ){
            echo "erro de conexão soap. Tente novamente mais tarde !<br>\n";
            echo $e->getMessage();
            return false;
        }
        //obtendo funcoes disponiveis do servidor
        //dd($client->__getFunctions()); 

        $cabecalho = '<ns2:cabecalho versao="3" xmlns:ns2="http://www.ginfes.com.br/cabecalho_v03.xsd"><versaoDados>3</versaoDados></ns2:cabecalho>';
        /*
        $xml2 = '<?xml version="1.0" encoding="UTF-8"?><ns3:ConsultarLoteRpsEnvio xmlns:ns3="http://www.ginfes.com.br/servico_consultar_lote_rps_envio_v03.xsd" xmlns:ns4="http://www.ginfes.com.br/tipos_v03.xsd"><ns3:Prestador><ns4:Cnpj>28110564000104</ns4:Cnpj><ns4:InscricaoMunicipal>1605500</ns4:InscricaoMunicipal></ns3:Prestador><ns3:Protocolo>9628183</ns3:Protocolo><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/><SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/><Reference URI=""><Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/><Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><DigestValue>W9y7VddbQC3BidS9RED1LoqFC6E=</DigestValue></Reference></SignedInfo><SignatureValue>G0Gf0E8Npa94tGikXjbK1AMGdqLgoR8RnZcdDF3F4/otHcb0YK7FjGVzvh1jAM8x+oJM9LuSnpDN833VZl3ttaKTHCy+xH83fMBxG/eRwnUsSzI6RtZkyuqHzq5FLX7gYyrdcAqoBGLPBaJhjGI/nbpNLDZU6cuh3glFAC20i6bRLsK204ckesYHfIbWcE1GnxxV6dCRpdLIXHb/rBPuq0B0rwqkBciS9RxgvMmuj4h47kb2BLpsWUE1/2UQd+CbiaH2hRLD89I7p1Vjqd6gmxMjdw5o2d89ywtdwd6PloaJysdQ5DaQgkDwigLwi5gpS9cBrYjCn62vAv0AJdFeTA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></ns3:ConsultarLoteRpsEnvio>';               
        */
                
        //dd($xml);   
        //Salvando XML
        Storage::disk('local')->put('nfse/XML_NFSE_Consulta.xml', $xml);
        try{
            //executando a funcao ConsultarLoteRpsV3 do webservice
            $result = $client->ConsultarLoteRpsV3($cabecalho, $xml);  
                                      
        }catch (Exception $e){ 
            $result = false;
            dd("<h2>Erro ao consultar o lote RPS!</h2>"); 
            echo $e->getMessage(); 
        }          
        $xmlObj = simplexml_load_string($result);                                                 
        $response = new Response();

        if(isset($xmlObj->ListaMensagemRetorno)){                        
            $msgRetorno = new \DOMDocument();
            $msgRetorno->loadXML($result); 
            $error = new Error();                
            $error->codigo = $msgRetorno->getElementsByTagName('Codigo')->item(0)->nodeValue;
            $error->mensagem = $msgRetorno->getElementsByTagName('Mensagem')->item(0)->nodeValue;
            $error->correcao = $msgRetorno->getElementsByTagName('Correcao')->item(0)->nodeValue;
            $response->addError($error);            
            $response->setStatus(false);                       
        }else {        

            Storage::disk('local')->put('nfse/XML_NFSE_Resposta.xml', $result);
            $getListaNFSe = $xmlObj->getElementsByTagName('ListaNfse');      
            $data = [];                          
            foreach($getListaNFSe as $lista){ 
                $getNFSe = $lista->getElementsByTagName('InfNfse'); 

                foreach($getNFSe as $nfse){      
                    $response->setStatus(true);
                    //retornando xml, rps, codigo verificacao e numero da nfs-e gerado pela prefeitura                    
                    $verificacao = $nfse->getElementsByTagName("CodigoVerificacao")->item(0)->nodeValue;
                    $numero = $nfse->getElementsByTagName("Numero")->item(0)->nodeValue;  
                    $rps = $nfse->getElementsByTagName("Numero")->item(1)->nodeValue;    
                    array_push($data, array(
                        'xml' => $result,
                        'codigoVerificacao' => $verificacao,
                        'NFSe' => $numero,
                        'Rps' => $rps
                    ));                          
                }         
            } 
            $response->setData($data); 
        }
        return $response;
    }

    /**
     * @param $xml
     * @return Response
     */
    public function consultarNfseRps($xml)
    {
        $wsdlSuffix = '/ServiceGinfesImpl?wsdl';

        $wsdl = $this->urlBase . $wsdlSuffix;
        try{
            $client = new \SoapClient($wsdl, $this->soapOptions);                        
        }catch (SoapFault $e ){
            echo "erro de conexão soap. Tente novamente mais tarde !<br>\n";
            echo $e->getMessage();
            return false;
        }
        //obtendo funcoes disponiveis do servidor
        //dd($client->__getFunctions()); 
        //$cabecalho = '<ns2:cabecalho versao="3" xmlns:ns2="http://www.ginfes.com.br/cabecalho_v03.xsd"><versaoDados>3</versaoDados></ns2:cabecalho>';
        $cabecalho = '<cabec:cabecalho xmlns:cabec="http://www.ginfes.com.br/cabecalho_v03.xsd" versao="3"><versaoDados>3</versaoDados></cabec:cabecalho>';
        /*
        $xml2 = '<?xml version="1.0" encoding="UTF-8"?><ns3:ConsultarLoteRpsEnvio xmlns:ns3="http://www.ginfes.com.br/servico_consultar_lote_rps_envio_v03.xsd" xmlns:ns4="http://www.ginfes.com.br/tipos_v03.xsd"><ns3:Prestador><ns4:Cnpj>28110564000104</ns4:Cnpj><ns4:InscricaoMunicipal>1605500</ns4:InscricaoMunicipal></ns3:Prestador><ns3:Protocolo>9628183</ns3:Protocolo><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/><SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/><Reference URI=""><Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/><Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><DigestValue>W9y7VddbQC3BidS9RED1LoqFC6E=</DigestValue></Reference></SignedInfo><SignatureValue>G0Gf0E8Npa94tGikXjbK1AMGdqLgoR8RnZcdDF3F4/otHcb0YK7FjGVzvh1jAM8x+oJM9LuSnpDN833VZl3ttaKTHCy+xH83fMBxG/eRwnUsSzI6RtZkyuqHzq5FLX7gYyrdcAqoBGLPBaJhjGI/nbpNLDZU6cuh3glFAC20i6bRLsK204ckesYHfIbWcE1GnxxV6dCRpdLIXHb/rBPuq0B0rwqkBciS9RxgvMmuj4h47kb2BLpsWUE1/2UQd+CbiaH2hRLD89I7p1Vjqd6gmxMjdw5o2d89ywtdwd6PloaJysdQ5DaQgkDwigLwi5gpS9cBrYjCn62vAv0AJdFeTA==</SignatureValue><KeyInfo><X509Data><X509Certificate>MIIHyjCCBbKgAwIBAgIIdcxpV7bdEuEwDQYJKoZIhvcNAQELBQAwczELMAkGA1UEBhMCQlIxEzARBgNVBAoTCklDUC1CcmFzaWwxNjA0BgNVBAsTLVNlY3JldGFyaWEgZGEgUmVjZWl0YSBGZWRlcmFsIGRvIEJyYXNpbCAtIFJGQjEXMBUGA1UEAxMOQUMgTElOSyBSRkIgdjIwHhcNMTkwNzExMTI1NTI0WhcNMjAwNzExMTI1NTI0WjCB7DELMAkGA1UEBhMCQlIxCzAJBgNVBAgTAk1HMRMwEQYDVQQHEwpDQVRBR1VBU0VTMRMwEQYDVQQKEwpJQ1AtQnJhc2lsMTYwNAYDVQQLEy1TZWNyZXRhcmlhIGRhIFJlY2VpdGEgRmVkZXJhbCBkbyBCcmFzaWwgLSBSRkIxFjAUBgNVBAsTDVJGQiBlLUNOUEogQTExFzAVBgNVBAsTDjEyNTE3NzA0MDAwMTE1MT0wOwYDVQQDEzRDTElNQSBSRUZSSUdFUkFDQU8gREUgQ0FUQUdVQVNFUyBMVERBOjI4MTEwNTY0MDAwMTA0MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuzxf/0mHVI7iWtSIwoJrKaourOeB5hJ6znogDsbqxt+eyBHmm51+9eC+cM5jOf0JhG6tZDMrCOg850Ls/dZQrtaARIPERfBWa+BvX4KU8zq1eAQLUdvD/57Zj3JIaLvFuLkVYWHegNzAGZhe9H3eKD4YgXguZpOhxanxZDkyvKsqX9yFGuqXhfbMN4NxYEJ03iao5nQq8+VLv4Rt6FlrwfsQixuA2cjxR9Z2WgMkWYStgYGkAFnZOUB1pgBDtU2viO3x2J0cAz0L1RpL4NpxdFgy78IO2xMBuC49q48xD6QlFtoUvt+xz5Hdcj9wRnQZUW15PukHHTMAGAHNyHjSswIDAQABo4IC5jCCAuIwHwYDVR0jBBgwFoAUDd/WR/QTTuUiWDIsZqbnLuRXvAIwDgYDVR0PAQH/BAQDAgXgMG4GA1UdIARnMGUwYwYGYEwBAgE7MFkwVwYIKwYBBQUHAgEWS2h0dHA6Ly9yZXBvc2l0b3Jpby5saW5rY2VydGlmaWNhY2FvLmNvbS5ici9hYy1saW5rcmZiL2FjLWxpbmstcmZiLXBjLWExLnBkZjCBsAYDVR0fBIGoMIGlMFCgTqBMhkpodHRwOi8vcmVwb3NpdG9yaW8ubGlua2NlcnRpZmljYWNhby5jb20uYnIvYWMtbGlua3JmYi9sY3ItYWMtbGlua3JmYnY1LmNybDBRoE+gTYZLaHR0cDovL3JlcG9zaXRvcmlvMi5saW5rY2VydGlmaWNhY2FvLmNvbS5ici9hYy1saW5rcmZiL2xjci1hYy1saW5rcmZidjUuY3JsMIGVBggrBgEFBQcBAQSBiDCBhTBSBggrBgEFBQcwAoZGaHR0cDovL3JlcG9zaXRvcmlvLmxpbmtjZXJ0aWZpY2FjYW8uY29tLmJyL2FjLWxpbmtyZmIvYWMtbGlua3JmYnY1LnA3YjAvBggrBgEFBQcwAYYjaHR0cDovL29jc3AubGlua2NlcnRpZmljYWNhby5jb20uYnIwgckGA1UdEQSBwTCBvoElQ0xJTUFSRUZSSUdFUkFDQU9GSU5BTkNFSVJPQEdNQUlMLkNPTaAnBgVgTAEDAqAeExxEQUlBTkEgTUFSSUEgT0xJVkVJUkEgU09BUkVToBkGBWBMAQMDoBATDjI4MTEwNTY0MDAwMTA0oDgGBWBMAQMEoC8TLTEwMDIxOTg5MDg2OTE3NTI2MjkwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMKAXBgVgTAEDB6AOEwwwMDAwMDAwMDAwMDAwHQYDVR0lBBYwFAYIKwYBBQUHAwIGCCsGAQUFBwMEMAkGA1UdEwQCMAAwDQYJKoZIhvcNAQELBQADggIBAAfAVoRQMLf/LNKvr0cYNCzWlGffSABrdBi4oas2HoSIbJObeQ1UagfM0AzoSTVtrFmQZcVeTn32tcOfunUHdNF3M8q0QOhbA5ESdHmO5BgHIGrPJ4yD7nzPd6sBXxLZLFgMDEKD7t7evNYFt+JXJD/7nhPI5kCO1LuRYmJvS0PslfL1VX6/GKX5fl8ycesgjLO8Nm1MKDuJe//QtrBPxAxe5dDr6jtES45Id31x3brYYR0+Q8HIDN493l3Pv5x90TMThTtHq0lahSDeOJuWfrM2xw5+lCZA93k8PBW3znhGFq53KD5xJXqwny41uP2+XYALCUlJKT8nPbrH45WEXL6e0kPIVGi8LusB4cIoorN0Q0ECWvo+13MipjX//jAlfR3eEnxCwDzAKBs3vh/ZuBRa46iEL06t1eV9j+N9/eFeFcqBC+um+5FysEZGx9X+38+6Rs6Xw1p36ngHJxg5xXp38H4MdFMLnAartZZvkhNaA0WM7+ezyzp0LT1drzLUVBstUxBR1ydkq5jqWHGLcKW6463EwTU9VCMJ30vUeXwfY2f9F9mJ36WKqS8/zZzO/TNC/gkrJ1qgHXOFolLsWXz7b+v0zb8vUdxefd8ACVeLAhEnYu+C2mcIjqrbRHKbLftK86Ldk2J0rQVeiifQ4gdLkk/qKzMdropwT4Y8Ku/R</X509Certificate></X509Data></KeyInfo></Signature></ns3:ConsultarLoteRpsEnvio>';               
        */
                
        //dd($xml);   
        //Salvando XML de envio
        Storage::disk('local')->put('nfse/XML_NFSE_Consulta_RPS.xml', $xml);

        try{
            //executando a funcao ConsultarLoteRpsV3 do webservice
            $result = $client->ConsultarNfsePorRpsV3($cabecalho, $xml);  
                                      
        }catch (Exception $e){ 
            $result = false;
            dd("<h2>Erro ao consultar o lote RPS!</h2>"); 
            echo $e->getMessage(); 
        }       
         
        $xmlObj = simplexml_load_string($result);      
        
        $xmlObj = new \DOMDocument();
        $xmlObj->loadXML($result);                                                  
        $response = new Response();

        if(isset($xmlObj->ListaMensagemRetorno)){  
                                  
            $msgRetorno = new \DOMDocument();
            $msgRetorno->loadXML($result); 
            $error = new Error();                
            $error->codigo = $msgRetorno->getElementsByTagName('Codigo')->item(0)->nodeValue;
            $error->mensagem = $msgRetorno->getElementsByTagName('Mensagem')->item(0)->nodeValue;
            $error->correcao = $msgRetorno->getElementsByTagName('Correcao')->item(0)->nodeValue;
            $response->addError($error);            
            $response->setStatus(false);                       
        }else {        
            Storage::disk('local')->put('nfse/XML_NFSE__RPS_Resposta.xml', $result);
            $getListaNFSe = $xmlObj->getElementsByTagName('CompNfse');      
            $data = [];                          
            foreach($getListaNFSe as $lista){ 
                $getNFSe = $lista->getElementsByTagName('InfNfse'); 

                foreach($getNFSe as $nfse){      
                    $response->setStatus(true);
                    //retornando xml, rps, codigo verificacao e numero da nfs-e gerado pela prefeitura                    
                    $verificacao = $nfse->getElementsByTagName("CodigoVerificacao")->item(0)->nodeValue;
                    $numero = $nfse->getElementsByTagName("Numero")->item(0)->nodeValue;  
                    $rps = $nfse->getElementsByTagName("Numero")->item(1)->nodeValue;    
                    array_push($data, array(
                        'xml' => $result,
                        'codigoVerificacao' => $verificacao,
                        'NFSe' => $numero,
                        'Rps' => $rps
                    ));                          
                }         
            } 
            $response->setData($data); 
        }
        return $response;
    }

    public function cancelarNFSe($xml)
    {
        
        $wsdlSuffix = '/ServiceGinfesImpl?wsdl';

        $wsdl = $this->urlBase . $wsdlSuffix;
        try{
            $client = new \SoapClient($wsdl, $this->soapOptions);                        
        }catch (SoapFault $e ){
            echo "erro de conexão soap. Tente novamente mais tarde !<br>\n";
            echo $e->getMessage();
            return false;
        }
        //obtendo funcoes disponiveis do servidor
        //dd($client->__getFunctions()); 

        //$cabecalho = '<ns2:cabecalho versao="3" xmlns:ns2="http://www.ginfes.com.br/cabecalho_v03.xsd"><versaoDados>3</versaoDados></ns2:cabecalho>';
        /*
        $xml2 = '<?xml version="1.0" encoding="UTF-8"?><ns3:ConsultarLoteRpsEnvio xmlns:ns3="http://www.ginfes.com.br/servico_consultar_lote_rps_envio_v03.xsd" xmlns:ns4="http://www.ginfes.com.br/tipos_v03.xsd"><ns3:Prestador><ns4:Cnpj>28110564000104</ns4:Cnpj><ns4:InscricaoMunicipal>1605500</ns4:InscricaoMunicipal></ns3:Prestador><ns3:Protocolo>9628183</ns3:Protocolo><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/><SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/><Reference URI=""><Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/><Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><DigestValue>W9y7VddbQC3BidS9RED1LoqFC6E=</DigestValue></Reference></SignedInfo><SignatureValue>G0Gf0E8Npa94tGikXjbK1AMGdqLgoR8RnZcdDF3F4/otHcb0YK7FjGVzvh1jAM8x+oJM9LuSnpDN833VZl3ttaKTHCy+xH83fMBxG/eRwnUsSzI6RtZkyuqHzq5FLX7gYyrdcAqoBGLPBaJhjGI/nbpNLDZU6cuh3glFAC20i6bRLsK204ckesYHfIbWcE1GnxxV6dCRpdLIXHb/rBPuq0B0rwqkBciS9RxgvMmuj4h47kb2BLpsWUE1/2UQd+CbiaH2hRLD89I7p1Vjqd6gmxMjdw5o2d89ywtdwd6PloaJysdQ5DaQgkDwigLwi5gpS9cBrYjCn62vAv0AJdFeTA==</SignatureValue><KeyInfo><X509Data><X509Certificate>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</X509Certificate></X509Data></KeyInfo></Signature></ns3:ConsultarLoteRpsEnvio>';               
        */
                
        //dd($xml);   
        //Salvando XML
        Storage::disk('local')->put('nfse/XML_NFSE_Cancelamento.xml', $xml);

        try{
            //executando a funcao ConsultarLoteRpsV3 do webservice
            //$result = $client->CancelarNfseV3($cabecalho, $xml);                                    
            $result = $client->CancelarNfse($xml);                                    
            //dd($result);
        }catch (Exception $e){ 
            $result = false;
            dd("<h2>Erro ao cancelar NFSe!</h2>"); 
            echo $e->getMessage(); 
        }          
        $xmlObj = simplexml_load_string($result);                                                             
        $response = new Response();

        if(isset($xmlObj->ListaMensagemRetorno)){                        
            $msgRetorno = new \DOMDocument();
            $msgRetorno->loadXML($result); 
            $error = new Error();                
            $error->codigo = $msgRetorno->getElementsByTagName('Codigo')->item(0)->nodeValue;
            $error->mensagem = $msgRetorno->getElementsByTagName('Mensagem')->item(0)->nodeValue;
            $error->correcao = $msgRetorno->getElementsByTagName('Correcao')->item(0)->nodeValue;
            $response->addError($error);            
            $response->setStatus(false);                       
        }else {        
            
            $xmlObj = new \DOMDocument();
            $xmlObj->loadXML($result);                
            $getNFSe = $xmlObj->getElementsByTagName('CancelarNfseResposta');                    
            foreach($getNFSe as $nfse){                
                $response->setStatus(true);
                //retornando sucesso true, e data de cancelamento da NFSe
                $sucesso = $nfse->getElementsByTagName("Sucesso");
                $dataHora = $nfse->getElementsByTagName("DataHora");    
                $data = array('sucesso' => $sucesso->item(0)->nodeValue, 'data' => $dataHora->item(0)->nodeValue);
                $response->setData($data);                
            } 
        }
        return $response;
    }    


    public function consultarNfse($xml)
    {
        $wsdlSuffix = '/rps/CONSULTANFSE/ConsultaNfse.svc?wsdl';

        $finalXml = $this->generateXmlBody($xml, 'ConsultarNfse', 'consultaxml');

        $response = $this->consult(
            $wsdlSuffix,
            $finalXml,
            'ConsultarNfse',
            'ConsultarNfseResult');

        return $response;
    }
}