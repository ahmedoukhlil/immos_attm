<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes - Impression</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: white;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 5mm;
            padding: 5mm;
        }

        .qr-item {
            border: 2px solid #000;
            padding: 8mm;
            page-break-inside: avoid;
            text-align: center;
            background: white;
        }

        .qr-code {
            width: 70mm;
            height: 70mm;
            margin: 0 auto 5mm;
        }

        .qr-code svg {
            width: 100%;
            height: 100%;
        }

        .code {
            font-size: 20pt;
            font-weight: bold;
            margin-bottom: 2mm;
            color: #000;
        }

        .name {
            font-size: 12pt;
            font-weight: 600;
            margin-bottom: 3mm;
            color: #333;
        }

        .location {
            font-size: 10pt;
            color: #666;
            margin: 1mm 0;
        }

        .qr-data {
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            background: #f0f0f0;
            padding: 2mm 4mm;
            margin-top: 3mm;
            display: inline-block;
            border: 1px solid #ddd;
        }

        @media print {
            body {
                background: white;
            }

            .grid {
                padding: 0;
            }

            .qr-item {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>
    <div class="grid">
        @foreach($qrCodes as $item)
            <div class="qr-item">
                <div class="qr-code">
                    {!! $item['qrCode'] !!}
                </div>

                <div class="code">{{ $item['site']->code }}</div>
                <div class="name">{{ $item['site']->site }}</div>
                
                @if($item['site']->localisation)
                    <div class="location">{{ $item['site']->localisation->localisation }}</div>
                @endif
                
                @if($item['site']->responsable)
                    <div class="location">{{ $item['site']->responsable }}</div>
                @endif

                <div class="qr-data">{{ $item['qrData'] }}</div>
            </div>
        @endforeach
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
