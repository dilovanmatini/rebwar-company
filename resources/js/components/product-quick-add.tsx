import { useHttp } from '@inertiajs/react';
import { Spinner, TextInput } from 'flowbite-react';
import { Search } from 'lucide-react';
import { useEffect, useId, useImperativeHandle, useRef, useState } from 'react';
import type { KeyboardEvent, Ref } from 'react';
import type { SearchableSelectOption } from '@/components/searchable-select';
import { lookupQuery } from '@/hooks/use-lookup-options';
import { useFormatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { products as productLookups } from '@/routes/lookups';

type LookupResponse = {
    data: SearchableSelectOption[];
};

export type ProductQuickAddHandle = {
    focus: () => void;
};

type Props = {
    addedProductIds: string[];
    disabled?: boolean;
    onPick: (option: SearchableSelectOption) => void;
    ref?: Ref<ProductQuickAddHandle>;
};

function pickProductOption(
    query: string,
    options: SearchableSelectOption[],
    activeIndex: number,
    navigatedWithKeys: boolean,
): SearchableSelectOption | null {
    if (options.length === 0) {
        return null;
    }

    if (navigatedWithKeys) {
        return options[activeIndex] ?? options[0] ?? null;
    }

    const normalized = query.trim();

    if (normalized !== '') {
        const exactBarcode = options.find(
            (option) => String(option.meta?.barcode ?? '') === normalized,
        );

        if (exactBarcode) {
            return exactBarcode;
        }

        const exactCode = options.find(
            (option) => String(option.meta?.code ?? '') === normalized,
        );

        if (exactCode) {
            return exactCode;
        }
    }

    return options[activeIndex] ?? options[0] ?? null;
}

export function ProductQuickAdd({
    addedProductIds,
    disabled = false,
    onPick,
    ref,
}: Props) {
    const formatMoney = useFormatMoney();
    const fieldId = useId();
    const listboxId = `${fieldId}-listbox`;
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const navigatedWithKeys = useRef(false);
    const { get, processing } = useHttp({});
    const getRef = useRef(get);

    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<SearchableSelectOption[]>([]);
    const [activeIndex, setActiveIndex] = useState(0);
    const [hasSearched, setHasSearched] = useState(false);

    const added = new Set(addedProductIds);

    useImperativeHandle(ref, () => ({
        focus: () => {
            inputRef.current?.focus();
            setOpen(true);
        },
    }));

    useEffect(() => {
        getRef.current = get;
    }, [get]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const term = query.trim();
        const delay = term === '' ? 0 : 150;
        const timer = window.setTimeout(() => {
            getRef.current(
                productLookups.url(lookupQuery(term, { limit: 15 })),
                {
                    onSuccess: (response) => {
                        const payload = response as LookupResponse;
                        const next = payload.data ?? [];
                        setOptions(next);
                        setHasSearched(true);
                        setActiveIndex(0);
                    },
                },
            );
        }, delay);

        return () => window.clearTimeout(timer);
    }, [open, query]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: MouseEvent) => {
            if (
                rootRef.current &&
                !rootRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointerDown);

        return () => document.removeEventListener('mousedown', onPointerDown);
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        document
            .getElementById(`${listboxId}-option-${activeIndex}`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex, listboxId, open]);

    const commit = (option: SearchableSelectOption) => {
        onPick(option);
        setQuery('');
        setActiveIndex(0);
        setOpen(false);
        navigatedWithKeys.current = false;
    };

    const commitFromKeyboard = () => {
        const option = pickProductOption(
            query,
            options,
            activeIndex,
            navigatedWithKeys.current,
        );

        if (option) {
            commit(option);

            return true;
        }

        return false;
    };

    const onInputKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);

            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            navigatedWithKeys.current = true;
            setActiveIndex((current) =>
                options.length === 0
                    ? 0
                    : Math.min(current + 1, options.length - 1),
            );

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            navigatedWithKeys.current = true;
            setActiveIndex((current) => Math.max(current - 1, 0));

            return;
        }

        if (
            event.key === 'Tab' &&
            open &&
            options.length > 0 &&
            query.trim() !== ''
        ) {
            event.preventDefault();
            commitFromKeyboard();

            return;
        }

        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        if (commitFromKeyboard()) {
            return;
        }

        const term = query.trim();

        if (term === '') {
            return;
        }

        get(productLookups.url(lookupQuery(term, { limit: 15 })), {
            onSuccess: (response) => {
                const payload = response as LookupResponse;
                const next = payload.data ?? [];
                setOptions(next);
                setHasSearched(true);

                const option = pickProductOption(term, next, 0, false);

                if (option) {
                    commit(option);
                }
            },
        });
    };

    const subtitle = (option: SearchableSelectOption): string | null => {
        const parts = [
            option.meta?.code,
            option.meta?.barcode,
            option.meta?.unit,
        ]
            .filter(
                (part): part is string | number =>
                    part !== null && part !== undefined && String(part) !== '',
            )
            .map((part) => String(part));

        return parts.length > 0 ? parts.join(' · ') : null;
    };

    return (
        <div ref={rootRef} className="relative">
            <label htmlFor={fieldId} className="sr-only">
                البحث عن منتج
            </label>
            <TextInput
                ref={inputRef}
                id={fieldId}
                type="text"
                icon={Search}
                value={query}
                disabled={disabled}
                autoComplete="off"
                spellCheck={false}
                placeholder="اكتب اسم المنتج أو الرمز ثم Enter"
                data-test="product-quick-add"
                aria-autocomplete="list"
                aria-controls={listboxId}
                aria-expanded={open}
                aria-activedescendant={
                    open ? `${listboxId}-option-${activeIndex}` : undefined
                }
                onFocus={() => setOpen(true)}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                    setActiveIndex(0);
                    navigatedWithKeys.current = false;
                }}
                onKeyDown={onInputKeyDown}
            />

            {open && (
                <div
                    className="absolute inset-x-0 z-20 mt-1 overflow-hidden rounded-lg border border-gray-200 bg-white shadow dark:border-gray-600 dark:bg-gray-700"
                    role="presentation"
                >
                    <ul
                        id={listboxId}
                        role="listbox"
                        aria-label="نتائج المنتجات"
                        className="max-h-72 overflow-y-auto py-1"
                    >
                        {processing && options.length === 0 ? (
                            <li
                                role="presentation"
                                className="flex items-center justify-center gap-2 px-4 py-6 text-sm text-gray-500 dark:text-gray-400"
                            >
                                <Spinner size="sm" />
                                جاري البحث...
                            </li>
                        ) : hasSearched && options.length === 0 ? (
                            <li
                                role="presentation"
                                className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                لا توجد منتجات مطابقة
                            </li>
                        ) : (
                            options.map((option, index) => {
                                const optionValue = String(option.value);
                                const isActive = index === activeIndex;
                                const alreadyAdded = added.has(optionValue);
                                const detail = subtitle(option);
                                const lastPrice = option.meta?.last_unit_price;

                                return (
                                    <li
                                        key={optionValue}
                                        id={`${listboxId}-option-${index}`}
                                        role="option"
                                        aria-selected={isActive}
                                    >
                                        <button
                                            type="button"
                                            tabIndex={-1}
                                            className={cn(
                                                'flex w-full cursor-pointer items-center gap-3 px-4 py-2.5 text-start',
                                                'hover:bg-gray-100 focus:bg-gray-100 focus:outline-none',
                                                'dark:hover:bg-gray-600 dark:focus:bg-gray-600',
                                                isActive &&
                                                    'bg-gray-100 dark:bg-gray-600',
                                            )}
                                            onMouseEnter={() => {
                                                navigatedWithKeys.current = false;
                                                setActiveIndex(index);
                                            }}
                                            onClick={() => commit(option)}
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium text-gray-900 dark:text-white">
                                                    {option.meta?.name_ar ??
                                                        option.label}
                                                </span>
                                                {detail && (
                                                    <span className="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">
                                                        {detail}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="flex shrink-0 flex-col items-end gap-1">
                                                {lastPrice !== null &&
                                                    lastPrice !== undefined &&
                                                    String(lastPrice) !==
                                                        '' && (
                                                        <span className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                            {formatMoney(
                                                                lastPrice,
                                                            )}
                                                        </span>
                                                    )}
                                                {alreadyAdded && (
                                                    <span className="rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-medium text-primary-700 dark:bg-gray-800 dark:text-primary-300">
                                                        في الفاتورة
                                                    </span>
                                                )}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
