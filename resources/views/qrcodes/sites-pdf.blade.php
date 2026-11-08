<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes Sites</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }

        .header h1 {
            font-size: 22pt;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 11pt;
            color: #666;
        }

        .qr-grid {
            width: 100%;
        }

        .qr-item {
            border: 2px solid #000;
            padding: 15px;
            margin-bottom: 15px;
            page-break-inside: avoid;
            text-align: center;
        }

        .qr-code {
            margin: 10px auto;
        }

        .qr-code svg {
            width: 180px;
            height: 180px;
        }

        .code {
            font-size: 16pt;
            font-weight: bold;
            margin: 8px 0;
        }

        .name {
            font-size: 13pt;
            margin: 5px 0;
        }

        .details {
            font-size: 10pt;
            color: #555;
            margin-top: 8px;
        }

        .details div {
            margin: 3px 0;
        }

        .qr-data {
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            background: #f0f0f0;
            padding: 5px;
            margin-top: 8px;
            display: inline-block;
        }

        .page-break {
            page-break-after: always;
        }

        /* Deux colonnes */
        .two-columns {
            display: table;
            width: 100%;
        }

        .column {
            display: table-cell;
            width: 50%;
            padding: 5px;
            vertical-align: top;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>QR Codes des Sites</h1>
        <p>Inventaire Mobile - Généré le {{ $date }}</p>
        <p>Total: {{ count($qrCodes) }} site(s)</p>
    </div>

    <div class="qr-grid">
        @foreach($qrCodes->chunk(2) as $chunk)
            <div class="two-columns">
                @foreach($chunk as $item)
                    <div class="column">
                        <div class="qr-item">
                            <div class="qr-code">
                                {!! base64_decode($item['qrCode']) !!}
                            </div>

                            <div class="code">{{ $item['site']->code }}</div>
                            <div class="name">{{ $item['site']->site }}</div>
                            
                            <div class="details">
                                @if($item['site']->localisation)
                                    <div>Localisation : {{ $item['site']->localisation->localisation }}</div>
                                @endif
                                @if($item['site']->responsable)
                                    <div>Responsable : {{ $item['site']->responsable }}</div>
                                @endif
                                <div>Biens : {{ $item['site']->immobilisations_count }}</div>
                            </div>

                            <div class="qr-data">{{ $item['qrData'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            @if(!$loop->last && $loop->iteration % 3 == 0)
                <div class="page-break"></div>
            @endif
        @endforeach
    </div>
</body>
</html>
