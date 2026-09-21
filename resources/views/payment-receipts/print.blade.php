<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>سند قبض — {{ $receipt['number'] }}</title>
    <style>
        {!! \App\Support\PrintFont::faces() !!}

        * {
            box-sizing: border-box;
        }

        body {
            font-family: {!! \App\Support\PrintFont::familyStack() !!};
            direction: rtl;
            color: #111;
            font-size: 13px;
            line-height: 1.4;
            margin: 0;
            padding: 24px 28px;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .actions {
            margin-bottom: 18px;
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

        .voucher {
            max-width: 760px;
            margin: 0 auto;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .brand img {
            max-height: 48px;
            max-width: 120px;
            object-fit: contain;
        }

        .brand-name {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .company-details {
            text-align: center;
            color: #374151;
            white-space: pre-wrap;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .head {
            position: relative;
            min-height: 58px;
            margin-bottom: 18px;
        }

        .title {
            margin: 0;
            padding-top: 8px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 6px;
        }

        table.id-box {
            position: absolute;
            top: 0;
            inset-inline-end: 0;
            border-collapse: collapse;
            width: 210px;
        }

        table.id-box td {
            border: 1px solid #111;
            padding: 5px 8px;
            font-variant-numeric: tabular-nums;
        }

        table.id-box .label {
            width: 72px;
            text-align: center;
            font-weight: 600;
            white-space: nowrap;
        }

        table.id-box .value {
            text-align: center;
        }

        table.form {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 7px;
        }

        table.form td {
            vertical-align: middle;
        }

        table.form.amounts {
            width: max-content;
            max-width: 100%;
            margin-inline-end: auto;
            border-spacing: 4px 7px;
        }

        table.form.amounts .box {
            width: 200px;
        }

        table.form.amounts .label-box {
            width: 88px;
        }

        table.balances td.box,
        table.balances td.label-box {
            width: 25%;
        }

        .box {
            border: 1px solid #111;
            padding: 7px 10px;
            text-align: center;
            font-variant-numeric: tabular-nums;
            min-width: 96px;
        }

        .label-box {
            border: 1px solid #111;
            padding: 7px 10px;
            text-align: center;
            font-weight: 600;
            white-space: nowrap;
            width: 1%;
        }

        .unit {
            white-space: nowrap;
            width: 1%;
            padding: 0;
            text-align: start;
        }

        .plain {
            white-space: nowrap;
            width: 1%;
            text-align: center;
            padding: 0 6px;
        }

        .grow {
            width: auto;
        }

        .amount-words {
            margin: 10px 8px 0;
            padding: 8px 10px;
            border: 1px solid #111;
            line-height: 1.7;
        }

        .amount-words strong {
            margin-inline-end: 6px;
        }

        .footer {
            margin-top: 18px;
            padding-top: 12px;
            border-top: 1px solid #d1d5db;
            color: #374151;
            white-space: pre-wrap;
            line-height: 1.7;
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            .actions {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">طباعة</button>
        <a href="{{ $back_url }}">رجوع</a>
    </div>

    <div class="voucher">
        <div class="brand">
            <img src="{{ $logo_url }}" alt="{{ $app_name }}">
            <p class="brand-name">{{ $app_name }}</p>
        </div>

        @if ($invoice_header)
            <div class="company-details">{{ $invoice_header }}</div>
        @endif

        <div class="head">
            <h1 class="title">سند قبض</h1>
            <table class="id-box">
                <tr>
                    <td class="label">الرقم</td>
                    <td class="value">{{ $receipt['number'] }}</td>
                </tr>
                <tr>
                    <td class="label">التاريخ</td>
                    <td class="value">{{ $receipt['receipt_date'] }}</td>
                </tr>
            </table>
        </div>

        <table class="form amounts">
            <tr>
                <td class="label-box">المبلغ</td>
                <td class="box">{{ $receipt['usd']['amount'] }}</td>
                <td class="unit">$</td>
                <td class="plain">مايعادل</td>
                <td class="box">{{ $receipt['iqd']['equivalent'] }}</td>
                <td class="unit">دينار</td>
            </tr>
            <tr>
                <td></td>
                <td class="box">{{ $receipt['iqd']['amount'] }}</td>
                <td class="unit">دينار</td>
                <td></td>
                <td class="box">{{ $receipt['usd']['equivalent'] }}</td>
                <td class="unit">$</td>
            </tr>
        </table>

        <table class="form">
            <tr>
                <td class="label-box">نوع القبض</td>
                <td class="box grow">{{ $receipt['receipt_type'] }}</td>
            </tr>
            <tr>
                <td class="label-box">الأسم</td>
                <td class="box grow">{{ $receipt['distributor_name'] }}</td>
            </tr>
        </table>

        <table class="form balances">
            <tr>
                <td class="label-box">ر.سابق للزبون $</td>
                <td class="box">{{ $receipt['usd']['before'] }}</td>
                <td class="label-box">المتبقي $</td>
                <td class="box">{{ $receipt['usd']['after'] }}</td>
            </tr>
            <tr>
                <td class="label-box">ر.سابق دينار</td>
                <td class="box">{{ $receipt['iqd']['before'] }}</td>
                <td class="label-box">المتبقي دينار</td>
                <td class="box">{{ $receipt['iqd']['after'] }}</td>
            </tr>
        </table>

        <table class="form">
            <tr>
                <td class="label-box">الملاحظات</td>
                <td class="box grow">{{ $receipt['notes'] }}</td>
            </tr>
        </table>

        <div class="amount-words">
            <strong>المبلغ كتابةً:</strong>
            {{ $receipt['total_amount_in_words'] }}
        </div>

        @if ($invoice_footer)
            <div class="footer">{{ $invoice_footer }}</div>
        @endif
    </div>

    <script>
        window.addEventListener('load', () => {
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
