<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada | SOLUCIONESCEL</title>
    <script>(function(){ var t=localStorage.getItem('sc_theme')||'dark'; document.documentElement.setAttribute('data-bs-theme',t); })();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-image:
                radial-gradient(ellipse 80% 60% at 50% 0%, rgba(59,130,246,0.08), transparent),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(99,102,241,0.06), transparent);
        }
        .page-404 {
            text-align: center;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }
        .error-code {
            font-size: clamp(6rem, 18vw, 12rem);
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 50%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            letter-spacing: -0.04em;
        }
        .error-icon-wrap {
            margin-bottom: 1.5rem;
        }
        .error-icon {
            font-size: 3rem;
            color: rgba(148,163,184,0.3);
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.75rem;
        }
        .error-desc {
            font-size: 0.9375rem;
            color: rgba(241,245,249,0.6);
            max-width: 400px;
            margin: 0 auto 2rem;
            line-height: 1.6;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 0.9375rem;
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            border-radius: 12px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59,130,246,0.35);
            color: #fff;
        }
        .bg-shapes {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }
        .bg-shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.04;
            filter: blur(60px);
        }
        .bg-shape-1 {
            width: 500px; height: 500px;
            background: #3b82f6;
            top: -10%; left: -10%;
        }
        .bg-shape-2 {
            width: 400px; height: 400px;
            background: #6366f1;
            bottom: -10%; right: -10%;
        }
        .bg-shape-3 {
            width: 300px; height: 300px;
            background: #a78bfa;
            top: 50%; left: 40%;
        }

        [data-bs-theme="light"] body {
            background: #f1f5f9;
            color: #0f172a;
            background-image:
                radial-gradient(ellipse 80% 60% at 50% 0%, rgba(59,130,246,0.06), transparent),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(99,102,241,0.04), transparent);
        }
        [data-bs-theme="light"] .error-title { color: #0f172a; }
        [data-bs-theme="light"] .error-desc { color: rgba(15,23,42,0.6); }
        [data-bs-theme="light"] .error-icon { color: rgba(71,85,105,0.4); }
        [data-bs-theme="light"] .bg-shape { opacity: 0.06; }
    </style>
</head>
<body>
    <div class="bg-shapes">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>
        <div class="bg-shape bg-shape-3"></div>
    </div>

    <div class="page-404">
        <div class="error-code">404</div>
        <div class="error-icon-wrap">
            <i class="bi bi-signpost-split error-icon"></i>
        </div>
        <h1 class="error-title">Página no encontrada</h1>
        <p class="error-desc">
            La página que buscas no existe, fue movida o la URL es incorrecta. 
            Verifica la dirección o regresa al inicio.
        </p>
        <a href="./" class="btn-home">
            <i class="bi bi-house-fill"></i>
            Volver a la página inicial
        </a>
    </div>
</body>
</html>
