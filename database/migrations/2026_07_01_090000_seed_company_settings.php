<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key'         => 'company_name',
                'value'       => 'Zalet d.o.o.',
                'type'        => 'string',
                'description' => 'Puni naziv firme (za račune i fakture).',
            ],
            [
                'key'         => 'company_pib',
                'value'       => '',
                'type'        => 'string',
                'description' => 'PIB (poreski identifikacioni broj) firme.',
            ],
            [
                'key'         => 'company_mb',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Matični broj preduzeća.',
            ],
            [
                'key'         => 'company_address',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Adresa sedišta firme (ulica i broj, grad).',
            ],
            [
                'key'         => 'company_email',
                'value'       => 'info@zaletyu.com',
                'type'        => 'string',
                'description' => 'Kontakt e-mail firme (pojavljuje se na računima).',
            ],
            [
                'key'         => 'company_bank_account',
                'value'       => '',
                'type'        => 'string',
                'description' => 'Broj tekućeg računa (za račune i fakture).',
            ],
        ];

        foreach ($settings as $s) {
            AppSetting::firstOrCreate(['key' => $s['key']], $s);
        }
    }

    public function down(): void
    {
        $keys = [
            'company_name', 'company_pib', 'company_mb',
            'company_address', 'company_email', 'company_bank_account',
        ];

        AppSetting::whereIn('key', $keys)->delete();
    }
};
