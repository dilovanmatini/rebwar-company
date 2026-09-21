import { Head, useForm } from '@inertiajs/react';
import { Button, Label, Textarea, TextInput } from 'flowbite-react';
import { Edit, FileText, Printer } from 'lucide-react';
import type { FormEvent } from 'react';
import SalesInvoiceController from '@/actions/App/Http/Controllers/SalesInvoiceController';
import { AsyncSearchableSelect } from '@/components/async-searchable-select';
import type { SearchableSelectOption } from '@/components/async-searchable-select';
import { ConfirmActionButton } from '@/components/confirm-action-button';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { FormActions, FormCard } from '@/components/form-card';
import InputError from '@/components/input-error';
import {
    calculateInvoiceSubtotal,
    SalesInvoiceLinesEditor,
} from '@/components/sales-invoice-lines-editor';
import type { SalesInvoiceLineDraft } from '@/components/sales-invoice-lines-editor';
import { lookupQuery } from '@/hooks/use-lookup-options';
import { useCurrency, useFormatMoney } from '@/lib/money';
import { distributors as distributorLookups } from '@/routes/lookups';
import {
    createEdit,
    index,
    print as salesInvoicesPrint,
} from '@/routes/sales-invoices';

type Props = {
    invoice: {
        id: number;
        number: string;
        invoice_date: string | null;
        distributor_id: number;
        notes: string | null;
        subtotal: string;
        discount: string;
        grand_total: string;
        status: 'draft' | 'posted' | 'cancelled';
        status_label: string;
        is_posted: boolean;
        lines: Array<{
            product_id: number;
            quantity: string;
            unit_price: string;
            line_total: string;
            product: {
                id: number;
                code: string | null;
                name_ar: string;
            } | null;
        }>;
    } | null;
    selected_distributor: SearchableSelectOption | null;
    selected_products: SearchableSelectOption[];
    can_edit: boolean;
    can_post: boolean;
    can_cancel: boolean;
    can_print: boolean;
};

type InvoiceForm = {
    invoice_date: string;
    distributor_id: string;
    notes: string;
    discount: string;
    lines: SalesInvoiceLineDraft[];
};

export default function SalesInvoicesCreateEdit({
    invoice,
    selected_distributor,
    selected_products,
    can_edit,
    can_post,
    can_cancel,
    can_print,
}: Props) {
    const formatMoney = useFormatMoney();
    const { symbol } = useCurrency();
    const form = useForm<InvoiceForm>({
        invoice_date:
            invoice?.invoice_date ?? new Date().toISOString().slice(0, 10),
        distributor_id: invoice
            ? String(invoice.distributor_id)
            : selected_distributor
              ? String(selected_distributor.value)
              : '',
        notes: invoice?.notes ?? '',
        discount: invoice?.discount ?? '0',
        lines: invoice
            ? invoice.lines.map((line) => ({
                  product_id: String(line.product_id),
                  quantity: line.quantity,
                  unit_price: line.unit_price,
              }))
            : [],
    });

    const subtotal = can_edit
        ? calculateInvoiceSubtotal(form.data.lines)
        : Number(invoice?.subtotal ?? 0);
    const discount = can_edit
        ? Number(form.data.discount) || 0
        : Number(invoice?.discount ?? 0);
    const grandTotal = can_edit
        ? Math.max(subtotal - discount, 0)
        : Number(invoice?.grand_total ?? 0);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!can_edit) {
            return;
        }

        form.post(SalesInvoiceController.storeUpdate.url(invoice?.id));
    };

    const isEdit = invoice !== null;

    return (
        <>
            <Head
                title={
                    isEdit
                        ? can_edit
                            ? `تعديل فاتورة ${invoice.number}`
                            : `عرض فاتورة ${invoice.number}`
                        : 'إضافة فاتورة مبيعات'
                }
            />
            <FormCard
                title={
                    isEdit
                        ? can_edit
                            ? 'تعديل فاتورة'
                            : 'عرض فاتورة'
                        : 'إضافة فاتورة مبيعات'
                }
                description={
                    isEdit ? invoice.number : 'إنشاء مسودة فاتورة جديدة'
                }
                icon={isEdit ? Edit : FileText}
                actions={
                    isEdit ? (
                        <DocumentStatusBadge
                            status={invoice.status}
                            label={invoice.status_label}
                        />
                    ) : undefined
                }
            >
                <form
                    id="sales-invoice-form"
                    onSubmit={submit}
                    className="space-y-6"
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="invoice_date">تاريخ الفاتورة</Label>
                            <TextInput
                                id="invoice_date"
                                type="date"
                                value={form.data.invoice_date}
                                disabled={!can_edit}
                                onChange={(event) =>
                                    form.setData(
                                        'invoice_date',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.invoice_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="distributor_id">الموزع</Label>
                            <AsyncSearchableSelect
                                id="distributor_id"
                                name="distributor_id"
                                required
                                disabled={!can_edit}
                                placeholder="اختر الموزع"
                                searchPlaceholder="ابحث عن موزع..."
                                value={form.data.distributor_id}
                                initialOptions={
                                    selected_distributor
                                        ? [selected_distributor]
                                        : []
                                }
                                buildUrl={(search) =>
                                    distributorLookups.url(
                                        lookupQuery(search, {
                                            include:
                                                form.data.distributor_id ||
                                                undefined,
                                        }),
                                    )
                                }
                                onChange={(value) =>
                                    form.setData('distributor_id', value)
                                }
                            />
                            <InputError message={form.errors.distributor_id} />
                        </div>
                    </div>

                    <SalesInvoiceLinesEditor
                        lines={form.data.lines}
                        selectedProducts={selected_products}
                        errors={form.errors}
                        readOnly={!can_edit}
                        onChange={(lines) => form.setData('lines', lines)}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="notes">ملاحظات</Label>
                        <Textarea
                            id="notes"
                            rows={3}
                            value={form.data.notes}
                            disabled={!can_edit}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <div className="grid gap-4 rounded-lg border border-gray-200 p-4 sm:grid-cols-3 dark:border-gray-700">
                        <div className="grid gap-2">
                            <Label>المجموع الفرعي</Label>
                            <TextInput
                                value={formatMoney(subtotal.toFixed(2))}
                                readOnly
                                disabled
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="discount">الخصم ({symbol})</Label>
                            <TextInput
                                id="discount"
                                type="number"
                                min="0"
                                step="any"
                                value={form.data.discount}
                                disabled={!can_edit}
                                onChange={(event) =>
                                    form.setData('discount', event.target.value)
                                }
                            />
                            <InputError message={form.errors.discount} />
                        </div>
                        <div className="grid gap-2">
                            <Label>الإجمالي</Label>
                            <TextInput
                                value={formatMoney(grandTotal.toFixed(2))}
                                readOnly
                                disabled
                                className="font-semibold"
                            />
                        </div>
                    </div>
                </form>

                {can_edit && (
                    <FormActions
                        className="mt-6"
                        secondary={
                            <Button color="light" href={index.url()} as="a">
                                رجوع
                            </Button>
                        }
                    >
                        <Button
                            type="submit"
                            form="sales-invoice-form"
                            disabled={form.processing}
                        >
                            {isEdit ? 'حفظ التعديلات' : 'إنشاء الفاتورة'}
                        </Button>
                        {can_post && invoice && (
                            <ConfirmActionButton
                                href={SalesInvoiceController.post.url(
                                    invoice.id,
                                )}
                                color="green"
                                confirmColor="green"
                                confirmTitle="تأكيد نهائي"
                                confirmMessage="هل تريد تأكيد هذه الفاتورة نهائياً؟ لن يمكن تعديلها بعد ذلك."
                                confirmLabel="تأكيد نهائي"
                                confirmingLabel="جارٍ التأكيد..."
                            >
                                تأكيد نهائي
                            </ConfirmActionButton>
                        )}
                    </FormActions>
                )}

                {!can_edit && isEdit && (
                    <FormActions
                        className="mt-6"
                        secondary={
                            <>
                                {can_cancel && (
                                    <ConfirmActionButton
                                        href={SalesInvoiceController.cancel.url(
                                            invoice.id,
                                        )}
                                        color="red"
                                        confirmColor="red"
                                        confirmTitle="إلغاء الفاتورة"
                                        confirmMessage="هل أنت متأكد من إلغاء هذه الفاتورة النشطة؟ سيتم عكس أثرها على المخزون والذمم."
                                        confirmLabel="إلغاء الفاتورة"
                                        confirmingLabel="جارٍ الإلغاء..."
                                    >
                                        إلغاء الفاتورة
                                    </ConfirmActionButton>
                                )}
                                <Button color="light" href={index.url()} as="a">
                                    رجوع
                                </Button>
                            </>
                        }
                    >
                        {can_print && (
                            <Button
                                color="dark"
                                href={salesInvoicesPrint.url(invoice.id)}
                                as="a"
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-2"
                            >
                                <Printer className="h-4 w-4" />
                                طباعة
                            </Button>
                        )}
                    </FormActions>
                )}
            </FormCard>
        </>
    );
}

SalesInvoicesCreateEdit.layout = ({ invoice }: Props) => ({
    breadcrumbs: [
        { title: 'فواتير المبيعات', href: index() },
        invoice
            ? { title: 'تعديل', href: createEdit(invoice.id) }
            : { title: 'إضافة', href: createEdit() },
    ],
});
