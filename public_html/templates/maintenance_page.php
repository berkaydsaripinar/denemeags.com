<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakım Çalışması</title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top, #fef3c7 0%, #f8fafc 45%, #e2e8f0 100%);
            color: #0f172a;
        }

        .card {
            width: min(680px, 100%);
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 20px;
            padding: clamp(28px, 5vw, 44px);
            text-align: center;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.12);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #fff7ed;
            color: #c2410c;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0 0 14px;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        p {
            margin: 0;
            font-size: clamp(16px, 2.6vw, 20px);
            line-height: 1.6;
            color: #334155;
        }

        .instagram {
            display: inline-block;
            margin-top: 18px;
            font-weight: 700;
            color: #be185d;
            text-decoration: none;
        }

        .instagram:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="badge">Bakım Modu Aktif</div>
        <h1>Kısa Bir Ara Verdik</h1>
        <p>
            Sitemiz bakıma alındı. İnstagram adresimizden güncellemeleri takip edebilirsiniz
            <a class="instagram" href="https://instagram.com/denemeags" target="_blank" rel="noopener noreferrer">@denemeags</a>
        </p>
    </main>
</body>
</html>
