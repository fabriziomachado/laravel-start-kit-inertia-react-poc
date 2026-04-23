{{--
  Variáveis: title, body, approveLabel, rejectLabel, askComment, actionUrl

  Nota: NÃO incluir <input name="__amp_source_origin">. O runtime AMP
  acrescenta automaticamente ?__amp_source_origin=<Origin> ao action-xhr
  (ver https://amp.dev/documentation/guides-and-tutorials/learn/amp-caches-and-cors/amp-cors-requests/).
  Nomes __amp* são reservados e falham na validação AMP4EMAIL.
--}}
<!doctype html>
<html ⚡4email data-css-strict>
<head>
    <meta charset="utf-8">
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <script async custom-element="amp-form" src="https://cdn.ampproject.org/v0/amp-form-0.1.js"></script>
    <script async custom-template="amp-mustache" src="https://cdn.ampproject.org/v0/amp-mustache-0.2.js"></script>
    <style amp4email-boilerplate>body{visibility:hidden}</style>
    <style amp-custom>
        body { font-family: system-ui, sans-serif; color: #18181b; padding: 1rem; max-width: 32rem; margin: 0 auto; }
        h1 { font-size: 1.25rem; margin: 0 0 0.75rem; }
        p { margin: 0 0 1rem; line-height: 1.5; }
        textarea { width: 100%; min-height: 4rem; margin-bottom: 1rem; padding: 0.5rem; box-sizing: border-box; border: 1px solid #d4d4d8; border-radius: 0.375rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        button {
            border: 0;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        button[value="approve"] { background: #16a34a; color: #fff; }
        button[value="reject"] { background: #dc2626; color: #fff; }
        .feedback { margin-top: 1rem; font-size: 0.9375rem; }
        .feedback--ok { color: #15803d; }
        .feedback--err { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{!! nl2br(e($body)) !!}</p>
    <form method="post" action-xhr="{{ $actionUrl }}">
        @if ($askComment ?? false)
            <textarea name="comment" placeholder="Comentário (opcional)"></textarea>
        @endif
        <div class="actions">
            <button type="submit" name="decision" value="approve">{{ $approveLabel }}</button>
            <button type="submit" name="decision" value="reject">{{ $rejectLabel }}</button>
        </div>
        <div submit-success>
            <template type="amp-mustache">
                <p class="feedback feedback--ok">Obrigado! Registramos sua resposta.</p>
            </template>
        </div>
        <div submit-error>
            <template type="amp-mustache">
                <p class="feedback feedback--err">Falha ao registrar: @{{error}}</p>
            </template>
        </div>
    </form>
</body>
</html>
