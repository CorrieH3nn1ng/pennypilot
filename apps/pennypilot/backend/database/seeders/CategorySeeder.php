<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Expenses
            ['name' => 'Groceries', 'icon' => 'shopping_cart', 'color' => '#4CAF50', 'is_income' => false, 'sort_order' => 1],
            ['name' => 'Transport', 'icon' => 'directions_car', 'color' => '#2196F3', 'is_income' => false, 'sort_order' => 2],
            ['name' => 'Fuel', 'icon' => 'local_gas_station', 'color' => '#FF5722', 'is_income' => false, 'sort_order' => 3],
            ['name' => 'Utilities', 'icon' => 'bolt', 'color' => '#FF9800', 'is_income' => false, 'sort_order' => 4],
            ['name' => 'Entertainment', 'icon' => 'movie', 'color' => '#9C27B0', 'is_income' => false, 'sort_order' => 5],
            ['name' => 'Dining Out', 'icon' => 'restaurant', 'color' => '#E91E63', 'is_income' => false, 'sort_order' => 6],
            ['name' => 'Healthcare', 'icon' => 'local_hospital', 'color' => '#00BCD4', 'is_income' => false, 'sort_order' => 7],
            ['name' => 'Shopping', 'icon' => 'local_mall', 'color' => '#795548', 'is_income' => false, 'sort_order' => 8],
            ['name' => 'Insurance', 'icon' => 'security', 'color' => '#607D8B', 'is_income' => false, 'sort_order' => 9],
            ['name' => 'Bank Fees', 'icon' => 'account_balance', 'color' => '#9E9E9E', 'is_income' => false, 'sort_order' => 10],
            ['name' => 'Subscriptions', 'icon' => 'subscriptions', 'color' => '#673AB7', 'is_income' => false, 'sort_order' => 11],
            ['name' => 'Medical Aid', 'icon' => 'health_and_safety', 'color' => '#F44336', 'is_income' => false, 'sort_order' => 12],
            ['name' => 'Pension', 'icon' => 'savings', 'color' => '#3F51B5', 'is_income' => false, 'sort_order' => 13],
            ['name' => 'Domestic Help', 'icon' => 'cleaning_services', 'color' => '#8BC34A', 'is_income' => false, 'sort_order' => 14],
            ['name' => 'Rates & Taxes', 'icon' => 'home', 'color' => '#CDDC39', 'is_income' => false, 'sort_order' => 15],
            ['name' => 'Gambling/Lotto', 'icon' => 'casino', 'color' => '#FFC107', 'is_income' => false, 'sort_order' => 16],
            ['name' => 'Business Expenses', 'icon' => 'business', 'color' => '#1976D2', 'is_income' => false, 'sort_order' => 17],
            ['name' => 'Other Expense', 'icon' => 'receipt', 'color' => '#757575', 'is_income' => false, 'sort_order' => 99],

            // Income
            ['name' => 'Salary', 'icon' => 'payments', 'color' => '#4CAF50', 'is_income' => true, 'sort_order' => 1],
            ['name' => 'Freelance/Contract', 'icon' => 'work', 'color' => '#8BC34A', 'is_income' => true, 'sort_order' => 2],
            ['name' => 'Investment', 'icon' => 'trending_up', 'color' => '#00BCD4', 'is_income' => true, 'sort_order' => 3],
            ['name' => 'Refund', 'icon' => 'replay', 'color' => '#FF9800', 'is_income' => true, 'sort_order' => 4],
            ['name' => 'Interest', 'icon' => 'percent', 'color' => '#03A9F4', 'is_income' => true, 'sort_order' => 5],
            ['name' => 'Other Income', 'icon' => 'attach_money', 'color' => '#9E9E9E', 'is_income' => true, 'sort_order' => 99],
        ];

        foreach ($categories as $category) {
            Category::create([
                'id' => Str::uuid(),
                'user_id' => null,
                'is_system' => true,
                'sync_status' => 'synced',
                ...$category,
            ]);
        }
    }
}
