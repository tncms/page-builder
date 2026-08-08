{{--
    Runtime preview document (Phase 26.41).

    A self-contained, read-only HTML document rendered inside a sandboxed iframe.
    $head and $body are trusted, server-generated fragments:
      - $head : the builder's runtime asset head (tokens + column-width + dynamic
                style CSS, and the frontend stylesheet link) — identical to what the
                public frontend injects.
      - $body : the resource rendered through the production pipeline.
    There is no editor chrome, no selection UI, and no scripts (see the CSP set by
    PreviewController). Device switching only changes the canvas width.
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}" data-pb-preview-device="{{ $device }}" data-pb-preview-kind="{{ $kind }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Preview</title>
    {!! $head !!}
    <style>
        html, body { margin: 0; padding: 0; background: #fff; }
        .pb-preview-frame {
            box-sizing: border-box;
            width: 100%;
            max-width: {{ $width }}px;
            margin: 0 auto;
        }
        .pb-preview-frame img { max-width: 100%; height: auto; }
    </style>
</head>
<body class="pb-preview pb-preview--{{ $device }}">
    <div class="pb-preview-frame" data-pb-preview-canvas>
        {!! $body !!}
    </div>
</body>
</html>
