<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>فاتورة — {{ $invoice['number'] }}</title>
    <style>
        {!! \App\Support\PrintFont::faces() !!}

        * {
            box-sizing: border-box;
        }

        @page {
            size: A4;
            margin: 10mm 12mm;
        }

        body {
            font-family: {!! \App\Support\PrintFont::familyStack() !!};
            direction: rtl;
            color: #111;
            font-size: 13px;
            line-height: 1.45;
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .actions {
            max-width: 210mm;
            width: 100%;
            margin: 16px auto 0;
            padding: 0 12px;
        }

        .actions a,
        .actions button {
            display: inline-block;
            margin-inline-start: 8px;
            padding: 8px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #111827;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
        }

        .sheet {
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            margin: 12px auto 24px;
            padding: 12mm 14mm 10mm;
            background: #fff;
            display: flex;
            flex-direction: column;
        }

        .brand {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 8px;
        }

        .brand-en {
            text-align: left;
            direction: ltr;
            color: #1a365d;
        }

        .brand-en .name {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.01em;
            line-height: 1.1;
        }

        .brand-en .tag {
            margin: 2px 0 0;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.2;
        }

        .brand-ar {
            text-align: right;
            color: #2b4c7e;
        }

        .brand-ar .name {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            line-height: 1.15;
        }

        .brand-ar .tag {
            margin: 2px 0 0;
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
        }

        .contact {
            display: grid;
            grid-template-columns: 1fr 120px 1fr;
            align-items: center;
            gap: 8px;
            border: 1.5px solid #111;
            padding: 8px 12px;
            min-height: 78px;
        }

        .contact-en {
            direction: ltr;
            text-align: left;
            font-size: 12px;
            line-height: 1.55;
        }

        .contact-ar {
            text-align: right;
            font-size: 13px;
            line-height: 1.55;
            white-space: pre-wrap;
        }

        .contact-logo {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .contact-logo img {
            max-height: 68px;
            max-width: 110px;
            object-fit: contain;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 16px;
            margin: 14px 0 16px;
            font-size: 15px;
            font-weight: 700;
        }

        .meta-invoice {
            direction: ltr;
            text-align: left;
        }

        .meta-date {
            unicode-bidi: isolate;
        }

        .parties {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 24px;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .parties .label {
            font-weight: 700;
        }

        .parties > div {
            min-width: 0;
        }

        .table-box {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            flex: 1;
            height: 100%;
            border: 1.5px solid #111;
        }

        table.lines col.amount { width: 16%; }
        table.lines col.discount { width: 12%; }
        table.lines col.details { width: auto; }
        table.lines col.qty { width: 12%; }
        table.lines col.price { width: 14%; }

        table.lines th {
            background: #1e4b8c;
            color: #fff;
            font-weight: 700;
            padding: 7px 8px;
            text-align: center;
            border: 1px solid #163a6b;
            font-size: 14px;
        }

        table.lines td {
            padding: 6px 8px;
            vertical-align: middle;
            text-align: center;
            border-inline-end: 1px solid #111;
            font-variant-numeric: tabular-nums;
        }

        table.lines tbody tr.data td {
            border-bottom: 1px solid #c5cdd6;
        }

        table.lines tbody tr.spacer td {
            height: 100%;
            border-bottom: none;
            padding: 0;
        }

        table.lines tfoot td {
            border-top: 1.5px solid #111;
            padding: 8px 12px 10px;
            text-align: start;
            vertical-align: top;
        }

        .totals {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            font-weight: 700;
            font-size: 14px;
            width: 100%;
        }

        .totals .row {
            display: flex;
            justify-content: flex-start;
            gap: 18px;
            min-width: 220px;
        }

        .totals .row .value {
            font-variant-numeric: tabular-nums;
            min-width: 90px;
            text-align: end;
            direction: ltr;
        }

        .after-table {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px;
            margin-top: 28px;
        }

        .signs {
            display: flex;
            flex-direction: column;
            gap: 18px;
            font-size: 14px;
        }

        .sign {
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }

        .sign .line {
            border-bottom: 1px solid #111;
            width: 160px;
            height: 1px;
            margin-bottom: 4px;
        }

        .bottom-date {
            font-size: 13px;
            font-variant-numeric: tabular-nums;
            direction: ltr;
            unicode-bidi: isolate;
        }

        .notes {
            margin-top: 16px;
            white-space: pre-wrap;
            font-size: 12px;
        }

        .disclaimer {
            margin-top: 18px;
            border: 1.5px solid #111;
            padding: 6px 10px;
            text-align: center;
            font-size: 13px;
        }

        @media print {
            body {
                background: #fff;
            }

            .actions {
                display: none;
            }

            .sheet {
                width: auto;
                min-height: 277mm;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">طباعة</button>
        <a href="{{ $back_url }}">رجوع</a>
    </div>

    <div class="sheet">
        <div class="brand">
            <div class="brand-ar">
                <p class="name">{{ $app_name }}</p>
                <p class="tag">للتجارة العامة</p>
            </div>
            <div class="brand-en">
                <p class="name">REBWAR CO.</p>
                <p class="tag">For General Trading</p>
            </div>
        </div>

        <div class="contact">
            <div class="contact-ar">
                @if ($invoice_header)
                    {{ $invoice_header }}
                @else
                    العنوان: دهوك - خانكي
                @endif
            </div>
            <div class="contact-logo">
                <img src="{{ $logo_url }}" alt="{{ $app_name }}">
            </div>
            <div class="contact-en">
                @if (! $invoice_header)
                    Address: DUHOK - KHANKI.<br>
                    Tel :0750 785 7834<br>
                    Korek :0750 445 7552
                @endif
            </div>
        </div>

        <div class="meta">
            <div>التاريخ: <span class="meta-date" dir="ltr">{{ $invoice['invoice_date'] }}</span></div>
            <div class="meta-invoice">Invoice # : {{ $invoice['number'] }}</div>
        </div>

        <div class="parties">
            <div>
                <span class="label">حضرة السيد:</span>
                {{ $invoice['distributor']['name'] }}
            </div>
            <div>
                <span class="label">العنوان:</span>
                {{ $invoice['distributor']['address'] ?: '—' }}
            </div>
        </div>

        <div class="table-box">
            <table class="lines">
                <colgroup>
                    <col class="amount">
                    <col class="discount">
                    <col class="details">
                    <col class="qty">
                    <col class="price">
                </colgroup>
                <thead>
                    <tr>
                        <th>المبلغ</th>
                        <th>الخصم</th>
                        <th>التفاصيل</th>
                        <th>العدد</th>
                        <th>السعر</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice['lines'] as $line)
                        <tr class="data">
                            <td>{{ $line['line_total'] }}</td>
                            <td></td>
                            <td>{{ $line['product_name'] }}</td>
                            <td>{{ $line['quantity'] }}</td>
                            <td>{{ $line['unit_price'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="spacer">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">
                            <div class="totals">
                                <div class="row">
                                    <span>المجموع الأولي:</span>
                                    <span class="value">{{ $invoice['subtotal'] }}</span>
                                </div>
                                @if ($invoice['has_discount'])
                                    <div class="row">
                                        <span>الخصم:</span>
                                        <span class="value">{{ $invoice['discount'] }}</span>
                                    </div>
                                @endif
                                <div class="row">
                                    <span>المجموع النهائي:</span>
                                    <span class="value">{{ $invoice['grand_total'] }}</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="after-table">
            <div class="signs">
                <div class="sign">
                    <span>اسم السائق</span>
                    <span class="line"></span>
                </div>
                <div class="sign">
                    <span>رقم السيارة</span>
                    <span class="line"></span>
                </div>
            </div>
            <div class="bottom-date" dir="ltr">{{ $invoice['invoice_date'] }}</div>
        </div>

        @if ($invoice['notes'])
            <div class="notes">{{ $invoice['notes'] }}</div>
        @endif

        <div class="disclaimer">
            {{ $invoice_footer ?: 'الغلط و السهو مرجوع للطرفين.' }}
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            if (new URLSearchParams(window.location.search).has('preview')) {
                return;
            }

            const triggerPrint = () => window.print();

            if (document.fonts?.ready) {
                document.fonts.ready.then(triggerPrint).catch(triggerPrint);
            } else {
                triggerPrint();
            }
        });
    </script>
</body>
</html>
