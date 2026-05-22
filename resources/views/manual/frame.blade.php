<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @foreach($stylesheets as $url)
        <link rel="stylesheet" href="{{ $url }}">
    @endforeach
    @if($inlineStyles)
        <style>{!! $inlineStyles !!}</style>
    @endif
    <style>
        html, body { margin: 0; padding: 0; background: transparent; }
    </style>
</head>
<body class="{{ $bodyClass }}">
    <div id="page" class="site">
        <div id="content" class="site-content">
            <div id="primary" class="content-area">
                <main id="main" class="site-main">
                    {!! $content !!}
                </main>
            </div>
        </div>
    </div>
    <script>
        function reportHeight() {
            window.parent.postMessage({ manualHeight: document.documentElement.scrollHeight }, '*');
        }
        window.addEventListener('load', reportHeight);
        // Re-report after fonts/images settle
        setTimeout(reportHeight, 800);
    </script>
</body>
</html>
