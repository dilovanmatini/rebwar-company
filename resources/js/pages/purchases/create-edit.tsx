import { Head, useForm } from '@inertiajs/react';
import { Button, Label, Textarea, TextInput } from 'flowbite-react';
import { Edit, ShoppingCart } from 'lucide-react';
import type { FormEvent } from 'react';
import PurchaseController from '@/actions/App/Http/Controllers/PurchaseController';
import { AsyncSearchableSelect } from '@/components/async-searchable-select';
import type { SearchableSelectOption } from '@/components/async-searchable-select';
import { ConfirmActionButton } from '@/components/confirm-action-button';
import { DocumentStatusBadge } from '@/components/document-status-badge';
import { FormActions, FormCard } from '@/components/form-card';
import InputError from '@/components/input-error';
import { PurchaseLinesEditor } from '@/components/purchase-lines-editor';
import type { PurchaseLineDraft } from '@/components/purchase-lines-editor';
import { lookupQuery } from '@/hooks/use-lookup-options';
import { suppliers as supplierLookups } from '@/routes/lookups';
import { createEdit, index } from '@/routes/purchases';

type Props = {
    purchase: {
        id: number;
        number: string;
        receipt_number: string | null;
        purchase_date: string | null;
        supplier_id: number;
        notes: string | null;
        status: 'draft' | 'posted' | 'cancelled';
        status_label: string;
        is_posted: boolean;
        lines: Array<{
            product_id: number;
            quantity: string;
            product: { id: number; code: string | null; name_ar: string } | null;
        }>;
    } | null;
    selected_supplier: SearchableSelectOption | null;
    selected_products: SearchableSelectOption[];
    can_edit: boolean;
    can_post: boolean;
    can_cancel: boolean;
};

type PurchaseForm = {
    purchase_date: string;
    receipt_number: string;
    supplier_id: string;
    notes: string;
    lines: PurchaseLineDraft[];
};

export default function PurchasesCreateEdit({
    purchase,
    selected_supplier,
    selected_products,
    can_edit,
    can_post,
    can_cancel,
}: Props) {
    const form = useForm<PurchaseForm>({
        purchase_date:
            purchase?.purchase_date ?? new Date().toISOString().slice(0, 10),
        receipt_number: purchase?.receipt_number ?? '',
        supplier_id: purchase ? String(purchase.supplier_id) : '',
        notes: purchase?.notes ?? '',
        lines: purchase
            ? purchase.lines.map((line) => ({
                  product_id: String(line.product_id),
                  quantity: line.quantity,
              }))
            : [{ product_id: '', quantity: '' }],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!can_edit) {
            return;
        }

        form.post(PurchaseController.storeUpdate.url(purchase?.id));
    };

    const isEdit = purchase !== null;

    return (
        <>
            <Head
                title={
                    isEdit
                        ? can_edit
                            ? `تعديل فاتورة مشتريات ${purchase.number}`
                            : `عرض فاتورة مشتريات ${purchase.number}`
                        : 'إضافة فاتورة مشتريات'
                }
            />
            <FormCard
                title={
                    isEdit
                        ? can_edit
                            ? 'تعديل فاتورة مشتريات'
                            : 'عرض فاتورة مشتريات'
                        : 'إضافة فاتورة مشتريات'
                }
                description={
                    isEdit
                        ? purchase.number
                        : 'إنشاء مسودة فاتورة مشتريات جديدة'
                }
                icon={isEdit ? Edit : ShoppingCart}
                actions={
                    isEdit ? (
                        <DocumentStatusBadge
                            status={purchase.status}
                            label={purchase.status_label}
                        />
                    ) : undefined
                }
            >
                <form
                    id="purchase-form"
                    onSubmit={submit}
                    className="space-y-6"
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="supplier_id">المورد</Label>
                            <AsyncSearchableSelect
                                id="supplier_id"
                                name="supplier_id"
                                required
                                disabled={!can_edit}
                                placeholder="اختر المورد"
                                searchPlaceholder="ابحث عن مورد..."
                                value={form.data.supplier_id}
                                initialOptions={
                                    selected_supplier ? [selected_supplier] : []
                                }
                                buildUrl={(search) =>
                                    supplierLookups.url(
                                        lookupQuery(search, {
                                            include:
                                                form.data.supplier_id ||
                                                undefined,
                                        }),
                                    )
                                }
                                onChange={(value) =>
                                    form.setData('supplier_id', value)
                                }
                            />
                            <InputError message={form.errors.supplier_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="purchase_date">تاريخ الشراء</Label>
                            <TextInput
                                id="purchase_date"
                                type="date"
                                value={form.data.purchase_date}
                                disabled={!can_edit}
                                onChange={(event) =>
                                    form.setData(
                                        'purchase_date',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.purchase_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="receipt_number">رقم الوصل</Label>
                            <TextInput
                                id="receipt_number"
                                name="receipt_number"
                                value={form.data.receipt_number}
                                disabled={!can_edit}
                                onChange={(event) =>
                                    form.setData(
                                        'receipt_number',
                                        event.target.value,
                                    )
                                }
                                required
                                placeholder="رقم وصل المشتريات"
                            />
                            <InputError message={form.errors.receipt_number} />
                        </div>
                    </div>

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

                    <PurchaseLinesEditor
                        lines={form.data.lines}
                        selectedProducts={selected_products}
                        errors={form.errors}
                        readOnly={!can_edit}
                        onChange={(lines) => form.setData('lines', lines)}
                    />
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
                            form="purchase-form"
                            disabled={form.processing}
                        >
                            {isEdit ? 'حفظ التعديلات' : 'إنشاء فاتورة مشتريات'}
                        </Button>
                        {can_post && purchase && (
                            <ConfirmActionButton
                                href={PurchaseController.post.url(purchase.id)}
                                color="green"
                                confirmColor="green"
                                confirmTitle="تأكيد نهائي"
                                confirmMessage="هل تريد تأكيد هذه الفاتورة المشتريات نهائياً؟ لن يمكن تعديلها بعد ذلك."
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
                                        href={PurchaseController.cancel.url(
                                            purchase.id,
                                        )}
                                        color="red"
                                        confirmColor="red"
                                        confirmTitle="إلغاء فاتورة مشتريات"
                                        confirmMessage="هل أنت متأكد من إلغاء هذه الفاتورة المشتريات النشطة؟ سيتم عكس أثرها على المخزون."
                                        confirmLabel="إلغاء فاتورة مشتريات"
                                        confirmingLabel="جارٍ الإلغاء..."
                                    >
                                        إلغاء فاتورة مشتريات
                                    </ConfirmActionButton>
                                )}
                                <Button color="light" href={index.url()} as="a">
                                    رجوع
                                </Button>
                            </>
                        }
                    />
                )}
            </FormCard>
        </>
    );
}

PurchasesCreateEdit.layout = ({ purchase }: Props) => ({
    breadcrumbs: [
        { title: 'المشتريات', href: index() },
        purchase
            ? { title: 'تعديل', href: createEdit(purchase.id) }
            : { title: 'إضافة', href: createEdit() },
    ],
});
