<?php

namespace Potelo\NfseSsa\Services;


use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SignatureService
{
    /**
     * @var string
     */
    public $certificatePrivate;

    /**
     * @var string
     */
    public $certificatePublic;

    public function __construct()
    {
        $configuracao =  DB::table('configuracoes')->select("id","nfse_link")->where('id','=',Auth::user()->unidade)->first();
        $this->certificatePrivate = config('nfse-ssa.certificado_privado_path'.$configuracao->id);
        $this->certificatePublic = config('nfse-ssa.certificado_publico_path'.$configuracao->id);
    }

    /**
     * @param $xml
     * @param bool $signRoot
     * @param array $tags
     * @return string
     * @throws \Exception
     */
    public function signXml($xml, $signRoot=true, $tags=[])
    {   
        //dd($xml);             
        // Load the XML to be signed
        $doc = new \DOMDocument();       
        $doc->loadXML($xml);  

        // Create a new Security object
        $objDSig = new XMLSecurityDSig('');
        // Use the c14n exclusive canonicalization
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);
        // Sign using SHA-SHA1
        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA1,
            array('http://www.w3.org/2000/09/xmldsig#enveloped-signature','http://www.w3.org/TR/2001/REC-xml-c14n-20010315'),            
            array('force_uri' => true)
        );
        // Create a new (private) Security key
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
        // Load the private key
        $objKey->loadKey($this->certificatePrivate, TRUE);

        // Sign the XML file
        //alterei dessa forma
        //$objDSig->sign($objKey);        
        $objDSig->sign($objKey, $doc->documentElement);

        // Add the associated public key to the signature
        $objDSig->add509Cert(file_get_contents($this->certificatePublic));
        if($signRoot) {
            // Append the signature to the XML
            $objDSig->appendSignature($doc->documentElement);
        }
        // The signed XML        
        return $doc->saveXML();
    }
}