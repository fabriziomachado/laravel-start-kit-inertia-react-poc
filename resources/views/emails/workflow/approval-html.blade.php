{{--
  Variáveis: title, body, approveLabel, rejectLabel, approveGetUrl, rejectGetUrl
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1a1a1a; max-width: 40rem; margin: 0 auto; padding: 1rem;">
    <h1 style="font-size: 1.25rem;">{{ $title }}</h1>
    <p style="white-space: pre-wrap;">{{ $body }}</p>
    <p style="margin-top: 1.5rem; padding: 1rem; background: #f4f4f5; border-radius: 0.5rem;">
        Este e-mail inclui ações interativas no <strong>Gmail</strong> (AMP for Email).
        Se o seu cliente não suportar AMP, use os links abaixo.
    </p>
    @if ((isset($approveGetUrl) && filled($approveGetUrl)) || (isset($rejectGetUrl) && filled($rejectGetUrl)))
        <p style="margin-top: 1rem;">
            @if (isset($approveGetUrl) && filled($approveGetUrl))
                <a href="{{ $approveGetUrl }}">{{ $approveLabel }}</a>
            @endif
            @if ((isset($approveGetUrl) && filled($approveGetUrl)) && (isset($rejectGetUrl) && filled($rejectGetUrl)))
                <span aria-hidden="true"> · </span>
            @endif
            @if (isset($rejectGetUrl) && filled($rejectGetUrl))
                <a href="{{ $rejectGetUrl }}">{{ $rejectLabel }}</a>
            @endif
        </p>
    @endif
</body>
</html>
