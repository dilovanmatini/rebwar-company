<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        {!! \App\Support\PrintFont::faces(! empty($forPdf)) !!}

        * {
            box-sizing: border-box;
        }

        body {
            font-family: {!! \App\Support\PrintFont::familyStack() !!};
            direction: rtl;
            color: #111827;
            font-size: 11px;
            line-height: 1.45;
            margin: {{ !empty($forPdf) ? '16px' : '24px' }};
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .doc-head {
            text-align: center;
            margin-bottom: 10px;
        }

        .company {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .subtitle {
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 12px;
        }

        .range {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 11px;
        }

        .range td {
            padding: 0;
            vertical-align: middle;
            width: 33.33%;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
        }

        table.lines col.description { width: auto; }
        table.lines col.qty { width: 16%; }
        table.lines col.total { width: 22%; }
        table.lines col.percent { width: 8%; }

        table.lines th {
            font-weight: 700;
            padding: 4px 6px;
            border-bottom: 1px solid #111827;
            text-align: right;
        }

        table.lines th.num,
        table.lines td.num {
            text-align: left;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        table.lines td {
            padding: 2px 6px;
            vertical-align: top;
            text-align: right;
        }

        tr.customer-head td {
            padding-top: 16px;
            padding-bottom: 2px;
            font-weight: 700;
        }

        tbody tr.customer-head:first-child td {
            padding-top: 8px;
        }

        tr.salesman-head td {
            text-align: center;
            font-weight: 600;
            padding-top: 2px;
            padding-bottom: 6px;
        }

        tr.subtotal td {
            border-top: 1px solid #111827;
            font-weight: 700;
            padding-top: 4px;
            padding-bottom: 2px;
        }

        tr.salesman-total td {
            border-top: 1px solid #111827;
            font-weight: 700;
        }

        tr.grand td {
            border-top: 2px solid #111827;
            font-weight: 700;
            padding-top: 6px;
        }

        .empty {
            text-align: center;
            color: #6b7280;
            padding: 24px 0;
        }

        .footer {
            margin-top: 28px;
            padding-top: 8px;
            border-top: 1px solid #111827;
            text-align: center;
            font-size: 11px;
        }

        .actions {
            margin-bottom: 16px;
            text-align: right;
        }

        .actions a,
        .actions button {
            display: inline-block;
            margin-left: 8px;
            padding: 7px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #111827;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
        }

        @media print {
            .actions {
                display: none;
            }

            body {
                margin: 0;
            }

            thead {
                display: table-header-group;
            }
        }
    </style>
</head>
<body>
    @unless(!empty($forPdf))
        <div class="actions">
            <button type="button" onclick="window.print()">طباعة</button>
            <a href="{{ route('reports.pdf', ['report' => $report['type']] + request()->query()) }}">PDF</a>
            <a href="{{ route('reports.excel', ['report' => $report['type']] + request()->query()) }}">Excel</a>
            <a href="{{ route('reports.show', ['report' => $report['type']] + request()->query()) }}">رجوع</a>
        </div>
    @endunless

    @php
        $forPdf = ! empty($forPdf);
        $fromLabel = $report['from_date'] ?: 'البداية';
        $toLabel = $report['to_date'] ?: 'اليوم';

        $dataCells = function (string $description, string $quantity, string $total, string $percent) use ($forPdf): array {
            $cells = [
                ['class' => '', 'value' => $description],
                ['class' => 'num', 'value' => $quantity],
                ['class' => 'num', 'value' => $total],
                ['class' => 'num', 'value' => $percent],
            ];

            return $forPdf ? array_reverse($cells) : $cells;
        };

        $columns = [
            ['label' => 'البيان', 'class' => ''],
            ['label' => 'الكمية', 'class' => 'num'],
            ['label' => 'الإجمالي', 'class' => 'num'],
            ['label' => '%', 'class' => 'num'],
        ];

        if ($forPdf) {
            $columns = array_reverse($columns);
        }
    @endphp

    <header class="doc-head">
        <p class="company">{{ $app_name }}</p>
        <p class="subtitle">{{ $report['subtitle'] }}</p>
    </header>

    <table class="range">
        <tr>
            @if ($forPdf)
                <td style="text-align:left;">{{ $print_date }}</td>
                <td style="text-align:center;">من : {{ $fromLabel }} &nbsp;&nbsp; إلى : {{ $toLabel }}</td>
                <td></td>
            @else
                <td>{{ $print_date }}</td>
                <td style="text-align:center;">من : {{ $fromLabel }} &nbsp;&nbsp; إلى : {{ $toLabel }}</td>
                <td></td>
            @endif
        </tr>
    </table>

    <table class="lines">
        @if ($forPdf)
            <colgroup>
                <col class="percent">
                <col class="total">
                <col class="qty">
                <col class="description">
            </colgroup>
        @else
            <colgroup>
                <col class="description">
                <col class="qty">
                <col class="total">
                <col class="percent">
            </colgroup>
        @endif
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th @class(['num' => $column['class'] === 'num'])>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['salesmen'] as $salesman)
                @foreach ($salesman['customers'] as $customer)
                    <tr class="customer-head">
                        <td colspan="4">اسم العميل: {{ $customer['name'] }}</td>
                    </tr>
                    <tr class="salesman-head">
                        <td colspan="4">{{ $salesman['name'] }}</td>
                    </tr>
                    @foreach ($customer['products'] as $product)
                        <tr>
                            @foreach ($dataCells($product['name'], $product['quantity'], $product['total'], $product['percent']) as $cell)
                                <td @class(['num' => $cell['class'] === 'num'])>{{ $cell['value'] }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="subtotal">
                        @foreach ($dataCells($salesman['name'], $customer['quantity'], $customer['total'], $customer['percent']) as $cell)
                            <td @class(['num' => $cell['class'] === 'num'])>{{ $cell['value'] }}</td>
                        @endforeach
                    </tr>
                @endforeach
                @if (count($report['salesmen']) > 1 && count($salesman['customers']) > 1)
                    <tr class="salesman-total">
                        @foreach ($dataCells($salesman['name'], $salesman['quantity'], $salesman['total'], $salesman['percent']) as $cell)
                            <td @class(['num' => $cell['class'] === 'num'])>{{ $cell['value'] }}</td>
                        @endforeach
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="4" class="empty">لا توجد بيانات</td>
                </tr>
            @endforelse

            @if (count($report['salesmen']) > 0)
                <tr class="grand">
                    @foreach ($dataCells('الإجمالي', $report['totals']['quantity'], $report['totals']['total'], $report['totals']['percent']) as $cell)
                        <td @class(['num' => $cell['class'] === 'num'])>{{ $cell['value'] }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">{{ $app_name }}</div>
</body>
</html>
