<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Imprimir recibo</title>
    <style>
        html, body { margin: 0; height: 100%; }
        iframe { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body>
    <iframe src="{{ $urlPdf }}" onload="this.contentWindow.focus(); this.contentWindow.print();"></iframe>
</body>
</html>
