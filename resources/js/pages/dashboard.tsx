import { Head, Link } from '@inertiajs/react';
import { Card } from 'flowbite-react';
import {
    ChartColumn,
    ChevronLeft,
    FileText,
    LayoutGrid,
    Package,
    ScrollText,
    Settings,
    ShoppingCart,
    Truck,
    Users,
    Warehouse,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { FormCard } from '@/components/form-card';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';

type Shortcut = {
    key: string;
    title: string;
    description: string;
    href: string;
};

type ShortcutGroup = {
    key: string;
    title: string;
    shortcuts: Shortcut[];
};

type Props = {
    groups: ShortcutGroup[];
};

const icons: Record<string, LucideIcon> = {
    'sales-invoices': FileText,
    'payment-receipts': Wallet,
    distributors: Users,
    statements: ScrollText,
    purchases: ShoppingCart,
    inventory: Warehouse,
    suppliers: Truck,
    products: Package,
    reports: ChartColumn,
    settings: Settings,
};

const iconClasses: Record<string, string> = {
    'sales-invoices':
        'bg-green-50 text-green-700 dark:bg-gray-700 dark:text-green-300',
    'payment-receipts':
        'bg-amber-50 text-amber-700 dark:bg-gray-700 dark:text-amber-300',
    distributors:
        'bg-purple-50 text-purple-700 dark:bg-gray-700 dark:text-purple-300',
    statements: 'bg-sky-50 text-sky-700 dark:bg-gray-700 dark:text-sky-300',
    purchases: 'bg-blue-50 text-blue-700 dark:bg-gray-700 dark:text-blue-300',
    inventory: 'bg-teal-50 text-teal-700 dark:bg-gray-700 dark:text-teal-300',
    suppliers:
        'bg-orange-50 text-orange-700 dark:bg-gray-700 dark:text-orange-300',
    products: 'bg-rose-50 text-rose-700 dark:bg-gray-700 dark:text-rose-300',
    reports:
        'bg-indigo-50 text-indigo-700 dark:bg-gray-700 dark:text-indigo-300',
    settings: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};

export default function Dashboard({ groups }: Props) {
    return (
        <>
            <Head title="الصفحة الرئيسية" />
            <FormCard
                title="الصفحة الرئيسية"
                description="اختصارات سريعة للأقسام الرئيسية"
                icon={LayoutGrid}
            >
                {groups.length === 0 ? (
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        لا توجد أقسام متاحة لحسابك.
                    </p>
                ) : (
                    <div className="space-y-8">
                        {groups.map((group) => (
                            <section
                                key={group.key}
                                className="space-y-3"
                                aria-labelledby={`dashboard-group-${group.key}`}
                            >
                                <h3
                                    id={`dashboard-group-${group.key}`}
                                    className="text-sm font-medium text-gray-500 dark:text-gray-400"
                                >
                                    {group.title}
                                </h3>
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    {group.shortcuts.map((shortcut) => {
                                        const Icon =
                                            icons[shortcut.key] ?? LayoutGrid;
                                        const iconClass =
                                            iconClasses[shortcut.key] ??
                                            'bg-primary-50 text-primary-700 dark:bg-gray-700 dark:text-primary-300';

                                        return (
                                            <Link
                                                key={shortcut.key}
                                                href={toUrl(shortcut.href)}
                                                prefetch
                                                className="group block rounded-lg focus:ring-0 focus:outline-none focus-visible:shadow-focus"
                                            >
                                                <Card
                                                    className="h-full transition group-hover:border-primary-200 group-hover:bg-gray-50 dark:group-hover:border-gray-600 dark:group-hover:bg-gray-800"
                                                    theme={{
                                                        root: {
                                                            children:
                                                                'flex h-full flex-col justify-between gap-6 p-6',
                                                        },
                                                    }}
                                                >
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div
                                                            className={`rounded-lg p-2.5 ${iconClass}`}
                                                        >
                                                            <Icon
                                                                className="h-6 w-6"
                                                                aria-hidden
                                                            />
                                                        </div>
                                                        <ChevronLeft
                                                            className="h-5 w-5 shrink-0 text-gray-300 transition group-hover:text-primary-600 dark:text-gray-500 dark:group-hover:text-primary-400"
                                                            aria-hidden
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <p className="text-lg font-semibold text-gray-900 dark:text-white">
                                                            {shortcut.title}
                                                        </p>
                                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                                            {
                                                                shortcut.description
                                                            }
                                                        </p>
                                                    </div>
                                                </Card>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </FormCard>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'الصفحة الرئيسية', href: dashboard() }],
};
