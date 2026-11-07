import { Head, Link, router } from '@inertiajs/react';
import {
    Button,
    Label,
    Select,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeadCell,
    TableRow,
    TextInput,
} from 'flowbite-react';
import { Ban, Pencil, Plus, ShoppingCart } from 'lucide-react';
import PurchaseController from '@/actions/App/Http/Controllers/PurchaseController';
import { AsyncSearchableSelect } from '@/components/async-searchable-select';
import type { SearchableSelectOption } from '@/components/async-searchable-select';
import { ConfirmActionButton } from '@/components/confirm-action-button';
import { DeleteButton } from '@/components/delete-button';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { FormCard } from '@/components/form-card';
import { PaginationLinks } from '@/components/pagination-links';
import type { Paginated } from '@/components/pagination-links';
import { SearchFilter } from '@/components/search-filter';
import { lookupQuery } from '@/hooks/use-lookup-options';
import { toUrl } from '@/lib/utils';
import { suppliers as supplierLookups } from '@/routes/lookups';
import { createEdit, index } from '@/routes/purchases';

type PurchaseRow = {
    id: number;
    number: string;
    receipt_number: string | null;
    purchase_date: string | null;
    supplier: { id: number; name: string } | null;
    status: 'draft' | 'posted' | 'cancelled';
    status_label: string;
    is_posted: boolean;
    can_edit: boolean;
    can_delete: boolean;
    can_cancel: boolean;
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    purchases: Paginated<PurchaseRow>;
    selected_supplier: SearchableSelectOption | null;
    filters: {
        search: string;
        status: string;
        supplier_id: number | null;
        from_date: string | null;
        to_date: string | null;
    };
    status_options: StatusOption[];
};

const allSuppliersOption: SearchableSelectOption = {
    value: '',
    label: 'كل الموردين',
};

export default function PurchasesIndex({
    purchases,
    selected_supplier,
    filters,
    status_options,
}: Props) {
    const supplierId = filters.supplier_id ? String(filters.supplier_id) : '';

    const filterParams = {
        status: filters.status || undefined,
        supplier_id: supplierId || undefined,
        from_date: filters.from_date || undefined,
        to_date: filters.to_date || undefined,
    };

    const applyFilters = (overrides: {
        status?: string;
        supplier_id?: string;
        from_date?: string;
        to_date?: string;
    }) => {
        const current =
            typeof window === 'undefined'
                ? {}
                : Object.fromEntries(
                      new URLSearchParams(window.location.search),
                  );

        const nextStatus =
            overrides.status !== undefined ? overrides.status : filters.status;
        const nextSupplierId =
            overrides.supplier_id !== undefined
                ? overrides.supplier_id
                : supplierId;
        const nextFromDate =
            overrides.from_date !== undefined
                ? overrides.from_date
                : (filters.from_date ?? '');
        const nextToDate =
            overrides.to_date !== undefined
                ? overrides.to_date
                : (filters.to_date ?? '');

        router.get(
            index.url(),
            {
                ...current,
                search: filters.search || undefined,
                status: nextStatus || undefined,
                supplier_id: nextSupplierId || undefined,
                from_date: nextFromDate || undefined,
                to_date: nextToDate || undefined,
                page: undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="المشتريات" />
            <FormCard
                title="المشتريات"
                description="إدارة فواتير المشتريات وترحيلها للمخزون"
                icon={ShoppingCart}
                actions={
                    <Button
                        as={Link}
                        href={toUrl(createEdit())}
                        className="inline-flex items-center gap-2"
                    >
                        <Plus className="h-4 w-4" />
                        إضافة فاتورة مشتريات
                    </Button>
                }
            >
                <div className="space-y-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
                        <SearchFilter
                            url={index.url()}
                            initial={filters.search}
                            placeholder="بحث بالرقم أو رقم الوصل أو اسم المورد..."
                            className="max-w-none grow sm:max-w-md"
                            params={filterParams}
                        />
                        <div className="w-full lg:max-w-xs">
                            <Label htmlFor="supplier_id" className="mb-2 block">
                                المورد
                            </Label>
                            <AsyncSearchableSelect
                                id="supplier_id"
                                name="supplier_id"
                                placeholder="كل الموردين"
                                searchPlaceholder="ابحث عن مورد..."
                                value={supplierId}
                                initialOptions={[
                                    allSuppliersOption,
                                    ...(selected_supplier
                                        ? [selected_supplier]
                                        : []),
                                ]}
                                buildUrl={(search) =>
                                    supplierLookups.url(
                                        lookupQuery(search, {
                                            active_only: 0,
                                            include: supplierId || undefined,
                                        }),
                                    )
                                }
                                onChange={(value) =>
                                    applyFilters({ supplier_id: value })
                                }
                            />
                        </div>
                        <div className="w-full sm:w-48">
                            <Label htmlFor="status" className="mb-2 block">
                                الحالة
                            </Label>
                            <Select
                                id="status"
                                value={filters.status}
                                onChange={(event) =>
                                    applyFilters({
                                        status: event.target.value,
                                    })
                                }
                            >
                                {status_options.map((option) => (
                                    <option
                                        key={option.value || 'all'}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="w-full sm:w-44">
                            <Label htmlFor="from_date" className="mb-2 block">
                                من تاريخ
                            </Label>
                            <TextInput
                                id="from_date"
                                type="date"
                                value={filters.from_date ?? ''}
                                onChange={(event) =>
                                    applyFilters({
                                        from_date: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="w-full sm:w-44">
                            <Label htmlFor="to_date" className="mb-2 block">
                                إلى تاريخ
                            </Label>
                            <TextInput
                                id="to_date"
                                type="date"
                                value={filters.to_date ?? ''}
                                onChange={(event) =>
                                    applyFilters({
                                        to_date: event.target.value,
                                    })
                                }
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableHeadCell className="text-start">
                                        الرقم
                                    </TableHeadCell>
                                    <TableHeadCell className="text-start">
                                        رقم الوصل
                                    </TableHeadCell>
                                    <TableHeadCell className="text-start">
                                        التاريخ
                                    </TableHeadCell>
                                    <TableHeadCell className="text-start">
                                        المورد
                                    </TableHeadCell>
                                    <TableHeadCell className="text-center">
                                        الحالة
                                    </TableHeadCell>
                                    <TableHeadCell className="text-end">
                                        <span className="sr-only">إجراءات</span>
                                    </TableHeadCell>
                                </TableRow>
                            </TableHead>
                            <TableBody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {purchases.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="py-10 text-center text-gray-500"
                                        >
                                            لا توجد مشتريات
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    purchases.data.map((purchase) => (
                                        <TableRow key={purchase.id}>
                                            <TableCell className="text-start font-medium">
                                                <Link
                                                    href={toUrl(
                                                        createEdit(purchase.id),
                                                    )}
                                                    className="text-primary-700 hover:underline dark:text-primary-400"
                                                    title={
                                                        purchase.can_edit
                                                            ? 'تعديل'
                                                            : 'عرض'
                                                    }
                                                >
                                                    {purchase.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-start">
                                                {purchase.receipt_number ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-start">
                                                {purchase.purchase_date ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-start">
                                                {purchase.supplier?.name ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <div className="flex justify-center">
                                                    <DocumentStatusBadge
                                                        status={purchase.status}
                                                        label={
                                                            purchase.status_label
                                                        }
                                                    />
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-end">
                                                <div className="inline-flex items-center justify-end gap-2">
                                                    {purchase.can_edit && (
                                                        <Button
                                                            as={Link}
                                                            href={toUrl(
                                                                createEdit(
                                                                    purchase.id,
                                                                ),
                                                            )}
                                                            size="xs"
                                                            color="light"
                                                            title="تعديل"
                                                            aria-label="تعديل"
                                                            className="inline-flex items-center justify-center p-2"
                                                        >
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </Button>
                                                    )}
                                                    {purchase.can_cancel && (
                                                        <ConfirmActionButton
                                                            href={PurchaseController.cancel.url(
                                                                purchase.id,
                                                            )}
                                                            size="xs"
                                                            color="light"
                                                            title="إلغاء"
                                                            aria-label="إلغاء"
                                                            className="inline-flex items-center justify-center p-2"
                                                            confirmTitle="إلغاء المشترى"
                                                            confirmMessage="هل أنت متأكد من إلغاء هذا المشترى النشط؟ سيتم عكس أثره على المخزون."
                                                            confirmLabel="إلغاء المشترى"
                                                            confirmingLabel="جارٍ الإلغاء..."
                                                            confirmColor="red"
                                                        >
                                                            <Ban className="h-3.5 w-3.5 text-red-600 dark:text-red-400" />
                                                        </ConfirmActionButton>
                                                    )}
                                                    {purchase.can_delete && (
                                                        <DeleteButton
                                                            href={PurchaseController.destroy.url(
                                                                purchase.id,
                                                            )}
                                                            confirmMessage="هل أنت متأكد من حذف هذا المشترى؟"
                                                        />
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <PaginationLinks meta={purchases} storageKey="purchases" />
                </div>
            </FormCard>
        </>
    );
}

PurchasesIndex.layout = {
    breadcrumbs: [{ title: 'المشتريات', href: index() }],
};
