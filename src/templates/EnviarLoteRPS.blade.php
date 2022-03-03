<?php echo '<?xml version="1.0" encoding="UTF-8"?>';?>
<ns3:EnviarLoteRpsEnvio xmlns:ns3="http://www.ginfes.com.br/servico_enviar_lote_rps_envio_v03.xsd" xmlns:ns4="http://www.ginfes.com.br/tipos_v03.xsd">
<ns3:LoteRps Id="{{ array_get($dados, 'id') }}">
{!! array_xml_get($dados, 'numero_lote') !!}
{!! array_xml_get($dados, 'cnpj') !!}
{!! array_xml_get($dados, 'inscricao_municipal') !!}
{!! array_xml_get($dados, 'quantidade_rps') !!}
<ns4:ListaRps>
    @foreach($dados['rps'] as $key => $rps)
    <ns4:Rps>
        <ns4:InfRps Id="{{ array_get($rps, 'id') }}">
        <ns4:IdentificacaoRps>            
            @foreach($rps['identificacao'] as $k => $identificacao)
                {!! array_xml_get($rps['identificacao'], $k) !!}
            @endforeach
        </ns4:IdentificacaoRps>
            {!! array_xml_get($rps, 'data_emissao') !!}
            {!! array_xml_get($rps, 'natureza_operacao') !!}
            {!! array_xml_get($rps, 'regime_especial_tributacao') !!}
            {!! array_xml_get($rps, 'optante_simples_nacional') !!}
            {!! array_xml_get($rps, 'incentivador_cultural') !!}
            {!! array_xml_get($rps, 'status') !!}
            <ns4:Servico>
                <ns4:Valores>
                @foreach($rps['servico']['valores'] as $k => $valor)
                    {!! array_xml_get($rps['servico']['valores'], $k) !!}
                @endforeach
                </ns4:Valores>
                {!! array_xml_get($rps['servico'], 'item_lista_servico') !!}
                {!! array_xml_get($rps['servico'], 'codigo_tributacao_municipio') !!}
                {!! array_xml_get($rps['servico'], 'discriminacao') !!}
                {!! array_xml_get($rps['servico'], 'codigo_municipio') !!}
            </ns4:Servico>
            <ns4:Prestador>
                {!! array_xml_get($rps['prestador'], 'cnpj') !!}
                {!! array_xml_get($rps['prestador'], 'inscricao_municipal') !!}
            </ns4:Prestador>
            <ns4:Tomador>
                <ns4:IdentificacaoTomador>
                    <ns4:CpfCnpj>{!! array_xml_get($rps['tomador']['identificacao_tomador']['cpf_cnpj'], 'cnpj') !!}
                    {!! array_xml_get($rps['tomador']['identificacao_tomador']['cpf_cnpj'], 'cpf') !!}
                    </ns4:CpfCnpj>
                    <ns4:InscricaoMunicipal>{!! $rps['tomador']['identificacao_tomador']['inscricao_municipal'] !!}</ns4:InscricaoMunicipal>
                </ns4:IdentificacaoTomador>{!! array_xml_get($rps['tomador'], 'razao_social') !!}
                <ns4:Endereco>
                    @foreach($rps['tomador']['endereco'] as $k => $valor)
                        {!! array_xml_get($rps['tomador']['endereco'], $k) !!}
                    @endforeach
                </ns4:Endereco>
                <ns4:Contato>
                    {!! array_xml_get($rps['tomador']['contato'], 'telefone') !!}
                    {!! array_xml_get($rps['tomador']['contato'], 'email') !!}
                </ns4:Contato>
            </ns4:Tomador>
            @if(isset($rps['construcao_civil']))
            <ns4:ConstrucaoCivil>
                {!! array_xml_get($rps['construcao_civil'], 'codigo_obra') !!}
                {!! array_xml_get($rps['construcao_civil'], 'art') !!}
            </ns4:ConstrucaoCivil>
            @endif
        </ns4:InfRps>
    </ns4:Rps>
    @endforeach
</ns4:ListaRps>
</ns3:LoteRps>
</ns3:EnviarLoteRpsEnvio>