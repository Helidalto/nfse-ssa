<?php echo '<?xml version="1.0" encoding="utf-8"?>'; ?>
<ns3:ConsultarNfseRpsEnvio xmlns:ns3="http://www.ginfes.com.br/servico_consultar_nfse_rps_envio_v03.xsd">
<ns3:IdentificacaoRps>
    <Numero xmlns="http://www.ginfes.com.br/tipos_v03.xsd">{{$dados['nfse']['rps']}}</Numero>
    <Serie xmlns="http://www.ginfes.com.br/tipos_v03.xsd">{{$dados['nfse']['serie']}}</Serie>
    <Tipo xmlns="http://www.ginfes.com.br/tipos_v03.xsd">{{$dados['nfse']['tipo']}}</Tipo>
</ns3:IdentificacaoRps>
<ns3:Prestador>
    <Cnpj xmlns="http://www.ginfes.com.br/tipos_v03.xsd">{{$dados['prestador']['cnpj']}}</Cnpj>
    <InscricaoMunicipal xmlns="http://www.ginfes.com.br/tipos_v03.xsd">{{$dados['prestador']['inscricao_municipal']}}</InscricaoMunicipal>
</ns3:Prestador>
</ns3:ConsultarNfseRpsEnvio>