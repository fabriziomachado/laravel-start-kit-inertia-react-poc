{{--
  Variáveis esperadas: title, body, approveLabel, rejectLabel,
  approveGetUrl, rejectGetUrl (URLs GET assinadas, rota workflow-approvals.fallback)
--}}
{{ $title }}

{{ $body }}

@if (isset($approveGetUrl) && filled($approveGetUrl))
{{ $approveLabel }}: {!! $approveGetUrl !!}
@endif
@if (isset($rejectGetUrl) && filled($rejectGetUrl))
{{ $rejectLabel }}: {!! $rejectGetUrl !!}
@endif

@if ((! isset($approveGetUrl) || blank($approveGetUrl)) && (! isset($rejectGetUrl) || blank($rejectGetUrl)))
Este pedido de aprovação foi concebido para Gmail com conteúdo dinâmico (AMP). Abra o e-mail no Gmail para aprovar ou rejeitar.
@endif
