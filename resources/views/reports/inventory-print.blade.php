<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        {!! \App\Support\PrintFont::faces(! empty($forPdf)) !!}

        * { box-sizing: border-box; }

        body {
            font-family: {!! \App\Support\PrintFont::familyStack() !!};
            direction: rtl;
            color: #111827;
            font-size: 10px;
            line-height: 1.4;
            margin: {{ !empty($forPdf) ? '12px' : '20px' }};
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .actions { margin-bottom: 16px; }
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

        .doc-head {
            text-align: center;
            margin-bottom: 8px;
        }

        .company {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 2px;
        }

        .subtitle {
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .meta {
            color: #4b5563;
            margin: 0 0 10px;
        }

        table.inventory {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        col.col-name { width: 22%; }
        col.col-qty { width: 9%; }
        col.col-unit { width: 8%; }
        col.col-money { width: 10.16%; }

        table.inventory th,
        table.inventory td {
            border: 1px solid #9ca3af;
            padding: 3px 5px;
            text-align: right;
            vertical-align: middle;
        }

        table.inventory th {
            background: #f3f4f6;
            font-weight: 700;
        }

        table.inventory .num {
            text-align: left;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        table.inventory .identity td,
        table.inventory .location td,
        table.inventory .group-heading td {
            text-align: center;
            font-weight: 700;
            background: #f9fafb;
        }

        table.inventory .identity td,
        table.inventory .location td {
            background: #fff;
            padding: 6px;
        }

        table.inventory .totals td,
        table.inventory .grand td {
            font-weight: 700;
            background: #f9fafb;
        }

        table.inventory .grand td {
            background: #e5e7eb;
        }

        table.inventory .empty {
            text-align: center;
            color: #6b7280;
            padding: 16px;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        @media print {
            .actions { display: none; }
            body { margin: 0; font-size: 9px; }
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
        $columns = $forPdf ? array_reverse($report['columns']) : $report['columns'];
        $colCount = count($columns);
        $colClass = function (array $column): string {
            return match ($column['key']) {
                'name' => 'col-name',
                'quantity' => 'col-qty',
                'unit' => 'col-unit',
                default => 'col-money',
            };
        };
    @endphp

    <header class="doc-head">
        <p class="company">{{ $app_name }}</p>
        <p class="subtitle">{{ $report['title'] }}</p>
        <p class="meta">{{ $generated_at }}</p>
    </header>

    <table class="inventory">
        <colgroup>
            @foreach ($columns as $column)
                <col class="{{ $colClass($column) }}">
            @endforeach
        </colgroup>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th @class(['num' => ! empty($column['numeric'])])>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr class="identity">
                <td colspan="{{ $colCount }}">الاسم : {{ $app_name }}</td>
            </tr>
            <tr class="location">
                <td colspan="{{ $colCount }}">الموقع : المخزن</td>
            </tr>

            @forelse ($report['groups'] as $group)
                <tr class="group-heading">
                    <td colspan="{{ $colCount }}">{{ $group['name'] }}</td>
                </tr>

                @foreach ($group['rows'] as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td @class(['num' => ! empty($column['numeric'])])>{{ $row[$column['key']] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach

                <tr class="totals">
                    @foreach ($columns as $column)
                        <td @class(['num' => ! empty($column['numeric'])])>{{ $group['totals'][$column['key']] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ $colCount }}">لا توجد بيانات</td>
                </tr>
            @endforelse

            @if (count($report['groups']) > 0)
                <tr class="grand">
                    @foreach ($columns as $column)
                        <td @class(['num' => ! empty($column['numeric'])])>{{ $report['grand_totals'][$column['key']] ?? '' }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
