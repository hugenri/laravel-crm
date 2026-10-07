{{--
    Documento de la cotización.

    Es una vista Blade propia y no la pantalla de Inertia porque dompdf no
    ejecuta JavaScript: una página que arma sus totales en el navegador
    llegaría aquí con la tabla vacía. Los importes que se imprimen son los
    que el servidor guardó y calculó a partir de las partidas, así que el
    papel y la pantalla no pueden discrepar.

    dompdf implementa CSS 2.1, así que el estilo va en la propia vista, en
    línea o en el atributo style. No hay flexbox ni grid: el motor los
    ignora en silencio y el documento saldría mal alineado.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $quote->folio }}</title>
    <style>
        @page {
            margin: 30px 35px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1e293b;
            line-height: 1.45;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            padding: 7px 8px;
            text-align: left;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #475569;
        }

        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 7px 8px;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #64748b;
        }

        .totals td {
            border-bottom: none;
            padding: 4px 8px;
        }

        .grand-total td {
            border-top: 1px solid #94a3b8;
            font-size: 12.5px;
            font-weight: bold;
            padding-top: 7px;
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 58%; border: none;">
                <h1 style="font-size: 17px; margin: 0 0 2px 0;">Cotización</h1>
                <p style="margin: 0; font-size: 10px;" class="muted">
                    {{ $quote->folio }}
                </p>
            </td>
            <td style="width: 42%; border: none;">
                <table>
                    <tr>
                        <td class="muted" style="border: none; padding: 2px 0;">Emisión</td>
                        <td class="right" style="border: none; padding: 2px 0;">{{ $quote->issue_date->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td class="muted" style="border: none; padding: 2px 0;">Válida hasta</td>
                        <td class="right" style="border: none; padding: 2px 0;">{{ $quote->valid_until->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td class="muted" style="border: none; padding: 2px 0;">Estado</td>
                        <td class="right" style="border: none; padding: 2px 0;">
                            {{ $statuses[$quote->display_status] ?? $quote->display_status }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 18px;">
        <tr>
            <td style="width: 50%; border: 1px solid #e2e8f0; padding: 9px;">
                <p style="margin: 0 0 4px 0; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.03em;" class="muted">
                    Cliente
                </p>
                <p style="margin: 0; font-weight: bold;">{{ $quote->client->name }}</p>
                @if ($quote->client->rfc)
                    <p style="margin: 2px 0 0 0;" class="muted">RFC: {{ $quote->client->rfc }}</p>
                @endif
                @if ($quote->contact)
                    <p style="margin: 6px 0 0 0;">
                        <span class="muted">Contacto:</span>
                        {{ $quote->contact->first_name }} {{ $quote->contact->last_name }}
                    </p>
                @endif
            </td>
            <td style="width: 4%; border: none;"></td>
            <td style="width: 46%; border: 1px solid #e2e8f0; padding: 9px;">
                <p style="margin: 0 0 4px 0; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.03em;" class="muted">
                    Responsable
                </p>
                <p style="margin: 0;">{{ $quote->user->name ?? 'Sin responsable asignado' }}</p>
                @if ($quote->opportunity)
                    <p style="margin: 6px 0 0 0;">
                        <span class="muted">Oportunidad:</span>
                        {{ $quote->opportunity->name }}
                    </p>
                @endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 18px;">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 54%;">Descripción</th>
                <th style="width: 13%;" class="right">Cantidad</th>
                <th style="width: 15%;" class="right">Precio unitario</th>
                <th style="width: 12%;" class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td class="center muted">{{ $item->position }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                    <td class="right">${{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">${{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center muted">Sin partidas registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{--
        Los totales van en una tabla anidada y no en un contenedor con text-align
        porque dompdf no reparte bien el ancho automático de una celda vacía.
    --}}
    <table style="margin-top: 14px;">
        <tr>
            <td style="width: 62%; border: none;"></td>
            <td style="width: 38%; border: none; padding: 0;">
                <table class="totals">
                    <tr>
                        <td class="muted">Subtotal</td>
                        <td class="right">${{ number_format((float) $quote->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="muted">IVA ({{ rtrim(rtrim($quote->tax_rate, '0'), '.') }}%)</td>
                        <td class="right">${{ number_format((float) $quote->tax_amount, 2) }}</td>
                    </tr>
                    <tr class="grand-total">
                        <td>Total</td>
                        <td class="right">${{ number_format((float) $quote->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($quote->notes)
        <table style="margin-top: 20px;">
            <tr>
                <td style="border: none; padding: 0;">
                    <p style="margin: 0 0 4px 0; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.03em;" class="muted">
                        Notas
                    </p>
                    <p style="margin: 0;">{!! nl2br(e($quote->notes)) !!}</p>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin-top: 26px; font-size: 9px;" class="muted">
        Esta cotización es una propuesta comercial y no constituye un comprobante fiscal.
        Los importes están expresados en pesos mexicanos.
    </p>
</body>
</html>
