import {
    Button,
    Label,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeadCell,
    TableRow,
    TextInput,
} from 'flowbite-react';
import { Minus, PackageSearch, Plus, Trash2 } from 'lucide-react';
import { useLayoutEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import type { SearchableSelectOption } from '@/components/async-searchable-select';
import InputError from '@/components/input-error';
import { ProductQuickAdd } from '@/components/product-quick-add';
import type { ProductQuickAddHandle } from '@/components/product-quick-add';
import { useCurrency, useFormatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

export type SalesInvoiceLineDraft = {
    product_id: string;
    quantity: string;
    unit_price: string;
};

type Props = {
    lines: SalesInvoiceLineDraft[];
    selectedProducts?: SearchableSelectOption[];
    errors: Record<string, string | undefined>;
    readOnly?: boolean;
    onChange: (lines: SalesInvoiceLineDraft[]) => void;
};

type FocusTarget = {
    index: number;
    field: 'quantity' | 'unit_price';
};

function lineTotal(line: SalesInvoiceLineDraft): string {
    const quantity = Number(line.quantity);
    const unitPrice = Number(line.unit_price);

    if (!Number.isFinite(quantity) || !Number.isFinite(unitPrice)) {
        return '0.00';
    }

    return (quantity * unitPrice).toFixed(2);
}

function bumpQuantity(value: string, delta: number): string {
    const current = Number(value);
    const base = Number.isFinite(current) ? current : 0;
    const next = Math.max(1, base + delta);

    return Number.isInteger(next) ? String(next) : String(next);
}

function lineFieldId(index: number, field: FocusTarget['field']): string {
    return `lines_${index}_${field}`;
}

export function SalesInvoiceLinesEditor({
    lines,
    selectedProducts = [],
    errors,
    readOnly = false,
    onChange,
}: Props) {
    const formatMoney = useFormatMoney();
    const { symbol } = useCurrency();
    const searchRef = useRef<ProductQuickAddHandle>(null);
    const pendingFocus = useRef<FocusTarget | null>(null);
    const [pickedProductOptions, setPickedProductOptions] = useState<
        Record<string, SearchableSelectOption>
    >({});
    const [highlightedProductId, setHighlightedProductId] = useState<
        string | null
    >(null);

    const selectedProductOptions = useMemo(() => {
        const merged: Record<string, SearchableSelectOption> = {
            ...pickedProductOptions,
        };

        for (const option of selectedProducts) {
            merged[String(option.value)] = option;
        }

        return merged;
    }, [pickedProductOptions, selectedProducts]);

    useLayoutEffect(() => {
        const target = pendingFocus.current;

        if (target === null) {
            return;
        }

        pendingFocus.current = null;
        const field = document.getElementById(
            lineFieldId(target.index, target.field),
        );

        if (field instanceof HTMLInputElement) {
            field.focus();
            field.select();
        }
    }, [lines]);

    const updateLine = (
        index: number,
        field: keyof SalesInvoiceLineDraft,
        value: string,
    ) => {
        onChange(
            lines.map((line, i) =>
                i === index ? { ...line, [field]: value } : line,
            ),
        );
    };

    const focusSearch = () => {
        searchRef.current?.focus();
    };

    const removeLine = (index: number) => {
        onChange(lines.filter((_, i) => i !== index));
        focusSearch();
    };

    const addOrIncrementProduct = (option: SearchableSelectOption) => {
        const productId = String(option.value);
        const existingIndex = lines.findIndex(
            (line) => line.product_id === productId,
        );

        setPickedProductOptions((current) => ({
            ...current,
            [productId]: option,
        }));
        setHighlightedProductId(productId);
        window.setTimeout(() => {
            setHighlightedProductId((current) =>
                current === productId ? null : current,
            );
        }, 1200);

        if (existingIndex >= 0) {
            pendingFocus.current = {
                index: existingIndex,
                field: 'quantity',
            };
            onChange(
                lines.map((line, index) =>
                    index === existingIndex
                        ? {
                              ...line,
                              quantity: bumpQuantity(line.quantity, 1),
                          }
                        : line,
                ),
            );

            return;
        }

        const lastPrice = option.meta?.last_unit_price;
        const unitPrice =
            lastPrice !== null && lastPrice !== undefined
                ? String(lastPrice)
                : '';

        pendingFocus.current = {
            index: lines.length,
            field: 'quantity',
        };
        onChange([
            ...lines,
            {
                product_id: productId,
                quantity: '1',
                unit_price: unitPrice,
            },
        ]);
    };

    const onLineKeyDown = (
        event: KeyboardEvent<HTMLInputElement>,
        index: number,
        field: FocusTarget['field'],
    ) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            focusSearch();

            return;
        }

        if (event.key === 'Backspace' && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            removeLine(index);

            return;
        }

        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        if (field === 'quantity') {
            const price = document.getElementById(
                lineFieldId(index, 'unit_price'),
            );

            if (price instanceof HTMLInputElement) {
                price.focus();
                price.select();
            }

            return;
        }

        focusSearch();
    };

    const productTitle = (line: SalesInvoiceLineDraft): string => {
        const option = selectedProductOptions[line.product_id];

        return String(option?.meta?.name_ar ?? option?.label ?? '—');
    };

    const productDetail = (line: SalesInvoiceLineDraft): string | null => {
        const option = selectedProductOptions[line.product_id];
        const parts = [option?.meta?.code, option?.meta?.unit].filter(
            (part): part is string | number =>
                part !== null && part !== undefined && String(part) !== '',
        );

        return parts.length > 0 ? parts.map(String).join(' · ') : null;
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <Label>العناصر</Label>
                {lines.length > 0 && (
                    <span className="text-sm text-gray-500 dark:text-gray-400">
                        {lines.length} عنصر
                    </span>
                )}
            </div>

            {!readOnly && (
                <div className="space-y-1.5">
                    <ProductQuickAdd
                        ref={searchRef}
                        addedProductIds={lines
                            .map((line) => line.product_id)
                            .filter((id) => id !== '')}
                        onPick={addOrIncrementProduct}
                    />
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        ↑↓ للتنقل · Enter اختيار المنتج ثم الكمية ثم السعر ·
                        Ctrl+Backspace لحذف السطر
                    </p>
                </div>
            )}

            <p className="sr-only" aria-live="polite">
                {highlightedProductId
                    ? `تمت إضافة ${productTitle({ product_id: highlightedProductId, quantity: '', unit_price: '' })}`
                    : ''}
            </p>

            <InputError message={errors.lines} />

            {lines.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center dark:border-gray-600">
                    <PackageSearch
                        className="h-8 w-8 text-gray-400"
                        aria-hidden
                    />
                    <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                        لا توجد عناصر بعد
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        اكتب اسم المنتج واضغط Enter، ثم أدخل الكمية والسعر
                    </p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableHeadCell className="text-start">
                                    المنتج
                                </TableHeadCell>
                                <TableHeadCell className="text-start">
                                    الكمية
                                </TableHeadCell>
                                <TableHeadCell className="text-start">
                                    سعر الوحدة ({symbol})
                                </TableHeadCell>
                                <TableHeadCell className="text-end">
                                    الإجمالي
                                </TableHeadCell>
                                {!readOnly && (
                                    <TableHeadCell className="text-end">
                                        <span className="sr-only">إجراءات</span>
                                    </TableHeadCell>
                                )}
                            </TableRow>
                        </TableHead>
                        <TableBody className="divide-y divide-gray-200 dark:divide-gray-700">
                            {lines.map((line, index) => {
                                const isHighlighted =
                                    highlightedProductId === line.product_id;
                                const detail = productDetail(line);

                                return (
                                    <TableRow
                                        key={`${line.product_id}-${index}`}
                                        className={cn(
                                            isHighlighted &&
                                                'bg-primary-50 dark:bg-primary-900/20',
                                        )}
                                        data-test="invoice-line-row"
                                    >
                                        <TableCell className="min-w-48 text-start">
                                            <p className="font-medium text-gray-900 dark:text-white">
                                                {productTitle(line)}
                                            </p>
                                            {detail && (
                                                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                    {detail}
                                                </p>
                                            )}
                                            <InputError
                                                message={
                                                    errors[
                                                        `lines.${index}.product_id`
                                                    ]
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="align-middle">
                                            {readOnly ? (
                                                <span>{line.quantity}</span>
                                            ) : (
                                                <div className="inline-flex items-center gap-1">
                                                    <Button
                                                        type="button"
                                                        color="light"
                                                        size="xs"
                                                        tabIndex={-1}
                                                        aria-label="إنقاص الكمية"
                                                        className="inline-flex items-center justify-center p-2"
                                                        onClick={() =>
                                                            updateLine(
                                                                index,
                                                                'quantity',
                                                                bumpQuantity(
                                                                    line.quantity,
                                                                    -1,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Minus className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <TextInput
                                                        id={lineFieldId(
                                                            index,
                                                            'quantity',
                                                        )}
                                                        type="number"
                                                        min="0"
                                                        step="any"
                                                        value={line.quantity}
                                                        required
                                                        aria-label="الكمية"
                                                        className="w-16"
                                                        onFocus={(event) =>
                                                            event.target.select()
                                                        }
                                                        onKeyDown={(event) =>
                                                            onLineKeyDown(
                                                                event,
                                                                index,
                                                                'quantity',
                                                            )
                                                        }
                                                        onChange={(event) =>
                                                            updateLine(
                                                                index,
                                                                'quantity',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                    <Button
                                                        type="button"
                                                        color="light"
                                                        size="xs"
                                                        tabIndex={-1}
                                                        aria-label="زيادة الكمية"
                                                        className="inline-flex items-center justify-center p-2"
                                                        onClick={() =>
                                                            updateLine(
                                                                index,
                                                                'quantity',
                                                                bumpQuantity(
                                                                    line.quantity,
                                                                    1,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Plus className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            )}
                                            <InputError
                                                message={
                                                    errors[
                                                        `lines.${index}.quantity`
                                                    ]
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="align-middle">
                                            {readOnly ? (
                                                <span>
                                                    {formatMoney(
                                                        line.unit_price,
                                                    )}
                                                </span>
                                            ) : (
                                                <TextInput
                                                    id={lineFieldId(
                                                        index,
                                                        'unit_price',
                                                    )}
                                                    type="number"
                                                    min="0"
                                                    step="any"
                                                    value={line.unit_price}
                                                    required
                                                    aria-label={`سعر الوحدة (${symbol})`}
                                                    className="w-28"
                                                    onFocus={(event) =>
                                                        event.target.select()
                                                    }
                                                    onKeyDown={(event) =>
                                                        onLineKeyDown(
                                                            event,
                                                            index,
                                                            'unit_price',
                                                        )
                                                    }
                                                    onChange={(event) =>
                                                        updateLine(
                                                            index,
                                                            'unit_price',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                            <InputError
                                                message={
                                                    errors[
                                                        `lines.${index}.unit_price`
                                                    ]
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="text-end font-medium text-gray-900 dark:text-white">
                                            {formatMoney(lineTotal(line))}
                                        </TableCell>
                                        {!readOnly && (
                                            <TableCell className="text-end">
                                                <Button
                                                    type="button"
                                                    color="red"
                                                    size="xs"
                                                    tabIndex={-1}
                                                    onClick={() =>
                                                        removeLine(index)
                                                    }
                                                    title="حذف العنصر"
                                                    aria-label="حذف العنصر"
                                                    className="inline-flex items-center justify-center p-2"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            )}
        </div>
    );
}

export function calculateInvoiceSubtotal(
    lines: SalesInvoiceLineDraft[],
): number {
    return lines.reduce((sum, line) => sum + Number(lineTotal(line)), 0);
}
