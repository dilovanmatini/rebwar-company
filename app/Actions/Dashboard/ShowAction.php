<?php

namespace App\Actions\Dashboard;

use App\Authorization\Ability;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ShowAction
{
    public function handle(User $user): Response
    {
        return Inertia::render('dashboard', [
            'groups' => $this->groupsFor($user),
        ]);
    }

    /**
     * @return list<array{key: string, title: string, shortcuts: list<array{key: string, title: string, description: string, href: string}>}>
     */
    private function groupsFor(User $user): array
    {
        $groups = [
            [
                'key' => 'sales',
                'title' => 'المبيعات',
                'shortcuts' => [
                    [
                        'key' => 'sales-invoices',
                        'title' => 'فواتير المبيعات',
                        'description' => 'إنشاء ومتابعة فواتير العملاء',
                        'href' => route('sales-invoices.index'),
                        'ability' => Ability::ManageSales->value,
                    ],
                    [
                        'key' => 'payment-receipts',
                        'title' => 'سندات القبض',
                        'description' => 'تسجيل دفعات الموزعين',
                        'href' => route('payment-receipts.index'),
                        'ability' => Ability::ManageReceipts->value,
                    ],
                    [
                        'key' => 'distributors',
                        'title' => 'الموزعون',
                        'description' => 'إدارة حسابات الموزعين',
                        'href' => route('distributors.index'),
                        'ability' => Ability::ManageDistributors->value,
                    ],
                    [
                        'key' => 'statements',
                        'title' => 'كشف حساب العميل',
                        'description' => 'عرض حركة وأرصدة العملاء',
                        'href' => route('statements.index'),
                        'ability' => Ability::ViewStatements->value,
                    ],
                ],
            ],
            [
                'key' => 'purchasing',
                'title' => 'المشتريات والمخزون',
                'shortcuts' => [
                    [
                        'key' => 'purchases',
                        'title' => 'المشتريات',
                        'description' => 'تسجيل ومتابعة فواتير الشراء',
                        'href' => route('purchases.index'),
                        'ability' => Ability::ManagePurchases->value,
                    ],
                    [
                        'key' => 'inventory',
                        'title' => 'المخزون',
                        'description' => 'متابعة كميات الأصناف المتاحة',
                        'href' => route('inventory.index'),
                        'ability' => Ability::ViewInventory->value,
                    ],
                    [
                        'key' => 'suppliers',
                        'title' => 'الموردون',
                        'description' => 'إدارة حسابات الموردين',
                        'href' => route('suppliers.index'),
                        'ability' => Ability::ManageSuppliers->value,
                    ],
                    [
                        'key' => 'products',
                        'title' => 'المنتجات',
                        'description' => 'إدارة أصناف المنتجات والأسعار',
                        'href' => route('products.index'),
                        'ability' => Ability::ManageProducts->value,
                    ],
                ],
            ],
            [
                'key' => 'system',
                'title' => 'النظام',
                'shortcuts' => [
                    [
                        'key' => 'reports',
                        'title' => 'التقارير',
                        'description' => 'تقارير المبيعات والمخزون والذمم',
                        'href' => route('reports.index'),
                        'ability' => Ability::ViewReports->value,
                    ],
                    [
                        'key' => 'settings',
                        'title' => 'الإعدادات',
                        'description' => 'إعدادات النظام والحساب',
                        'href' => route('settings.index'),
                        'ability' => null,
                    ],
                ],
            ],
        ];

        $visibleGroups = [];

        foreach ($groups as $group) {
            $shortcuts = [];

            foreach ($group['shortcuts'] as $shortcut) {
                if ($shortcut['ability'] !== null && ! $user->hasAbility($shortcut['ability'])) {
                    continue;
                }

                unset($shortcut['ability']);
                $shortcuts[] = $shortcut;
            }

            if ($shortcuts === []) {
                continue;
            }

            $visibleGroups[] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'shortcuts' => $shortcuts,
            ];
        }

        return $visibleGroups;
    }
}
