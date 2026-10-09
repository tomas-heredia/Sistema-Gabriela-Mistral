<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo N° {{ $numeroRecibo }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1c2333; }
        .membrete { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .membrete td { border: none; padding: 0; vertical-align: middle; }
        .membrete .logo { width: 80px; }
        .membrete .logo img { width: 64px; }
        .membrete .datos { text-align: center; }
        .membrete .datos .nombre-colegio { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        .membrete .datos .linea { margin-bottom: 2px; }
        .membrete .datos .de { font-style: italic; margin: 6px 0; }
        hr { border: none; border-top: 1px solid #999; margin-bottom: 12px; }
        .encabezado-recibo { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .encabezado-recibo td { border: none; padding: 0; }
        .encabezado-recibo .fecha { text-align: right; }
        .datos-pago p { margin: 4px 0; }
        .label { color: #444; }
        table.montos { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.montos th, table.montos td { border: none; padding: 3px 6px 3px 0; text-align: left; }
        table.montos thead th { border-bottom: 1px solid #999; padding-bottom: 4px; }
        table.montos .importe { text-align: right; }
        table.montos .total td { border-top: 1px solid #999; padding-top: 6px; font-weight: bold; font-size: 13px; }
        .son { margin-top: 14px; font-style: italic; }
    </style>
</head>
<body>
    @include('pagos.partials.recibo')
</body>
</html>
